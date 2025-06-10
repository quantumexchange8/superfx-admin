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

            $query = OpenTrade::query()
                ->leftJoin('users as user', 'open_trade.user_id', '=', 'user.id')
                ->leftJoin('users as upline', 'user.upline_id', '=', 'upline.id')
                ->leftJoin('trading_accounts', 'open_trade.meta_login', '=', 'trading_accounts.meta_login')
                ->leftJoin('account_types', 'trading_accounts.account_type_id', '=', 'account_types.id')
                ->select([
                    'open_trade.*',
                    'user.name as name',
                    'user.email as email',
                    'user.id_number as id_number',
                    'upline.id as upline_id',
                    'upline.name as upline_name',
                    'upline.email as upline_email',
                    'upline.id_number as upline_id_number',
                    'account_types.name as account_type_name',
                    'account_types.slug as account_type_slug',
                    'account_types.account_group as account_type_account_group',
                    'account_types.currency as account_type_currency',
                    'account_types.color as account_type_color',
                ])
                ->whereIn('open_trade.trade_type', ['buy', 'sell'])
                ->where('open_trade.status', 'open');
        
            // Handle search functionality
            $search = $data['filters']['global'];
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('user.name', 'like', "%{$search}%")
                      ->orWhere('user.email', 'like', "%{$search}%")
                      ->orWhere('user.id_number', 'like', "%{$search}%")
                      ->orWhere('upline.name', 'like', "%{$search}%")
                      ->orWhere('upline.email', 'like', "%{$search}%")
                      ->orWhere('upline.id_number', 'like', "%{$search}%")
                      ->orWhere('account_types.name', 'like', "%{$search}%")
                      ->orWhere('account_types.slug', 'like', "%{$search}%")
                      ->orWhere('account_types.account_group', 'like', "%{$search}%")
                      ->orWhere('open_trade.meta_login', 'like', "%{$search}%")
                      ->orWhere('open_trade.trade_deal_id', 'like', "%{$search}%");
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
            
            return response()->json([
                'success' => true,
                'data' => $openTrades,
                'totalLots' => $totalLots,
                'totalCommission' => $totalCommission,
                'totalSwap' => $totalSwap,
                'totalProfit' => $totalProfit,
            ]);
        }
        
        return response()->json(['success' => false, 'data' => []]);

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

            $query = TradeBrokerHistory::query()
                ->leftJoin('users as user', 'trade_broker_histories.user_id', '=', 'user.id')
                ->leftJoin('users as upline', 'user.upline_id', '=', 'upline.id')
                ->leftJoin('trading_accounts', 'trade_broker_histories.meta_login', '=', 'trading_accounts.meta_login')
                ->leftJoin('account_types', 'trading_accounts.account_type_id', '=', 'account_types.id')
                ->select([
                    // all trade_broker_histories columns
                    'trade_broker_histories.*',
            
                    // user columns with alias
                    'user.name as name',
                    'user.email as email',
                    'user.id_number as id_number',
            
                    // upline columns with alias
                    'upline.id as upline_id',
                    'upline.name as upline_name',
                    'upline.email as upline_email',
                    'upline.id_number as upline_id_number',
            
                    // account_types columns with alias
                    'account_types.name as account_type_name',
                    'account_types.slug as account_type_slug',
                    'account_types.currency as account_type_currency',
                    'account_types.color as account_type_color',
                ]);
        
            // Handle search functionality
            $search = $data['filters']['global'];
            if ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('user.name', 'like', '%' . $search . '%')
                        ->orWhere('user.email', 'like', '%' . $search . '%')
                        ->orWhere('user.id_number', 'like', '%' . $search . '%')
                        ->orWhere('upline.name', 'like', '%' . $search . '%')
                        ->orWhere('upline.email', 'like', '%' . $search . '%')
                        ->orWhere('upline.id_number', 'like', '%' . $search . '%')
                        ->orWhere('account_types.name', 'like', '%' . $search . '%')
                        ->orWhere('account_types.slug', 'like', '%' . $search . '%')
                        ->orWhere('account_types.account_group', 'like', '%' . $search . '%')
                        ->orWhere('trade_broker_histories.meta_login', 'like', '%' . $search . '%')
                        ->orWhere('trade_broker_histories.trade_deal_id', 'like', '%' . $search . '%');
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

            return response()->json([
                'success' => true,
                'data' => $closeTrades,
                'totalLots' => $totalLots,
                'totalCommission' => $totalCommission,
                'totalSwap' => $totalSwap,
                'totalProfit' => $totalProfit,
            ]);
        }
        
        return response()->json(['success' => false, 'data' => []]);
    }
}
