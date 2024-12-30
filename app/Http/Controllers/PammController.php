<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Group;
use App\Models\AssetMaster;
use App\Models\AssetRevoke;
use Illuminate\Http\Request;
use App\Models\TradingAccount;
use Illuminate\Support\Carbon;
use App\Models\AssetSubscription;
use App\Models\AssetMasterToGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\DropdownOptionService;
use App\Models\AssetMasterUserFavourite;
use Illuminate\Support\Facades\Validator;
use App\Models\AssetMasterProfitDistribution;

class PammController extends Controller
{
    public function pamm_allocate()
    {
        return Inertia::render('PammAllocate/PammAllocate');
    }

    public function getMasters(Request $request)
    {
        // fetch limit with default
        $limit = $request->input('limit', 12);

        // Fetch parameter from request
        $search = $request->input('search', '');
        $sortType = $request->input('sortType');
        $groups = $request->input('groups');
        $adminUser = $request->input('adminUser', '');
        $tag = $request->input('tag', '');
        $status = $request->input('status', '');

        // Fetch paginated masters
        $mastersQuery = AssetMaster::query();

        // Apply search parameter to multiple fields
        if (!empty($search)) {
            $mastersQuery->where(function($query) use ($search) {
                $query->where('trader_name', 'LIKE', "%$search%")
                    ->orWhere('asset_name', 'LIKE', "%$search%")
                    ->orWhere('total_investors', 'LIKE', "%$search%")
                    ->orWhere('total_fund', 'LIKE', "%$search%")
                    ->orWhere('performance_fee', 'LIKE', "%$search%")
                    ->orWhere('total_gain', 'LIKE', "%$search%")
                    ->orWhere('monthly_gain', 'LIKE', "%$search%")
                    ->orWhere('latest_profit', 'LIKE', "%$search%");
            });
        }

        // Apply sorting dynamically
        if (in_array($sortType, ['latest', 'popular', 'largest_fund', 'most_investors'])) {
            switch ($request->sortType) {
                case 'latest':
                    $mastersQuery->orderBy('created_at', 'desc');
                    break;

                case 'popular':
                    $mastersQuery->leftJoin('asset_master_user_favourites', 'asset_masters.id', '=', 'asset_master_user_favourites.asset_master_id')
                        ->select('asset_masters.*', DB::raw('COUNT(asset_master_user_favourites.id) as total_like_count'))
                        ->groupBy('asset_masters.id')
                        ->orderByDesc('total_likes_count');
                    break;

                case 'largest_fund':
                    $mastersQuery->leftJoin('asset_subscriptions', function ($join) {
                        $join->on('asset_masters.id', '=', 'asset_subscriptions.asset_master_id')
                            ->where('asset_subscriptions.status', 'ongoing');
                    })
                        ->select('asset_masters.*',
                            DB::raw('total_fund + COALESCE(SUM(asset_subscriptions.investment_amount), 0) AS total_fund_combined')
                        )
                        ->groupBy('asset_masters.id', 'total_fund')
                        ->orderBy(DB::raw('total_fund + COALESCE(SUM(asset_subscriptions.investment_amount), 0)'), 'desc');
                    break;

                case 'most_investors':
                    $mastersQuery->leftJoin('asset_subscriptions', function ($join) {
                        $join->on('asset_masters.id', '=', 'asset_subscriptions.asset_master_id')
                            ->where('asset_subscriptions.status', 'ongoing');
                    })
                        ->select('asset_masters.*',
                            DB::raw('total_investors + COALESCE(COUNT(asset_subscriptions.id), 0) AS total_investors_combined')
                        )
                        ->groupBy('asset_masters.id', 'total_investors')
                        ->orderBy(DB::raw('total_investors + COALESCE(COUNT(asset_subscriptions.id), 0)'), 'desc');
                    break;

                default:
                    return response()->json(['error' => 'Invalid filter'], 400);
            }
        }

        // // Apply groups filter
         if (!empty($groups)) {
             if ($groups == 'public') {
                 $mastersQuery->where('type', 'public');
             } else {
                 $mastersQuery->whereHas('visible_to_groups', function ($query) use ($groups) {
                     $query->whereIn('group_id', [$groups]);
                 });
             }
         }

        // // Apply adminUser filter
        // if (!empty($adminUser)) {
        //     dd($request->all());
        // }

        // // Apply tag filter
         if (!empty($tag)) {
             switch ($tag) {
                 case 'no_min_investment':
                     $mastersQuery->where('minimum_investment', 0);
                     break;

                 case 'lock_free':
                     $mastersQuery->where('minimum_investment_period', 0);
                     break;

                 case 'zero_fee':
                     $mastersQuery->where('performance_fee', 0);
                     break;

                 default:
                     return response()->json(['error' => 'Invalid filter'], 400);
             }
         }

        // Apply status filter
        if (!empty($status)) {
            $mastersQuery->where('status', $status);
        }

        // Get total count of masters
        $totalRecords = $mastersQuery->count();

        // Fetch paginated results
        $masters = $mastersQuery->paginate($limit);

        // Format masters
        $formattedMasters = $masters->map(function($master) {

            $group_names = null;
            $group_ids = $master->visible_to_groups()
                ->pluck('group_id')
                ->toArray();

            if ($master->type == 'private') {
                $groups = Group::whereIn('id', $group_ids)->get();

                $group_names = $groups->pluck('name')->implode(', ');
            }

            $asset_subscription = AssetSubscription::where('asset_master_id', $master->id)
                ->where('status', 'ongoing');

            $total_real_fund = TradingAccount::whereIn('meta_login', $asset_subscription->pluck('meta_login')->toArray())->sum('balance');

            // Get the last profit distribution before today
            $last_profit_distribution = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
                ->whereDate('profit_distribution_date', '<', today())
                ->orderByDesc('profit_distribution_date')
                ->first();

            $asset_distribution_counts = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
                ->whereDate('profit_distribution_date', '>=', today())
                ->count();

            // Initialize the profit with the master's latest profit as a fallback
            $profit = $master->latest_profit;

            // If there's a last profit distribution, update the profit to that value
            if ($last_profit_distribution) {
                $profit = $last_profit_distribution->profit_distribution_percent;
            }

            // Calculate the monthly gain for the current month
            $monthly_gain = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
                ->whereMonth('profit_distribution_date', Carbon::now()->month)
                ->whereDate('profit_distribution_date', '<', Carbon::today())
                ->sum('profit_distribution_percent');

            // Calculate the cumulative gain until yesterday, excluding the current month
            $cumulative_gain_until_yesterday = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
                ->whereMonth('profit_distribution_date', '<', Carbon::now()->month)
                ->whereDate('profit_distribution_date', '<', Carbon::today())
                ->sum('profit_distribution_percent');

            if ($master->created_at->isCurrentMonth()) {
                $monthly_gain += $master->monthly_gain;
                $total_gain = $master->total_gain + $monthly_gain;
            } else {
                $total_gain = $master->total_gain + $cumulative_gain_until_yesterday;
            }

            $userFavourites = $master->asset_user_favourites->count();

            return [
                'id' => $master->id,
                'asset_name' => $master->asset_name,
                'trader_name' => $master->trader_name,
                'total_real_investors' => $asset_subscription->count(),
                'total_investors' => $master->total_investors,
                'total_real_fund' => $total_real_fund,
                'total_fund' => $master->total_fund,
                'minimum_investment' => $master->minimum_investment,
                'minimum_investment_period' => $master->minimum_investment_period,
                'performance_fee' => $master->performance_fee,
                'penalty_fee' => $master->penalty_fee,
                'total_gain' => $total_gain,
                'monthly_gain' => $monthly_gain,
                'latest_profit' => $profit,
                'master_profile_photo' => $master->getFirstMediaUrl('master_profile_photo'),
                'total_likes_count' => $master->total_likes_count + $userFavourites,
                'status' => $master->status,
                'started_at' => date_format($master->started_at, 'Y/m/d'),
                'visible_to' => $master->type,
                'group_names' => $group_names,
                'asset_distribution_counts' => $asset_distribution_counts,
                'last_distribution_date' => $master->asset_distributions()->latest('profit_distribution_date')->first()->profit_distribution_date,
            ];
        });

        return response()->json([
            'masters' => $formattedMasters,
            'totalRecords' => $totalRecords,
            'currentPage' => $masters->currentPage(),
        ]);
    }

