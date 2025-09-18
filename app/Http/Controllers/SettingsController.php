<?php

namespace App\Http\Controllers;

use App\Models\TradingPlatform;
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

    public function getTradingPlatforms(Request $request)
    {
        if ($request->has('lazyEvent')) {
            $data = json_decode($request->only(['lazyEvent'])['lazyEvent'], true);

            $query = TradingPlatform::query();

            if ($data['sortField'] && $data['sortOrder']) {
                $order = $data['sortOrder'] == 1 ? 'asc' : 'desc';
                $query->orderBy($data['sortField'], $order);
            } else {
                $query->orderByDesc('created_at');
            }

            $platforms = $query->paginate($data['rows']);

            return response()->json([
                'success' => true,
                'data' => $platforms,
            ]);
        }

        return response()->json(['success' => false, 'data' => []]);
    }

    public function updateTradingPlatform(Request $request, $id)
    {
        $platform = TradingPlatform::find($id);

        if (!$platform) {
            return response()->json([
                'toast_title' => trans('public.platform_not_found'),
                'type' => 'error'
            ], 400);
        }

        $platform->status = $request->status;
        $platform->save();

        return response()->json([
            'toast_title' => $platform->status == 'active' ? trans("public.toast_setting_activated") : trans("public.toast_setting_deactivated"),
            'type' => 'success',
            'platform' => $platform,
        ]);
    }
}
