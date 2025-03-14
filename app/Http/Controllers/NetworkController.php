<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NetworkController extends Controller
{
    public function network()
    {
        return Inertia::render('Member/Network/MemberNetwork');
    }

    public function getDownlineData(Request $request)
    {
        $upline_id = $request->upline_id;
        $parent_id = $request->parent_id ?: 2;

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $parent = User::whereIn('role', ['ib', 'member'])
                ->where('name', 'LIKE', $search)
                ->orWhere('id_number', 'LIKE', $search)
                ->orWhere('email', 'LIKE', $search)
                ->first();

            $parent_id = $parent->id;
            $upline_id = $parent->upline_id;
        }

        $parent = User::with(['directChildren:id,name,email,id_number,upline_id,role,hierarchyList'])
            ->whereIn('role', ['ib', 'member'])
            ->select('id', 'name', 'email', 'id_number', 'upline_id', 'role', 'hierarchyList')
            ->find($parent_id);

        $upline = $upline_id ? User::select('id', 'name', 'email', 'id_number', 'upline_id', 'role', 'hierarchyList')->find($upline_id) : null;

        $parent_data = $this->formatUserData($parent);
        $upline_data = $upline ? $this->formatUserData($upline) : null;

        $direct_children = [];
        foreach ($parent->directChildren as $child) {
            if (in_array($child->role, ['ib', 'member'])) {
                $direct_children[] = $this->formatUserData($child);
            }
        }

        return response()->json([
            'upline' => $upline_data,
            'parent' => $parent_data,
            'direct_children' => $direct_children,
        ]);
    }

    private function formatUserData($user)
    {
        if ($user->upline) {
            $upper_upline = $user->upline->upline;
        }

        return array_merge(
            $user->only(['id', 'name', 'email', 'id_number', 'upline_id', 'role']),
            [
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo' => $user->getFirstMediaUrl('profile_photo'),
                'upper_upline_id' => $upper_upline->id ?? null,
                'level' => $this->calculateLevel($user->hierarchyList),
                'total_ib_count' => $this->getChildrenCount('ib', $user->id),
                'total_member_count' => $this->getChildrenCount('member', $user->id),
            ]
        );
    }

    private function calculateLevel($hierarchyList)
    {
        if (is_null($hierarchyList) || $hierarchyList === '') {
            return 0;
        }
        return substr_count($hierarchyList, '-') - 1;
    }

    private function getChildrenCount($role, $user_id): int
    {
        return User::where('role', $role)
            ->where('hierarchyList', 'like', '%-' . $user_id . '-%')
            ->count();
    }
}
