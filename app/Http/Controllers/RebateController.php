<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\RebateAllocation;
use Illuminate\Support\Facades\Auth;

class RebateController extends Controller
{
    public function rebate_allocate()
    {
        return Inertia::render('RebateAllocate/RebateAllocate', [
            'accountTypes' => (new GeneralController())->getAllAccountTypes(true),
        ]);
    }

    public function getCompanyProfileData(Request $request)
    {
        $userId = 2;

        $company_profile = RebateAllocation::with(['user' => function ($query) {
            $query->withCount(['directChildren as direct_ib' => function ($q) {
                $q->where('role', 'ib');
            }]);
        }])
            ->where('user_id', $userId)
            ->where('account_type_id', $request->account_type_id)
            ->first();

        if (!$company_profile || !$company_profile->user) {
            return back()->with('toast', [
                'title' => 'Invalid Account Type',
                'type' => 'warning',
            ]);
        }

        $company_profile->user->group_ib = $this->getChildrenCount($userId);

        $levels = $this->getHierarchyLevels($company_profile->user, $company_profile->user->id);
        $company_profile->user->minimum_level = $levels['min'];
        $company_profile->user->maximum_level = $levels['max'];

        // Fetch rebate details
        $rebate_details = RebateAllocation::with('symbol_group:id,display')
            ->where('user_id', $userId)
            ->where('account_type_id', $request->account_type_id)
            ->get();

        return response()->json([
            'companyProfile' => $company_profile,
            'rebateDetails' => $rebate_details
        ]);
    }

    public function updateRebateAllocation(Request $request)
    {
        $ids = $request->id;
        $amounts = $request->amount;

        foreach ($ids as $index => $id) {
            RebateAllocation::find($id)->update([
                'amount' => $amounts[$index],
                'edited_by' => Auth::id()
            ]);
        }

        return redirect()->back()->with('toast', [
            'title' => trans('public.update_rebate_success_alert'),
            'type' => 'success'
        ]);
    }

    public function getRebateStructureData(Request $request)
    {
        // Retrieve rebate structure data based on account type ID and user ID
        $rebate_structure = RebateAllocation::with(['user:id,email,id_number,upline_id,hierarchyList'])
            ->where('account_type_id', $request->account_type_id)
            ->where('user_id', $request->user_id)
            ->get();

        // Get the upline user
        $upline = User::find($request->user_id);

        // Get the direct children of the upline user
        $users = $upline->directChildren;

        // Function to get all downline users recursively with the role 'ib'
        function getDownlineIBs($user)
        {
            $downline = collect();

            foreach ($user->directChildren as $child) {
                if ($child->role == 'ib') {
                    $downline->push([
                        'id' => $child->id,
                        'name' => $child->name,
                        'email' => $child->email,
                        'profile_pic' => $child->getFirstMediaUrl('profile_photo'),
                    ]);
                }
                $downline = $downline->merge(getDownlineIBs($child));
            }

            return $downline;
        }

        // Get all downline ibs for each direct child
        $downline_ibs = collect();
        foreach ($users as $user) {
            $downline_ibs = $downline_ibs->merge(getDownlineIBs($user));
        }

        return response()->json([
            'rebateStructures' => $rebate_structure,
            'users' => $users,
            'downlineIBs' => $downline_ibs,
        ]);
    }


    private function getChildrenCount($user_id): int
    {
        return User::where('role', 'ib')
            ->where('hierarchyList', 'like', '%-' . $user_id . '-%')
            ->count();
    }

    private function getHierarchyLevels($upline, $user_id)
    {
        $users = User::whereIn('id', $upline->getChildrenIds())->get();
        $minLevel = PHP_INT_MAX;
        $maxLevel = PHP_INT_MIN;

        foreach ($users as $user) {
            $levels = explode('-', trim($user->hierarchyList, '-'));
            if (in_array($user_id, array_map('intval', $levels))) {
                $levelCount = count($levels);
                $minLevel = min($minLevel, $levelCount);
                $maxLevel = max($maxLevel, $levelCount);
            }
        }

        return [
            'min' => $minLevel == PHP_INT_MAX ? 0 : $minLevel,
            'max' => $maxLevel == PHP_INT_MIN ? 0 : $maxLevel
        ];
    }

