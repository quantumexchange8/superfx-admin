<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\OpenTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Exports\OpenTradesExport;
use App\Exports\CloseTradesExport;
use App\Models\TradeBrokerHistory;
use Maatwebsite\Excel\Facades\Excel;

class TradePositionController extends Controller
{
    public function open_positions()
    {
        return Inertia::render('TradePosition/OpenPositions', [
            'uplines' => (new GeneralController())->getRebateUplines(true),
            'symbols' => (new GeneralController())->getSymbols(true),
        ]);
    }

    public function open_trade(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true); //only() extract parameters in lazyEvent

            $query = OpenTrade::with([
                    'user:id,name,email,id_number,upline_id',
                    'user.upline:id,name,email,id_number',
                    'trading_account:id,meta_login,account_type_id',
                    'trading_account.accountType:id,name,slug,account_group,currency,color',
                ])
                ->whereIn('trade_type', ['buy', 'sell'])
                ->where('status', 'open');

            // Handle search functionality
            $search = $data['filters']['global'];
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('user.upline', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('id_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('id_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('trading_account.accountType', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('slug', 'like', '%' . $search . '%')
                            ->orWhere('account_group', 'like', '%' . $search . '%');
                    })
                    ->orWhere('meta_login', 'like', '%' . $search . '%')
                    ->orWhere('trade_deal_id', 'like', '%' . $search . '%');
                });
            }

            $startDate = $data['filters']['start_date'];
            $endDate = $data['filters']['end_date'];

            if ($startDate && $endDate) {
                $start_date = Carbon::parse($startDate)->addDay()->startOfDay();
                $end_date = Carbon::parse($endDate)->addDay()->endOfDay();
                
                $query->whereBetween('trade_open_time', [$start_date, $end_date]);
            }
        
            if ($data['filters']['upline_id']) {
                $uplineId = $data['filters']['upline_id'];

                // Get upline and their children IDs
                $upline = User::find($uplineId);
                $childrenIds = $upline ? $upline->getChildrenIds() : [];
                $childrenIds[] = $uplineId;
            
                // Filter OpenTrade by user.upline_id in childrenIds
                $query->whereHas('user', function ($q) use ($childrenIds) {
                    $q->whereIn('upline_id', $childrenIds);
                });
            }

            if ($data['filters']['symbol']) {
                $query->where('trade_symbol', $data['filters']['symbol']);
            }

            if ($data['filters']['trade_type']) {
                $query->where('trade_type', $data['filters']['trade_type']);
            }

            if ($data['filters']['account_currency']) {
                $query->whereHas('trading_account.accountType', function ($q) use ($data) {
                    $q->where('currency', $data['filters']['account_currency']);
                });
            }

            // Handle sorting
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('trade_open_time')->orderByDesc('id');
            }

            // Handle pagination
            $rowsPerPage = $data['rows'] ?? 15; // Default to 15 if 'rows' not provided
                    
            // Export logic
            if ($request->has('exportStatus') && $request->exportStatus) {
                $records = $query->get();

                return Excel::download(new OpenTradesExport($records), now() . '-open-trade-report.xlsx');
            }

            $totalLots = (clone $query)->sum('trade_lots');
            $totalCommission = (clone $query)->sum('trade_commission');
            $totalSwap = (clone $query)->sum('trade_swap_usd');
            $totalProfit = (clone $query)->sum('trade_profit_usd');

            $openTrades = $query->paginate($rowsPerPage);
            
            foreach ($openTrades as $openTrade) {
                // Flatten user-related fields if user exists
                if ($openTrade->user) {
                    $openTrade->name = $openTrade->user->name ?? null;
                    $openTrade->email = $openTrade->user->email ?? null;
                    $openTrade->id_number = $openTrade->user->id_number ?? null;
            
                    // Flatten upline-related fields if upline exists
                    $upline = $openTrade->user->upline;
                    if ($upline) {
                        $openTrade->upline_id = $upline->id ?? null;
                        $openTrade->upline_name = $upline->name ?? null;
                        $openTrade->upline_email = $upline->email ?? null;
                        $openTrade->upline_id_number = $upline->id_number ?? null;
                    }
                }
            
                // Flatten trading_account-related fields if trading_account exists
                if ($openTrade->trading_account) {
                    $accountType = $openTrade->trading_account->accountType;
                    if ($accountType) {
                        $openTrade->account_type_name = $accountType->name ?? null;
                        $openTrade->account_type_slug = $accountType->slug ?? null;
                        $openTrade->account_type_currency = $accountType->currency ?? null;
                        $openTrade->account_type_color = $accountType->color ?? null;
                    }
                }
            
                // Remove unnecessary nested relationships to keep data clean
                unset($openTrade->user);
                unset($openTrade->trading_account);
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $openTrades,
            'totalLots' => $totalLots,
            'totalCommission' => $totalCommission,
            'totalSwap' => $totalSwap,
            'totalProfit' => $totalProfit,
        ]);

    }

    public function closed_positions()
    {
        return Inertia::render('TradePosition/ClosedPositions', [
            'uplines' => (new GeneralController())->getRebateUplines(true),
            'symbols' => (new GeneralController())->getSymbols(true),
        ]);
    }

    public function closed_trade(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true); //only() extract parameters in lazyEvent

            $query = TradeBrokerHistory::with([
                    'user:id,name,email,id_number,upline_id',
                    'user.upline:id,name,email,id_number',
                    'trading_account:id,meta_login,account_type_id',
                    'trading_account.accountType:id,name,slug,account_group,currency,color',
                ]);

            // Handle search functionality
            $search = $data['filters']['global'];
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('user.upline', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('id_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('id_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('trading_account.accountType', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('slug', 'like', '%' . $search . '%')
                            ->orWhere('account_group', 'like', '%' . $search . '%');
                    })
                    ->orWhere('meta_login', 'like', '%' . $search . '%')
                    ->orWhere('trade_deal_id', 'like', '%' . $search . '%');
                });
            }

            $startDate = $data['filters']['start_date'];
            $endDate = $data['filters']['end_date'];

            if ($startDate && $endDate) {
                $start_date = Carbon::parse($startDate)->addDay()->startOfDay();
                $end_date = Carbon::parse($endDate)->addDay()->endOfDay();
                
                $query->whereBetween('trade_open_time', [$start_date, $end_date]);
            }
        
            $startClosedDate = $data['filters']['start_close_date'];
            $endClosedDate = $data['filters']['end_close_date'];

            if ($startClosedDate && $endClosedDate) {
                $start_close_date = Carbon::parse($startClosedDate)->addDay()->startOfDay();
                $end_close_date = Carbon::parse($endClosedDate)->addDay()->endOfDay();

                $query->whereBetween('trade_close_time', [$start_close_date, $end_close_date]);
            }

            if ($data['filters']['upline_id']) {
                $uplineId = $data['filters']['upline_id'];

                // Get upline and their children IDs
                $upline = User::find($uplineId);
                $childrenIds = $upline ? $upline->getChildrenIds() : [];
                $childrenIds[] = $uplineId;
            
                // Filter OpenTrade by user.upline_id in childrenIds
                $query->whereHas('trading_account.ofUser', function ($q) use ($childrenIds) {
                    $q->whereIn('upline_id', $childrenIds);
                });
            }

            if ($data['filters']['symbol']) {
                $query->where('trade_symbol', $data['filters']['symbol']);
            }

            if ($data['filters']['trade_type']) {
                $query->where('trade_type', $data['filters']['trade_type']);
            }

            if ($data['filters']['account_currency']) {
                $query->whereHas('trading_account.accountType', function ($q) use ($data) {
                    $q->where('currency', $data['filters']['account_currency']);
                });
            }

            // Handle sorting
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('trade_open_time')->orderByDesc('id');
            }

            // Handle pagination
            $rowsPerPage = $data['rows'] ?? 15; // Default to 15 if 'rows' not provided
                    
            // Export logic
            if ($request->has('exportStatus') && $request->exportStatus) {
                $records = $query->get();

                return Excel::download(new CloseTradesExport($records), now() . '-close-trade-report.xlsx');
            }

            $totalLots = (clone $query)->sum('trade_lots');
            $totalCommission = (clone $query)->sum('trade_commission');
            $totalSwap = (clone $query)->sum('trade_swap_usd');
            $totalProfit = (clone $query)->sum('trade_profit_usd');

            $closeTrades = $query->paginate($rowsPerPage);
            
            foreach ($closeTrades as $closeTrade) {
                // Flatten user-related fields if user exists
                if ($closeTrade->user) {
                    $closeTrade->name = $closeTrade->user->name ?? null;
                    $closeTrade->email = $closeTrade->user->email ?? null;
                    $closeTrade->id_number = $closeTrade->user->id_number ?? null;
            
                    // Flatten upline-related fields if upline exists
                    $upline = $closeTrade->user->upline;
                    if ($upline) {
                        $closeTrade->upline_id = $upline->id ?? null;
                        $closeTrade->upline_name = $upline->name ?? null;
                        $closeTrade->upline_email = $upline->email ?? null;
                        $closeTrade->upline_id_number = $upline->id_number ?? null;
                    }
                }
            
                // Flatten trading_account-related fields if trading_account exists
                if ($closeTrade->trading_account) {
                    $accountType = $closeTrade->trading_account->accountType;
                    if ($accountType) {
                        $closeTrade->account_type_name = $accountType->name ?? null;
                        $closeTrade->account_type_slug = $accountType->slug ?? null;
                        $closeTrade->account_type_currency = $accountType->currency ?? null;
                        $closeTrade->account_type_color = $accountType->color ?? null;
                    }
                }
            
                // Remove unnecessary nested relationships to keep data clean
                unset($closeTrade->user);
                unset($closeTrade->trading_account);
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $closeTrades,
            'totalLots' => $totalLots,
            'totalCommission' => $totalCommission,
            'totalSwap' => $totalSwap,
            'totalProfit' => $totalProfit,
        ]);

    }
}
