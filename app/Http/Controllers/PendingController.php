<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\AssetRevoke;
use App\Models\TradingUser;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\PaymentGateway;
use Illuminate\Support\Carbon;
use App\Services\MetaFourService;
use App\Mail\FailedWithdrawalMail;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Mail\WithdrawalApprovalMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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
        $pendingWithdrawals = Transaction::with([
            'user:id,email,name',
            'payment_account:id,payment_account_name,account_no',
        ])
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'processing')
            ->whereNot('category', 'bonus_wallet')
            ->latest()
            ->get()
            ->map(function ($transaction) {
                // Check if from_meta_login exists and fetch the latest balance
                if ($transaction->from_meta_login) {
                    (new MetaFourService())->getUserInfo($transaction->from_meta_login);

                    // After calling getUserInfo, fetch the latest balance
                    $balance = $transaction->from_meta_login ? $transaction->fromMetaLogin->balance : 0;
                } else {
                    // Fallback to using the wallet balance if from_meta_login is not available
                    $balance = $transaction->from_wallet ? $transaction->from_wallet->balance : 0;
                }

                return [
                    'id' => $transaction->id,
                    'created_at' => $transaction->created_at,
                    'user_name' => $transaction->user->name,
                    'user_email' => $transaction->user->email,
                    'from' => $transaction->from_meta_login ? $transaction->from_meta_login : 'rebate_wallet',
                    'balance' => $balance, // Get balance after ensuring it's updated
                    'amount' => $transaction->amount,
                    'transaction_charges' => $transaction->transaction_charges,
                    'transaction_amount' => $transaction->transaction_amount,
                    'wallet_name' => $transaction->payment_account?->payment_account_name,
                    'wallet_address' => $transaction->payment_account?->account_no,
                    'payment_account_name' => $transaction->payment_account_name,
                    'payment_platform' => $transaction->payment_platform,
                    'payment_platform_name' => $transaction->payment_platform_name,
                    'payment_account_no' => $transaction->payment_account_no,
                    'payment_account_type' => $transaction->payment_account_type,
                    'bank_code' => $transaction->bank_code,
                ];
            });

        $totalAmount = $pendingWithdrawals->sum('transaction_amount');

        return response()->json([
            'pendingWithdrawals' => $pendingWithdrawals,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function withdrawalApproval(Request $request)
    {
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
            $environment = App::environment() == 'production' ? 'production' : 'local';

            $payment_gateway = PaymentGateway::where([
                ['platform', $transaction->payment_platform],
                ['environment', $environment],
            ])->first();

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

            // POST to payment gateway by diff methods
            $params = [
                'partner_id' => $payment_gateway->payment_app_number,
                'timestamp' => Carbon::now()->timestamp,
                'random' => Str::random(14),
                'partner_order_code' => $transaction->transaction_number,
                'amount' => $conversion_amount,
                'notify_url' => route('transactionCallback'),
            ];

            switch ($transaction->payment_platform) {
                case 'bank':
                    $params = array_merge($params, [
                        'payee_bank_code' => $transaction->bank_code,
                        'payee_bank_account_type' => $transaction->payment_account_type,
                        'payee_bank_account_no' => $transaction->payment_account_no,
                        'payee_bank_account_name' => $transaction->payment_account_name,
                    ]);

                    $data = [
                        $params['partner_id'],
                        $params['timestamp'],
                        $params['random'],
                        $params['partner_order_code'],
                        $params['amount'],
                        $params['payee_bank_code'],
                        $params['payee_bank_account_type'],
                        $params['payee_bank_account_no'],
                        $params['payee_bank_account_name'],
                        '',
                        '',
                        $payment_gateway->payment_app_key,
                    ];

                    $baseUrl = $payment_gateway->payment_url . '/gateway/bnb/transferATM.do';
                    break;

                case 'crypto':
                    $params = array_merge($params, [
                        'channel_code' => $transaction->payment_account_type,
                        'address' => $transaction->payment_account_no,
                    ]);

                    $data = [
                        $params['partner_id'],
                        $params['timestamp'],
                        $params['random'],
                        $params['partner_order_code'],
                        $params['channel_code'],
                        $params['address'],
                        $params['amount'],
                        $params['notify_url'],
                        '',
                        '',
                        $payment_gateway->payment_app_key,
                    ];

                    $baseUrl = $payment_gateway->payment_url . '/gateway/usdt/transfer.do';
                    break;

                default:
                    return back()
                        ->with('toast', [
                            'title' => 'Payment Account platform not found',
                            'type' => 'error'
                        ]);
            }

            $hashedCode = md5(implode(':', $data));
            $params['sign'] = $hashedCode;

            $response = Http::post($baseUrl, $params);
            $responseData = $response->json();
            Log::debug('Approve Withdraw Response: ', $responseData);

            if ($responseData['code'] == 200) {
                return redirect()->back()->with('toast', [
                    'title' => trans('public.toast_approve_withdrawal_request'),
                    'type' => 'success',
                ]);
            }

            // Handle error scenario if "code" is not present in the response
            return redirect()->back()->with('toast', [
                'title' => $responseData['msg'] ?? 'Payment gateway error',
                'type' => 'error',
            ]);
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
