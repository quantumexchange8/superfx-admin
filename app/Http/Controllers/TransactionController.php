<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\TradingPlatform;
use App\Models\User;
use Carbon\CarbonPeriod;
use Inertia\Inertia;
use App\Models\SymbolGroup;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\RebateAllocation;
use App\Models\TradeRebateSummary;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\DropdownOptionService;
use App\Exports\PayoutTransactionExport;
use App\Exports\DepositTransactionExport;
use App\Exports\TransferTransactionExport;
use App\Exports\WithdrawalTransactionExport;
use PhpOffice\PhpSpreadsheet\Exception;

class TransactionController extends Controller
{
    public function listing(Request $request)
    {
        $months = collect(CarbonPeriod::create(
            Transaction::oldest('created_at')->first()->created_at->startOfMonth(),
            '1 month',
            now()->startOfMonth()
        ))->map(fn($d) => $d->format('Y/m'));

        return Inertia::render('Transaction/Transaction', [
            'months' => $months,
            'tradingPlatforms' => TradingPlatform::where('status', 'active')->get()->toArray(),
        ]);
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getTransactionListingData(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $type = $data['filters']['type']['value'];
            $month_range = $data['filters']['month_range']['value'] ?? [];

            if (empty($month_range)) {
                // default to current month
                $firstMonth = now()->startOfMonth();
                $lastMonth = now()->endOfMonth();
            } else {
                // Convert all months to Carbon start-of-month
                $months = collect($month_range)->map(function ($m) {
                    return Carbon::createFromFormat('Y/m', $m)->startOfMonth();
                });

                $firstMonth = $months->min();
                $lastMonth = $months->max()->copy()->endOfMonth();
            }

            $query = Transaction::with([
                'user:id,name,email,hierarchyList,id_number',
                'user.media' => function ($q) {
                    $q->where('collection_name', 'profile_photo');
                },
                'payment_gateway:id,name,platform'
            ])
                ->where('transaction_type', $type)
                ->whereBetween('created_at', [$firstMonth, $lastMonth]);

            if ($type == 'deposit') {
                $query->with([
                    'to_login:id,meta_login,account_type_id',
                    'to_login.account_type:id,account_group,trading_platform_id,color',
                    'to_login.account_type.trading_platform:id,slug',
                ]);
            } elseif ($type == 'withdrawal') {
                $query->with([
                    'from_login:id,meta_login,account_type_id',
                    'from_login.account_type:id,account_group,trading_platform_id,color',
                    'from_login.account_type.trading_platform:id,slug',
                    'from_wallet'
                ]);
            }

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('user', function ($query) use ($keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('name', 'like', '%' . $keyword . '%')
                                ->orWhere('email', 'like', '%' . $keyword . '%')
                                ->orWhere('id_number', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('from_meta_login', 'like', '%' . $keyword . '%')
                        ->orWhere('to_meta_login', 'like', '%' . $keyword . '%')
                        ->orWhere('transaction_number', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['start_date']['value']) && !empty($data['filters']['end_date']['value'])) {
                $start_date = Carbon::parse($data['filters']['start_date']['value'])->addDay()->startOfDay();
                $end_date = Carbon::parse($data['filters']['end_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('created_at', [$start_date, $end_date]);
            }

            if (!empty($data['filters']['trading_platform']['value'])) {
                if ($type == 'deposit') {
                    $query->whereHas('to_login.account_type.trading_platform', function ($q) use ($data) {
                        $q->where('slug', $data['filters']['trading_platform']['value']);
                    });
                } else {
                    $query->whereHas('from_login.account_type.trading_platform', function ($q) use ($data) {
                        $q->where('slug', $data['filters']['trading_platform']['value']);
                    });
                }
            }

            if (!empty($data['filters']['account_type']['value'])) {
                $rawValue = $data['filters']['account_type']['value'];
                $ids = collect($rawValue)->pluck('id')->filter()->values();
                if ($ids->isEmpty()) {
                    $ids = collect($rawValue)->filter()->values();
                }

                if ($type == 'deposit') {
                    $query->whereHas('to_login.account_type', function ($q) use ($ids) {
                        $q->whereIn('id', $ids);
                    });
                } else {
                    $query->whereHas('from_login.account_type', function ($q) use ($ids) {
                        $q->whereIn('id', $ids);
                    });
                }
            }

            if (!empty($data['filters']['status']['value'])) {
                $query->where('status', $data['filters']['status']['value']);
            }

            if (!empty($data['filters']['role']['value'])) {
                $query->whereHas('user', function ($q) use ($data) {
                    $q->where('role', $data['filters']['role']['value']);
                });
            }

            if (!empty($data['filters']['payment_platform']['value'])) {
                $query->where('payment_gateway_id', $data['filters']['payment_platform']['value']);
            }

            if (!empty($data['filters']['upline']['value'])) {
                $query->whereHas('user', function ($q) use ($data) {
                    $selected_referrers = User::find($data['filters']['upline']['value']['id']);

                    $userIds = $selected_referrers->getChildrenIds();
                    $userIds[] = $data['filters']['upline']['value']['id'];

                    $q->whereIn('upline_id', $userIds);
                });
            }

            //sort field/order
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('created_at');
            }

            $total_success_amount = (clone $query)->where('status', 'successful')
                ->sum('transaction_amount');

            $max_amount = (clone $query)
                ->where('status', 'successful')
                ->max('transaction_amount');

            // Export logic
            if ($request->has('exportStatus') && $request->exportStatus) {
                if ($type == 'deposit') {
                    return Excel::download(new DepositTransactionExport($query), now() . '-deposit.xlsx');
                } else {
                    return Excel::download(new WithdrawalTransactionExport($query), now() . '-withdrawal.xlsx');
                }
            }

            $transactions = $query->paginate($data['rows']);

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'totalSuccessAmount' => (float)$total_success_amount,
                'maxAmount' => (float)$max_amount,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getTransferData(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $month_range = $data['filters']['month_range']['value'] ?? [];

            if (empty($month_range)) {
                // default to current month
                $firstMonth = now()->startOfMonth();
                $lastMonth = now()->endOfMonth();
            } else {
                // Convert all months to Carbon start-of-month
                $months = collect($month_range)->map(function ($m) {
                    return Carbon::createFromFormat('Y/m', $m)->startOfMonth();
                });

                $firstMonth = $months->min();
                $lastMonth = $months->max()->copy()->endOfMonth();
            }

            $query = Transaction::with([
                'user:id,name,email,hierarchyList,id_number',
                'user.media' => function ($q) {
                    $q->where('collection_name', 'profile_photo');
                },
                'to_login:id,meta_login,account_type_id',
                'to_login.account_type:id,account_group,trading_platform_id,color',
                'to_login.account_type.trading_platform:id,slug',
                'from_login:id,meta_login,account_type_id',
                'from_login.account_type:id,account_group,trading_platform_id,color',
                'from_login.account_type.trading_platform:id,slug',
                'from_wallet'
            ])
                ->whereBetween('created_at', [$firstMonth, $lastMonth]);

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('user', function ($query) use ($keyword) {
                        $query->where(function ($q) use ($keyword) {
                            $q->where('name', 'like', '%' . $keyword . '%')
                                ->orWhere('email', 'like', '%' . $keyword . '%')
                                ->orWhere('id_number', 'like', '%' . $keyword . '%');
                        });
                    })->orWhere('from_meta_login', 'like', '%' . $keyword . '%')
                        ->orWhere('to_meta_login', 'like', '%' . $keyword . '%')
                        ->orWhere('transaction_number', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['start_date']['value']) && !empty($data['filters']['end_date']['value'])) {
                $start_date = Carbon::parse($data['filters']['start_date']['value'])->addDay()->startOfDay();
                $end_date = Carbon::parse($data['filters']['end_date']['value'])->addDay()->endOfDay();

                $query->whereBetween('created_at', [$start_date, $end_date]);
            }

            $type = $data['filters']['type']['value'];

            if (!empty($type)) {
                $query->where('transaction_type', $type);
            } else {
                $query->whereIn('transaction_type', ['transfer_to_account', 'account_to_account']);
            }

            if (!empty($data['filters']['from_trading_platform']['value'])) {
                $slug = $data['filters']['from_trading_platform']['value'];
                $query->whereHas('from_login.account_type.trading_platform', function ($q) use ($slug) {
                    $q->where('slug', $slug);
                });
            }

            if (!empty($data['filters']['from_account_type']['value'])) {
                $rawValue = $data['filters']['from_account_type']['value'];
                $ids = collect($rawValue)->pluck('id')->filter()->values();
                if ($ids->isEmpty()) {
                    $ids = collect($rawValue)->filter()->values();
                }

                $query->whereHas('from_login.account_type', function ($q) use ($ids) {
                    $q->whereIn('id', $ids);
                });
            }

            if (!empty($data['filters']['to_trading_platform']['value'])) {
                $slug = $data['filters']['to_trading_platform']['value'];
                $query->whereHas('to_login.account_type.trading_platform', function ($q) use ($slug) {
                    $q->where('slug', $slug);
                });
            }

            if (!empty($data['filters']['to_account_type']['value'])) {
                $rawValue = $data['filters']['to_account_type']['value'];
                $ids = collect($rawValue)->pluck('id')->filter()->values();
                if ($ids->isEmpty()) {
                    $ids = collect($rawValue)->filter()->values();
                }

                $query->whereHas('to_login.account_type', function ($q) use ($ids) {
                    $q->whereIn('id', $ids);
                });
            }

            if (!empty($data['filters']['upline']['value'])) {
                $query->whereHas('user', function ($q) use ($data) {
                    $selected_referrers = User::find($data['filters']['upline']['value']['id']);

                    $userIds = $selected_referrers->getChildrenIds();
                    $userIds[] = $data['filters']['upline']['value']['id'];

                    $q->whereIn('upline_id', $userIds);
                });
            }

            //sort field/order
            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('created_at');
            }

            $total_success_amount = (clone $query)->where('status', 'successful')
                ->sum('transaction_amount');

            $max_amount = (clone $query)
                ->where('status', 'successful')
                ->max('transaction_amount');

            if ($request->has('exportStatus') && $request->exportStatus) {
                return Excel::download(new TransferTransactionExport($query), now() . '-transfer.xlsx');
            }

            $transactions = $query->paginate($data['rows']);

            return response()->json([
                'success' => true,
                'data' => $transactions,
                'totalSuccessAmount' => (float)$total_success_amount,
                'maxAmount' => (float)$max_amount,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getPayoutData(Request $request)
    {
        if (!$request->has('lazyEvent')) {
            return response()->json(['success' => false, 'data' => []]);
        }

        $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

        // -------- Month range --------
        $month_range = $data['filters']['month_range']['value'] ?? [];
        if (empty($month_range)) {
            $firstMonth = now()->startOfMonth();
            $lastMonth  = now()->endOfMonth();
        } else {
            $months = collect($month_range)->map(fn($m) =>
            Carbon::createFromFormat('Y/m', $m)->startOfMonth()
            );
            $firstMonth = $months->min();
            $lastMonth  = $months->max()->copy()->endOfMonth();
        }

        // -------- Date filters --------
        if (!empty($data['filters']['start_date']['value']) && !empty($data['filters']['end_date']['value'])) {

            // Parse incoming values as Carbon
            $userStart = Carbon::parse($data['filters']['start_date']['value']);
            $userEnd   = Carbon::parse($data['filters']['end_date']['value']);

            // Check if start date is today
            if ($userStart->isToday()) {
                $startDate = $userStart->startOfDay();
            } else {
                // If not today, addDay to start date
                $startDate = $userStart->addDay()->startOfDay();
            }

            // Check if end date is today
            if ($userEnd->isToday()) {
                $endDate = $userEnd->endOfDay();
            } else {
                // If not today, addDay to end date
                $endDate = $userEnd->addDay()->endOfDay();
            }

        } else {
            $startDate = $firstMonth;
            $endDate   = $lastMonth;
        }

        // -------- Base query with filters (for totals & details) --------
        $base = TradeRebateSummary::query()->whereBetween('execute_at', [$startDate, $endDate]);

        if (!empty($data['filters']['global']['value'])) {
            $keyword = $data['filters']['global']['value'];
            $base->whereHas('upline_user', function ($q) use ($keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%')
                        ->orWhere('id_number', 'like', '%' . $keyword . '%');
                });
            });
        }

        // -------- Totals before paginate --------
        $totalPayout = (float) (clone $base)->sum('rebate');

        // For maxAmount at symbol-group level
        $maxAmount = (float) (clone $base)
            ->selectRaw('upline_user_id, DATE(execute_at) as execute_date, SUM(rebate) as rebate_sum')
            ->groupBy('upline_user_id', 'execute_date')
            ->get()
            ->max('rebate_sum') ?? 0;

        // -------- Aggregated main rows (paginated) --------
        $query = (clone $base)
            ->selectRaw('upline_user_id, DATE(execute_at) as execute_date, SUM(volume) as total_volume, SUM(rebate) as total_rebate, MAX(net_rebate) as net_rebate')
            ->with([
                'upline_user',
                'upline_user.media' => fn($q) => $q->where('collection_name', 'profile_photo')
            ])
            ->groupBy('upline_user_id', 'execute_date');

        // -------- Sorting before paginate --------
        if ($data['sortField'] && $data['sortOrder']) {
            $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';

            $fieldMap = [
                'rebate' => 'total_rebate',
                'volume' => 'total_volume',
                'execute_at' => 'execute_date',
            ];

            $field = $fieldMap[$data['sortField']] ?? 'execute_date';

            $query->orderBy($field, $order);
        } else {
            // default sort by execute_date desc
            $query->orderByDesc('execute_date');
        }

        // Export logic
        if ($request->has('exportStatus') && $request->exportStatus) {
            return Excel::download(new PayoutTransactionExport($query), now() . '-payout.xlsx');
        }

        $perPage = $data['rows'] ?? 15;
        $transactions = $query->paginate($perPage);

        // -------- Keys of current page for details --------
        $pageKeys = $transactions->getCollection()->map(fn($item) => [
            'upline_user_id' => $item->upline_user_id,
            'execute_date'   => $item->execute_date,
        ]);

        // -------- Details query only for current page --------
        $detailsQuery = TradeRebateSummary::query()
            ->selectRaw('upline_user_id, account_type_id, DATE(execute_at) as execute_date, symbol_group,
            SUM(volume) as volume, SUM(rebate) as rebate, MAX(net_rebate) as net_rebate')
            ->groupBy('upline_user_id', 'account_type_id', 'execute_date', 'symbol_group')
            ->where(function ($q) use ($pageKeys) {
                foreach ($pageKeys as $key) {
                    $q->orWhere(function ($sub) use ($key) {
                        $sub->where('upline_user_id', $key['upline_user_id'])
                            ->whereDate('execute_at', $key['execute_date']);
                    });
                }
            });

        $allDetails = $detailsQuery->get();
        $detailsByKey = $allDetails->groupBy(fn($row) => $row->upline_user_id . '|' . $row->execute_date);

        // -------- Lookups (symbol groups, account types, platform slugs) --------
        $allSymbolGroups = SymbolGroup::pluck('display', 'id')->toArray();
        $accountTypes    = AccountType::with('trading_platform:id,slug')->get();
        $allAccountTypes = $accountTypes->pluck('name', 'id')->toArray();
        $accountTypeColors = $accountTypes->pluck('color', 'id')->toArray();
        $accountTypePlatforms = $accountTypes
            ->mapWithKeys(fn($at) => [$at->id => $at->trading_platform->slug ?? null])
            ->toArray();

        // -------- Transform paginated collection with details --------
        $transactions->getCollection()->transform(function ($item) use (
            $detailsByKey, $allSymbolGroups, $allAccountTypes, $accountTypeColors, $accountTypePlatforms
        ) {
            $key = $item->upline_user_id . '|' . $item->execute_date;
            $detailRows = $detailsByKey->get($key, collect());
            $byAccountType = $detailRows->groupBy('account_type_id');

            $details = $byAccountType->map(function ($rows, $accountTypeId) use ($allSymbolGroups, $allAccountTypes, $accountTypeColors, $accountTypePlatforms) {
                $symbolGroups = $rows->map(fn($row) => [
                    'id'         => $row->symbol_group,
                    'name'       => $allSymbolGroups[$row->symbol_group] ?? 'Unknown',
                    'volume'     => $row->volume,
                    'net_rebate' => $row->net_rebate,
                    'rebate'     => $row->rebate,
                ]);

                // fill missing symbol groups
                foreach ($allSymbolGroups as $symbolGroupId => $symbolGroupName) {
                    if (!$symbolGroups->pluck('id')->contains($symbolGroupId)) {
                        $symbolGroups->push([
                            'id'         => $symbolGroupId,
                            'name'       => $symbolGroupName,
                            'volume'     => 0,
                            'net_rebate' => 0,
                            'rebate'     => 0,
                        ]);
                    }
                }

                return [
                    'account_type_id'    => $accountTypeId,
                    'account_type_name'  => $allAccountTypes[$accountTypeId] ?? 'Unknown',
                    'account_type_color' => $accountTypeColors[$accountTypeId] ?? null,
                    'trading_platform'   => $accountTypePlatforms[$accountTypeId] ?? null,
                    'total_volume'       => $rows->sum('volume'),
                    'total_rebate'       => $rows->sum('rebate'),
                    'symbol_groups'      => $symbolGroups->sortBy('id')->values(),
                ];
            })->values();

            return [
                'user_id'       => $item->upline_user_id,
                'name'          => $item->upline_user->name ?? null,
                'email'         => $item->upline_user->email ?? null,
                'id_number'     => $item->upline_user->id_number ?? null,
                'profile_photo' => $item->upline_user->getFirstMediaUrl('profile_photo') ?? null,
                'execute_at'    => $item->execute_date,
                'volume'        => $item->total_volume,
                'rebate'        => $item->total_rebate,
                'details'       => $details,
            ];
        });

        return response()->json([
            'success'     => true,
            'data'        => $transactions,
            'totalPayout' => $totalPayout,
            'maxAmount'   => $maxAmount,
        ]);
    }
}
