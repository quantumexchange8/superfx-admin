<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\SymbolGroup;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\MarkupProfile;
use App\Models\RebateAllocation;
use App\Models\UserToMarkupProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\MarkupProfileToAccountType;

class MarkupProfileController extends Controller
{
    public function index()
    {
        return Inertia::render('MarkupProfile/MarkupProfile', [
            'accountTypes' => (new GeneralController())->getAllAccountTypes(true),
            'users' => (new GeneralController())->getAllUsers(true),
        ]);
    }

    // public function getMarkupProfiles()
    // {
    //     $markupProfiles = MarkupProfile::with([
    //             'markupProfileToAccountTypes.accountType:id,name',
    //             'userToMarkupProfiles'
    //         ])->get();
    
    //     foreach ($markupProfiles as $profile) {
    //         $profile->account_types = $profile->markupProfileToAccountTypes ? $profile->markupProfileToAccountTypes->pluck('accountType')->filter()->values() : collect();
    //         $profile->total_account = $profile->userToMarkupProfiles ? $profile->userToMarkupProfiles->count() : 0;
    
    //         // Remove relations if not needed
    //         unset($profile->markupProfileToAccountTypes, $profile->userToMarkupProfiles);
    //     }
    
    //     return response()->json(['markupProfiles' => $markupProfiles]);
    // }
    
    public function getMarkupProfiles(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);
    
            $query = MarkupProfile::with([
                'markupProfileToAccountTypes.accountType:id,name',
            ])->withCount('userToMarkupProfiles');
        
            // Handle sorting
            if (!empty($data['sortField']) && isset($data['sortOrder'])) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
    
