<?php

namespace App\Http\Controllers;

use App\Exports\RebateHistoryExport;
use Inertia\Inertia;
use App\Models\SymbolGroup;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\RebateAllocation;
use App\Models\TradeRebateHistory;
use App\Models\TradeRebateSummary;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/Report', [
            'uplines' => (new GeneralController())->getRebateUplines(true),
        ]);
    }

    // public function getRebateSummary(Request $request)
    // {
    //     $userId = Auth::id();

    //     // Retrieve date parameters from request
    //     $startDate = $request->query('startDate');
    //     $endDate = $request->query('endDate');

    //     // Initialize query for rebate summary with date filtering
    //     $query = TradeRebateSummary::with('symbolGroup')
    //         ->where('upline_user_id', $userId);

    //     // Apply date filter based on availability of startDate and/or endDate
    //     if ($startDate && $endDate) {
    //         // Both startDate and endDate are provided
    //         $query->whereDate('execute_at', '>=', $startDate)
    //             ->whereDate('execute_at', '<=', $endDate);
    //     } else {
    //         // Both startDate and endDate are null, apply default start date
    //         $query->whereDate('execute_at', '>=', '2024-01-01');
    //     }

    //     // Fetch rebate summary data
    //     $rebateSummary = $query->get(['symbol_group', 'volume', 'rebate']);

    //     // Retrieve all symbol groups with non-null display values
    //     $symbolGroups = SymbolGroup::whereNotNull('display')->pluck('display', 'id');

    //     // Aggregate rebate data in PHP
    //     $rebateSummaryData = $rebateSummary->groupBy('symbol_group')->map(function ($items) {
    //         return [
    //             'volume' => $items->sum('volume'),
    //             'rebate' => $items->sum('rebate'),
    //         ];
    //     });

    //     // Initialize final summary and totals
    //     $finalSummary = [];
    //     $totalVolume = 0;
    //     $totalRebate = 0;

    //     // Iterate over all symbol groups
    //     foreach ($symbolGroups as $id => $display) {
    //         // Retrieve data or use default values
    //         $data = $rebateSummaryData->get($id, ['volume' => 0, 'rebate' => 0]);

    //         // Add to the final summary
    //         $finalSummary[] = [
    //             'symbol_group' => $display,
    //             'volume' => $data['volume'],
    //             'rebate' => $data['rebate'],
    //         ];

    //         // Accumulate totals
    //         $totalVolume += $data['volume'];
    //         $totalRebate += $data['rebate'];
    //     }

    //     // Return the response with rebate summary, total volume, and total rebate
    //     return response()->json([
    //         'rebateSummary' => $finalSummary,
    //         'totalVolume' => $totalVolume,
    //         'totalRebate' => $totalRebate,
    //     ]);
    // }

    // public function getRebateListing(Request $request)
    // {
    //     // Retrieve query parameters
    //     $startDate = $request->input('startDate');
    //     $endDate = $request->input('endDate');

    //     // Fetch all symbol groups from the database, ordered by the primary key (id)
    //     $allSymbolGroups = SymbolGroup::pluck('display', 'id')->toArray();

    //     $query = TradeRebateSummary::with('user')
    //         ->where('upline_user_id', Auth::id());

    //     // Apply date filter based on availability of startDate and/or endDate
    //     if ($startDate && $endDate) {
    //         $query->whereDate('execute_at', '>=', $startDate)
    //               ->whereDate('execute_at', '<=', $endDate);
    //     } else {
    //         $query->whereDate('execute_at', '>=', '2024-01-01');
    //     }

    //     // Fetch rebate listing data
    //     $data = $query->get()->map(function ($item) {
    //         return [
    //             'user_id' => $item->user_id,
    //             'name' => $item->user->name,
    //             'email' => $item->user->email,
    //             'meta_login' => $item->meta_login,
    //             'execute_at' => Carbon::parse($item->execute_at)->format('Y/m/d'),
    //             'symbol_group' => $item->symbol_group,
    //             'volume' => $item->volume,
    //             'net_rebate' => $item->net_rebate,
    //             'rebate' => $item->rebate,
    //         ];
    //     });

    //     // Group data by user_id and meta_login
    //     $rebateListing = $data->groupBy(function ($item) {
    //         return $item['user_id'] . '-' . $item['meta_login'];
    //     })->map(function ($group) use ($allSymbolGroups) {
    //         $group = collect($group);

    //         // Calculate overall volume and rebate for the user
    //         $volume = $group->sum('volume');
    //         $rebate = $group->sum('rebate');

    //         // Create summary by execute_at
    //         $summary = $group->groupBy('execute_at')->map(function ($executeGroup) use ($allSymbolGroups) {
    //             $executeGroup = collect($executeGroup);

    //             // Calculate details for each symbol group
    //             $details = $executeGroup->groupBy('symbol_group')->map(function ($symbolGroupItems) use ($allSymbolGroups) {
    //                 $symbolGroupId = $symbolGroupItems->first()['symbol_group'];

    //                 return [
    //                     'id' => $symbolGroupId,
    //                     'name' => $allSymbolGroups[$symbolGroupId] ?? 'Unknown',
    //                     'volume' => $symbolGroupItems->sum('volume'),
    //                     'net_rebate' => $symbolGroupItems->first()['net_rebate'] ?? 0,
    //                     'rebate' => $symbolGroupItems->sum('rebate'),
    //                 ];
    //             })->values();

    //             // Add missing symbol groups with volume, net_rebate, and rebate as 0
    //             foreach ($allSymbolGroups as $symbolGroupId => $symbolGroupName) {
    //                 if (!$details->pluck('id')->contains($symbolGroupId)) {
    //                     $details->push([
    //                         'id' => $symbolGroupId,
    //                         'name' => $symbolGroupName,
    //                         'volume' => 0,
    //                         'net_rebate' => 0,
    //                         'rebate' => 0,
    //                     ]);
    //                 }
    //             }

    //             // Sort the symbol group details array to match the order of symbol groups
    //             $details = $details->sortBy('id')->values();

    //             return [
    //                 'execute_at' => $executeGroup->first()['execute_at'],
    //                 'volume' => $executeGroup->sum('volume'),
    //                 'rebate' => $executeGroup->sum('rebate'),
    //                 'details' => $details,
    //             ];
    //         })->values();

    //         // Return rebateListing item with summaries included
    //         return [
    //             'user_id' => $group->first()['user_id'],
    //             'name' => $group->first()['name'],
    //             'email' => $group->first()['email'],
    //             'meta_login' => $group->first()['meta_login'],
    //             'volume' => $volume,
    //             'rebate' => $rebate,
    //             'summary' => $summary,
    //         ];
    //     })->values();

    //     // Return JSON response with combined rebateListing and details
    //     return response()->json([
    //         'rebateListing' => $rebateListing
    //     ]);
    // }

    // public function getGroupTransaction(Request $request)
    // {
    //     $user = Auth::user();
    //     $groupIds = $user->getChildrenIds();
    //     $groupIds[] = $user->id;

    //     $transactionType = $request->query('type');
    //     $startDate = $request->query('startDate');
    //     $endDate = $request->query('endDate');

    //     $transactionTypes = match($transactionType) {
    //         'deposit' => ['deposit', 'balance_in'],
    //         'withdrawal' => ['withdrawal', 'balance_out'],
    //         default => []
    //     };

    //     // Initialize the query for transactions
    //     $query = Transaction::whereIn('transaction_type', $transactionTypes)
    //         ->where('status', 'successful')
    //         ->whereIn('user_id', $groupIds);

    //     // Apply date filter based on availability of startDate and/or endDate
    //     if ($startDate && $endDate) {
    //         $query->whereDate('created_at', '>=', $startDate)
    //               ->whereDate('created_at', '<=', $endDate);
    //     } else {
    //         // Handle cases where startDate or endDate are not provided
    //         $query->whereDate('created_at', '>=', '2024-01-01'); // Default start date
    //     }

    //     $transactions = $query->get()
    //         ->map(function ($transaction) {
    //             $metaLogin = $transaction->to_meta_login ?: $transaction->from_meta_login;

    //             // Check for withdrawal type and modify meta_login based on category
    //             if ($transaction->transaction_type === 'withdrawal') {
    //                 switch ($transaction->category) {
    //                     case 'trading_account':
    //                         $metaLogin = $transaction->from_meta_login;
    //                         break;
    //                     case 'rebate_wallet':
    //                         $metaLogin = 'rebate';
    //                         break;
    //                     case 'bonus_wallet':
    //                         $metaLogin = 'bonus';
    //                         break;
    //                 }
    //             }

    //             // Return the formatted transaction data
    //             return [
    //                 'created_at' => $transaction->created_at,
    //                 'user_id' => $transaction->user_id,
    //                 'name' => $transaction->user->name,
    //                 'email' => $transaction->user->email,
    //                 'meta_login' => $metaLogin,
    //                 'transaction_amount' => $transaction->transaction_amount,
    //             ];
    //         });

    //     // Calculate total deposit and withdrawal amounts for the given date range
    //     $group_total_deposit = Transaction::whereIn('transaction_type', ['deposit', 'balance_in'])
    //         ->where('status', 'successful')
    //         ->whereIn('user_id', $groupIds)
    //         ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
    //             $query->whereDate('created_at', '>=', $startDate)
    //                   ->whereDate('created_at', '<=', $endDate);
    //         })
    //         ->sum('transaction_amount');

    //     $group_total_withdrawal = Transaction::whereIn('transaction_type', ['withdrawal', 'balance_out'])
    //         ->where('status', 'successful')
    //         ->whereIn('user_id', $groupIds)
    //         ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
    //             $query->whereDate('created_at', '>=', $startDate)
    //                   ->whereDate('created_at', '<=', $endDate);
    //         })
    //         ->sum('transaction_amount');

    //     return response()->json([
    //         'transactions' => $transactions,
    //         'groupTotalDeposit' => $group_total_deposit,
    //         'groupTotalWithdrawal' => $group_total_withdrawal,
    //         'groupTotalNetBalance' => $group_total_deposit - $group_total_withdrawal,
    //     ]);
    // }

    public function getRebateHistory(Request $request)
    {
        $query = TradeRebateHistory::with([
                'upline:id,name,email,id_number',
                'downline:id,name,email,id_number',
                'of_account_type:id,slug,color'
            ])
            ->where('t_status', 'approved');

        // Handle search functionality
        $search = $request->input('search');
        if ($search) {
            $query->where(function ($query) use ($search) {
                $query->whereHas('downline', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('id_number', 'like', '%' . $search . '%');
                })
                ->orWhere('meta_login', 'like', '%' . $search . '%')
                ->orWhere('deal_id', 'like', '%' . $search . '%');
            });
        }

        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        if ($startDate && $endDate) {
            $start_date = Carbon::createFromFormat('Y/m/d', $startDate)->startOfDay();
            $end_date = Carbon::createFromFormat('Y/m/d', $endDate)->endOfDay();

            $query->whereBetween('created_at', [$start_date, $end_date]);
        }
    
        $startClosedDate = $request->query('startClosedDate');
        $endClosedDate = $request->query('endClosedDate');

        if ($startClosedDate && $endClosedDate) {
            $start_close_date = Carbon::createFromFormat('Y/m/d', $startClosedDate)->startOfDay();
            $end_close_date = Carbon::createFromFormat('Y/m/d', $endClosedDate)->endOfDay();

            $query->whereBetween('closed_time', [$start_close_date, $end_close_date]);
        }

        if ($request->input('upline_id')) {
            $uplineIds = explode(',', $request->input('upline_id'));
            $query->whereIn('upline_user_id', $uplineIds);
        }

        if ($request->input('type')) {
            $query->where('t_type', $request->input('type'));
        }


        // Handle sorting
        $sortField = $request->input('sortField', 'created_at'); // Default to 'created_at'
        $sortOrder = $request->input('sortOrder', -1); // 1 for ascending, -1 for descending
        $query->orderBy($sortField, $sortOrder == 1 ? 'asc' : 'desc');

        // Handle pagination
        $rowsPerPage = $request->input('rows', 15); // Default to 15 if 'rows' not provided
        $currentPage = $request->input('page', 0) + 1; // Laravel uses 1-based page numbers, PrimeVue uses 0-based
                
        // Export logic
        if ($request->has('exportStatus') && $request->exportStatus) {
            $rebateHistory = $query->get();

            return Excel::download(new RebateHistoryExport($rebateHistory), now() . '-rebate-report.xlsx');
        }

        $totalRebateAmount = (clone $query)->sum('revenue');

        $histories = $query->paginate($rowsPerPage, ['*'], 'page', $currentPage);

        return response()->json([
            'success' => true,
            'data' => $histories,
            'totalRebateAmount' => $totalRebateAmount,
        ]);
    }
}
