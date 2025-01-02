<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\GroupSettlement;
use App\Models\TradingAccount;
use App\Models\User;
// use App\Services\CTraderService;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\GroupHasUser;
use Illuminate\Http\Request;
use App\Http\Requests\GroupRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\EditGroupRequest;
use App\Services\DropdownOptionService;

class GroupController extends Controller
{
    public function show()
    {
        $groupCount = Group::count();

        return Inertia::render('Group/Group', [
            'groupCount' => $groupCount,
        ]);
    }

    public function getGroups(Request $request)
    {
        $totals = [
            'total_net_balance' => 0,
            'total_deposit' => 0,
            'total_withdrawal' => 0,
            'total_charges' => 0,
        ];

        $groups = Group::get()
            ->map(function ($group) use ($request, &$totals) {
                $groupUserIds = GroupHasUser::where('group_id', $group->id)
                    ->pluck('user_id')
                    ->toArray();

                $startDate = $request->input('startDate') ? Carbon::createFromFormat('Y/m/d', $request->input('startDate'))->startOfDay() : now()->startOfYear();
                $endDate = $request->input('endDate') ? Carbon::createFromFormat('Y/m/d', $request->input('endDate'))->endOfDay() : today()->endOfDay();

                $total_deposit = Transaction::whereIn('user_id', $groupUserIds)
                    ->whereBetween('approved_at', [$startDate, $endDate])
                    ->where(function ($query) {
                        $query->where('transaction_type', 'deposit')
                            ->orWhere('transaction_type', 'balance_in');
                    })
                    ->where('status', 'successful')
                    ->sum('transaction_amount');

                $total_withdrawal = Transaction::whereIn('user_id', $groupUserIds)
                    ->whereBetween('approved_at', [$startDate, $endDate])
                    ->where(function ($query) {
                        $query->where('transaction_type', 'withdrawal')
                            ->orWhere('transaction_type', 'balance_out')
                            ->orWhere('transaction_type', 'rebate_out');
                    })
                    ->where('status', 'successful')
                    ->sum('amount');

                $transaction_fee_charges = $total_deposit / $group->fee_charges;
                $net_balance = $total_deposit - $transaction_fee_charges - $total_withdrawal;

                // Calculate account balance and equity
                $groupIds = AccountType::whereNotNull('account_group_id')
                    ->pluck('account_group_id')
                    ->toArray();

                $groupBalance = 0;
                $groupEquity = 0;

                // foreach ($groupIds as $groupId) {
                //     $startDateFormatted = $startDate->format('Y-m-d\TH:i:s.v');
                //     $endDateFormatted = $endDate->format('Y-m-d\TH:i:s.v');

                //     $response = (new CTraderService)->getMultipleTraders($startDateFormatted, $endDateFormatted, $groupId);

                //     $accountType = AccountType::where('account_group_id', $groupId)->first();

                //     $meta_logins = TradingAccount::where('account_type_id', $accountType->id)->whereIn('user_id', $groupUserIds)->pluck('meta_login')->toArray();

                //     if (isset($response['trader']) && is_array($response['trader'])) {
                //         foreach ($response['trader'] as $trader) {
                //             if (in_array($trader['login'], $meta_logins)) {
                //                 $moneyDigits = isset($trader['moneyDigits']) ? (int)$trader['moneyDigits'] : 0;
                //                 $divisor = $moneyDigits > 0 ? pow(10, $moneyDigits) : 1;

                //                 $groupBalance += $trader['balance'] / $divisor;
                //                 $groupEquity += $trader['equity'] / $divisor;
                //             }
                //         }
                //     }
                // }

                // Accumulate the totals
                $totals['total_net_balance'] += $net_balance;
                $totals['total_deposit'] += $total_deposit;
                $totals['total_withdrawal'] += $total_withdrawal;
                $totals['total_charges'] += $transaction_fee_charges;

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'fee_charges' => $group->fee_charges,
                    'color' => $group->color,
                    'leader_name' => $group->leader->name,
                    'leader_email' => $group->leader->email,
                    'profile_photo' => $group->leader->getFirstMediaUrl('profile_photo'),
                    'member_count' => $group->group_has_user->count(),
                    'deposit' => $total_deposit,
                    'withdrawal' => $total_withdrawal,
                    'transaction_fee_charges' => $transaction_fee_charges,
                    'net_balance' => $net_balance,
                    'account_balance' => $groupBalance,
                    'account_equity' => $groupEquity,
                ];
            });

