<?php

namespace App\Http\Controllers;

use App\Enums\MetaService;
use App\Models\AccountType;
use App\Models\TradingPlatform;
use App\Services\MetaFiveService;
use App\Services\TradingPlatform\TradingPlatformFactory;
use Carbon\Carbon;
use App\Models\User;
use Inertia\Inertia;
use App\Models\JobRunLog;
use App\Models\TradingUser;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TradingAccount;
use App\Jobs\UpdateAllAccountJob;
use App\Services\MetaFourService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AccountListingExport;
use App\Services\RunningNumberService;
use App\Services\ChangeTraderBalanceType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Exception;
use Throwable;

class TradingAccountController extends Controller
{
    public function index()
    {
        $last_refresh_datetime = JobRunLog::where('queue', 'refresh_accounts')->first();

        return Inertia::render('Member/Account/AccountListing', [
            'last_refresh_datetime' => $last_refresh_datetime?->last_ran_at,
            'leverages' => (new GeneralController())->getLeverages(true),
            'tradingPlatforms' => TradingPlatform::where('status', 'active')->get()->toArray(),
            'uplines' => (new GeneralController())->getUplines(true),
        ]);
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getAccountListingData(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $type = $data['filters']['type']['value'];

            if ($type == 'all_accounts') {
                $query = TradingUser::query()
                    ->with([
                        'users:id,name,email,upline_id',
                        'users.media',
                        'trading_account:id,meta_login,equity,balance,credit',
                        'accountType:id,trading_platform_id,slug,account_group,color',
                        'accountType.trading_platform:id,platform_name,slug',
                    ]);
            } else {
                $query = TradingUser::onlyTrashed()->with([
                    'users' => function ($q) {
                        $q->withTrashed()->select('id','name','email','upline_id','deleted_at');
                    },
                    'users.media', // media probably not soft-deleted
                    'trading_account' => function ($q) {
                        $q->withTrashed()->select('id','user_id','meta_login','balance','credit');
                    },
                    'accountType:id,trading_platform_id,slug,account_group,color',
                    'accountType.trading_platform:id,platform_name,slug',
                ]);
            }

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($query) use ($keyword) {
                    $query->whereHas('users', function ($query) use ($keyword) {
                        $query->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                    })
                        ->orWhere('meta_login', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['start_delete_date']['value']) && !empty($data['filters']['end_delete_date']['value'])) {
                $start_delete_date = Carbon::parse($data['filters']['start_delete_date']['value'])->addDay()->startOfDay();
                $end_delete_date = Carbon::parse($data['filters']['end_delete_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('deleted_at', [$start_delete_date, $end_delete_date]);
            }

            if (!empty($data['filters']['last_logged_in_days']['value'])) {
                switch ($data['filters']['last_logged_in_days']['value']) {
                    case 'greater_than_30_days':
                        $query->whereDate('last_access', '<=', today()->subDays(30));
                        break;

                    case 'greater_than_90_days':
                        $query->whereDate('last_access', '<=', today()->subDays(90));
                        break;

                    default:
                        $query->whereDate('last_access', '<=', today());
                }
            }

            if (!empty($data['filters']['balance_type']['value'])) {
                switch ($data['filters']['balance_type']['value']) {
                    case '0.00':
                        // Users with zero balance
                        $query->where('balance', 0);
                        break;

                    case 'never_deposited':
                        // Users who have never made a deposit
                        $query->whereNotExists(function ($subquery) {
                            $subquery->selectRaw('1')
                                ->from('transactions')
                                ->whereColumn('transactions.to_meta_login', 'trading_users.meta_login')
                                ->where('transactions.transaction_type', 'deposit');
                        });
                        break;

                    default:
                        $query->where('balance', $request->input('balance'));
                }
            }

            if (!empty($data['filters']['platform']['value'])) {
                $query->whereHas('accountType', function ($query) use ($data) {
                    $query->whereHas('trading_platform', function ($query) use ($data) {
                        $query->where('slug', $data['filters']['platform']['value']);
                    });
                });
            }

            if (!empty($data['filters']['account_type']['value'])) {
                $query->where('account_type_id', $data['filters']['account_type']['value']);
            }

            if (!empty($data['filters']['upline']['value'])) {
                $query->whereHas('users', function ($q) use ($data) {
                    $selected_referrers = User::find($data['filters']['upline']['value']['value']);

                    $userIds = $selected_referrers->getChildrenIds();
                    $userIds[] = $data['filters']['upline']['value']['value'];

                    $q->whereIn('upline_id', $userIds);
                });
            }

            if (!empty($data['filters']['leverage']['value'])) {
                $query->where('leverage', $data['filters']['leverage']['value']);
            }

            //sort field/order
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                if ($type == 'all_accounts') {
                    $query->orderByDesc('created_at');
                } else {
                    $query->orderByDesc('deleted_at');
                }
            }

            if ($request->has('exportStatus')) {
                return Excel::download(new AccountListingExport($query), now() . '-accounts.xlsx');
            }

            $accounts = $query->paginate($data['rows']);

            return response()->json([
                'success' => true,
                'data' => $accounts,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }

    public function getTradingAccountData(Request $request)
    {
        try {
            // Fetch and update user info using MetaFourService
            (new MetaFourService)->getUserInfo((int) $request->meta_login);

            // Retrieve the updated account data
            $account = TradingAccount::where('meta_login', $request->meta_login)->first();

            if (!$account) {
                return response()->json([
                    'message' => 'Account not found.',
                ], 404);
            }

            return response()->json([
                'currentAmount' => [
                    'account_balance' => $account->balance,
                    'account_credit' => $account->credit,
                ],
            ]);
        } catch (Throwable $e) {
            // Log any errors during the process
            Log::error("Error updating account {$request->meta_login}: {$e->getMessage()}");

            return response()->json([
                'message' => 'An error occurred while fetching account data.',
            ], 500);
        }
    }

    public function getFreshTradingAccountData(Request $request)
    {
        try {
            $account_type = AccountType::with('trading_platform')->find($request->account_type_id);

            $service = TradingPlatformFactory::make($account_type->trading_platform->slug);

            $service->getUserInfo($request->meta_login);

            $account = TradingUser::with([
                'users:id,name,email,upline_id',
                'users.media',
                'trading_account:id,user_id,meta_login,balance,credit',
                'accountType:id,trading_platform_id,slug,account_group,color',
                'accountType.trading_platform:id,platform_name,slug',
            ])
                ->withTrashed()
                ->where('meta_login', $request->meta_login)
                ->first();

            return response()->json([
                'data' => $account
            ]);
        } catch (Throwable $e) {
            Log::error("Error updating account $request->meta_login: {$e->getMessage()}");

            return response()->json([
                'message' => 'An error occurred while fetching account data.',
            ], 500);
        }
    }

    public function accountAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'remarks' => ['nullable'],
        ])->setAttributeNames([
            'action' => trans('public.action'),
            'amount' => trans('public.amount'),
            'remarks' => trans('public.remarks'),
        ]);
        $validator->validate();

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $trading_account = TradingAccount::with('account_type')
            ->where('meta_login', $request->meta_login)
            ->first();

        $trading_platform = TradingPlatform::find($trading_account->account_type->trading_platform_id);

        $service = TradingPlatformFactory::make($trading_platform->slug);

        try {
            // Fetch and update user info using MetaFourService
            $service->getUserInfo($trading_account->meta_login);

            // Retrieve the updated account data
            $account = TradingAccount::where('meta_login', $request->meta_login)->first();

            if (!$account) {
                return response()->json([
                    'message' => trans('public.no_account_found')
                ], 400);
            }
        } catch (Throwable $e) {
            // Log any errors during the process
            Log::error("Error updating account {$request->meta_login}: {$e->getMessage()}");

            return response()->json([
                'message' => trans('public.no_account_found')
            ], 400);
        }

        $action = $request->action;
        $type = $request->type;
        $amount = $request->amount;

        if ($type === 'account_balance' && $action === 'balance_out' && ($trading_account->balance - $trading_account->credit) < $amount) {
            throw ValidationException::withMessages(['amount' => trans('public.insufficient_balance')]);
        }

        if ($type === 'account_credit' && $action === 'credit_out' && $trading_account->credit < $amount) {
            throw ValidationException::withMessages(['amount' => trans('public.insufficient_credit')]);
        }

        $transaction = Transaction::create([
            'user_id' => $trading_account->user_id,
            'category' => 'trading_account',
            'transaction_type' => $action,
            'from_meta_login' => ($action === 'balance_out' || $action === 'credit_out') ? $trading_account->meta_login : null,
            'to_meta_login' => ($action === 'balance_in' || $action === 'credit_in') ? $trading_account->meta_login : null,
            'transaction_number' => RunningNumberService::getID('transaction'),
            'amount' => $amount,
            'transaction_amount' => $amount,
            'status' => 'processing',
            'remarks' => $request->remarks,
            'handle_by' => Auth::id(),
        ]);

        $changeType = match($type) {
            'account_balance' => match($action) {
                'balance_in', 'balance_out' => MetaService::BALANCE,
                default => throw ValidationException::withMessages(['action' => trans('public.invalid_type')]),
            },
            'account_credit' => match($action) {
                'credit_in', 'credit_out' => MetaService::CREDIT,
                default => throw ValidationException::withMessages(['action' => trans('public.invalid_type')]),
            },
            default => throw ValidationException::withMessages(['action' => trans('public.invalid_type')]),
        };

        if (($action === 'balance_out' || $action === 'credit_out')) {
            $amount = -abs($amount);
        }

        try {
            $trade = $service->createDeal($trading_account->meta_login, $amount, $transaction->remarks, $changeType, '');

            $transaction->update([
                'ticket' => $trade['ticket'],
                'approved_at' => now(),
                'status' => 'successful',
            ]);

            $trading_user = TradingUser::where('meta_login', $request->meta_login)->first();

            // Check if expiration_date exists and update the comment
            if (isset($trade['expiration_date'])) {
                $expiration_date = $trade['expiration_date'];
                $trading_user->update([
                    'comment' => "Credit expired on {$expiration_date}."
                ]);
            }

            $account = $this->getFreshAccount($trading_account->meta_login);

            return response()->json([
                'title' => trans('public.successful'),
                'message' => $type == 'account_balance'
                    ? trans('public.toast_balance_adjustment_success')
                    : trans('public.toast_credit_adjustment_success'),
                'account' => $account,
            ]);
        } catch (Throwable $e) {
            // Update transaction status to failed on error
            $transaction->update([
                'approved_at' => now(),
                'status' => 'failed'
            ]);

            // Log the main error
            Log::error('Error creating trade: ' . $e->getMessage());

            // Attempt to get the account and mark account as inactive if not found
            $account = (new MetaFourService())->getUser($trading_account->meta_login);
            if (!$account) {
                TradingUser::where('meta_login', $trading_account->meta_login)
                    ->update(['acc_status' => 'inactive']);
            }

            return response()->json([
                'message' => trans('public.toast_adjustment_error')
            ], 400);
        }
    }

    public function updateLeverage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meta_login' => ['required'],
            'leverage' => ['required'],
        ])->setAttributeNames([
            'meta_login' => trans('public.meta_login'),
            'leverage' => trans('public.leverage'),
        ]);
        $validator->validate();

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $trading_account = TradingAccount::with('account_type')
            ->where('meta_login', $request->meta_login)
            ->first();

        $trading_platform = TradingPlatform::find($trading_account->account_type->trading_platform_id);

        $service = TradingPlatformFactory::make($trading_platform->slug);

        try {
            $service->updateLeverage($request->meta_login, $request->leverage);

            $account = $this->getFreshAccount($trading_account->meta_login);

            return response()->json([
                'title' => trans('public.successful'),
                'message' => trans('public.toast_change_leverage_success'),
                'account' => $account,
            ]);
        } catch (Throwable $e) {
            // Log the main error
            Log::error('Error update leverage: ' . $e->getMessage());

            return response()->json([
                'message' => trans('public.toast_change_leverage_failed')
            ], 400);
        }
    }

    public function updateAccountGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meta_login' => ['required'],
            'account_group' => ['required'],
        ])->setAttributeNames([
            'meta_login' => trans('public.meta_login'),
            'account_group' => trans('public.account_type'),
        ]);
        $validator->validate();

        try {
            (new MetaFourService())->updateAccountGroup($request->meta_login, $request->account_group);

            return redirect()->back()->with('toast', [
                'title' => trans('public.toast_change_account_type_success'),
                'type' => 'success'
            ]);
        } catch (Throwable $e) {
            // Log the main error
            Log::error('Error creating trade: ' . $e->getMessage());

            return back()
                ->with('toast', [
                    'title' => trans('public.toast_change_account_type_failed'),
                    'type' => 'error'
                ]);
        }
    }

    public function change_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'meta_login' => ['required'],
            'master_password' => ['nullable', Password::min(8)->letters()->mixedCase()->numbers()->symbols(), 'required_without:investor_password'],
            'investor_password' => ['nullable', Password::min(8)->letters()->mixedCase()->numbers()->symbols(), 'required_without:master_password'],
        ])->setAttributeNames([
            'meta_login' => trans('public.meta_login'),
            'master_password' => trans('public.master_password'),
            'investor_password' => trans('public.investor_password'),
        ]);
        $validator->validate();

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        // $user = User::find($request->user_id);
        $meta_login = $request->meta_login;
        $master_password = $request->master_password;
        $investor_password = $request->investor_password;

        $trading_account = TradingAccount::with('account_type')
            ->where('meta_login', $request->meta_login)
            ->first();

        $trading_platform = TradingPlatform::find($trading_account->account_type->trading_platform_id);

        $service = TradingPlatformFactory::make($trading_platform->slug);

        try {
            if ($master_password || $investor_password) {
                if ($master_password) {
                    $service->changeMasterPassword($meta_login, $master_password);
                }

                if ($investor_password) {
                    $service->changeInvestorPassword($meta_login, $investor_password);
                }

                // // Send notification
                // Notification::route('mail', $user->email)
                //     ->notify(new ChangeTradingAccountPasswordNotification($user, $meta_login, $master_password, $investor_password));
            }

            $account = $this->getFreshAccount($trading_account->meta_login);

            return response()->json([
                'title' => trans('public.successful'),
                'message' => trans('public.toast_update_password_success'),
                'account' => $account,
            ]);
        } catch (Throwable $e) {
            // Log the main error
            Log::error('Error updating trading account password: ' . $e->getMessage());

            return response()->json([
                'message' => trans('public.toast_update_password_failed')
            ], 400);
        }
    }

    public function accountDelete(Request $request)
    {
        // $cTraderService = (new CTraderService);

        // $conn = $cTraderService->connectionStatus();
        // if ($conn['code'] != 0) {
        //     return back()
        //         ->with('toast', [
        //             'title' => 'Connection Error',
        //             'type' => 'error'
        //         ]);
        // }

        // try {
        //     $cTraderService->getUserInfo($request->meta_login);
        // } catch (\Throwable $e) {
        //     Log::error($e->getMessage());

        //     return back()
        //         ->with('toast', [
        //             'title' => 'No Account Found',
        //             'type' => 'error'
        //         ]);
        // }

        $trading_account = TradingAccount::where('meta_login', $request->meta_login)->first();

        if ($trading_account->balance > 0 || $trading_account->equity > 0 || $trading_account->credit > 0) {
            return back()
                ->with('toast', [
                    'title' => trans('public.account_have_balance'),
                    'type' => 'error'
                ]);
        }

        // try {
        //     $cTraderService->deleteTrader($trading_account->meta_login);

        //     $trading_account->trading_user->delete();
        //     $trading_account->delete();

        //     // Return success response with a flag for toast
        //     return redirect()->back()->with('toast', [
        //         'title' => trans('public.toast_delete_trading_account_success'),
        //         'type' => 'success',
        //     ]);
        // } catch (\Throwable $e) {
        //     // Log the error and return failure response
        //     Log::error('Failed to delete trading account: ' . $e->getMessage());

        //     return back()
        //         ->with('toast', [
        //             'title' => 'No Account Found',
        //             'type' => 'error'
        //         ]);
        // }
    }

    public function refreshAllAccount(): void
    {
        UpdateAllAccountJob::dispatch();
    }

    private function getFreshAccount($meta_login)
    {
        return TradingUser::with([
            'users:id,name,email,upline_id',
            'users.media',
            'trading_account:id,user_id,meta_login,balance,credit',
            'accountType:id,trading_platform_id,slug,account_group,color',
            'accountType.trading_platform:id,platform_name,slug',
        ])
            ->withTrashed()
            ->where('meta_login', $meta_login)
            ->first();
    }
}
