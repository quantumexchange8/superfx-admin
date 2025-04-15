<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\SymbolGroup;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\RebateAllocation;
use App\Models\TradeRebateHistory;
use App\Models\TradeRebateSummary;
use App\Exports\RebateHistoryExport;
use App\Exports\RebateListingExport;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/Report', [
            'uplines' => (new GeneralController())->getRebateUplines(true),
        ]);
    }

    public function getRebateSummary(Request $request)
    {
        // Retrieve date parameters from request
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $group = $request->query('group');

        // Initialize query for rebate summary with date filtering
        $query = TradeRebateSummary::with('symbolGroup', 'user', 'accountType');

        if ($request->input('search')) {
            $keyword = $request->input('search');

            $query->where(function ($q) use ($keyword) {
                $q->whereHas('user', function ($query) use ($keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%')
                        ->orWhere('id_number', 'like', '%' . $keyword . '%');
                    });
                })->orWhere('meta_login', 'like', '%' . $keyword . '%');
            });
        }

        // Apply date filter based on availability of startDate and/or endDate
        if ($startDate && $endDate) {
            // Both startDate and endDate are provided
            $query->whereDate('execute_at', '>=', $startDate)
                ->whereDate('execute_at', '<=', $endDate);
        } else {
            // Both startDate and endDate are null, apply default start date
            $query->whereDate('execute_at', '>=', '2024-01-01');
        }

        if ($group) {
            $query->whereHas('accountType', function ($q) use ($group) {
                $q->where('category', $group);
            });
        }

        if ($request->input('upline_id')) {
            $uplineId = $request->input('upline_id');

            // Get upline and their children IDs
            $upline = User::find($uplineId);
            $childrenIds = $upline ? $upline->getChildrenIds() : [];
            $childrenIds[] = $uplineId;
        
            $query->whereIn('upline_user_id', $childrenIds);
        }

        // Fetch rebate summary data
        $rebateSummary = $query->get(['symbol_group', 'volume', 'rebate']);

        // Retrieve all symbol groups with non-null display values
        $symbolGroups = SymbolGroup::whereNotNull('display')->pluck('display', 'id');

        // Aggregate rebate data in PHP
        $rebateSummaryData = $rebateSummary->groupBy('symbol_group')->map(function ($items) {
            return [
                'volume' => $items->sum('volume'),
                'rebate' => $items->sum('rebate'),
            ];
        });

        // Initialize final summary and totals
        $finalSummary = [];
        $totalVolume = 0;
        $totalRebate = 0;

        // Iterate over all symbol groups
        foreach ($symbolGroups as $id => $display) {
            // Retrieve data or use default values
            $data = $rebateSummaryData->get($id, ['volume' => 0, 'rebate' => 0]);

            // Add to the final summary
            $finalSummary[] = [
                'symbol_group' => $display,
                'volume' => $data['volume'],
                'rebate' => $data['rebate'],
            ];

            // Accumulate totals
            $totalVolume += $data['volume'];
            $totalRebate += $data['rebate'];
        }

        // Return the response with rebate summary, total volume, and total rebate
        return response()->json([
            'rebateSummary' => $finalSummary,
            'totalVolume' => $totalVolume,
            'totalRebate' => $totalRebate,
        ]);
    }

    public function getRebateListing(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true); //only() extract parameters in lazyEvent

            $startDate = $data['filters']['start_date'];
            $endDate = $data['filters']['end_date'];
            $group = $data['filters']['group'];
            $sortField = $data['sortField'];
            $sortOrder = $data['sortOrder'];

            $allSymbolGroups = SymbolGroup::pluck('display', 'id')->toArray();
        
            $query = TradeRebateSummary::with('user', 'accountType');
        
            if ($data['filters']['global']) {
                $keyword = $data['filters']['global'];

                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('user', function ($query) use ($keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%')
                            ->orWhere('id_number', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('meta_login', 'like', '%' . $keyword . '%');
                });
            }

            if ($startDate && $endDate) {
                $start_date = Carbon::parse($startDate)->addDay()->startOfDay();
                $end_date = Carbon::parse($endDate)->addDay()->endOfDay();
                
                $query->whereBetween('execute_at', [$start_date, $end_date]);
            } else {
                $query->whereDate('execute_at', '>=', '2024-01-01');
            }
        
            if ($group) {
                $query->whereHas('accountType', function ($q) use ($group) {
                    $q->where('category', $group);
                });
            }

            if ($data['filters']['upline_id']) {
                $uplineId = $data['filters']['upline_id'];

                // Get upline and their children IDs
                $upline = User::find($uplineId);
                $childrenIds = $upline ? $upline->getChildrenIds() : [];
                $childrenIds[] = $uplineId;
            
                $query->whereIn('upline_user_id', $childrenIds);
            }

            if ($sortField && $sortOrder) {
                $order = $sortOrder == 1 ? 'asc' : 'desc';

                // Check if sorting by a related field (like "name")
                if (in_array($sortField, ['name', 'email', 'id_number'])) {
                    $query->join('users', 'trade_rebate_summaries.user_id', '=', 'users.id')
                        ->orderBy('users.' . $sortField, $order);
                } else {
                    $query->orderBy($sortField, $order);
                }
            } else {
                $query->orderByDesc('id');
            }

            $rawData = $query->get();
        
            $itemData = [];
            foreach ($rawData as $item) {
                $itemData[] = [
                    'upline_user_id' => $item->upline_user_id,
                    'user_id' => $item->user_id,
                    'name' => $item->user->name,
                    'email' => $item->user->email,
                    'id_number' => $item->user->id_number,
                    'meta_login' => $item->meta_login,
                    'execute_at' => Carbon::parse($item->execute_at)->format('Y/m/d'),
                    'symbol_group' => $item->symbol_group,
                    'volume' => $item->volume,
                    'net_rebate' => $item->net_rebate,
                    'rebate' => $item->rebate,
                    'slug' => $item->accountType->slug,
                    'color' => $item->accountType->color,
                ];
            }
    
            $grouped = [];
            foreach ($itemData as $item) {
                $key = $item['upline_user_id'] . '-' . $item['user_id'] . '-' . $item['meta_login'];
                $grouped[$key][] = $item;
            }
    
            $rebateListing = [];
            foreach ($grouped as $items) {
                $volume = 0;
                $rebate = 0;
                $summaryByDate = [];
        
                foreach ($items as $item) {
                    $volume += $item['volume'];
                    $rebate += $item['rebate'];
                    $date = $item['execute_at'];
        
                    if (!isset($summaryByDate[$date])) {
                        $summaryByDate[$date] = [];
                    }
        
                    $summaryByDate[$date][] = $item;
                }
        
                $summaries = [];
                foreach ($summaryByDate as $date => $executeGroup) {
                    $detailsBySymbol = [];
        
                    foreach ($executeGroup as $item) {
                        $symbolGroupId = $item['symbol_group'];
                        if (!isset($detailsBySymbol[$symbolGroupId])) {
                            $detailsBySymbol[$symbolGroupId] = [
                                'id' => $symbolGroupId,
                                'name' => $allSymbolGroups[$symbolGroupId] ?? 'Unknown',
                                'volume' => 0,
                                'net_rebate' => $item['net_rebate'] ?? 0,
                                'rebate' => 0,
                            ];
                        }
        
                        $detailsBySymbol[$symbolGroupId]['volume'] += $item['volume'];
                        $detailsBySymbol[$symbolGroupId]['rebate'] += $item['rebate'];
                    }
        
                    foreach ($allSymbolGroups as $symbolGroupId => $symbolGroupName) {
                        if (!isset($detailsBySymbol[$symbolGroupId])) {
                            $detailsBySymbol[$symbolGroupId] = [
                                'id' => $symbolGroupId,
                                'name' => $symbolGroupName,
                                'volume' => 0,
                                'net_rebate' => 0,
                                'rebate' => 0,
                            ];
                        }
                    }
        
                    // Sort by ID
                    usort($detailsBySymbol, function ($a, $b) {
                        return $a['id'] <=> $b['id'];
                    });
        
                    $summaries[] = [
                        'execute_at' => $date,
                        'volume' => array_sum(array_column($executeGroup, 'volume')),
                        'rebate' => array_sum(array_column($executeGroup, 'rebate')),
                        'details' => array_values($detailsBySymbol),
                    ];
                }
        
                $first = $items[0];
        
                $rebateListing[] = [
                    'user_id' => $first['user_id'],
                    'name' => $first['name'],
                    'email' => $first['email'],
                    'id_number' => $first['id_number'],
                    'meta_login' => $first['meta_login'],
                    'volume' => $volume,
                    'rebate' => $rebate,
                    'summary' => $summaries,
                    'slug' => $first['slug'],
                    'color' => $first['color'],
                ];
            }
    
            // Export logic
            if ($request->has('exportStatus') && $request->exportStatus) {
                return Excel::download(new RebateListingExport($rebateListing), now() . '-rebate-summary.xlsx');
            }
        
            // // Handle pagination
            $rowsPerPage = $data['rows'] ?? 15; // Default to 15 if 'rows' not provided
            // $currentPage = $request->input('page', 0); // Laravel uses 1-based page numbers, PrimeVue uses 0-based

            $page = LengthAwarePaginator::resolveCurrentPage();
            $rebateCollection = collect($rebateListing);
            $paginatedListing = new LengthAwarePaginator(
                $rebateCollection->slice(($page - 1) * $rowsPerPage, $rowsPerPage)->values(),
                $rebateCollection->count(),
                $rowsPerPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );
        }
        
        return response()->json([
            'data' => $paginatedListing
        ]);
    }
    
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
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true); //only() extract parameters in lazyEvent

            $query = TradeRebateHistory::with([
                    'upline:id,name,email,id_number',
                    'downline:id,name,email,id_number',
                    'of_account_type:id,slug,color'
                ])
                ->where('t_status', 'approved');

            // Handle search functionality
            $search = $data['filters']['global'];
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('downline', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('id_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('upline', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('id_number', 'like', '%' . $search . '%');
                    })
                    ->orWhere('meta_login', 'like', '%' . $search . '%')
                    ->orWhere('deal_id', 'like', '%' . $search . '%');
                });
            }

            $startDate = $data['filters']['start_date'];
            $endDate = $data['filters']['end_date'];

            if ($startDate && $endDate) {
                $start_date = Carbon::parse($startDate)->addDay()->startOfDay();
                $end_date = Carbon::parse($endDate)->addDay()->endOfDay();
                
                $query->whereBetween('created_at', [$start_date, $end_date]);
            } else {
                $query->whereDate('created_at', '>=', '2024-01-01');
            }
        
            $startClosedDate = $data['filters']['start_close_date'];
            $endClosedDate = $data['filters']['end_close_date'];

            if ($startClosedDate && $endClosedDate) {
                $start_close_date = Carbon::parse($startClosedDate)->addDay()->startOfDay();
                $end_close_date = Carbon::parse($endClosedDate)->addDay()->endOfDay();
                
                $query->whereBetween('created_at', [$start_close_date, $end_close_date]);
            } else {
                $query->whereDate('created_at', '>=', '2024-01-01');
            }

            if ($data['filters']['upline_id']) {
                $uplineId = $data['filters']['upline_id'];

                // Get upline and their children IDs
                $upline = User::find($uplineId);
                $childrenIds = $upline ? $upline->getChildrenIds() : [];
                $childrenIds[] = $uplineId;
            
                $query->whereIn('upline_user_id', $childrenIds);
            }

            if ($data['filters']['t_type']) {
                $query->where('t_type', $data['filters']['t_type']);
            }

            // Handle sorting
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('created_at');
            }

            // Handle pagination
            $rowsPerPage = $data['rows'] ?? 15; // Default to 15 if 'rows' not provided
                    
            // Export logic
            if ($request->has('exportStatus') && $request->exportStatus) {
                $rebateHistory = $query->get();

                return Excel::download(new RebateHistoryExport($rebateHistory), now() . '-rebate-report.xlsx');
            }

            $totalVolume = (clone $query)->sum('volume');
            $totalRebateAmount = (clone $query)->sum('revenue');

            $histories = $query->paginate($rowsPerPage);
        }
        
        return response()->json([
            'success' => true,
            'data' => $histories,
            'totalVolume' => $totalVolume,
            'totalRebateAmount' => $totalRebateAmount,
        ]);
    }
}
