<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PaymentService;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\AssetRevoke;
use App\Models\TradingUser;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\PaymentGateway;
use App\Services\MetaFourService;
use App\Mail\FailedWithdrawalMail;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Mail\WithdrawalApprovalMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\CurrencyConversionRate;
use App\Services\RunningNumberService;
use App\Services\ChangeTraderBalanceType;

class PendingController extends Controller
{
    public function index()
    {
        return Inertia::render('Pending/Pending');
    }

    public function getPendingWithdrawalData(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $query = Transaction::with([
                'user:id,email,name',
                'user.media',
                'payment_account:id,payment_account_name,account_no',
                'fromMetaLogin:id,meta_login,balance'
            ])
                ->where('transaction_type', 'withdrawal')
                ->where('status', 'processing')
                ->whereNot('category', 'bonus_wallet');

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('user', function ($query) use ($keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('name', 'like', '%' . $keyword . '%')
                                ->orWhere('email', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('transaction_number', 'like', '%' . $keyword . '%')
                        ->orWhere('from_meta_login', 'like', '%' . $keyword . '%');
                });
            }

            //sort field/order
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('created_at');
            }

            //            if ($request->has('exportStatus')) {
            //                return Excel::download(new InvestmentExport($query, $status), now() . '-investment-report.xlsx');
            //            }

            $pendingWithdrawals = $query->paginate($data['rows']);

            $pendingWithdrawals->getCollection()->transform(function ($transaction) {
                if ($transaction->from_meta_login) {
                    (new MetaFourService())->getUserInfo($transaction->from_meta_login);
                    $transaction->balance = $transaction->fromMetaLogin->balance ?? 0;
                } else {
                    $transaction->balance = $transaction->from_wallet->balance ?? 0;
                }

                return $transaction;
            });

            $totalAmount = (clone $query)
                ->sum('transaction_amount');

            return response()->json([
                'success' => true,
                'data' => $pendingWithdrawals,
                'totalAmount' => $totalAmount,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }

    public function withdrawalApproval(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required'],
            'remarks' => ['nullable'],
        ])->setAttributeNames([
            'action' => trans('public.action'),
            'remarks' => trans('public.remarks'),
        ]);
        $validator->validate();

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $action = $request->action;

        $status = $action == 'approve' ? 'pending' : 'rejected';

        $transaction = Transaction::find($request->id);

        if ($transaction->status != 'processing') {
            return redirect()->back()->with('toast', [
                'title' => 'Invalid action. Please try again.',
                'type' => 'warning'
            ]);
        }

        $transaction->update([
            'remarks' => $request->remarks,
            'status' => $status,
            'approved_at' => now(),
            'handle_by' => Auth::id()
        ]);

