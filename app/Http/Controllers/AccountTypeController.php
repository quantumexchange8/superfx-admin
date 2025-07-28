<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\AccountType;
use Illuminate\Http\Request;
use App\Models\AccountTypeAccess;
use App\Services\DropdownOptionService;
use App\Http\Requests\UpdateAccountTypeRequest;

class AccountTypeController extends Controller
{
    public function show()
    {
        return Inertia::render('AccountType/AccountType', [
            'leverages' => (new GeneralController())->getLeverages(true),
            'users' => (new GeneralController())->getAllUsers(true),
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
    
            $query = AccountType::with('trading_accounts:id,account_type_id');
    
            // Handle sorting
            if (!empty($data['sortField']) && isset($data['sortOrder'])) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('created_at');
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

                $accountType->total_account = $accountType->trading_accounts->count();
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
    }
        
    public function syncAccountTypes()
    {
        //function

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
