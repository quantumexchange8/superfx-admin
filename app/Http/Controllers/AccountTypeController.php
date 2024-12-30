<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountTypeRequest;
use App\Models\AccountType;
use App\Services\DropdownOptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountTypeController extends Controller
{
    public function show()
    {
        return Inertia::render('AccountType/AccountType');
    }

    public function getAccountTypes()
    {
        $accountTypes = AccountType::with('trading_accounts:id,account_type_id')
            ->get()
            ->map(function($accountType) {
                $locale = app()->getLocale();
                $translations = json_decode($accountType->descriptions, true);

                if ($accountType->trade_open_duration >= 60) {
                    $accountType['trade_delay'] = ($accountType->trade_open_duration / 60).' min';
                } else {
                    $accountType['trade_delay'] = $accountType->trade_open_duration. ' sec';
                }

                // need to change to calculate total account created for each type
                $accountType['total_account'] = $accountType->trading_accounts()->count();
                $accountType['description_locale'] = $translations[$locale] ?? '-';
                $accountType['description_en'] = $translations['en'] ?? '-';
                $accountType['description_tw'] = $translations['tw'] ?? '-';

                return $accountType;
            });

        return response()->json(['accountTypes' => $accountTypes]);
    }

    public function syncAccountTypes()
    {
        //function

        return back()->with('toast', [
            'title' => trans('public.toast_sync_account_type'),
            'type'=> 'success',
        ]);
    }

    public function findAccountType($id)
    {
        $accountType = AccountType::find($id);

        $locale = app()->getLocale();
        $translations = json_decode($accountType->descriptions, true);

        $accountType['description_locale'] = $translations[$locale] ?? '-';
        $accountType['description_en'] = $translations['en'] ?? '-';
        $accountType['description_tw'] = $translations['tw'] ?? '-';

        return response()->json([
            'account_type' => $accountType
        ]);
    }

    public function getLeverages()
    {
        return response()->json([
            'leverages' => (new DropdownOptionService())->getLeverages(),
        ]);
    }

    public function updateAccountType(UpdateAccountTypeRequest $request, $id)
    {
        $account_type = AccountType::find($id);
        $account_type->category = $request->category;
        $account_type->descriptions = json_encode($request->descriptions);
        $account_type->leverage = $request->leverage;
        $account_type->trade_open_duration = $request->trade_delay_duration;
        $account_type->maximum_account_number = $request->max_account;
        $account_type->status = 'active';
        $account_type->save();

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
}
