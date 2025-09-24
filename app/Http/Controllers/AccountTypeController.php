<?php

namespace App\Http\Controllers;

use App\Jobs\SyncRebateAllocationJob;
use App\Models\RebateAllocation;
use App\Models\TradingPlatform;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Models\AccountType;
use Illuminate\Http\Request;
use App\Models\AccountTypeAccess;
use App\Http\Requests\UpdateAccountTypeRequest;
use Throwable;

class AccountTypeController extends Controller
{
    public function show()
    {
        return Inertia::render('AccountType/AccountType', [
            'leverages' => (new GeneralController())->getLeverages(true),
            'users' => (new GeneralController())->getAllUsers(true),
            'tradingPlatforms' => TradingPlatform::where('status', 'active')->get()->toArray(),
        ]);
    }

    // public function getAccountTypes()
    // {
    //     $accountTypes = AccountType::with('trading_accounts:id,account_type_id')
    //         ->get()
    //         ->map(function($accountType) {
    //             $locale = app()->getLocale();
    //             $translations = json_decode($accountType->descriptions, true);

    //             if ($accountType->trade_open_duration >= 60) {
    //                 $accountType['trade_delay'] = ($accountType->trade_open_duration / 60).' min';
    //             } else {
    //                 $accountType['trade_delay'] = $accountType->trade_open_duration. ' sec';
    //             }

    //             // need to change to calculate total account created for each type
    //             $accountType['total_account'] = $accountType->trading_accounts()->count();
    //             $accountType['description_locale'] = $translations[$locale] ?? '-';
    //             $accountType['description_en'] = $translations['en'] ?? '-';
    //             $accountType['description_tw'] = $translations['tw'] ?? '-';

    //             return $accountType;
    //         });

    //     return response()->json(['accountTypes' => $accountTypes]);
    // }

