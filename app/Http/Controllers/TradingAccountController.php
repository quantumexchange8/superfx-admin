<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AccountListingExport;
use App\Services\RunningNumberService;
use App\Services\Data\UpdateTradingUser;
use App\Services\ChangeTraderBalanceType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Services\Data\UpdateTradingAccount;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use App\Notifications\ChangeTradingAccountPasswordNotification;

class TradingAccountController extends Controller
{
    public function index()
    {
        $last_refresh_datetime = JobRunLog::where('queue', 'refresh_accounts')->first();

        return Inertia::render('Member/Account/AccountListing', [
            'last_refresh_datetime' => $last_refresh_datetime?->last_ran_at,
            'leverages' => (new GeneralController())->getLeverages(true),
            'accountTypes' => (new GeneralController())->getAccountTypes(true),
            'uplines' => (new GeneralController())->getUplines(true),
        ]);
    }

    public function getAccountListingData(Request $request)
    {
        if ($request->account_listing == 'all') {
            $accountQuery = TradingUser::with([
                'user:id,name,email',
                'trading_account:id,meta_login,equity'
            ]);

            if ($request->last_logged_in_days) {
                switch ($request->last_logged_in_days) {
                    case 'greater_than_90_days':
                        $accountQuery->whereDate('last_access', '<=', today()->subDays(90));
                        break;

                    default:
                        $accountQuery->whereDate('last_access', '<=', today());
                }
            }

            $accounts = $accountQuery
                ->orderByDesc('last_access')
                ->get()
                ->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'meta_login' => $account->meta_login,
                        'user_name' => $account->user->name,
                        'user_email' => $account->user->email,
                        'user_profile_photo' => $account->user->getFirstMediaUrl('profile_photo'),
                        'balance' => $account->balance,
                        'equity' => $account->trading_account->equity,
                        'credit' => $account->credit,
                        'leverage' => $account->leverage,
                        'last_login' => $account->last_access,
                        'last_login_days' => Carbon::parse($account->last_access)->diffInDays(today()),
                    ];
                });
        } else {
            $startDate = $request->query('startDate');
            $endDate = $request->query('endDate');

            $accountQuery = TradingUser::onlyTrashed()
                ->with([
                    'user:id,name,email',
                    'trading_account:id,user_id,meta_login'
                ])->withTrashed(['user:id,name,email', 'trading_account:id,user_id,meta_login']);

            if ($startDate && $endDate) {
                $start_date = Carbon::createFromFormat('Y/m/d', $startDate)->startOfDay();
                $end_date = Carbon::createFromFormat('Y/m/d', $endDate)->endOfDay();

                $accountQuery->whereBetween('deleted_at', [$start_date, $end_date]);
            }

            $accounts = $accountQuery
                ->orderByDesc('deleted_at')
                ->get()
                ->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'meta_login' => $account->meta_login,
                        'user_name' => optional($account->user)->name,
                        'user_email' => optional($account->user)->email,
                        'user_profile_photo' => optional($account->user)->getFirstMediaUrl('profile_photo'),
                        'balance' => $account->balance,
                        'equity' => 0,
                        'credit' => $account->credit,
                        'leverage' => $account->leverage,
                        'deleted_at' => $account->deleted_at,
                        'last_login' => $account->last_access,
                        'last_login_days' => Carbon::parse($account->last_access)->diffInDays(today()),
                    ];
                });
        }

        return response()->json([
            'accounts' => $accounts
        ]);
    }

    public function getAccountListingPaginate(Request $request)
    {
        $type = $request->type;

        if ($type === 'all') {
            $query = TradingUser::query()
                ->with([
                    'users:id,name,email,upline_id',
                    'trading_account:id,meta_login,equity',
                    'accountType:id,slug,account_group,color',
                ]);

            if ($request->last_logged_in_days) {
                switch ($request->last_logged_in_days) {
                    case 'greater_than_90_days':
                        $query->whereDate('last_access', '<=', today()->subDays(90));
                        break;

                    default:
                        $query->whereDate('last_access', '<=', today());
                }
            }
            // Filters
            // Handle search functionality
            $search = $request->input('search');
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('users', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                              ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhere('meta_login', 'like', '%' . $search . '%');
                });
            }
            
            if ($request->input('balance')) {
                $query->where('balance', $request->input('balance'));
            }

            if ($request->input('leverage')) {
                $query->where('leverage', $request->input('leverage'));
            }

            if ($request->input('account_type')) {
                $query->where('account_type_id', $request->input('account_type'));
            }

            if ($request->input('upline_id')) {
                $uplineId = $request->input('upline_id');
            
                // Get upline and their children IDs
                $upline = User::find($uplineId);
                $childrenIds = $upline ? $upline->getChildrenIds() : [];
                $childrenIds[] = $uplineId;
            
                // Filter only active users where upline_id is in the list or user is the upline
                $query->whereHas('users', function ($q) use ($childrenIds, $uplineId) {
                    $q->where(function ($query) use ($childrenIds, $uplineId) {
                        $query->where('id', $uplineId)
                            ->orWhereIn('upline_id', $childrenIds);
                      });
                });
            }
            
            // Handle sorting
            $sortField = $request->input('sortField', 'meta_login'); // Default to 'meta_login'
            $sortOrder = $request->input('sortOrder', -1); // 1 for ascending, -1 for descending
            $query->orderBy($sortField, $sortOrder == 1 ? 'asc' : 'desc');
    
            // Handle pagination
            $rowsPerPage = $request->input('rows', 15); // Default to 15 if 'rows' not provided
            $currentPage = $request->input('page', 0) + 1; // Laravel uses 1-based page numbers, PrimeVue uses 0-based

            // Export logic
            if ($request->has(key: 'exportStatus') && $request->exportStatus == true) {
                $accounts = $query->clone();
                return Excel::download(new AccountListingExport($accounts), now() . '-accounts.xlsx');
            }

            // Now re-fetch the data after updating the statuses
            $accounts = $query->select([
                'id',
                'user_id',
                'meta_login',
                'account_type_id',
                'balance',
                'credit',
                'leverage',
                'deleted_at',
                'last_access as last_login',
                DB::raw('DATEDIFF(CURRENT_DATE, last_access) as last_login_days'), // Raw SQL for last login days
            ])
            ->paginate($rowsPerPage, ['*'], 'page', $currentPage);
            
            // After the accounts are retrieved, you can access `getFirstMediaUrl` for each user using foreach
            foreach ($accounts as $account) {
                $account->upline_id = optional($account->users)->upline_id;
                $account->user_profile_photo = optional($account->users)->getFirstMediaUrl('profile_photo');
                $account->user_name = optional($account->users)->name;
                $account->user_email = optional($account->users)->email;
                $account->equity = optional($account->trading_account)->equity ?? 0;
                $account->account_type = optional($account->accountType)->slug;
                $account->account_type_color = optional($account->accountType)->color;
                $account->account_group = optional($account->accountType)->account_group;
            
                // Remove unnecessary nested data (users and trading_account)
                unset($account->users);
                unset($account->trading_account);
                unset($account->accountType);
            }
                            
            // After the status update, return the re-fetched paginated data
            return response()->json([
                'success' => true,
                'data' => $accounts,
            ]);
        } else {
            // Handle inactive accounts or other types
            $query = TradingUser::onlyTrashed() // Only consider soft-deleted trading users
                ->withTrashed([
                    'user:id,name,email,upline_id', 
                    'trading_account:id,user_id,meta_login',
                    'accountType:id,slug,account_group,color',
                ]); // Include soft-deleted related users, trading accounts and account type
        
            // Filters
            // Handle search functionality
            $search = $request->input('search');
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('users', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                              ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhere('meta_login', 'like', '%' . $search . '%');
                });
            }

            // if ($request->input('upline_id')) {
            //     $uplineId = $request->input('upline_id');
            
            //     // Get upline and their children IDs
            //     $upline = User::withTrashed()->find($uplineId);
            //     $childrenIds = $upline ? $upline->getChildrenIds() : [];
            //     $childrenIds[] = $uplineId;
            
            //     // Filter only deleted users that match the upline logic
            //     $query->whereHas('users', function ($q) use ($childrenIds, $uplineId) {
            //         $q->onlyTrashed()->where(function ($query) use ($childrenIds, $uplineId) {
            //             $query->where('id', $uplineId)
            //                 ->orWhereIn('upline_id', $childrenIds);
            //         });
            //     });
            // }
                        
            // Handle sorting
            $sortField = $request->input('sortField', 'deleted_at'); // Default to 'created_at'
            $sortOrder = $request->input('sortOrder', -1); // 1 for ascending, -1 for descending
            $query->orderBy($sortField, $sortOrder == 1 ? 'asc' : 'desc');
    
            // Handle pagination
            $rowsPerPage = $request->input('rows', 15); // Default to 15 if 'rows' not provided
            $currentPage = $request->input('page', 0) + 1; // Laravel uses 1-based page numbers, PrimeVue uses 0-based
    
            // // Export logic
            // if ($request->has(key: 'exportStatus') && $request->exportStatus == true) {
            //     $accounts = $query->clone();
            //     return Excel::download(new AccountListingExport($accounts), now() . '-accounts.xlsx');
            // }

            $accounts = $query->select([
                'id',
                'user_id',
                'meta_login',
                'balance',
                'credit',
                'leverage',
                'deleted_at',
                'last_access as last_login',
                DB::raw('DATEDIFF(CURRENT_DATE, last_access) as last_login_days'), // Raw SQL for last login days
            ])
            ->paginate($rowsPerPage, ['*'], 'page', $currentPage);
            
            // After the accounts are retrieved, you can access `getFirstMediaUrl` for each user using foreach
            foreach ($accounts as $account) {
                $account->user_profile_photo = optional($account->users)->getFirstMediaUrl('profile_photo');
                $account->user_name = optional($account->users)->name;
                $account->user_email = optional($account->users)->email;
                $account->equity = optional($account->trading_account)->equity;
                $account->account_type = optional($account->accountType)->slug;
                $account->account_type_color = optional($account->accountType)->color;
                $account->account_group = optional($account->accountType)->account_group;
            
                // Remove unnecessary nested data (users and trading_account)
                unset($account->users);
                unset($account->trading_account);
                unset($account->accountType);
            }

            return response()->json([
                'success' => true,
                'data' => $accounts,
            ]);
        }
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
        } catch (\Throwable $e) {
            // Log any errors during the process
            Log::error("Error updating account {$request->meta_login}: {$e->getMessage()}");
    
            return response()->json([
                'message' => 'An error occurred while fetching account data.',
            ], 500);
        }
    }
    
    public function accountAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required'],
            'amount' => ['required', 'numeric', 'gt:1'],
            'remarks' => ['nullable'],
        ])->setAttributeNames([
            'action' => trans('public.action'),
            'amount' => trans('public.amount'),
            'remarks' => trans('public.remarks'),
        ]);
        $validator->validate();

        try {
            // Fetch and update user info using MetaFourService
            (new MetaFourService)->getUserInfo((int) $request->meta_login);
    
            // Retrieve the updated account data
            $account = TradingAccount::where('meta_login', $request->meta_login)->first();
    
            if (!$account) {
                return back()
                    ->with('toast', [
                        'title' => 'No Account Found',
                        'type' => 'error'
                    ]);
            }
        } catch (\Throwable $e) {
            // Log any errors during the process
            Log::error("Error updating account {$request->meta_login}: {$e->getMessage()}");
    
            return back()
                    ->with('toast', [
                        'title' => 'No Account Found',
                        'type' => 'error'
                    ]);
        }

        $trading_account = TradingAccount::where('meta_login', $request->meta_login)->first();
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
                'balance_in' => ChangeTraderBalanceType::DEPOSIT,
                'balance_out' => ChangeTraderBalanceType::WITHDRAW,
                default => throw ValidationException::withMessages(['action' => trans('public.invalid_type')]),
            },
            'account_credit' => match($action) {
                'credit_in' => ChangeTraderBalanceType::CREDIT_IN,
                'credit_out' => ChangeTraderBalanceType::CREDIT_OUT,
                default => throw ValidationException::withMessages(['action' => trans('public.invalid_type')]),
            },
            default => throw ValidationException::withMessages(['action' => trans('public.invalid_type')]),
        };

        if (($action === 'balance_out' || $action === 'credit_out')) {
            $amount = -abs($amount);
        }

        try {
            $trade = (new MetaFourService())->createTrade($trading_account->meta_login, $amount, $transaction->remarks, $changeType);

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

            return redirect()->back()->with('toast', [
                'title' => $type == 'account_balance' ? trans('public.toast_balance_adjustment_success') : trans('public.toast_credit_adjustment_success'),
                'type' => 'success'
            ]);
        } catch (\Throwable $e) {
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

            return back()
                ->with('toast', [
                    'title' => 'Adjustment failed',
                    'type' => 'error'
                ]);
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

        try {
            (new MetaFourService())->updateLeverage($request->meta_login, $request->leverage);

            return redirect()->back()->with('toast', [
                'title' => trans('public.toast_change_leverage_success'),
                'type' => 'success'
            ]);
        } catch (\Throwable $e) {
            // Log the main error
            Log::error('Error creating trade: ' . $e->getMessage());

            return back()
                ->with('toast', [
                    'title' => trans('public.toast_change_leverage_failed'),
                    'type' => 'error'
                ]);
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
        } catch (\Throwable $e) {
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
        Validator::make($request->all(), [
            'meta_login' => ['required'],
            'master_password' => ['nullable', Password::min(8)->letters()->mixedCase()->numbers()->symbols(), 'required_without:investor_password'],
            'investor_password' => ['nullable', Password::min(8)->letters()->mixedCase()->numbers()->symbols(), 'required_without:master_password'],
        ])->setAttributeNames([
            'meta_login' => trans('public.meta_login'),
            'master_password' => trans('public.master_password'),
            'investor_password' => trans('public.investor_password'),
        ])->validate();

        // $user = User::find($request->user_id);
        $meta_login = $request->meta_login;
        $master_password = $request->master_password;
        $investor_password = $request->investor_password;
        
        // Try to update passwords and send notification
        try {
            if ($master_password || $investor_password) {
                if ($master_password) {
                    (new MetaFourService())->updateMasterPassword($meta_login, $master_password);
                }

                if ($investor_password) {
                    (new MetaFourService())->updateInvestorPassword($meta_login, $investor_password);
                }

                // // Send notification
                // Notification::route('mail', $user->email)
                //     ->notify(new ChangeTradingAccountPasswordNotification($user, $meta_login, $master_password, $investor_password));
            }

            // Success response
            return back()->with('toast', [
                'title' => trans("public.toast_update_password_success"),
                'type' => 'success',
            ]);

        } catch (\Throwable $e) {
            // Log the error
            Log::error('Error updating trading account password: ' . $e->getMessage());

            // Failure response
            return back()->with('toast', [
                'title' => trans('public.toast_update_password_failed'),
                'type' => 'error',
            ]);
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
}
