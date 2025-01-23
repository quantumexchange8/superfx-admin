<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Team;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Country;
use App\Models\AccountType;
use App\Models\LeaderboardBonus;
use App\Models\TeamHasUser;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TeamSettlement;
use App\Models\TradingAccount;
use App\Models\SettingLeverage;
use App\Services\CTraderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
            });

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

    public function getAccountTypes($returnAsArray = false)
    {
        $accountTypes = AccountType::all()
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

    public function getAccountGroups($returnAsArray = false)
    {
        $accountGroups = AccountType::all()
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
            ];
        });

        if ($returnAsArray) {
            return $countries;
        }

        return response()->json([
            'countries' => $countries,
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
}