    public function getMetrics()
    {
        // current month
        $endOfMonth = Carbon::now()->endOfMonth();

        // last month
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $asset_subscription_query = AssetSubscription::where('status', 'ongoing');

        // current month assets
        $current_month_assets = (clone $asset_subscription_query)
            ->where('status', 'ongoing')
            ->whereDate('created_at', '<=', $endOfMonth)
            ->sum('investment_amount');

        // current month investors
        $current_month_investors = (clone $asset_subscription_query)
            ->where('status', 'ongoing')
            ->whereDate('created_at', '<=', $endOfMonth)
            ->count();

        // last month assets
        $last_month_assets = (clone $asset_subscription_query)
            ->where('status', 'ongoing')
            ->whereDate('created_at', '<=', $endOfLastMonth)
            ->sum('investment_amount');

        // last month investors
        $last_month_investors = (clone $asset_subscription_query)
            ->where('status', 'ongoing')
            ->whereDate('created_at', '<=', $endOfLastMonth)
            ->count();

        // comparison % of assets vs last month
        $last_month_asset_comparison = $last_month_assets > 0
            ? (($current_month_assets - $last_month_assets) / $last_month_assets) * 100
            : ($current_month_assets > 0 ? 100 : 0);

        // comparison % of investors vs last month
        $last_month_investor_comparison = $current_month_investors - $last_month_investors;

        // Get and format top 3 masters by total fund
        $topThreeMasters = AssetMaster::get()
            ->map(function ($master) use ($endOfMonth) {
                $asset_subscriptions_fund = AssetSubscription::where('status', 'ongoing')
                    ->where('asset_master_id', $master->id)
                    ->whereDate('created_at', '<=', $endOfMonth)
                    ->sum('investment_amount');

                return [
                    'id' => $master->id,
                    'asset_name' => $master->asset_name,
                    'trader_name' => $master->trader_name,
                    'total_fund' => $asset_subscriptions_fund,
                    'minimum_investment' => $master->minimum_investment,
                    'minimum_investment_period' => $master->minimum_investment_period,
                    'performance_fee' => $master->performance_fee,
                    'total_gain' => $master->total_gain,
                    'monthly_gain' => $master->monthly_gain,
                    'latest_profit' => $master->latest_profit,
                    'status' => $master->status,
                    'created_at' => $master->created_at,
                    'visible_to' => $master->type,
                ];
            })
            ->sortByDesc('total_fund')
            ->take(3)
            ->values();

        return response()->json([
            'currentAssets' => $current_month_assets,
            'lastMonthAssetComparison' => $last_month_asset_comparison,
            'currentInvestors' => $current_month_investors,
            'lastMonthInvestorComparison' => $last_month_investor_comparison,
            'topThreeMasters' => $topThreeMasters,
        ]);
    }