    public function getIBs(Request $request)
    {
        $type_id = $request->type_id;
        $search = $request->search;  // Get the search term

        // Start the query for ibs
        $query = User::where('role', 'ib');

        // If there is no search term, filter by upline_id
        if (empty($search)) {
            $query->where('upline_id', 2);  // Only apply upline_id filter if no search term
        } else {
            // If there is a search term, search by name or email
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('id_number', 'like', "%$search%");
            });
        }
        
        // Get the level 1 ibs based on the query
        $lv1_ibs = $query->get()
            ->map(function ($ib) use ($search) {
                // Determine the ib's level based on whether there's a search or not
                $level = $search ? $this->calculateLevel($ib->hierarchyList) : 1;

                return [
                    'id' => $ib->id,
                    'profile_photo' => $ib->getFirstMediaUrl('profile_photo'),
                    'name' => $ib->name,
                    'email' => $ib->email,
                    'hierarchy_list' => $ib->hierarchyList,
                    'upline_id' => $ib->upline_id,
                    'level' => $level,
                ];
            })
            ->toArray();

        // Check if ID 2 exists and move it to the first position
        $id_2_index = array_search(2, array_column($lv1_ibs, 'id'));
        if ($id_2_index !== false) {
            $id_2_ib = $lv1_ibs[$id_2_index];
            unset($lv1_ibs[$id_2_index]);
            array_unshift($lv1_ibs, $id_2_ib); // Add to the start
        }

        $ibs_array = [];
        $lv1_data = [];

        // Check if there are any ibs found
        if (!empty($lv1_ibs)) {
            // Get level 1 children rebate only if ibs are found
            $lv1_rebate = $this->getRebateAllocate($lv1_ibs[0]['id'], $type_id);

            // Push level 1 ib & rebate data
            array_push($lv1_data, $lv1_ibs, $lv1_rebate);
            $ibs_array[] = $lv1_data;

            // Get direct ibs of the first upline
            $loop_flag = true;
            $current_ib_id = $lv1_ibs[0]['id'];
            while ($loop_flag) {
                $next_level = $this->getDirectIBs($current_ib_id, $type_id);

                // If next level ibs are found, continue the loop
                if (!empty($next_level) && isset($next_level[0][0]['id'])) {
                    $current_ib_id = $next_level[0][0]['id'];
                    $ibs_array[] = $next_level;
                } else {
                    $loop_flag = false; // Stop looping if no more ibs
                }
            }
        }

        return response()->json($ibs_array);
    }

    private function getDirectIBs($ib_id, $type_id)
    {
        // children of id passed in
        $children = User::find($ib_id)->directChildren()->where('role', 'ib')->select('id', 'hierarchyList')->get();

        // find children same level
        if ( $children->isNotEmpty() ) {
            $ibs = User::where(['hierarchyList' => $children[0]->hierarchyList, 'role' => 'ib'])->get()
                ->map(function ($ib) {
                    return [
                        'id' => $ib->id,
                        'profile_photo' => $ib->getFirstMediaUrl('profile_photo'),
                        'name' => $ib->name,
                        'email' => $ib->email,
                        'hierarchy_list' => $ib->hierarchyList,
                        'upline_id' => $ib->upline_id,
                        'level' => $this->calculateLevel($ib->hierarchyList),
                    ];
                })
                ->sortBy(fn($ib) => $ib['id'] != $children[0]->id)
                ->toArray();

            // reindex
            $ibs = array_values($ibs);

            // push current level hierarchy ib & rebate into array
            $temp = [];
            $rebate = $this->getRebateAllocate($ibs[0]['id'], $type_id);

            array_push($temp, $ibs, $rebate);

            return $temp;
        }

        return '';
    }

    private function calculateLevel($hierarchyList)
    {
        if (is_null($hierarchyList) || $hierarchyList === '') {
            return 0;
        }

        $split = explode('-2-', $hierarchyList);
        return substr_count($split[1], '-') + 1;
    }

    private function getRebateAllocate($user_id, $type_id)
    {
        $user = User::find($user_id);
        $rebate = $user->rebateAllocations()->where('account_type_id', $type_id)->get();
        $upline_rebate = User::find($user->upline_id)->rebateAllocations()->where('account_type_id', $type_id)->get();

        $rebates = [
            'user_id' => $rebate[0]->user_id,
            'account_type_id' => $type_id,
            $rebate[0]->symbol_group_id => floatval($rebate[0]->amount),
            $rebate[1]->symbol_group_id => floatval($rebate[1]->amount),
            $rebate[2]->symbol_group_id => floatval($rebate[2]->amount),
            $rebate[3]->symbol_group_id => floatval($rebate[3]->amount),
            $rebate[4]->symbol_group_id => floatval($rebate[4]->amount),
            $rebate[5]->symbol_group_id => floatval($rebate[5]->amount),
            'upline_forex' => floatval($upline_rebate[0]->amount),
            'upline_indexes' => floatval($upline_rebate[1]->amount),
            'upline_commodities' => floatval($upline_rebate[2]->amount),
            'upline_metals' => floatval($upline_rebate[3]->amount),
            'upline_cryptocurrency' => floatval($upline_rebate[4]->amount),
            'upline_shares' => floatval($upline_rebate[5]->amount),
        ];

        $downlines = $user->directChildren()->where('role', 'ib')->get();

        if ($downlines->isNotEmpty()) {
            $rebateCategories = [
                'downline_forex' => null,
                'downline_indexes' => null,
                'downline_commodities' => null,
                'downline_metals' => null,
                'downline_cryptocurrency' => null,
                'downline_shares' => null,
            ];

            foreach ($downlines as $downline) {
                $downline_rebate = $downline->rebateAllocations()->where('account_type_id', $type_id)->get();

                if (!$downline_rebate->isEmpty()) {
                    $rebateCategories['downline_forex'] = is_null($rebateCategories['downline_forex'])
                        ? floatval($downline_rebate[0]->amount)
                        : max($rebateCategories['downline_forex'], floatval($downline_rebate[0]->amount));

                    $rebateCategories['downline_indexes'] = is_null($rebateCategories['downline_indexes'])
                        ? floatval($downline_rebate[1]->amount)
                        : max($rebateCategories['downline_indexes'], floatval($downline_rebate[1]->amount));

                    $rebateCategories['downline_commodities'] = is_null($rebateCategories['downline_commodities'])
                        ? floatval($downline_rebate[2]->amount)
                        : max($rebateCategories['downline_commodities'], floatval($downline_rebate[2]->amount));

                    $rebateCategories['downline_metals'] = is_null($rebateCategories['downline_metals'])
                        ? floatval($downline_rebate[3]->amount)
                        : max($rebateCategories['downline_metals'], floatval($downline_rebate[3]->amount));

                    $rebateCategories['downline_cryptocurrency'] = is_null($rebateCategories['downline_cryptocurrency'])
                        ? floatval($downline_rebate[4]->amount)
                        : max($rebateCategories['downline_cryptocurrency'], floatval($downline_rebate[4]->amount));

                    $rebateCategories['downline_shares'] = is_null($rebateCategories['downline_shares'])
                        ? floatval($downline_rebate[5]->amount)
                        : max($rebateCategories['downline_shares'], floatval($downline_rebate[5]->amount));
                }
            }

            $rebates += $rebateCategories;
        }

        return $rebates;
    }

    public function changeIBs(Request $request)
    {
        $selected_ib_id = $request->id;
        $type_id = $request->type_id;
        $ibs_array = [];

        $selected_ib = User::where('id', $selected_ib_id)->first();

        // determine is the selected ib other than level 1
        if ($selected_ib->upline_id !== 2) {
            $split_hierarchy = explode('-2-', $selected_ib->hierarchyList);
            $upline_ids = explode('-', $split_hierarchy[1]);

            array_pop($upline_ids);

            $uplines = User::whereIn('id', $upline_ids)->get()
                ->map(function($upline) use ($type_id) {
                    $rebate = $this->getRebateAllocate($upline->id, $type_id);

                    $same_level_ibs = User::where(['hierarchyList' => $upline->hierarchyList, 'role' => 'ib'])->get()
                        ->map(function($same_level_ib) {
                            return [
                                'id' => $same_level_ib->id,
                                'profile_photo' => $same_level_ib->getFirstMediaUrl('profile_photo'),
                                'name' => $same_level_ib->name,
                                'email' => $same_level_ib->email,
                                'hierarchy_list' => $same_level_ib->hierarchyList,
                                'upline_id' => $same_level_ib->upline_id,
                                'level' => $this->calculateLevel($same_level_ib->hierarchyList),
                            ];
                        })
                        ->sortBy(fn($ib) => $ib['id'] != $upline->id)
                        ->toArray();

                    // reindex
                    $same_level_ibs = array_values($same_level_ibs);

                    $data = [];
                    array_push($data, $same_level_ibs, $rebate);
                    return $data;
                })->toArray();

            $ibs_array = $uplines;
        }

        // selected ib & same level ibs
        $ibs = User::where(['hierarchyList' => $selected_ib->hierarchyList, 'role' => 'ib'])->get()
            ->map(function($ib) {
                return [
                    'id' => $ib->id,
                    'profile_photo' => $ib->getFirstMediaUrl('profile_photo'),
                    'name' => $ib->name,
                    'email' => $ib->email,
                    'hierarchy_list' => $ib->hierarchyList,
                    'upline_id' => $ib->upline_id,
                    'level' => $this->calculateLevel($ib->hierarchyList),
                ];
            })
            ->sortBy(fn($ib) => $ib['id'] != $selected_ib->id)
            ->toArray();

        // reindex
        $ibs = array_values($ibs);

        // selected ib rebate
        $rebate = $this->getRebateAllocate($selected_ib_id, $type_id);

        //push selected ib level into array
        $temp = [];
        array_push($temp, $ibs, $rebate);
        $ibs_array[] = $temp;

        //pass to getDirectibs
        $loop_flag = true;
        $current_ib_id = $selected_ib_id;
        while ($loop_flag) {
            $next_level = $this->getDirectIBs($current_ib_id, $type_id);
            if ( !empty($next_level) ) {
                $current_ib_id = $next_level[0][0]['id'];
                $ibs_array[] = $next_level;
            } else {
                $loop_flag = false;
            }
        }

        return response()->json($ibs_array);
    }

    public function updateRebateAmount(Request $request)
    {
        $data = $request->rebates;

        $rebates = RebateAllocation::where('user_id', $data['user_id'])
            ->where('account_type_id', $data['account_type_id'])
            ->get();

        foreach ($rebates as $rebate) {
            if (isset($data[$rebate->symbol_group_id])) {
                $rebate->amount = $data[$rebate->symbol_group_id];
                $rebate->save();
            }
        }

        return back()->with('toast', [
            'title' => trans('public.toast_update_rebate_success'),
            'type' => 'success',
        ]);
    }
}