                if ($data['sortField'] === 'total_account') {
                    $query->orderBy('user_to_markup_profiles_count', $order);
                } else {
                    $query->orderBy($data['sortField'], $order);
                }
            } else {
                $query->orderByDesc('created_at');
            }
        
            // Handle pagination
            $rowsPerPage = $data['rows'] ?? 15;
            $result = $query->paginate($rowsPerPage);
    
            // Transform data
            foreach ($result->items() as $profile) {
                $profile->account_types = $profile->markupProfileToAccountTypes ? $profile->markupProfileToAccountTypes->pluck('accountType')->filter()->values() : collect();
                $profile->total_account = $profile->user_to_markup_profiles_count ?? 0;
    
                unset($profile->markupProfileToAccountTypes, $profile->user_to_markup_profiles_count);
            }
        }
    
        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function addMarkupProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'unique:markup_profiles,name'],
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
            'slug' => Str::slug($request->name),
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
                $user = User::find($userId);
                if ($user && $user->role === 'ib') {
                    // Generate a unique referral code if the user role is 'ib'
                    do {
                        $referralCode = Str::random(10);
                    } while (UserToMarkupProfile::where('referral_code', $referralCode)->exists());
                } else {
                    $referralCode = null;
                }
    
                UserToMarkupProfile::create([
                    'markup_profile_id' => $markupProfile->id,
                    'user_id' => $userId,
                    'referral_code' => $referralCode,
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

    // public function updateMarkupProfile(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'name' => ['required', 'string', 'unique:markup_profiles,name,' . $request->id],
    //         'description' => ['nullable', 'string'],
    //         'account_type_ids' => ['nullable', 'array'],
    //         'account_type_ids.*' => ['exists:account_types,id'],
    //         'user_ids' => ['nullable', 'array'],
    //         'user_ids.*' => ['exists:users,id'],
    //     ])->setAttributeNames([
    //         'name' => trans('public.name'),
    //         'description' => trans('public.description'),
    //         'account_type_ids' => trans('public.account_type_ids'),
    //         'user_ids' => trans('public.user_ids'),
    //     ]);

    //     $validator->validate(); // Validate the request

    //     $profile = MarkupProfile::findOrFail($request->id);
    
    //     // Update AccountType fields
    //     $profile->update([
    //         'name' => $request->name,
    //         'slug' => Str::slug($request->name),
    //         'description' => $request->description,
    //         'status' => 'active',
    //     ]);
    
    //     // Handle account types update
    //     $accountTypes = $request->account_type_ids ?? []; // New account type IDs passed in the request

    //     if ($accountTypes) {
    //         // Get existing account type IDs associated with the profile
    //         $existingAccountTypeIds = MarkupProfileToAccountType::where('markup_profile_id', $request->id)
    //             ->pluck('account_type_id')
    //             ->toArray();

    //         // Get the account types to add and remove
    //         $accountTypesToAdd = array_diff($accountTypes, $existingAccountTypeIds);
    //         $accountTypesToRemove = array_diff($existingAccountTypeIds, $accountTypes);

    //         // Remove outdated account type associations
    //         if (!empty($accountTypesToRemove)) {
    //             // Remove RebateAllocations for the removed account types
    //             foreach ($accountTypesToRemove as $accountTypeId) {
    //                 foreach ($request->user_ids as $userId) {
    //                     // Check if the user has other active markup profiles with the same account type
    //                     $hasOtherMarkupProfiles = UserToMarkupProfile::where('user_id', $userId)
    //                         ->whereHas('markupProfile.markupProfileToAccountTypes', function ($query) use ($accountTypeId) {
    //                             $query->where('account_type_id', $accountTypeId);
    //                         })
    //                         ->exists();

    //                     // Only delete RebateAllocations if no other active markup profiles exist for this user and account type
    //                     if (!$hasOtherMarkupProfiles) {
    //                         RebateAllocation::where('user_id', $userId)
    //                             ->where('account_type_id', $accountTypeId)
    //                             ->delete();
    //                     }
    //                 }
    //             }

    //             // Also remove the account type associations from the profile
    //             MarkupProfileToAccountType::where('markup_profile_id', $request->id)
    //                 ->whereIn('account_type_id', $accountTypesToRemove)
    //                 ->delete();
    //         }

    //         // Add new account type associations
    //         foreach ($accountTypesToAdd as $accountTypeId) {
    //             // Add account type associations to the markup profile
    //             MarkupProfileToAccountType::create([
    //                 'markup_profile_id' => $request->id,
    //                 'account_type_id' => $accountTypeId,
    //             ]);

    //             // Add RebateAllocations for users if account types are added
    //             foreach ($request->user_ids as $userId) {
    //                 // Ensure RebateAllocation is created for the new account type and user
    //                 foreach (SymbolGroup::all() as $symbolGroup) {
    //                     RebateAllocation::firstOrCreate([
    //                         'user_id' => $userId,
    //                         'account_type_id' => $accountTypeId,
    //                         'symbol_group_id' => $symbolGroup->id,
    //                         'amount' => 0,
    //                     ]);
    //                 }
    //             }
    //         }
    //     } else {
    //         // If no account types are provided, remove all associated account types
    //         $accountTypeIds = MarkupProfileToAccountType::where('markup_profile_id', $request->id)
    //             ->pluck('account_type_id')
    //             ->toArray();

    //         // Remove RebateAllocations for all users and account types associated with the profile
    //         foreach ($request->user_ids as $userId) {
    //             foreach ($accountTypeIds as $accountTypeId) {
    //                 // Check if the user has other active markup profiles with the same account type
    //                 $hasOtherMarkupProfiles = UserToMarkupProfile::where('user_id', $userId)
    //                     ->whereHas('markupProfile.markupProfileToAccountTypes', function ($query) use ($accountTypeId) {
    //                         $query->where('account_type_id', $accountTypeId);
    //                     })
    //                     ->exists();

    //                 // Only delete RebateAllocations if no other active markup profiles exist for this user and account type
    //                 if (!$hasOtherMarkupProfiles) {
    //                     RebateAllocation::where('user_id', $userId)
    //                         ->where('account_type_id', $accountTypeId)
    //                         ->delete();
    //                 }
    //             }
    //         }

    //         // Finally, remove the account type associations from the profile
    //         MarkupProfileToAccountType::where('markup_profile_id', $request->id)->delete();
    //     }
    
    //     // Handle user associations update
    //     $userIds = $request->user_ids ?? [];

    //     if ($userIds) {
    //         // Get existing user IDs associated with the profile
    //         $existingUserIds = UserToMarkupProfile::where('markup_profile_id', $request->id)
    //             ->pluck('user_id')
    //             ->toArray();

    //         // Get the user IDs to add and remove
    //         $userIdsToAdd = array_diff($userIds, $existingUserIds);
    //         $userIdsToRemove = array_diff($existingUserIds, $userIds);

    //         // Remove outdated user associations
    //         if (!empty($userIdsToRemove)) {
    //             // First, remove RebateAllocations for the users to be removed
    //             foreach ($userIdsToRemove as $userId) {
    //                 foreach ($accountTypes as $accountTypeId) {
    //                     // Check if the user has other active markup profiles with the same account type
    //                     $hasOtherMarkupProfiles = UserToMarkupProfile::where('user_id', $userId)
    //                         ->whereHas('markupProfile.markupProfileToAccountTypes', function ($query) use ($accountTypeId) {
    //                             $query->where('account_type_id', $accountTypeId);
    //                         })
    //                         ->exists();

    //                     // Only delete RebateAllocations if no other active markup profiles exist for this user and account type
    //                     if (!$hasOtherMarkupProfiles) {
    //                         RebateAllocation::where('user_id', $userId)
    //                             ->where('account_type_id', $accountTypeId)
    //                             ->delete();
    //                     }
    //                 }
    //             }

    //             // Then, remove the user associations
    //             UserToMarkupProfile::where('markup_profile_id', $request->id)
    //                 ->whereIn('user_id', $userIdsToRemove)
    //                 ->delete();
    //         }

    //         // Add new user associations
    //         foreach ($userIdsToAdd as $userId) {
    //             $user = User::find($userId);

    //             if (!$user) {
    //                 continue; // Skip if user not found
    //             }

    //             // Check if user is IB
    //             $referralCode = null;
    //             if ($user->role === 'ib') {
    //                 // Generate unique referral code
    //                 do {
    //                     $referralCode = Str::random(10);
    //                 } while (UserToMarkupProfile::where('referral_code', $referralCode)->exists());
    //             }

    //             // Create user to markup profile record
    //             UserToMarkupProfile::create([
    //                 'markup_profile_id' => $request->id,
    //                 'user_id' => $userId,
    //                 'referral_code' => $referralCode,
    //             ]);

    //             // Add RebateAllocations for the newly added user(s)
    //             foreach ($request->account_type_ids as $accountTypeId) {
    //                 foreach (SymbolGroup::all() as $symbolGroup) {
    //                     RebateAllocation::firstOrCreate([
    //                         'user_id' => $userId,
    //                         'account_type_id' => $accountTypeId,
    //                         'symbol_group_id' => $symbolGroup->id,
    //                         'amount' => 0, // Default amount is 0
    //                     ]);
    //                 }
    //             }
    //         }
    //     } else {
    //         // If no user IDs are provided, remove all associated users and their RebateAllocations
    //         $existingUserIds = UserToMarkupProfile::where('markup_profile_id', $request->id)
    //             ->pluck('user_id')
    //             ->toArray();

    //         // Remove RebateAllocations for all users associated with the profile
    //         foreach ($existingUserIds as $userId) {
    //             foreach ($accountTypeIds as $accountTypeId) {
    //                 // Check and delete RebateAllocations
    //                 $hasOtherMarkupProfiles = UserToMarkupProfile::where('user_id', $userId)
    //                     ->whereHas('markupProfile.markupProfileToAccountTypes', function ($query) use ($accountTypeId) {
    //                         $query->where('account_type_id', $accountTypeId);
    //                     })
    //                     ->exists();

    //                 if (!$hasOtherMarkupProfiles) {
    //                     RebateAllocation::where('user_id', $userId)
    //                         ->where('account_type_id', $accountTypeId)
    //                         ->delete();
    //                 }
    //             }
    //         }

    //         // Remove all associated user profiles
    //         UserToMarkupProfile::where('markup_profile_id', $request->id)->delete();
    //     }
    
    //     return back()->with('toast', [
    //         'title' => trans('public.toast_update_markup_profile_success'),
    //         'type' => 'success',
    //     ]);
    // }

    public function updateMarkupProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'unique:markup_profiles,name,' . $request->id],
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

        $profile = MarkupProfile::findOrFail($request->id);

        // Update AccountType fields
        $profile->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => 'active',
        ]);

        // Update Account Type Associations
        $this->updateAccountTypes($request);

        // Update User Associations
        $this->updateUserAssociations($request);

        return back()->with('toast', [
            'title' => trans('public.toast_update_markup_profile_success'),
            'type' => 'success',
        ]);
    }

    public function updateAccountTypes(Request $request)
    {
        $accountTypes = $request->account_type_ids ?? [];
        $markupProfileId = $request->id; // Get the current markup profile ID
    
        if ($accountTypes) {
            // Get existing account type IDs associated with the profile
            $existingAccountTypeIds = MarkupProfileToAccountType::where('markup_profile_id', $markupProfileId)
                ->pluck('account_type_id')
                ->toArray();
    
            // Get the account types to add and remove
            $accountTypesToAdd = array_diff($accountTypes, $existingAccountTypeIds);
            $accountTypesToRemove = array_diff($existingAccountTypeIds, $accountTypes);
    
            // Remove outdated account type associations
            if (!empty($accountTypesToRemove)) {
                MarkupProfileToAccountType::where('markup_profile_id', $markupProfileId)
                    ->whereIn('account_type_id', $accountTypesToRemove)
                    ->delete();
            }
    
            foreach ($accountTypesToAdd as $accountTypeId) {
                MarkupProfileToAccountType::create([
                    'markup_profile_id' => $markupProfileId,
                    'account_type_id' => $accountTypeId,
                ]);
            }
        } else {
            MarkupProfileToAccountType::where('markup_profile_id', $markupProfileId)->delete();
        }
    }
        
    public function updateUserAssociations(Request $request)
    {
        $userIds = $request->user_ids ?? [];
        $markupProfileId = $request->id; // Get the current markup profile ID
    
        if ($userIds) {
            $existingUserIds = UserToMarkupProfile::where('markup_profile_id', $markupProfileId)
                ->pluck('user_id')
                ->toArray();
    
            $userIdsToAdd = array_diff($userIds, $existingUserIds);
            $userIdsToRemove = array_diff($existingUserIds, $userIds);
    
            if (!empty($userIdsToRemove)) {
                UserToMarkupProfile::where('markup_profile_id', $markupProfileId)
                    ->whereIn('user_id', $userIdsToRemove)
                    ->delete();
            }
    
            foreach ($userIdsToAdd as $userId) {
                $user = User::find($userId);
    
                if (!$user) {
                    continue;
                }
    
                $referralCode = null;
                if ($user->role === 'ib') {
                    do {
                        $referralCode = Str::random(10);
                    } while (UserToMarkupProfile::where('referral_code', $referralCode)->exists());
                }
    
                UserToMarkupProfile::create([
                    'markup_profile_id' => $markupProfileId,
                    'user_id' => $userId,
                    'referral_code' => $referralCode,
                ]);
            }
        } else {
            UserToMarkupProfile::where('markup_profile_id', $markupProfileId)->delete();
        }
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