        if ($transaction->status == 'rejected') {
            // Handle different categories (rebate_wallet, bonus_wallet, trading_account)
            $this->handleTransactionUpdate($transaction);

            $user = User::find($transaction->user_id);
            Mail::to($user->email)->send(new FailedWithdrawalMail($user, $transaction));

            return redirect()->back()->with('toast', [
                'title' => trans('public.toast_reject_withdrawal_request'),
                'type' => 'success'
            ]);
        } else {
            if ($transaction->payment_gateway) {
                $payment_gateway = $transaction->payment_gateway;
            } else {
                $environment = App::environment() == 'production' ? 'production' : 'local';

                $payment_gateway = PaymentGateway::where([
                    ['platform', $transaction->payment_platform],
                    ['environment', $environment],
                ])->first();
            }

            $conversion_rate = null;
            $conversion_amount = $transaction->transaction_amount;

            if ($transaction->payment_platform == 'bank') {
                $conversion_rate = CurrencyConversionRate::firstWhere('base_currency', 'VND');

                if ($conversion_rate) {
                    $conversion_amount = round((float) $transaction->transaction_amount * $conversion_rate->withdrawal_rate);
                }
            } else {
                $transaction->update([
                    'from_currency' => 'USD',
                    'to_currency' => 'USD',
                ]);
            }

            $transaction->update([
                'conversion_rate' => $conversion_rate?->withdrawal_rate,
                'conversion_amount' => $conversion_amount,
            ]);

            try {
                // POST to payment gateway by diff methods
                $responseData = (new PaymentService())->proceedPayout($payment_gateway, $transaction);
                Log::debug('Approve Withdraw Response: ', $responseData);

                if ($responseData['code'] == 200) {

                    return response()->json([
                        'success'       => true,
                        'toast_title'   => trans('public.successful'),
                        'toast_message' => trans('public.toast_approve_withdrawal_request'),
                        'toast_type'    => 'success'
                    ]);
                }

                return response()->json([
                    'success'       => false,
                    'toast_title'   => trans('public.gateway_error'),
                    'toast_message' => trans('public.please_try_again_later'),
                    'toast_type'    => 'error'
                ]);
            } catch (Exception $e) {
                Log::error('Withdraw error: ' . $e->getMessage());

                $transaction->update([
                    'status' => 'failed',
                    'approved_at' => now()
                ]);

                $this->handleTransactionUpdate($transaction);

                $user = User::find($transaction->user_id);
                Mail::to($user->email)->send(new FailedWithdrawalMail($user, $transaction));

                return response()->json([
                    'success'       => false,
                    'toast_title'   => trans('public.gateway_error'),
                    'toast_message' => $e->getMessage(),
                    'toast_type'    => 'error',
                ], 400);
            }
        }
    }

    public function getPendingRevokeData(Request $request)
    {
        $pendingRevokes = AssetRevoke::with([
            'user:id,email,name',
            'asset_master:id,asset_name',
        ])
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function ($revoke) {
                return [
                    'id' => $revoke->id,
                    'created_at' => $revoke->created_at,
                    'user_name' => $revoke->user->name,
                    'user_email' => $revoke->user->email,
                    'user_profile_photo' => $revoke->user->getFirstMediaUrl('profile_photo'),
                    'meta_login' => $revoke->meta_login,
                    'asset_master_name' => $revoke->asset_master->asset_name,
                    'amount' => $revoke->penalty_fee,
                ];
            });

        $totalAmount = $pendingRevokes->sum('amount');

        return response()->json([
            'pendingRevokes' => $pendingRevokes,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function revokeApproval(Request $request)
    {
        // Validate request data
        $request->validate([
            'id' => 'required|integer|exists:asset_revokes,id',
            'remarks' => 'required|string|max:255',
        ]);

        // Find the AssetRevoke record or fail
        $assetRevoke = AssetRevoke::findOrFail($request->id);

        // Update the AssetRevoke record
        $assetRevoke->update([
            'status' => 'revoked',
            'remarks' => $request->remarks,
            'approval_at' => now(),
            'handle_by' => Auth::id(),
        ]);

        // Update the related AssetSubscription record using the relationship
        $assetRevoke->asset_subscription()->update([
            'status' => 'revoked',
            'remarks' => $request->remarks,
            'revoked_at' => now(),
        ]);

        // Create a trade using MetaFourService
        try {
            $trade = (new MetaFourService())->createTrade((int) $assetRevoke->meta_login, -abs($assetRevoke->penalty_fee),$request->remarks,ChangeTraderBalanceType::WITHDRAW);

            // Create a new Transaction record
            Transaction::create([
                'user_id' => $assetRevoke->user_id,
                'category' => 'trading_account',
                'transaction_type' => 'penalty_fee',
                'from_meta_login' => $assetRevoke->meta_login,
                'ticket' => $trade['ticket'],
                'transaction_number' => RunningNumberService::getID('transaction'),
                'amount' => $assetRevoke->penalty_fee,
                'transaction_amount' => $assetRevoke->penalty_fee,
                'status' => 'successful',
                'remarks' => 'System Approval',
                'approved_at' => now(),
                'handle_by' => Auth::id(),
            ]);

        } catch (\Throwable $e) {
            // Log the main error
            Log::error('Error creating trade: ' . $e->getMessage());

            // Attempt to get the account and mark account as inactive if not found
            $account = (new MetaFourService())->getUser($assetRevoke->meta_login);
            if (!$account) {
                TradingUser::where('meta_login', $assetRevoke->meta_login)
                    ->update(['acc_status' => 'inactive']);
            }

            return back()->with('toast', [
                'title' => 'Trading account error',
                'type' => 'error'
            ]);
        }

        // Return a success response
        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_approve_revoke_request'),
            'type' => 'success'
        ]);
    }

    public function transactionCallback(Request $request)
    {
        $rawBody = $request->getContent();

        if ($request->header('Content-Type') == 'application/x-www-form-urlencoded') {
            // Parse form-encoded data
            parse_str($rawBody, $response);
        } else {
            // Attempt JSON decoding as fallback
            $response = json_decode($rawBody, true);
        }

        Log::debug("Callback Response: " , $response);

        $transaction_number = $response['partner_order_code'] ?? $response['outwithdrawno'];

        $transaction = Transaction::where([
            'transaction_number' => $transaction_number,
            'status' => 'pending',
        ])->first();

        switch ($transaction->payment_platform) {
            case 'bank':

                $result = [
                    'status' => $response['transfer_record']['status'] ?? null,
                    'fail_reason' => $response['transfer_record']['fail_reason'] ?? null,
                ];

                $failReasons = [
                    "0" => "SUCCESS",
                    "1" => "The information on the name/account number/card number is incorrect",
                    "2" => "The transaction amount exceeds the daily limit",
                    "3" => "Bank is maintaining",
                    "4" => "An unknown error",
                    "5" => "The information is wrong or the bank encountered an unknown error",
                ];

                if ($result['fail_reason'] != '0') {
                    $fail_reason = $failReasons[$result['fail_reason']] ?? $failReasons['4'];
                    Log::error("Transaction failed: " . $fail_reason);

                    $transaction->update([
                        'remarks' => $fail_reason,
                    ]);
                }

                break;

            case 'crypto':

                $result = [
                    'status' => $response['is_state'] ?? null,
                    'fail_reason' => $response['error'] ?? null,
                ];

                $failReasons = [
                    "0" => "Withdrawal successful",
                    "1" => "Incorrect receiving address",
                    "2" => "Amount exceeds limit",
                    "3" => "Mainchain network error",
                    "4" => "Unknown error",
                ];

                if ($result['fail_reason'] != '0') {
                    $fail_reason = $failReasons[$result['fail_reason']] ?? $failReasons['4'];
                    Log::error("Transaction failed: " . $fail_reason);

                    $transaction->update([
                        'remarks' => $fail_reason,
                    ]);
                }

                break;

            default:
                Log::error("Transaction failed: unknown payment platform");
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Transaction failed: unknown payment platform',
                ]);
        }
        $status = $result['status'] == '2' ? 'successful' : 'failed';

        $transaction->update([
            'status' => $status,
            'approved_at' => now(),
        ]);

        $user = User::find($transaction->user_id);

        if ($transaction->status == 'successful') {
            // Check if `meta_login` exists
            if ($transaction->from_meta_login) {
                $data = (new MetaFourService())->getUser($transaction->from_meta_login);

                Mail::to($user->email)->send(new WithdrawalApprovalMail(
                        $user,
                        $transaction->from_meta_login,
                        $data['group'],
                        $transaction->transaction_amount,
                        $transaction->payment_account_no,
                        $transaction->payment_platform)
                );
            } else {
                Mail::to($user->email)->send(new WithdrawalApprovalMail($user, null, null, $transaction->transaction_amount, $transaction->payment_account_no, $transaction->payment_platform));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction success',
            ]);
        } else {
            // Handle different categories (rebate_wallet, bonus_wallet, trading_account)
            $this->handleTransactionUpdate($transaction);

            Mail::to($user->email)->send(new FailedWithdrawalMail($user, $transaction));

            return response()->json([
                'status' => 'fail',
                'message' => 'Transaction failed',
            ]);
        }
    }

    /**
     * @throws Exception
     */
    public function zpay_payout_callback(Request $request)
    {
        $dataArray = $request->all();

        Log::debug("ZPay Callback Response: ", $dataArray);

        if (!$dataArray['signature']) {
            throw new Exception('Missing signature in callback');
        }

        $transaction = Transaction::with('payment_gateway')->firstWhere('transaction_number', $dataArray['reference_code']);

        $scaled_amount = $transaction->conversion_amount * pow(10, 2);

        $rawString = "{$transaction->payment_gateway->payment_app_key}&$transaction->to_currency&{$dataArray['transaction_id']}&$transaction->transaction_number&$scaled_amount";

        $signature = strtoupper(hash('sha256', $rawString));

        if ($signature != $dataArray['signature']) {
            Log::error('Signature verification failed', [
                'response signature' => $dataArray['signature'],
                'signature' => $signature
            ]);

            return response("SIGNATURE VERIFICATION FAILED", 404)
                ->header('Content-Type', 'text/plain');
        }

        $status = $dataArray['status_code'] == '10001' ? 'successful' : 'failed';

        if ($transaction->status != 'pending') {
            return response("TRANSACTION COMPLETED", 404)
                ->header('Content-Type', 'text/plain');
        }

        $transaction->update([
            'transaction_amount' => $transaction->amount,
            'status' => $status,
            'comment' => $dataArray['amount'] ?? null,
        ]);

        $user = User::find($transaction->user_id);

        if ($transaction->status == 'successful') {

            if ($transaction->from_meta_login) {
                $data = (new MetaFourService())->getUser($transaction->from_meta_login);

                Mail::to($user->email)->send(new WithdrawalApprovalMail(
                        $user,
                        $transaction->from_meta_login,
                        $data['group'],
                        $transaction->transaction_amount,
                        $transaction->payment_account_no,
                        $transaction->payment_platform)
                );
            } else {
                Mail::to($user->email)->send(new WithdrawalApprovalMail($user, null, null, $transaction->transaction_amount, $transaction->payment_account_no, $transaction->payment_platform));
            }

            return response("RECEIVED", 200)
                ->header('Content-Type', 'text/plain');
        } else {
            // Handle different categories (rebate_wallet, bonus_wallet, trading_account)
            $this->handleTransactionUpdate($transaction);

            Mail::to($user->email)->send(new FailedWithdrawalMail($user, $transaction));

            return response("SIGNATURE VERIFICATION FAILED", 404)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * @throws Exception
     */
    public function psp_payout_callback(Request $request)
    {
        $dataArray = $request->all();

        Log::debug("PSP Callback Response: ", $dataArray);

        // Extract the signature
        $signature = $dataArray['sign'] ?? null;

        if (!$signature) {
            throw new Exception('Missing signature in callback');
        }

        unset($dataArray['sign']);

        $filtered = array_filter($dataArray, fn($v) => $v !== null && $v !== '');
        ksort($filtered);

        $stringA = urldecode(http_build_query($filtered));

        $publicKeyPath = storage_path('app/keys/psp_public.pem');
        $publicKey = file_get_contents($publicKeyPath);

        $isValid = openssl_verify($stringA, base64_decode($signature), $publicKey);

        if ($isValid !== 1) {
            Log::error('Signature verification failed', ['stringA' => $stringA, 'signature' => $signature]);
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Signature verification failed.',
                ]);
        }

        $transaction = Transaction::firstWhere('transaction_number', $dataArray['seqId']);

        $status = $dataArray['stat'] == '0000' ? 'successful' : 'failed';

        $transaction->update([
            'transaction_amount' => $transaction->amount,
            'status' => $status,
            'comment' => $dataArray['amount'] ?? null,
        ]);

        $user = User::find($transaction->user_id);

        if ($transaction->status == 'successful') {
            if ($transaction->from_meta_login) {
                $data = (new MetaFourService())->getUser($transaction->from_meta_login);

                Mail::to($user->email)->send(new WithdrawalApprovalMail(
                        $user,
                        $transaction->from_meta_login,
                        $data['group'],
                        $transaction->transaction_amount,
                        $transaction->payment_account_no,
                        $transaction->payment_platform)
                );
            } else {
                Mail::to($user->email)->send(new WithdrawalApprovalMail($user, null, null, $transaction->transaction_amount, $transaction->payment_account_no, $transaction->payment_platform));
            }

            return response("SUCCESS", 200)
                ->header('Content-Type', 'text/plain');
        } else {
            // Handle different categories (rebate_wallet, bonus_wallet, trading_account)
            $this->handleTransactionUpdate($transaction);

            Mail::to($user->email)->send(new FailedWithdrawalMail($user, $transaction));

            return response("SIGNATURE VERIFICATION FAILED", 404)
                ->header('Content-Type', 'text/plain');
        }
    }

    private function handleTransactionUpdate($transaction): void
    {
        // Wallet handling logic here
        $wallets = ['rebate_wallet', 'bonus_wallet'];

        foreach ($wallets as $walletType) {
            if ($transaction->category == $walletType) {
                $wallet = Wallet::where('user_id', $transaction->user_id)
                    ->where('type', $walletType)
                    ->first();

                if ($wallet) {
                    $transaction->update([
                        'old_wallet_amount' => $wallet->balance,
                        'new_wallet_amount' => $wallet->balance + $transaction->amount,
                    ]);

                    $wallet->balance += $transaction->amount;
                    $wallet->save();
                }
            }
        }

        // Trading account logic
        if ($transaction->category == 'trading_account') {
            try {
                $tradingUser = TradingUser::where('meta_login', $transaction->from_meta_login)->with('trading_account', 'accountType')->first();
                $multiplier = $tradingUser->accountType ? $tradingUser->accountType->balance_multiplier : 1;
                $adjustedAmount = $transaction->amount * $multiplier;

                $trade = (new MetaFourService)->createTrade(
                    $transaction->from_meta_login,
                    $adjustedAmount,
                    $transaction->remarks,
                    ChangeTraderBalanceType::DEPOSIT
                );

                $transaction->update([
                    'ticket' => $trade['ticket'],
                ]);
            } catch (\Throwable $e) {
                Log::error('Error creating trade: ' . $e->getMessage());

                $account = (new MetaFourService())->getUser($transaction->from_meta_login);
                if (!$account) {
                    TradingUser::where('meta_login', $transaction->from_meta_login)
                        ->update(['acc_status' => 'inactive']);
                }

                back()->with('toast', [
                    'title' => 'Trading account error',
                    'type' => 'error'
                ]);
                return;
            }
        }
    }
}
