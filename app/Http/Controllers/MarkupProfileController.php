<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\MarkupProfile;
use App\Models\UserToMarkupProfile;
use Illuminate\Support\Facades\Validator;
use App\Models\MarkupProfileToAccountType;

class MarkupProfileController extends Controller
{
    public function index()
    {
        return Inertia::render('MarkupProfile/MarkupProfile', [
            'accountTypes' => (new GeneralController())->getAccountTypes(true),
            'users' => (new GeneralController())->getAllUsers(true),
        ]);
    }

    public function getMarkupProfiles()
    {
        $markupProfiles = MarkupProfile::with([
                'markupProfileToAccountTypes.accountType:id,name',
                'userToMarkupProfiles'
            ])->get();
    
        foreach ($markupProfiles as $profile) {
            $profile->account_types = $profile->markupProfileToAccountTypes ? $profile->markupProfileToAccountTypes->pluck('accountType')->filter()->values() : collect();
            $profile->total_account = $profile->userToMarkupProfiles ? $profile->userToMarkupProfiles->count() : 0;
    
            // Remove relations if not needed
            unset($profile->markupProfileToAccountTypes, $profile->userToMarkupProfiles);
        }
    
        return response()->json(['markupProfiles' => $markupProfiles]);
    }
    
    public function addMarkupProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'account_type_ids' => ['nullable', 'array'],
            'account_type_ids.*' => ['exists:account_types,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ])->setAttributeNames([
            'name' => trans('public.name'),
            'description' => trans('public.description'),
            'account_type_ids' => trans('public.account_type_ids'),
            'user_ids' => trans('public.user_ids'),
        ]);

        $validator->validate(); // Validate the request
        
        // Create MarkupProfile
        $markupProfile = MarkupProfile::create([
            'name' => $request->name,
            'description' => $request->description ?? null,
        ]);

        // Insert into MarkupProfileToAccountType using the Model
        if (!empty($request->account_type_ids)) {
            foreach ($request->account_type_ids as $accountTypeId) {
                MarkupProfileToAccountType::create([
                    'markup_profile_id' => $markupProfile->id,
                    'account_type_id' => $accountTypeId,
                ]);
            }
        }

        // Insert into UserToMarkupProfile using the Model
        if (!empty($request->user_ids)) {
            foreach ($request->user_ids as $userId) {
                UserToMarkupProfile::create([
                    'markup_profile_id' => $markupProfile->id,
                    'user_id' => $userId,
                ]);
            }
        }

        return back()->with('toast', [
            'title' => trans('public.toast_create_profile_success'),
            'type' => 'success',
        ]);

    }

    public function getMarkupProfileData(Request $request)
    {
        $accountTypes = MarkupProfileToAccountType::where('markup_profile_id', $request->id)
            ->with('accountType') // Eager load account type data
            ->get()
            ->map(function ($accountTypeData) {
                return [
                    'value' => $accountTypeData->accountType?->id,
                    'name' => $accountTypeData->accountType?->name,
                ];
            });

        $users = UserToMarkupProfile::where('markup_profile_id', $request->id)
            ->with('user') // Eager load user data
            ->get()
            ->map(function ($userData) {
                return [
                    'value' => $userData->user?->id,
                    'name' => $userData->user?->name,
                    'email' => $userData->user?->email,
                    'id_number' => $userData->user?->id_number,
                ];
            });
    
        return response()->json([
            'accountTypes' => $accountTypes,
            'users' => $users
        ]);
    }

    public function updateMarkupProfile(Request $request)
    {
        $profile = MarkupProfile::findOrFail($request->id);
    
        // Update AccountType fields
        $profile->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => 'active',
        ]);
    
        // Handle account types update
        $accountTypes = $request->account_type_ids ?? []; // New account type IDs passed in the request
    
        if ($accountTypes) {
            // Get existing account type IDs associated with the profile
            $existingAccountTypeIds = MarkupProfileToAccountType::where('markup_profile_id', $request->id)
                ->pluck('account_type_id')
                ->toArray();
    
            // Get the account types to add and remove
            $accountTypesToAdd = array_diff($accountTypes, $existingAccountTypeIds);
            $accountTypesToRemove = array_diff($existingAccountTypeIds, $accountTypes);
    
            // Remove outdated account type associations
            if (!empty($accountTypesToRemove)) {
                MarkupProfileToAccountType::where('markup_profile_id', $request->id)
                    ->whereIn('account_type_id', $accountTypesToRemove)
                    ->delete();
            }
    
            // Add new account type associations
            foreach ($accountTypesToAdd as $accountTypeId) {
                MarkupProfileToAccountType::create([
                    'markup_profile_id' => $request->id,
                    'account_type_id' => $accountTypeId,
                ]);
            }
        } else {
            // If no account types are provided, remove all associated account types
            MarkupProfileToAccountType::where('markup_profile_id', $request->id)->delete();
        }
    
        // Handle user associations update
        $userIds = $request->user_ids ?? []; // New user IDs passed in the request
    
        if ($userIds) {
            // Get existing user IDs associated with the profile
            $existingUserIds = UserToMarkupProfile::where('markup_profile_id', $request->id)
                ->pluck('user_id')
                ->toArray();
    
            // Get the user IDs to add and remove
            $userIdsToAdd = array_diff($userIds, $existingUserIds);
            $userIdsToRemove = array_diff($existingUserIds, $userIds);
    
            // Remove outdated user associations
            if (!empty($userIdsToRemove)) {
                UserToMarkupProfile::where('markup_profile_id', $request->id)
                    ->whereIn('user_id', $userIdsToRemove)
                    ->delete();
            }
    
            // Add new user associations
            foreach ($userIdsToAdd as $userId) {
                UserToMarkupProfile::create([
                    'markup_profile_id' => $request->id,
                    'user_id' => $userId,
                ]);
            }
        } else {
            // If no user IDs are provided, remove all associated users
            UserToMarkupProfile::where('markup_profile_id', $request->id)->delete();
        }
    
        return back()->with('toast', [
            'title' => trans('public.toast_update_markup_profile_success'),
            'type' => 'success',
        ]);
    }
        
    public function updateStatus(Request $request)
    {
        $profile = MarkupProfile::find($request->id);
        $profile->status = $profile->status == 'active' ? 'inactive' : 'active';
        $profile->save();

        return back()->with('toast', [
            'title' => $profile->status == 'active' ? trans("public.toast_profile_activated") : trans("public.toast_profile_deactivated"),
            'type' => 'success',
        ]);
    }

}