        return response()->json([
            'groups' => $groups,
            'total' => $totals,
        ]);
    }

    public function refreshGroup(Request $request)
    {
        $group = Group::where('id', $request->group_id)->first();

        if ($group) {
            $groupUserIds = GroupHasUser::where('group_id', $group->id)
                ->pluck('user_id')
                ->toArray();

            $startDateInput = $request->input('startDate') ?? now()->startOfYear();
            $endDateInput = $request->input('endDate') ?? today()->endOfDay();

            $startDate = $startDateInput ? Carbon::createFromFormat('Y/m/d', $startDateInput)->startOfDay() : now()->startOfYear();
            $endDate = $endDateInput ? Carbon::createFromFormat('Y/m/d', $endDateInput)->endOfDay() : today()->endOfDay();

            $total_deposit = Transaction::whereIn('user_id', $groupUserIds)
                ->whereBetween('approved_at', [$startDate, $endDate])
                ->where(function ($query) {
                    $query->where('transaction_type', 'deposit')
                        ->orWhere('transaction_type', 'balance_in');
                })
                ->where('status', 'successful')
                ->sum('transaction_amount');

            $total_withdrawal = Transaction::whereIn('user_id', $groupUserIds)
                ->whereBetween('approved_at', [$startDate, $endDate])
                ->where(function ($query) {
                    $query->where('transaction_type', 'withdrawal')
                        ->orWhere('transaction_type', 'balance_out')
                        ->orWhere('transaction_type', 'rebate_out');
                })
                ->where('status', 'successful')
                ->sum('amount');

            $transaction_fee_charges = $total_deposit / $group->fee_charges;
            $net_balance = $total_deposit - $transaction_fee_charges - $total_withdrawal;

            // Standard Account and Premium Account group IDs
            $groupIds = AccountType::whereNotNull('account_group_id')
                ->pluck('account_group_id')
                ->toArray();

            $groupBalance = 0;
            $groupEquity = 0;

            // foreach ($groupIds as $groupId) {
            //     // Fetch data for each group ID
            //     $startDateFormatted = $startDate->format('Y-m-d\TH:i:s.v');
            //     $endDateFormatted = $endDate->format('Y-m-d\TH:i:s.v');

            //     $response = (new CTraderService)->getMultipleTraders($startDateFormatted, $endDateFormatted, $groupId);

            //     // Find the corresponding AccountType model
            //     $accountType = AccountType::where('account_group_id', $groupId)->first();

            //     $meta_logins = TradingAccount::where('account_type_id', $accountType->id)
            //         ->whereIn('user_id', $groupUserIds)
            //         ->pluck('meta_login')
            //         ->toArray();

            //     // Assuming the response is an associative array with a 'trader' key
            //     if (isset($response['trader']) && is_array($response['trader'])) {
            //         foreach ($response['trader'] as $trader) {
            //             if (in_array($trader['login'], $meta_logins)) {
            //                 // Determine the divisor based on moneyDigits
            //                 $moneyDigits = isset($trader['moneyDigits']) ? (int)$trader['moneyDigits'] : 0;
            //                 $divisor = $moneyDigits > 0 ? pow(10, $moneyDigits) : 1; // 10^moneyDigits

            //                 // Adjust balance and equity based on the divisor
            //                 $groupBalance += $trader['balance'] / $divisor;
            //                 $groupEquity += $trader['equity'] / $divisor;
            //             }
            //         }
            //     }
            // }

            $result = [
                'id' => $group->id,
                'name' => $group->name,
                'fee_charges' => $group->fee_charges,
                'color' => $group->color,
                'leader_name' => $group->leader->name,
                'leader_email' => $group->leader->email,
                'profile_photo' => $group->leader->getFirstMediaUrl('profile_photo'),
                'member_count' => $group->group_has_user->count(),
                'deposit' => $total_deposit,
                'withdrawal' => $total_withdrawal,
                'transaction_fee_charges' => $transaction_fee_charges,
                'net_balance' => $net_balance,
                'account_balance' => $groupBalance,
                'account_equity' => $groupEquity,
            ];

            // Overall totals for all groups
            $overallTotals = Group::get()->reduce(function ($carry, $group) use ($startDate, $endDate) {
                $groupUserIds = GroupHasUser::where('group_id', $group->id)
                    ->pluck('user_id')
                    ->toArray();

                $total_deposit = Transaction::whereIn('user_id', $groupUserIds)
                    ->whereBetween('approved_at', [$startDate, $endDate])
                    ->where(function ($query) {
                        $query->where('transaction_type', 'deposit')
                            ->orWhere('transaction_type', 'balance_in');
                    })
                    ->where('status', 'successful')
                    ->sum('transaction_amount');

                $total_withdrawal = Transaction::whereIn('user_id', $groupUserIds)
                    ->whereBetween('approved_at', [$startDate, $endDate])
                    ->where(function ($query) {
                        $query->where('transaction_type', 'withdrawal')
                            ->orWhere('transaction_type', 'balance_out')
                            ->orWhere('transaction_type', 'rebate_out');
                    })
                    ->where('status', 'successful')
                    ->sum('amount');

                $transaction_fee_charges = $total_deposit / $group->fee_charges;
                $net_balance = $total_deposit - $transaction_fee_charges - $total_withdrawal;

                $carry['total_net_balance'] += $net_balance;
                $carry['total_deposit'] += $total_deposit;
                $carry['total_withdrawal'] += $total_withdrawal;
                $carry['total_charges'] += $transaction_fee_charges;

                return $carry;
            }, [
                'total_net_balance' => 0,
                'total_deposit' => 0,
                'total_withdrawal' => 0,
                'total_charges' => 0,
            ]);
        } else {
            // Handle the case where the group is not found
            $result = null;

            $overallTotals = [
                'total_net_balance' => 0,
                'total_deposit' => 0,
                'total_withdrawal' => 0,
                'total_charges' => 0,
            ];
        }

        return response()->json([
            'refreshed_group' => $result,
            'total' => $overallTotals,
        ]);
    }

    public function getAgents()
    {
        $users = (new DropdownOptionService())->getAgents();

        return response()->json($users);
    }

    public function createGroup(GroupRequest $request)
    {
        $agent_id = $request->agent['value'];
        $group = Group::create([
            'name' => $request->group_name,
            'fee_charges' => $request->fee_charges,
            'color' => $request->color,
            'group_leader_id' => $agent_id,
            'edited_by' => Auth::id(),
        ]);

        $group_id = $group->id;
        GroupHasUser::create([
            'group_id' => $group_id,
            'user_id' => $agent_id
        ]);

        $children_ids = User::find($agent_id)->getChildrenIds();
        User::whereIn('id', $children_ids)->chunk(500, function($users) use ($group_id) {
            $users->each->assignedGroup($group_id);
        });

        return back()->with('toast', [
            'title' => trans('public.toast_create_group_success'),
            'type' => 'success',
        ]);
    }

    public function editGroup(EditGroupRequest $request, $id)
    {
        $group = Group::find($id);
        $group->name = $request->group_name;
        $group->fee_charges = $request->fee_charges;
        $group->color = $request->color;
        $group->group_leader_id = $request->agent['value'];
        $group->edited_by = Auth::id();
        $group->save();

        return back()->with('toast', [
            'title' => trans('public.toast_update_group_success'),
            'type' => 'success',
        ]);
    }

    public function getGroupTransaction(Request $request)
    {
        $groupId = $request->input('groupId');
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        // Get all user IDs associated with the groupId
        $userIds = GroupHasUser::where('group_id', $groupId)
            ->pluck('user_id');

        // Start building the query
        $query = Transaction::with('user')
            ->whereIn('user_id', $userIds)
            ->whereIn('transaction_type', ['deposit', 'withdrawal'])
            ->where('status', 'successful');

        // Apply date range filter if startDate and endDate are provided
        if ($startDate && $endDate) {
            // Both startDate and endDate are provided
            $query->whereDate('approved_at', '>=', $startDate)
                ->whereDate('approved_at', '<=', $endDate);
        } else {
            // Apply default start date if no endDate is provided
            $query->whereDate('approved_at', '>=', '2024-01-01');
        }

        // Execute the query and get the results
        $transactions = $query->get();

        // Map the results to include user details
        $result = $transactions->map(function ($transaction) {
            return [
                'approved_at' => $transaction->approved_at,
                'user_id' => $transaction->user_id,
                'name' => $transaction->user->name,
                'email' => $transaction->user->email,
                'profile_photo' => $transaction->user->getFirstMediaUrl('profile_photo'),
                'transaction_type' => $transaction->transaction_type,
                'amount' => $transaction->amount,
                'transaction_charges' => $transaction->transaction_charges,
                'transaction_amount' => $transaction->transaction_amount,
            ];
        });

        // Calculate total values
        $totalAmount = $transactions->sum('amount');
        $totalFee = $transactions->sum('transaction_charges');
        $totalBalance = $transactions->sum('transaction_amount');

        // Return response with totals
        return response()->json([
            'transactions' => $result,
            'totalAmount' => $totalAmount,
            'totalFee' => $totalFee,
            'totalBalance' => $totalBalance,
        ]);
    }

    public function deleteGroup($id)
    {
        Group::destroy($id);

        // Delete the related GroupSettlement records
        GroupSettlement::where('team_id', $id)->delete();

        GroupHasUser::where('group_id', $id)->delete();

        return back()->with('toast', [
            'title' => trans('public.toast_delete_group_success'),
            'type' => 'success',
        ]);
    }

    public function getSettlementReport(Request $request)
    {
        $selectedMonths = $request->query('selectedMonths');

        $selectedMonthsArray = !empty($selectedMonths) ? explode(',', $selectedMonths) : [];

        $monthYearFilters = array_map(function($monthYear) {
            $date = Carbon::createFromFormat('m/Y', $monthYear);
            return [
                'month' => $date->month,
                'year' => $date->year,
            ];
        }, $selectedMonthsArray);

        $groupSettlements = GroupSettlement::with('group:id,name')
            ->when($selectedMonthsArray, function($query) use ($monthYearFilters) {
                foreach ($monthYearFilters as $filter) {
                    $query->orWhere(function($query) use ($filter) {
                        $query->whereYear('transaction_start_at', $filter['year'])
                            ->whereMonth('transaction_start_at', $filter['month']);
                    });
                }
            })
            ->orderBy('group_deposit', 'desc')
            ->get();

        // Initialize an array to hold settlements grouped by month
        $settlementReports = [];

        foreach ($groupSettlements as $settlement) {
            // Format the month for grouping
            $month = $settlement->transaction_start_at->format('m/Y');

            // Initialize the month array if it doesn't exist
            if (!isset($settlementReports[$month])) {
                $settlementReports[$month] = [
                    'month' => $month,
                    'total_fee' => 0,
                    'total_balance' => 0,
                    'group_settlements' => []
                ];
            }

            // Add settlement details to the month array
            $settlementReports[$month]['total_fee'] += $settlement->group_fee;
            $settlementReports[$month]['total_balance'] += $settlement->group_balance;

            $settlementReports[$month]['group_settlements'][] = [
                'id' => $settlement->id,
                'group_id' => $settlement->group_id,
                'group_name' => $settlement->group->name,
                'transaction_start_at' => $settlement->transaction_start_at->format('Y-m-d'),
                'transaction_end_at' => $settlement->transaction_end_at->format('Y-m-d'),
                'group_deposit' => $settlement->group_deposit,
                'group_withdrawal' => $settlement->group_withdrawal,
                'group_fee_percentage' => $settlement->group_fee_percentage,
                'group_fee' => $settlement->group_fee,
                'group_balance' => $settlement->group_balance,
                'settled_at' => $settlement->settled_at->format('Y-m-d'),
            ];
        }

        // Prepare the final response
        return response()->json([
            'settlementReports' => array_values($settlementReports) // Re-index the array to avoid key issues
        ]);
    }

    public function markSettlementReport(Request $request, $id)
    {
        $group = Group::find($id);
        $groupUserIds = GroupHasUser::where('group_id', $id)
            ->pluck('user_id')
            ->toArray();

        // First day and last day of the previous month
        $startDate = Carbon::now()->subMonth()->startOfMonth();
        $endDate = Carbon::now()->subMonth()->endOfMonth();

        // Check if a settlement for this group and month has already been processed
        $existingSettlement = GroupSettlement::where('group_id', $id)
            ->where('transaction_start_at', $startDate)
            ->where('transaction_end_at', $endDate)
            ->first();

        // If a settlement for this period already exists, return an error
        if ($existingSettlement) {
            return back()->with('toast', [
                'title' => trans('public.toast_settlement_already_exists'),
                'type' => 'warning',
            ]);
        }

        // Calculate total deposits for the group users in the last month
        $total_deposit = Transaction::whereIn('user_id', $groupUserIds)
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('transaction_type', 'deposit')
                    ->orWhere('transaction_type', 'balance_in');
            })
            ->where('status', 'successful')
            ->sum('transaction_amount');

        // Calculate total withdrawals for the group users in the last month
        $total_withdrawal = Transaction::whereIn('user_id', $groupUserIds)
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('transaction_type', 'withdrawal')
                    ->orWhere('transaction_type', 'balance_out')
                    ->orWhere('transaction_type', 'rebate_out');
            })
            ->where('status', 'successful')
            ->sum('amount');

        // Calculate fee charges and net balance
        $transaction_fee_charges = $total_deposit / $group->fee_charges;
        $net_balance = $total_deposit - $transaction_fee_charges - $total_withdrawal;

        // Create a new settlement record
        GroupSettlement::create([
            'group_id' => $id,
            'transaction_start_at' => $startDate,
            'transaction_end_at' => $endDate,
            'group_deposit' => $total_deposit,
            'group_withdrawal' => $total_withdrawal,
            'group_fee_percentage' => $group->fee_charges,
            'group_fee' => $transaction_fee_charges,
            'group_balance' => $net_balance,
            'settled_at' => now(),
        ]);

        return back()->with('toast', [
            'title' => trans('public.toast_mark_settlement_success'),
            'type' => 'success',
        ]);
    }
}
