<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/Settings');
    }

    public function getSiteSettings(Request $request)
    {
        $query = SiteSetting::query();

        // Sorting
        if ($request->has('sortField')) {
            $direction = $request->sortOrder == 1 ? 'asc' : 'desc';
            $query->orderBy($request->sortField, $direction);
        } else {
            $query->orderBy('id', 'desc');
        }

        // Pagination
        $perPage = 10;
        $page = $request->page ?? 1;

        $settings = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $settings
        ]);
    }

    public function updateSiteSettingsStatus($id)
    {
        $siteSetting = SiteSetting::findOrFail($id);
        $siteSetting->status = $siteSetting->status === 'active' ? 'inactive' : 'active';
        $siteSetting->save();

        return back()->with('toast', [
            'title' => $siteSetting->status == 'active' ? trans("public.toast_setting_activated") : trans("public.toast_setting_deactivated"),
            'type' => 'success',
        ]);
    }
}