    public function getAccountTypes(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $locale = app()->getLocale();
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $query = AccountType::with('trading_platform:id,platform_name,slug')
                ->withCount('trading_accounts');

            if ($data['filters']['global']['value']) {
                $keyword = $data['filters']['global']['value'];

                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('member_display_name', 'like', '%' . $keyword . '%');
                });
            }

            if (!empty($data['filters']['platform']['value'])) {
                $query->whereHas('trading_platform', function ($q) use ($data) {
                    $q->whereIn('slug', $data['filters']['platform']['value']);
                });
            }

            if (!empty($data['filters']['category']['value'])) {
                $query->whereIn('category', $data['filters']['category']['value']);
            }

            if (!empty($data['filters']['status']['value'])) {
                $query->whereIn('status', $data['filters']['status']['value']);
            }

            // Handle sorting
            if (!empty($data['sortField']) && isset($data['sortOrder'])) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';

                // Sort by actual field or by trading_accounts_count alias
                if ($data['sortField'] === 'total_account') {
                    $query->orderBy('trading_accounts_count', $order);
                } else {
                    $query->orderBy($data['sortField'], $order);
                }
            } else {
                $query
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderByRaw("CASE WHEN category = 'dollar' THEN 0 ELSE 1 END")
                    ->orderByDesc('created_at');
            }

            // Handle pagination
            $rowsPerPage = $data['rows'] ?? 15;
            $result = $query->paginate($rowsPerPage);

            // Transform each account type
            foreach ($result->items() as $accountType) {
                $translations = json_decode($accountType->descriptions, true);

                if ($accountType->trade_open_duration >= 60) {
                    $accountType->trade_delay = ($accountType->trade_open_duration / 60).' min';
                } else {
                    $accountType->trade_delay = $accountType->trade_open_duration. ' sec';
                }

                $accountType->total_account = $accountType->trading_accounts_count;
                $accountType->description_locale = $translations[$locale] ?? '-';
                $accountType->description_en = $translations['en'] ?? '-';
                $accountType->description_tw = $translations['tw'] ?? '-';

                unset($accountType->trading_accounts);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }

        return response()->json([
            'success' => false,
            'data' => [],
        ]);
    }

    /**
     * @throws ConnectionException
     */
    public function syncAccountTypes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trading_platform' => ['required'],
            'account_types' => ['nullable'],
        ])->setAttributeNames([
            'trading_platform' => trans('public.platform'),
            'account_types' => trans('public.account_type'),
        ]);
        $validator->validate();

        $trading_platform = TradingPlatform::firstWhere('slug', $request->trading_platform);

        if (!$trading_platform) {
            throw ValidationException::withMessages([
                'trading_platform' => trans('public.platform_not_found'),
            ]);
        }

        $trading_platform = TradingPlatform::where('slug', $request->trading_platform)->firstOrFail();

        $account_types = $request->input('account_types', []);

        $incomingNames = collect($account_types)->pluck('name')->toArray();

        foreach ($account_types as $account_type) {
            $existing = AccountType::withTrashed()
                ->where('trading_platform_id', $trading_platform->id)
                ->where('name', $account_type['name'])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    'category' => $account_type['currency'] == 'USD' ? 'dollar' : 'cent',
                    'account_group' => $account_type['name'],
                    'currency' => strtoupper($account_type['currency']),
                    'balance_multiplier' => $account_type['currency'] == 'USD' ? 1 : 100,
                    'edited_by' => Auth::id(),
                ]);
            } else {
                AccountType::create([
                    'name' => $account_type['name'],
                    'slug' => Str::slug($account_type['name']),
                    'member_display_name' => $account_type['name'],
                    'trading_platform_id' => $trading_platform->id,
                    'category' => $account_type['currency'] == 'USD' ? 'dollar' : 'cent',
                    'account_group' => $account_type['name'],
                    'minimum_deposit' => 0,
                    'leverage' => 0,
                    'currency' => strtoupper($account_type['currency']),
                    'trade_open_duration' => '10',
                    'maximum_account_number' => 5,
                    'balance_multiplier' => $account_type['currency'] == 'USD' ? 1 : 100,
                    'descriptions' => json_encode(['en' => '-', 'tw' => '-']),
                    'color' => '3ecf8e',
                    'status' => 'inactive',
                    'edited_by' => Auth::id(),
                ]);
            }
        }

        AccountType::where('trading_platform_id', $trading_platform->id)
            ->whereNotIn('name', $incomingNames)
            ->delete();

        // Execute job to sync rebate allocations
        SyncRebateAllocationJob::dispatch();

        $accountTypeIds = AccountType::where('trading_platform_id', $trading_platform->id)
            ->pluck('id')
            ->toArray();

        dispatch(function () use ($accountTypeIds) {
            Http::acceptJson()
                ->post("http://45.128.12.105:5001/api/account_types", [
                    'accountTypeIds' => $accountTypeIds
                ]);
        })->onQueue('sync_account_type_symbols');

        return back()->with('toast', [
            'title' => trans('public.toast_sync_account_type'),
            'type'=> 'success',
        ]);
    }

    public function updateAccountType(UpdateAccountTypeRequest $request, $id)
    {
        $account_type = AccountType::findOrFail($id);

        // Update AccountType fields
        $account_type->update([
            'member_display_name' => $request->member_display_name,
            'category' => $request->category,
            'descriptions' => json_encode($request->descriptions),
            'leverage' => $request->leverage,
            'trade_open_duration' => $request->trade_delay_duration,
            'maximum_account_number' => $request->max_account,
            'minimum_deposit' => $request->min_deposit,
            'status' => 'active',
            'color' => $request->color,
        ]);

        // Handle user access updates
        $userAccess = $request->user_access ?? [];

        if ($userAccess) {
            $existingUserAccessIds = AccountTypeAccess::where('account_type_id', $id)
                ->pluck('user_id')
                ->toArray();

            $incomingUserIds = collect($userAccess)->toArray();

            if (!empty(array_diff($existingUserAccessIds, $incomingUserIds)) || !empty(array_diff($incomingUserIds, $existingUserAccessIds))) {
                AccountTypeAccess::where('account_type_id', $id)->delete();

                foreach ($userAccess as $userId) {
                    AccountTypeAccess::create([
                        'account_type_id' => $id,
                        'user_id' => $userId,
                        'status' => 'active',
                    ]);
                }
            }
        } else {
            AccountTypeAccess::where('account_type_id', $id)->delete();
        }

        return back()->with('toast', [
            'title' => trans('public.toast_update_account_type_success'),
            'type' => 'success',
        ]);
    }

    public function updateStatus($id)
    {
        $account_type = AccountType::find($id);
        $account_type->status = $account_type->status == 'active' ? 'inactive' : 'active';
        $account_type->save();

        return back()->with('toast', [
            'title' => $account_type->status == 'active' ? trans("public.toast_account_type_activated") : trans("public.toast_account_type_deactivated"),
            'type' => 'success',
        ]);
    }

    public function getAccountTypeUsers(Request $request)
    {
        $users = AccountTypeAccess::where('account_type_id', $request->account_type_id)
            ->with('user') // Eager load user data
            ->get()
            ->map(function ($access) {
                return [
                    'value' => $access->user?->id,
                    'name' => $access->user?->name,
                    'email' => $access->user?->email,
                    'id_number' => $access->user?->id_number,
                ];
            });

        return response()->json([
            'users' => $users
        ]);
    }
}
