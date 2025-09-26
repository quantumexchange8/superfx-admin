<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\TradingPlatform;
use App\Services\MetaFiveService;
use Carbon\Carbon;
use App\Models\Bank;
use App\Models\Team;
use App\Models\User;
use App\Models\Symbol;
use App\Models\Wallet;
use App\Models\Country;
use App\Models\AccountType;
use App\Models\TeamHasUser;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TeamSettlement;
use App\Models\TradingAccount;
use App\Models\SettingLeverage;
use App\Models\LeaderboardBonus;
use App\Services\CTraderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class GeneralController extends Controller
{
    public function getWalletData(Request $request, $returnAsArray = false)
    {
        $wallets = Wallet::where('user_id', $request->user_id)
                         ->where('type', 'rebate_wallet')
                         ->first();

        if ($returnAsArray) {
            return $wallets;
        }

        return response()->json([
            'walletData' => $wallets,
        ]);
    }

    // public function getTradingAccountData(Request $request, $returnAsArray = false)
    // {
    //     $conn = (new CTraderService)->connectionStatus();
    //     if ($conn['code'] != 0) {
    //         return collect([
    //             'toast' => [
    //                 'title' => 'Connection Error',
    //                 'type' => 'error'
    //             ]
    //         ]);
    //     }

    //     $accounts = TradingAccount::where('user_id', $request->user_id)->get();
    //     $accountData = $accounts->map(function ($account) {
    //         try {
    //             (new CTraderService)->getUserInfo($account->meta_login);
    //             $updatedAccount = TradingAccount::where('meta_login', $account->meta_login)->first();

    //             return [
    //                 'meta_login' => $updatedAccount->meta_login,
    //                 'balance' => $updatedAccount->balance - $updatedAccount->credit,
    //                 'credit' => $updatedAccount->credit,
    //             ];
    //         } catch (\Throwable $e) {
    //             Log::error("Error processing account {$account->meta_login}: " . $e->getMessage());

    //             return [
    //                 'meta_login' => $account->meta_login,
    //                 'balance' => 0,
    //                 'credit' => 0,
    //             ];
    //         }
    //     });

    //     if ($returnAsArray) {
    //         return $accountData;
    //     }

    //     return collect(['accountData' => $accountData]);
    // }

    // public function updateAccountData(Request $request)
    // {
    //     $conn = (new CTraderService)->connectionStatus();
    //     if ($conn['code'] != 0) {
    //         return collect([
    //             'toast' => [
    //                 'title' => 'Connection Error',
    //                 'type' => 'error'
    //             ]
    //         ]);
    //     }

    //     try {
    //         // Fetch updated account info using CTraderService
    //         (new CTraderService)->getUserInfo($request->meta_login);

    //         $updatedAccount = TradingAccount::where('meta_login', $request->meta_login)->first();

    //         return response()->json([
    //             'meta_login' => $updatedAccount->meta_login,
    //             'balance' => $updatedAccount->balance - $updatedAccount->credit,
    //             'credit' => $updatedAccount->credit,
    //         ]);
    //     } catch (\Throwable $e) {
    //         Log::error("Error processing account {$request->meta_login}: " . $e->getMessage());

    //         return response()->json([
    //             'meta_login' => $request->meta_login,
    //             'balance' => 0,
    //             'credit' => 0,
    //         ]);
    //     }
    // }

    public function getLeverages($returnAsArray = false)
    {
        $leverages = SettingLeverage::where('status', 'active')->get()
            ->map(function ($leverage) {
                return [
                    'name' => $leverage->display,
                    'value' => $leverage->value,
                ];
            })
            ->prepend([
                'name' => trans('public.all'),
                'value' => 0,
            ]);

        if ($returnAsArray) {
            return $leverages;
        }

        return response()->json([
            'leverages' => $leverages,
        ]);
    }

    // public function getTransactionMonths($returnAsArray = false)
    // {
    //     $transactionDates = Transaction::pluck('created_at');
    //     $months = $transactionDates
    //         ->map(function ($date) {
    //             // Extract only the "F Y" format for uniqueness
    //             return Carbon::parse($date)->format('F Y');
    //         })
    //         ->unique()
    //         ->map(function ($month) {
    //             // Reformat the unique months to include "01" in front
    //             return '01 ' . $month;
    //         })
    //         ->values();

    //     // Add the current month at the end if it's not already in the list
    //     $currentMonth = '01 ' . Carbon::now()->format('F Y');
    //     if (!$months->contains($currentMonth)) {
    //         $months->push($currentMonth);
    //     }

    //     if ($returnAsArray) {
    //         return $months;
    //     }

    //     return response()->json([
    //         'months' => $months,
    //     ]);
    // }

    public function getTransactionMonths($returnAsArray = false)
    {
        // Fetch the created_at dates of all transactions
        $transactionDates = Transaction::pluck('created_at');

        // Map the dates to the desired format (m/Y) and remove duplicates
        $months = $transactionDates
            ->map(function ($date) {
                return Carbon::parse($date)->format('m/Y');
            })
            ->unique()
            ->values();

        // Add the current month at the end if it's not already in the list
        $currentMonth = Carbon::now()->format('m/Y');
        if (!$months->contains($currentMonth)) {
            $months->push($currentMonth);
        }

        // Return as an array if requested
        if ($returnAsArray) {
            return $months;
        }

        // Return as JSON response by default
        return response()->json([
            'months' => $months,
        ]);
    }

    // public function getIncentiveMonths($returnAsArray = false)
    // {
    //     $incentiveDates = LeaderboardBonus::pluck('created_at');
    //     $months = $incentiveDates
    //         ->map(function ($date) {
    //             return Carbon::parse($date)->format('F Y');
    //         })
    //         ->unique()
    //         ->map(function ($month) {
    //             // Reformat the unique months to include "01" in front
    //             return '01 ' . $month;
    //         })
    //         ->values();

    //     // Add the current month at the end if it's not already in the list
    //     $currentMonth = '01 ' . Carbon::now()->format('F Y');
    //     if (!$months->contains($currentMonth)) {
    //         $months->push($currentMonth);
    //     }

    //     if ($returnAsArray) {
    //         return $months;
    //     }

    //     return response()->json([
    //         'months' => $months,
    //     ]);
    // }

    public function getAllAccountTypes($returnAsArray = false)
    {
        $accountTypes = AccountType::with('trading_platform:id,slug')
            ->where('status', 'active')
            ->where('account_group', '!=', 'Demo Account')
            ->get()
            ->map(function ($accountType) {
                return [
                    'value' => $accountType->id,
                    'name' => trans('public.' . $accountType->slug),
                    'trading_platform' => $accountType->trading_platform->slug,
                ];
            });

        if ($returnAsArray) {
            return $accountTypes;
        }

        return response()->json([
            'accountTypes' => $accountTypes,
        ]);
    }

    public function getAccountTypes($returnAsArray = false)
    {
        $accountTypes = AccountType::where('status', 'active')
            ->where('account_group', '!=', 'Demo Account')
            ->whereHas('markupProfileToAccountTypes.markupProfile.userToMarkupProfiles')
            ->get()
            ->map(function ($accountType) {
                return [
                    'value' => $accountType->id,
                    'name' => trans('public.' . $accountType->slug),
                ];
            });

        if ($returnAsArray) {
            return $accountTypes;
        }

        return response()->json([
            'accountTypes' => $accountTypes,
        ]);
    }

    public function getAllAccountGroups($returnAsArray = false)
    {
        $accountGroups = AccountType::where('status', 'active')
            ->where('account_group', '!=', 'Demo Account')
            ->get()
            ->map(function ($accountType) {
                return [
                    'value' => $accountType->account_group,
                    'name' => trans('public.' . $accountType->slug),
                ];
            });

        if ($returnAsArray) {
            return $accountGroups;
        }

        return response()->json([
            'accountGroups' => $accountGroups,
        ]);
    }

    public function getAccountGroups($returnAsArray = false)
    {
        $accountGroups = AccountType::where('status', 'active')
            ->where('account_group', '!=', 'Demo Account')
            ->whereHas('markupProfileToAccountTypes.markupProfile.userToMarkupProfiles')
            ->get()
            ->map(function ($accountType) {
                return [
                    'value' => $accountType->account_group,
                    'name' => trans('public.' . $accountType->slug),
                ];
            });

        if ($returnAsArray) {
            return $accountGroups;
        }

        return response()->json([
            'accountGroups' => $accountGroups,
        ]);
    }

    // public function getSettlementMonths($returnAsArray = false)
    // {
    //     $settledDates = GroupSettlement::pluck('transaction_start_at');
    //     $months = $settledDates
    //         ->map(function ($date) {
    //             return Carbon::parse($date)->format('F Y');
    //         })
    //         ->unique()
    //         ->map(function ($month) {
    //             // Reformat the unique months to include "01" in front
    //             return '01 ' . $month;
    //         })
    //         ->values();

    //     if ($returnAsArray) {
    //         return $months;
    //     }

    //     return response()->json([
    //         'months' => $months,
    //     ]);
    // }

    public function getUplines($returnAsArray = false)
    {
        $uplines = User::whereIn('role', ['ib', 'member'])
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'id_number' => $user->id_number,
                    // 'profile_photo' => $user->getFirstMediaUrl('profile_photo')
                ];
            });

        if ($returnAsArray) {
            return $uplines;
        }

        return response()->json([
            'uplines' => $uplines,
        ]);
    }

    public function getRebateUplines($returnAsArray = false)
    {
        $uplines = User::whereIn('role', ['ib'])
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'id_number' => $user->id_number,
                    // 'profile_photo' => $user->getFirstMediaUrl('profile_photo')
                ];
            });

        if ($returnAsArray) {
            return $uplines;
        }

        return response()->json([
            'uplines' => $uplines,
        ]);
    }


    public function getCountries($returnAsArray = false)
    {
        $countries = Country::get()->map(function ($country) {
            return [
                'id' => $country->id,
                'name' => $country->name,
                'phone_code' => $country->phone_code,
                'nationality' => $country->nationality,
            ];
        });

        if ($returnAsArray) {
            return $countries;
        }

        return response()->json([
            'countries' => $countries,
        ]);
    }

    public function getBanks($returnAsArray = false)
    {
        $banks = Bank::get()->map(function ($bank) {
            return [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'bank_code' => $bank->bank_code,
            ];
        });

        if ($returnAsArray) {
            return $banks;
        }

        return response()->json([
            'banks' => $banks,
        ]);
    }

    // public function getGroups($returnAsArray = false)
    // {
    //     $groups = Group::all()->map(function ($group) {
    //         return [
    //             'value' => $group->id,
    //             'name' => $group->name,
    //             'color' => $group->color,
    //         ];
    //     });

    //     if ($returnAsArray) {
    //         return $groups;
    //     }

    //     return response()->json([
    //         'groups' => $groups,
    //     ]);
    // }

    public function getAllUsers($returnAsArray = false)
    {
        $users = User::whereIn('role', ['ib', 'member'])
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'id_number' => $user->id_number,
                ];
            });

        if ($returnAsArray) {
            return $users;
        }

        return response()->json([
            'users' => $users,
        ]);
    }

    public function getSymbols($returnAsArray = false)
    {
        $symbols = Symbol::distinct()->pluck('meta_symbol_name');

        if ($returnAsArray) {
            return $symbols;
        }

        return response()->json([
            'symbols' => $symbols,
        ]);
    }

    public function get_payment_gateways($returnAsArray = false)
    {
        $payments = PaymentGateway::where([
            'status' => 'active',
            'environment' => 'production',
        ])
            ->select([
                'id',
                'name',
                'platform',
                'status'
            ])->get();

            if ($returnAsArray) {
                return $payments;
            }

        return response()->json([
            'paymentGateways' => $payments,
        ]);
    }

    public function getPlatformAccountTypes(Request $request)
    {
        $syncedAccountTypes = AccountType::whereHas('trading_platform', function ($query) use ($request) {
            $query->where('slug', $request->trading_platform);
        })
            ->select([
                'name', 'account_group'
            ])
            ->get()
            ->toArray();

        switch ($request->trading_platform) {
            case 'mt4':
                $fetchedGroups = [];
                break;

            case 'mt5':
                $service = new MetaFiveService();
                $fetchedGroups = $service->getGroups();

                break;

            default:
                // Any other or missing platform → empty array
                $fetchedGroups = [];
                $syncedAccountTypes = [];
                break;
        }


        return response()->json([
            'fetchedGroups' => $fetchedGroups['groups'] ?? [],
            'syncedAccountTypes' => $syncedAccountTypes,
        ]);
    }

    public function getAccountTypeByPlatform(Request $request)
    {
        $accountTypes = AccountType::whereHas('trading_platform', function ($query) use ($request) {
            $query->where('slug', $request->trading_platform);
        })
            ->select([
                'id', 'name', 'account_group'
            ])
            ->get()
            ->toArray();

        return response()->json([
            'accountTypes' => $accountTypes,
        ]);
    }


    public function getUsers(Request $request)
    {
        $users = User::whereIn('role', ['ib', 'member'])
            ->select([
                'id', 'name', 'email', 'id_number',
            ])
            ->get()
            ->toArray();

        return response()->json([
            'users' => $users,
        ]);
    }
}