    public function getOptions()
    {
        return response()->json([
            'groupsOptions' => (new DropdownOptionService())->getGroups(),
        ]);
    }

    public function getProfitLoss(Request $request)
    {
        $profit = AssetMasterProfitDistribution::whereDate('profit_distribution_date', $request->date)
            ->where('profit_distribution_percent', '>', 0)
            ->sum('profit_distribution_percent');

        $loss = AssetMasterProfitDistribution::whereDate('profit_distribution_date', $request->date)
            ->where('profit_distribution_percent', '<', 0)
            ->sum('profit_distribution_percent');

         return response()->json([
             'profit' => $profit,
             'loss' => abs($loss),
         ]);
    }

    public function validateStep(Request $request)
    {
        $rules = [
            'pamm_name' => ['required', 'regex:/^[a-zA-Z0-9\p{Han}. ]+$/u', 'max:255'],
            'trader_name' => ['required', 'regex:/^[a-zA-Z0-9\p{Han}. ]+$/u', 'max:255'],
            'started_at' => ['required'],
            'groups' => ['required'],
            'total_investors' => ['required', 'integer'],
            'total_fund' => ['required', 'numeric'],
            'master_profile_photo' => ['nullable', 'image'],
        ];

        $attributes = [
            'pamm_name'=> trans('public.pamm_name'),
            'trader_name'=> trans('public.trader_name'),
            'started_at'=> trans('public.created_date'),
            'groups'=> trans('public.group'),
            'total_investors'=> trans('public.total_investors'),
            'total_fund'=> trans('public.total_fund'),
            'master_profile_photo' => trans('public.image'),
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->setAttributeNames($attributes);

        if ($request->step == 1) {
             $validator->validate();
        } elseif ($request->step == 2) {
            $additionalRules = [
                'min_investment' => ['required'],
                'min_investment_period' => ['required'],
                'performance_fee' => ['required'],
                'penalty_fee' => ['required'],
                'total_gain' => ['required'],
                'monthly_gain' => ['required'],
                'latest' => ['required'],
            ];
            $rules = array_merge($rules, $additionalRules);

            $additionalAttributes = [
                'min_investment'=> trans('public.min_investment'),
                'min_investment_period'=> trans('public.min_investment_period'),
                'performance_fee'=> trans('public.performance_fee'),
                'penalty_fee'=> trans('public.penalty_fee'),
                'total_gain'=> trans('public.total_gain'),
                'monthly_gain'=> trans('public.monthly_gain'),
                'latest'=> trans('public.latest'),
            ];
            $attributes = array_merge($attributes, $additionalAttributes);

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($attributes);
            $validator->validate();
        }

        return back();
    }

    public function create_asset_master(Request $request)
    {
        // Define validation rules and attributes
        $rules = [
            'expected_gain' => ['nullable', 'numeric'],
        ];

        $attributes = [
            'expected_gain'=> trans('public.expected_gain'),
        ];

        // Validate the request data
        $validator = Validator::make($request->all(), $rules);
        $validator->setAttributeNames($attributes);
        $validator->validate();

        // Determine the value of $visible based on the groups field
        $groups = $request->input('groups');

        $groupsDatas = [];
        if (in_array('public', $groups)) {
            $visible = 'public';
        } else {
            $visible = 'private';
            // Ensure $groups is an array
            $groupArray = is_array($groups) ? $groups : [];

            // Fetch groups with IDs in the $groupArray
            $groupsDatas = Group::whereIn('id', $groupArray)->get();
        }

        try {
             $asset_master = AssetMaster::create([
                 'asset_name' => $request->pamm_name,
                 'trader_name' => $request->trader_name,
                 'category' => 'pamm',
                 'type' => $visible,
                 'started_at' => $request->started_at,
                 'total_investors' => $request->total_investors,
                 'total_fund' => $request->total_fund,
                 'minimum_investment' => $request->min_investment,
                 'minimum_investment_period' => $request->min_investment_period,
                 'performance_fee' => $request->performance_fee,
                 'penalty_fee' => $request->penalty_fee,
                 'total_gain' => $request->total_gain,
                 'monthly_gain' => $request->monthly_gain,
                 'latest_profit' => $request->latest,
                 'profit_generation_mode' => $request->profit_generation_mode,
                 'expected_gain_profit' => $request->expected_gain,
                 'status' => 'active',
                 'edited_by' => Auth::id(),
             ]);

             if ($asset_master->type == 'private') {
                 foreach ($groupsDatas as $group) {
                     AssetMasterToGroup::create([
                         'asset_master_id' => $asset_master->id,
                         'group_id' => $group->id,
                     ]);
                 }
             }

             $daily_profits = $request->daily_profits;
             if ($daily_profits) {
                 foreach ($daily_profits as $daily_profit) {
                     $date = \DateTime::createFromFormat('d/m', $daily_profit['date']);

                     if ($date) {
                         $date->setDate(date('Y'), $date->format('m'), $date->format('d'));

                         AssetMasterProfitDistribution::create([
                             'asset_master_id' => $asset_master->id,
                             'profit_distribution_date' => $date->format('Y-m-d'),
                             'profit_distribution_percent' => $daily_profit['daily_profit'],
                         ]);
                     }
                 }
             }

            if ($request->hasFile('master_profile_photo')) {
                $asset_master->clearMediaCollection('master_profile_photo');
                $asset_master->addMedia($request->master_profile_photo)->toMediaCollection('master_profile_photo');
            }

            // Redirect with success message
            return redirect()->back()->with('toast', [
                "title" => trans('public.toast_create_asset_master_success'),
                "type" => "success"
            ]);
        } catch (\Exception $e) {
            // Log the exception and show a generic error message
            Log::error('Error creating asset master: '.$e->getMessage());

            return redirect()->back()->with('toast', [
                'title' => trans('public.toast_create_asset_master_error'),
                'type' => 'error'
            ]);
        }
    }

    public function edit_asset_master(Request $request)
    {
        // Define validation rules and attributes
        $rules = [
            'pamm_name' => ['required', 'regex:/^[a-zA-Z0-9\p{Han}. ]+$/u', 'max:255'],
            'trader_name' => ['required', 'regex:/^[a-zA-Z0-9\p{Han}. ]+$/u', 'max:255'],
            'started_at' => ['required', 'date'],
            'groups' => ['required'],
            'total_investors' => ['nullable', 'integer'],
            'total_fund' => ['nullable', 'numeric'],
            'min_investment' => ['nullable', 'numeric'],
            'min_investment_period' => ['required', 'integer'],
            'performance_fee' => ['required', 'numeric'],
            'penalty_fee' => ['required', 'numeric'],
            'master_profile_photo' => ['nullable', 'image'],
        ];

        $attributes = [
            'pamm_name' => trans('public.pamm_name'),
            'trader_name' => trans('public.trader_name'),
            'started_at' => trans('public.created_date'),
            'groups' => trans('public.group'),
            'total_investors' => trans('public.total_investors'),
            'total_fund' => trans('public.total_fund'),
            'min_investment' => trans('public.min_investment'),
            'min_investment_period' => trans('public.min_investment_period'),
            'performance_fee' => trans('public.profit_sharing'),
            'penalty_fee' => trans('public.penalty_fee'),
            'master_profile_photo' => trans('public.image'),
        ];

        // Validate the request data
        $validator = Validator::make($request->all(), $rules);
        $validator->setAttributeNames($attributes);
        $validator->validate();

        // Determine the value of $visible based on the groups field
        $groups = $request->input('groups');

        $groupsDatas = [];
        if (in_array('public', $groups)) {
            $visible = 'public';
        } else {
            $visible = 'private';
            // Ensure $groups is an array
            $groupArray = is_array($groups) ? $groups : [];

            // Fetch groups with IDs in the $groupArray
            $groupsDatas = Group::whereIn('id', $groupArray)->get();
        }

        try {
            // Find the asset master by ID
            $assetMaster = AssetMaster::findOrFail($request->id);

            $started_at = $request->started_at;
            // Update the asset master record
             $assetMaster->update([
                 'asset_name' => $request->pamm_name,
                 'trader_name' => $request->trader_name,
                 'type' => $visible,
                 'started_at' => Carbon::parse($started_at)->addDay()->startOfDay(),
                 'total_investors' => $request->total_investors,
                 'total_fund' => $request->total_fund,
                 'min_investment' => $request->min_investment,
                 'min_investment_period' => $request->min_investment_period,
                 'performance_fee' => $request->performance_fee,
                 'penalty_fee' => $request->penalty_fee,
                 'edited_by' => Auth::id(),
             ]);

            if ($assetMaster->type == 'private') {

                $currentVisibleGroups = $assetMaster->visible_to_groups;
                foreach ($currentVisibleGroups as $currentGroup) {
                    $currentGroup->delete();
                }

                foreach ($groupsDatas as $group) {
                    AssetMasterToGroup::create([
                        'asset_master_id' => $assetMaster->id,
                        'group_id' => $group->id,
                    ]);
                }
            }

            if ($request->hasFile('master_profile_photo')) {
                $assetMaster->clearMediaCollection('master_profile_photo');
                $assetMaster->addMedia($request->master_profile_photo)->toMediaCollection('master_profile_photo');
            }

            // Redirect with success message
            return redirect()->back()->with('toast', [
                "title" => "You've successfully updated the asset master!",
                "type" => "success"
            ]);
        } catch (\Exception $e) {
            // Log the exception and show a generic error message
            Log::error('Error updating asset master: '.$e->getMessage());

            return redirect()->back()->with('toast', [
                'title' => 'There was an error updating the asset master.',
                'type' => 'error'
            ]);
        }
    }

    public function update_asset_master_status(Request $request)
    {
        $assetMaster = AssetMaster::find($request->id);

        $assetMaster->status = $assetMaster->status == 'active' ? 'inactive' : 'active';
        $assetMaster->save();

        return back()->with('toast', [
            'title' => $assetMaster->status == 'active' ? trans("public.toast_asset_master_show") : trans("public.toast_asset_master_hide"),
            'type' => 'success',
        ]);
    }

    public function disband(Request $request)
    {
        $assetMaster = AssetMaster::find($request->id);

        if ($request->action == 'delete') {
            $assetMaster->delete();
        } else {
            $assetMaster->asset_subscriptions()->delete();
            $assetMaster->total_fund = 0;
            $assetMaster->total_investors = 0;
            $assetMaster->save();

            $assetMaster->delete();
        }

        return back()->with('toast', [
            'title' => trans("public.toast_asset_master_disband"),
            'type' => 'success',
        ]);
    }

    public function updateLikeCounts(Request $request)
    {
        $assetMaster = AssetMaster::find($request->master_id);

        $assetMaster->total_likes_count += $request->likeCounts;
        $assetMaster->save();

        return back();
    }

    public function getJoiningPammAccountsData(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $query = AssetSubscription::where('asset_master_id', $request->asset_master_id)
            ->whereNot('status', 'revoked');

        if ($startDate && $endDate) {
            $start_date = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            $query->whereBetween('created_at', [$start_date, $end_date]);
        }

        $joiningPammAccounts = $query
            ->latest()
            ->get()
            ->map(function ($item) {

                if ($item->status == 'ongoing') {
                    $displayStatus = $item->matured_at ? intval(now()->diffInDays($item->matured_at)) : null;
                } else {
                    $displayStatus = $item->status;
                }

                return [
                    'id' => $item->id,
                    'user_profile_photo' => $item->user->getFirstMediaUrl('profile_photo'),
                    'user_name' => $item->user->name,
                    'user_email' => $item->user->email,
                    'join_date' => $item->created_at,
                    'meta_login' => $item->meta_login,
                    'balance' => $item->trading_account->balance,
                    'status' => $item->status,
                    'remaining_days' => $displayStatus,
                    'investment_periods' => $item->investment_periods
                ];
            });

        return response()->json([
            'joiningPammAccounts' => $joiningPammAccounts,
            'totalInvestmentAmount' => $joiningPammAccounts->sum('balance'),
        ]);
    }

    public function getRevokePammAccountsData(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $query = AssetRevoke::where('asset_master_id', $request->asset_master_id)
            ->where('status', 'revoked');

        if ($startDate && $endDate) {
            $start_date = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            $query->whereBetween('created_at', [$start_date, $end_date]);
        }

        $revokePammAccounts = $query
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_profile_photo' => $item->user->getFirstMediaUrl('profile_photo'),
                    'user_name' => $item->user->name,
                    'user_email' => $item->user->email,
                    'revoked_date' => $item->approval_at,
                    'meta_login' => $item->meta_login,
                    'penalty_fee' => $item->penalty_fee,
                ];
            });

        return response()->json([
            'revokePammAccounts' => $revokePammAccounts,
            'totalPenaltyFee' => $revokePammAccounts->sum('penalty_fee'),
        ]);
    }

    public function getPammAccountsDataCount(Request $request)
    {
        $assetMasterId = $request->input('asset_master_id');

        // Get the count of AssetSubscription records that are not revoked
        $joiningCount = AssetSubscription::where('asset_master_id', $assetMasterId)
            ->where('status', '!=', 'revoked')
            ->count();

        // Get the count of AssetRevoke records that are revoked
        $revokeCount = AssetRevoke::where('asset_master_id', $assetMasterId)
            ->where('status', 'revoked')
            ->count();

        return response()->json([
            'joiningCount' => $joiningCount,
            'revokeCount' => $revokeCount
        ]);
    }

    public function addProfitDistribution(Request $request)
    {
        $asset_master = AssetMaster::find($request->id);

        $daily_profits = $request->daily_profits;
        if ($daily_profits) {
            foreach ($daily_profits as $daily_profit) {
                $date = \DateTime::createFromFormat('d/m', $daily_profit['date']);

                if ($date) {
                    $date->setDate(date('Y'), $date->format('m'), $date->format('d'));

                    AssetMasterProfitDistribution::create([
                        'asset_master_id' => $asset_master->id,
                        'profit_distribution_date' => $date->format('Y-m-d'),
                        'profit_distribution_percent' => $daily_profit['daily_profit'],
                    ]);
                }
            }
        }

        return redirect()->back()->with('toast', [
            "title" => trans('public.toast_allocate_daily_profit_success'),
            "type" => "success"
        ]);
    }

    public function getMasterMonthlyProfit(Request $request)
    {
        $dateParts = explode('/', $request->input('month'));
        $month = $dateParts[0];
        $year = $dateParts[1];

        // Apply month and year filtering if provided
        $filteredQuery = AssetMasterProfitDistribution::where('asset_master_id', $request->master_id)
            ->when($request->filled('month'), function ($query) use ($month, $year) {
                $query->whereYear('profit_distribution_date', $year)
                    ->whereMonth('profit_distribution_date', $month);
            });

        // Generate chart results using the filtered query
        $chartResults = $filteredQuery->select(
            DB::raw('DAY(profit_distribution_date) as day'),
            DB::raw('SUM(profit_distribution_percent) as pamm_return')
        )
            ->groupBy('day')
            ->get();

        $monthlyGain = $chartResults->sum('pamm_return');

        // Initialize the chart data structure
        $chartData = [
            'labels' => array_map(function($day) use ($month, $year) {
                return sprintf('%02d/%02d', $day, $month);
            }, range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year))), // Generate an array of labels in 'day/month' format
            'datasets' => [],
        ];

        $dataset = [
            'label' => trans('public.profit'),
            'data' => array_map(function ($label) use ($chartResults) {
                // Extract the day part from the label (formatted as 'day/month')
                $day = (int) explode('/', $label)[0];
                return $chartResults->firstWhere('day', $day)->pamm_return ?? 0;
            }, $chartData['labels']),
            'backgroundColor' => array_map(function ($label) use ($chartResults) {
                // Extract the day part from the label (formatted as 'day/month')
                $day = (int) explode('/', $label)[0];
                $pammReturn = $chartResults->firstWhere('day', $day)->pamm_return ?? 0;
                return $pammReturn > 0 ? '#06D001' : '#FF2D58';
            }, $chartData['labels']),
            'pointStyle' => false,
            'fill' => true,
        ];

        $chartData['datasets'][] = $dataset;

        return response()->json([
            'chartData' => $chartData,
            'monthlyGain' => number_format($monthlyGain,2 )
        ]);
    }
}
