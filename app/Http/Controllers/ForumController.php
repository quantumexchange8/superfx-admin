<?php

namespace App\Http\Controllers;

use App\Models\ForumPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ForumController extends Controller
{
    public function index()
    {
        $author = ForumPost::where('user_id', \Auth::id())->first();

        return Inertia::render('Member/Forum/MemberForum', [
            'postCounts' => ForumPost::count(),
            'authorName' => $author?->display_name
        ]);
    }

    public function createPost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'display_avatar' => ['required'],
            'display_name' => ['required'],
        ])->setAttributeNames([
            'display_avatar' => trans('public.display_avatar'),
            'display_name' => trans('public.display_name'),
        ]);
        $validator->validate();

        if (!$request->filled('subject') && !$request->filled('message') && !$request->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'subject' => trans('public.at_least_one_field_required'),
            ]);
        }

        try {
            $post = ForumPost::create([
                'user_id' => \Auth::id(),
                'display_name' => $request->display_name,
                'subject' => $request->subject,
                'message' => $request->message,
            ]);

            if ($request->display_avatar) {
                $path = public_path($request->display_avatar);
                $post->copyMedia($path)->toMediaCollection('display_avatar');
            }

            if ($request->attachment) {
                $post->addMedia($request->attachment)->toMediaCollection('post_attachment');
            }

            // Redirect with success message
            return redirect()->back()->with('toast', [
                "title" => trans('public.toast_create_post_success'),
                "type" => "success"
            ]);
        } catch (\Exception $e) {
            // Log the exception and show a generic error message
            Log::error('Error updating asset master: '.$e->getMessage());

            return redirect()->back()->with('toast', [
                'title' => 'There was an error creating the post.',
                'type' => 'error'
            ]);
        }
    }

    public function getPosts(Request $request)
    {
        $posts = ForumPost::with([
            'user:id,name',
            'media'
        ])
            ->latest()
            ->get()
            ->map(function ($post) {
                $post->profile_photo = $post->user->getFirstMediaUrl('profile_photo');
                $post->display_avatar = $post->getFirstMediaUrl('display_avatar');
                $post->post_attachment = $post->getFirstMediaUrl('post_attachment');
                return $post;
            });

        return response()->json($posts);
    }

    public function getAgents(Request $request)
    {
        $allRolesInDatabase = Role::all()->pluck('name');

        if (!$allRolesInDatabase->contains('agent')) {
            Role::create(['name' => 'agent']);
        }

        $agentWithoutRole = User::where('role', 'agent')
            ->withoutRole('agent')
            ->get();

        foreach ($agentWithoutRole as $agentRole) {
            $agentRole->syncRoles('agent');
        }

        $allPermissionsInDatabase = Permission::all()->pluck('name');

        if (!$allPermissionsInDatabase->contains('post_forum')) {
            Permission::create(['name' => 'post_forum']);
        }

        $agents = User::role('agent')
            ->with('media')
            ->where('status', 'active')
            ->select('id', 'name', 'email', 'id_number')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('id_number', 'like', '%' . $search . '%');
                });
            })
            ->get()
            ->map(function ($user) {
                $user->profile_photo = $user->getFirstMediaUrl('profile_photo');
                $user->isSelected = $user->hasPermissionTo('post_forum');

                return $user;
            });

        $selected_agents = $agents->filter(function ($user) {
            return $user->isSelected;
        });

        $non_selected_agents = $agents->reject(function ($user) {
            return $user->isSelected;
        });

        return response()->json([
            'selectedAgents' => $selected_agents->values(),
            'agents' => $non_selected_agents->values(),
        ]);
    }

    public function updatePostPermission(Request $request)
    {
        $user = User::find($request->id);

        $user->hasPermissionTo('post_forum');

        if ($user->hasPermissionTo('post_forum')) {
            $user->revokePermissionTo('post_forum');
        } else {
            $user->givePermissionTo('post_forum');
        }

        return back()->with('toast', [
            'title' => $user->hasPermissionTo('post_forum') ? trans("public.toast_permit_granted") : trans("public.toast_permit_removed"),
            'type' => 'success',
        ]);
    }

    public function deletePost(Request $request)
    {
        $post = ForumPost::find($request->id);
        
        if ($post->hasMedia('post_attachment')) {
            $post->clearMediaCollection('post_attachment');
        }

        $post->delete();

        return redirect()->back()->with('toast', [
            'title' => trans('public.toast_delete_post_success'),
            'type' => 'success',
        ]);
        
    }

    public function updateLikeCounts(Request $request)
    {
        $post = ForumPost::find($request->id);
    
        // Update the likes or dislikes based on the type
        if ($request->type === 'like') {
            $post->total_likes_count += $request->count;
        } elseif ($request->type === 'dislike') {
            $post->total_dislikes_count += $request->count;
        }
    
        // Save the updated post
        $post->save();
    
        return back();
    }

}
