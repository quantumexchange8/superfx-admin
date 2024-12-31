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
            'accountTypes' => (new GeneralController())->getAccountTypes(true),
        ]);
    }

    public function getCompanyProfileData(Request $request)
    {
        $userId = 2;

        $company_profile = RebateAllocation::with(['user' => function ($query) {
            $query->withCount(['directChildren as direct_agent' => function ($q) {
                $q->where('role', 'agent');
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

        $company_profile->user->group_agent = $this->getChildrenCount($userId);

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

        // Function to get all downline users recursively with the role 'agent'
        function getDownlineAgents($user)
        {
            $downline = collect();

            foreach ($user->directChildren as $child) {
                if ($child->role == 'agent') {
                    $downline->push([
                        'id' => $child->id,
                        'name' => $child->name,
                        'email' => $child->email,
                        'profile_pic' => $child->getFirstMediaUrl('profile_photo'),
                    ]);
                }
                $downline = $downline->merge(getDownlineAgents($child));
            }

            return $downline;
        }

        // Get all downline agents for each direct child
        $downline_agents = collect();
        foreach ($users as $user) {
            $downline_agents = $downline_agents->merge(getDownlineAgents($user));
        }

        return response()->json([
            'rebateStructures' => $rebate_structure,
            'users' => $users,
            'downlineAgents' => $downline_agents,
        ]);
    }


    private function getChildrenCount($user_id): int
    {
        return User::where('role', 'agent')
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

    public function getAgents(Request $request)
    {
        $type_id = $request->type_id;
        $search = $request->search;  // Get the search term

        // Start the query for agents
        $query = User::where('role', 'agent');

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
        
        // Get the level 1 agents based on the query
        $lv1_agents = $query->get()
            ->map(function ($agent) use ($search) {
                // Determine the agent's level based on whether there's a search or not
                $level = $search ? $this->calculateLevel($agent->hierarchyList) : 1;

                return [
                    'id' => $agent->id,
                    'profile_photo' => $agent->getFirstMediaUrl('profile_photo'),
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'hierarchy_list' => $agent->hierarchyList,
                    'upline_id' => $agent->upline_id,
                    'level' => $level,
                ];
            })
            ->toArray();

        // Check if ID 2 exists and move it to the first position
        $id_2_index = array_search(2, array_column($lv1_agents, 'id'));
        if ($id_2_index !== false) {
            $id_2_agent = $lv1_agents[$id_2_index];
            unset($lv1_agents[$id_2_index]);
            array_unshift($lv1_agents, $id_2_agent); // Add to the start
        }

        $agents_array = [];
        $lv1_data = [];

        // Check if there are any agents found
        if (!empty($lv1_agents)) {
            // Get level 1 children rebate only if agents are found
            $lv1_rebate = $this->getRebateAllocate($lv1_agents[0]['id'], $type_id);

            // Push level 1 agent & rebate data
            array_push($lv1_data, $lv1_agents, $lv1_rebate);
            $agents_array[] = $lv1_data;

            // Get direct agents of the first upline
            $loop_flag = true;
            $current_agent_id = $lv1_agents[0]['id'];
            while ($loop_flag) {
                $next_level = $this->getDirectAgents($current_agent_id, $type_id);

                // If next level agents are found, continue the loop
                if (!empty($next_level) && isset($next_level[0][0]['id'])) {
                    $current_agent_id = $next_level[0][0]['id'];
                    $agents_array[] = $next_level;
                } else {
                    $loop_flag = false; // Stop looping if no more agents
                }
            }
        }

        return response()->json($agents_array);
    }

    private function getDirectAgents($agent_id, $type_id)
    {
        // children of id passed in
        $children = User::find($agent_id)->directChildren()->where('role', 'agent')->select('id', 'hierarchyList')->get();

        // find children same level
        if ( $children->isNotEmpty() ) {
            $agents = User::where(['hierarchyList' => $children[0]->hierarchyList, 'role' => 'agent'])->get()
                ->map(function ($agent) {
                    return [
                        'id' => $agent->id,
                        'profile_photo' => $agent->getFirstMediaUrl('profile_photo'),
                        'name' => $agent->name,
                        'email' => $agent->email,
                        'hierarchy_list' => $agent->hierarchyList,
                        'upline_id' => $agent->upline_id,
                        'level' => $this->calculateLevel($agent->hierarchyList),
                    ];
                })
                ->sortBy(fn($agent) => $agent['id'] != $children[0]->id)
                ->toArray();

            // reindex
            $agents = array_values($agents);

            // push current level hierarchy agent & rebate into array
            $temp = [];
            $rebate = $this->getRebateAllocate($agents[0]['id'], $type_id);

            array_push($temp, $agents, $rebate);

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
            'upline_forex' => floatval($upline_rebate[0]->amount),
            'upline_stocks' => floatval($upline_rebate[1]->amount),
            'upline_indices' => floatval($upline_rebate[2]->amount),
            'upline_commodities' => floatval($upline_rebate[3]->amount),
            'upline_cryptocurrency' => floatval($upline_rebate[4]->amount),
        ];

        $downline = $user->directChildren()->where('role', 'agent')->first();

        if ($downline) {
            $downline_rebate = User::find($downline->id)->rebateAllocations()->where('account_type_id', $type_id)->get();

            if (!$downline_rebate->isEmpty()) {
                $rebates += [
                    'downline_forex' => floatval($downline_rebate[0]->amount),
                    'downline_stocks' => floatval($downline_rebate[1]->amount),
                    'downline_indices' => floatval($downline_rebate[2]->amount),
                    'downline_commodities' => floatval($downline_rebate[3]->amount),
                    'downline_cryptocurrency' => floatval($downline_rebate[4]->amount),
                ];
            }
        }

        return $rebates;
    }

    public function changeAgents(Request $request)
    {
        $selected_agent_id = $request->id;
        $type_id = $request->type_id;
        $agents_array = [];

        $selected_agent = User::where('id', $selected_agent_id)->first();

        // determine is the selected agent other than level 1
        if ($selected_agent->upline_id !== 2) {
            $split_hierarchy = explode('-2-', $selected_agent->hierarchyList);
            $upline_ids = explode('-', $split_hierarchy[1]);

            array_pop($upline_ids);

            $uplines = User::whereIn('id', $upline_ids)->get()
                ->map(function($upline) use ($type_id) {
                    $rebate = $this->getRebateAllocate($upline->id, $type_id);

                    $same_level_agents = User::where(['hierarchyList' => $upline->hierarchyList, 'role' => 'agent'])->get()
                        ->map(function($same_level_agent) {
                            return [
                                'id' => $same_level_agent->id,
                                'profile_photo' => $same_level_agent->getFirstMediaUrl('profile_photo'),
                                'name' => $same_level_agent->name,
                                'email' => $same_level_agent->email,
                                'hierarchy_list' => $same_level_agent->hierarchyList,
                                'upline_id' => $same_level_agent->upline_id,
                                'level' => $this->calculateLevel($same_level_agent->hierarchyList),
                            ];
                        })
                        ->sortBy(fn($agent) => $agent['id'] != $upline->id)
                        ->toArray();

                    // reindex
                    $same_level_agents = array_values($same_level_agents);

                    $data = [];
                    array_push($data, $same_level_agents, $rebate);
                    return $data;
                })->toArray();

            $agents_array = $uplines;
        }

        // selected agent & same level agents
        $agents = User::where(['hierarchyList' => $selected_agent->hierarchyList, 'role' => 'agent'])->get()
            ->map(function($agent) {
                return [
                    'id' => $agent->id,
                    'profile_photo' => $agent->getFirstMediaUrl('profile_photo'),
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'hierarchy_list' => $agent->hierarchyList,
                    'upline_id' => $agent->upline_id,
                    'level' => $this->calculateLevel($agent->hierarchyList),
                ];
            })
            ->sortBy(fn($agent) => $agent['id'] != $selected_agent->id)
            ->toArray();

        // reindex
        $agents = array_values($agents);

        // selected agent rebate
        $rebate = $this->getRebateAllocate($selected_agent_id, $type_id);

        //push selected agent level into array
        $temp = [];
        array_push($temp, $agents, $rebate);
        $agents_array[] = $temp;

        //pass to getDirectAgents
        $loop_flag = true;
        $current_agent_id = $selected_agent_id;
        while ($loop_flag) {
            $next_level = $this->getDirectAgents($current_agent_id, $type_id);
            if ( !empty($next_level) ) {
                $current_agent_id = $next_level[0][0]['id'];
                $agents_array[] = $next_level;
            } else {
                $loop_flag = false;
            }
        }

        return response()->json($agents_array);
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
