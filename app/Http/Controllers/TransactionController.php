<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\SymbolGroup;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\RebateAllocation;
use App\Models\TradeRebateSummary;
use App\Services\DropdownOptionService;

class TransactionController extends Controller
{
    public function listing()
    {
        return Inertia::render('Transaction/Transaction');
    }

    public function getTransactionListingData(Request $request)
    {
        $type = $request->query('type');
        $selectedMonths = $request->query('selectedMonths'); // Get selectedMonths as a comma-separated string

        // Convert the comma-separated string to an array
        $selectedMonthsArray = !empty($selectedMonths) ? explode(',', $selectedMonths) : [];

        // Define common fields
        $commonFields = [
            'id',
            'user_id',
            'category',
            'transaction_type',
            'transaction_number',
            'amount',
            'transaction_charges',
            'transaction_amount',
            'status',
            'remarks',
            'created_at',
        ];

        if (empty($selectedMonthsArray)) {
            // If selectedMonths is empty, return an empty result
            return response()->json([
                'transactions' => [],
            ]);
        }

        if ($type === 'payout') {
        // Retrieve query parameters
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        // Fetch all symbol groups from the database
        $allSymbolGroups = SymbolGroup::pluck('display', 'id')->toArray();

        // Initialize query for TradeRebateSummary
        $query = TradeRebateSummary::with('upline_user', 'account_type');

        // Apply monthly filters
        $query->where(function ($query) use ($selectedMonthsArray) {
            foreach ($selectedMonthsArray as $range) {
                [$month, $year] = explode('/', $range);
                $startDate = "$year-$month-01";
                $endDate = date("Y-m-t", strtotime($startDate)); // Last day of the month
                $query->orWhereBetween('created_at', [$startDate, $endDate]);
            }
        });

        // Apply date filter based on availability of startDate and/or endDate
        if ($startDate && $endDate) {
            // Both startDate and endDate are provided
            $query->whereDate('execute_at', '>=', $startDate)
                ->whereDate('execute_at', '<=', $endDate);
        } else {
            // Apply default start date if no endDate is provided
            $query->whereDate('execute_at', '>=', '2024-01-01');
        }

        // Fetch and map summarized data from TradeRebateSummary
        $data = $query->get()->map(function ($item) {
            return [
                'user_id' => $item->upline_user_id,
                'name' => $item->upline_user->name,
                'email' => $item->upline_user->email,
                'account_type' => $item->account_type->slug ?? null,
                'execute_at' => Carbon::parse($item->execute_at)->toDateString(),
                'symbol_group' => $item->symbol_group,
                'volume' => $item->volume,
                'net_rebate' => $item->net_rebate,
                'rebate' => $item->rebate,
            ];
        });

        // Generate summary and details
        $summary = $data->groupBy(function ($item) {
            return $item['execute_at'] . '-' . $item['user_id'];
        })->map(function ($group) use ($allSymbolGroups) {
            $group = collect($group);

            // Generate detailed data for this summary item
            $symbolGroupDetails = $group->groupBy('symbol_group')->map(function ($symbolGroupItems) use ($allSymbolGroups) {
                $symbolGroupId = $symbolGroupItems->first()['symbol_group'];

                return [
                    'id' => $symbolGroupId,
                    'name' => $allSymbolGroups[$symbolGroupId] ?? 'Unknown',
                    'volume' => $symbolGroupItems->sum('volume'),
                    'net_rebate' => $symbolGroupItems->first()['net_rebate'] ?? 0,
                    'rebate' => $symbolGroupItems->sum('rebate'),
                ];
            })->values();

            // Add missing symbol groups with volume, net_rebate, and rebate as 0
            foreach ($allSymbolGroups as $symbolGroupId => $symbolGroupName) {
                if (!$symbolGroupDetails->pluck('id')->contains($symbolGroupId)) {
                    $symbolGroupDetails->push([
                        'id' => $symbolGroupId,
                        'name' => $symbolGroupName,
                        'volume' => 0,
                        'net_rebate' => 0,
                        'rebate' => 0,
                    ]);
                }
            }

            // Sort the symbol group details array to match the order of symbol groups
            $symbolGroupDetails = $symbolGroupDetails->sortBy('id')->values();

            // Return summary item with details included
            return [
                'user_id' => $group->first()['user_id'],
                'name' => $group->first()['name'],
                'email' => $group->first()['email'],
                'account_type' => $group->first()['account_type'],
                'execute_at' => $group->first()['execute_at'],
                'volume' => $group->sum('volume'),
                'rebate' => $group->sum('rebate'),
                'details' => $symbolGroupDetails,
            ];
        })->values();

        // Sort summary by execute_at in descending order to get the latest dates first
        $summary = $summary->sortByDesc('execute_at');

        $data = $summary;

        } else {
            $query = Transaction::with('user', 'from_wallet', 'to_wallet');

            // Apply filtering for each selected month-year pair
            if (!empty($selectedMonthsArray)) {
                $query->where(function ($q) use ($selectedMonthsArray) {
                    foreach ($selectedMonthsArray as $range) {
                        [$month, $year] = explode('/', $range);
                        $startDate = "$year-$month-01";
                        $endDate = date("Y-m-t", strtotime($startDate)); // Last day of the month

                        // Add a condition to match transactions for this specific month-year
                        $q->orWhereBetween('created_at', [$startDate, $endDate]);
                    }
                });
            }

            // Filter by transaction type
            if ($type) {
                if ($type === 'transfer') {
                    $query->where(function ($q) {
                        $q->where('transaction_type', 'transfer_to_account')
                        ->orWhere('transaction_type', 'account_to_account');
                    });
                } else {
                    $query->where('transaction_type', $type);
                }
            }

            // Fetch data
            $data = $query->latest()->get()->map(function ($transaction) use ($commonFields, $type) {
                // Initialize result array with common fields
                $result = $transaction->only($commonFields);

                // Add common user fields
                $result['name'] = $transaction->user ? $transaction->user->name : null;
                $result['email'] = $transaction->user ? $transaction->user->email : null;
                $result['role'] = $transaction->user ? $transaction->user->role : null;
                $result['profile_photo'] = $transaction->user ? $transaction->user->getFirstMediaUrl('profile_photo') : null;

                // Add type-specific fields
                if ($type === 'deposit') {
                    $result['from_wallet_address'] = $transaction->from_wallet_address;
                    $result['to_wallet_address'] = $transaction->to_wallet_address;
                    $result['to_meta_login'] = $transaction->to_meta_login;
                    $result['to_wallet_id'] = $transaction->to_wallet ? $transaction->to_wallet->id : null;
                    $result['to_wallet_name'] = $transaction->to_wallet ? $transaction->to_wallet->name : null;
                } elseif ($type === 'withdrawal') {
                    $result['to_wallet_address'] = $transaction->to_wallet_address;
                    $result['from_meta_login'] = $transaction->from_meta_login;
                    $result['from_wallet_id'] = $transaction->from_wallet ? $transaction->from_wallet->id : null;
                    $result['from_wallet_name'] = $transaction->from_wallet ? $transaction->from_wallet->type : null;
                    $result['to_wallet_id'] = $transaction->to_wallet ? $transaction->to_wallet->id : null;
                    $result['to_wallet_name'] = $transaction->payment_account_id ? $transaction->payment_account->payment_account_name : null;
                    $result['approved_at'] = $transaction->approved_at;
                } elseif ($type === 'transfer') {
                    $result['from_meta_login'] = $transaction->from_meta_login;
                    $result['to_meta_login'] = $transaction->to_meta_login;
                    $result['from_wallet_id'] = $transaction->from_wallet ? $transaction->from_wallet->id : null;
                    $result['from_wallet_name'] = $transaction->from_wallet ? $transaction->from_wallet->name : null;
                    $result['to_wallet_id'] = $transaction->to_wallet ? $transaction->to_wallet->id : null;
                    $result['to_wallet_name'] = $transaction->to_wallet ? $transaction->to_wallet->name : null;
                }

                return $result;
            });
        }

        return response()->json([
            'transactions' => $data,
        ]);
    }

    public function getTransactionMonths()
    {
        $transactionMonths = (new DropdownOptionService())->getTransactionMonths();

        return response()->json($transactionMonths);
    }

}
