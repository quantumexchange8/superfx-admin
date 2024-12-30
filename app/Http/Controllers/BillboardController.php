<?php

namespace App\Http\Controllers;

use App\Models\BillboardBonus;
use App\Models\BillboardProfile;
use App\Models\TradeBrokerHistory;
use App\Models\TradingAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class BillboardController extends Controller
{
    public function index()
    {
        $profileCount = BillboardProfile::count();

        return Inertia::render('Billboard/BillboardListing', [
            'profileCount' => $profileCount,
        ]);
    }

    public function getBonusProfiles(Request $request)
    {
        $bonusQuery = BillboardProfile::query();

        $search = $request->search;
        $sales_calculation_mode = $request->sales_calculation_mode;
        $sales_category = $request->sales_category;

        if (!empty($search)) {
            $bonusQuery->whereHas('user', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('id_number', 'like', '%' . $search . '%');
            });
        }

        if (!empty($sales_calculation_mode)) {
            $bonusQuery->where('sales_calculation_mode', $sales_calculation_mode);
        }

        if (!empty($sales_category)) {
            $bonusQuery->where('sales_category', $sales_category);
        }

        $totalRecords = $bonusQuery->count();

        $profiles = $bonusQuery->paginate($request->paginate);

        $formattedProfiles = $profiles->map(function($profile) {
            $bonus_amount = 0;
            $achieved_percentage = 0;
            $achieved_amount = 0;

            $today = Carbon::today();

            // Set start and end dates based on calculation period
            if ($profile->calculation_period == 'every_sunday') {
                // Start of the current week (Monday) and end of the current week (Sunday)
                $startDate = $today->copy()->startOfWeek();
                $endDate = $today->copy()->endOfWeek();
            } elseif ($profile->calculation_period == 'every_second_sunday') {
                // Start of the month
                $startDate = $today->copy()->startOfMonth();

                // Find the first Sunday of the month
                $firstSunday = $startDate->copy()->next('Sunday');

                // Find the second Sunday of the month
                $secondSunday = $firstSunday->copy()->addWeek();

                // If today is before or on the second Sunday, calculate until the day before the second Sunday
                if ($today->lessThan($secondSunday)) {
                    $endDate = $secondSunday->copy()->subDay()->endOfDay();
                } else {
                    // If today is after the second Sunday, set startDate to the second Sunday
                    $startDate = $secondSunday->copy();
                    $endDate = $today->copy()->endOfWeek(); // Or end of current week if needed
                }

            } elseif ($profile->calculation_period == 'first_sunday_of_every_month') {
                $startDate = $today->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
            } else {
                // Default to the entire current month if no calculation period is specified
                $startDate = $today->copy()->startOfMonth();
                $endDate = $today->copy()->endOfMonth();
            }

            if ($profile->sales_calculation_mode == 'personal_sales') {
                if ($profile->sales_category == 'gross_deposit') {
                    $gross_deposit = Transaction::where('user_id', $profile->user_id)
                        ->whereBetween('approved_at', [$startDate, $endDate])
                        ->where(function ($query) {
                            $query->where('transaction_type', 'deposit')
                                ->orWhere('transaction_type', 'balance_in');
                        })
                        ->where('status', 'successful')
                        ->sum('transaction_amount');

                    $achieved_percentage = ($gross_deposit / $profile->target_amount) * 100;
                    $bonus_amount = ($gross_deposit * $profile->bonus_rate) / 100;
                    $achieved_amount = $gross_deposit;
                } elseif ($profile->sales_category == 'net_deposit') {
                    $total_deposit = Transaction::where('user_id', $profile->user_id)
                        ->whereBetween('approved_at', [$startDate, $endDate])
                        ->where(function ($query) {
                            $query->where('transaction_type', 'deposit')
                                ->orWhere('transaction_type', 'balance_in');
                        })
                        ->where('status', 'successful')
                        ->sum('transaction_amount');

                    $total_withdrawal = Transaction::where('user_id', $profile->user_id)
                        ->whereBetween('approved_at', [$startDate, $endDate])
                        ->where(function ($query) {
                            $query->where('transaction_type', 'withdrawal')
                                ->orWhere('transaction_type', 'balance_out')
                                ->orWhere('transaction_type', 'rebate_out');
                        })
                        ->where('status', 'successful')
                        ->sum('transaction_amount');

                    $net_deposit = $total_deposit - $total_withdrawal;

                    $achieved_percentage = ($net_deposit / $profile->target_amount) * 100;
                    $bonus_amount = ($net_deposit * $profile->bonus_rate) / 100;
                    $achieved_amount = $net_deposit;
                } elseif ($profile->sales_category == 'trade_volume') {
                    $meta_logins = $profile->user->tradingAccounts->pluck('meta_login');

                    $trade_volume = TradeBrokerHistory::whereIn('meta_login', $meta_logins)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->sum('trade_lots');

                    $achieved_percentage = ($trade_volume / $profile->target_amount) * 100;
                    $bonus_amount = $achieved_percentage >= $profile->bonus_calculation_threshold ? $profile->bonus_rate : 0;
                    $achieved_amount = $trade_volume;
                }
            } elseif ($profile->sales_calculation_mode == 'group_sales') {
                $child_ids = $profile->user->getChildrenIds();
                $child_ids[] = $profile->user_id;

                if ($profile->sales_category == 'gross_deposit') {
                    $gross_deposit = Transaction::whereIn('user_id', $child_ids)
                        ->whereBetween('approved_at', [$startDate, $endDate])
                        ->where(function ($query) {
                            $query->where('transaction_type', 'deposit')
                                ->orWhere('transaction_type', 'balance_in');
                        })
                        ->where('status', 'successful')
                        ->sum('transaction_amount');

                    $achieved_percentage = ($gross_deposit / $profile->target_amount) * 100;
                    $bonus_amount = ($gross_deposit * $profile->bonus_rate) / 100;
                    $achieved_amount = $gross_deposit;
                } elseif ($profile->sales_category == 'net_deposit') {
                    $total_deposit = Transaction::whereIn('user_id', $child_ids)
                        ->whereBetween('approved_at', [$startDate, $endDate])
                        ->where(function ($query) {
                            $query->where('transaction_type', 'deposit')
                                ->orWhere('transaction_type', 'balance_in');
                        })
                        ->where('status', 'successful')
                        ->sum('transaction_amount');

                    $total_withdrawal = Transaction::whereIn('user_id', $child_ids)
                        ->whereBetween('approved_at', [$startDate, $endDate])
                        ->where(function ($query) {
                            $query->where('transaction_type', 'withdrawal')
                                ->orWhere('transaction_type', 'balance_out')
                                ->orWhere('transaction_type', 'rebate_out');
                        })
                        ->where('status', 'successful')
                        ->sum('transaction_amount');

                    $net_deposit = $total_deposit - $total_withdrawal;

                    $achieved_percentage = ($net_deposit / $profile->target_amount) * 100;
                    $bonus_amount = ($net_deposit * $profile->bonus_rate) / 100;
                    $achieved_amount = $net_deposit;
                } elseif ($profile->sales_category == 'trade_volume') {
                    $meta_logins = TradingAccount::whereIn('user_id', $child_ids)
                        ->get()
                        ->pluck('meta_login')
                        ->toArray();

                    $trade_volume = TradeBrokerHistory::whereIn('meta_login', $meta_logins)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->sum('trade_lots');

                    $achieved_percentage = ($trade_volume / $profile->target_amount) * 100;
                    $bonus_amount = $achieved_percentage >= $profile->bonus_calculation_threshold ? $profile->bonus_rate : 0;
                    $achieved_amount = $trade_volume;
                }
            }

            return [
                'id' => $profile->id,
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'profile_photo' => $profile->user->getFirstMediaUrl('profile_photo'),
                'sales_calculation_mode' => $profile->sales_calculation_mode == 'personal_sales' ? 'personal' : 'group',
                'bonus_badge' => $profile->sales_calculation_mode == 'personal_sales' ? 'gray' : 'info',
                'sales_category' => $profile->sales_category,
                'target_amount' => $profile->target_amount,
                'bonus_amount' => $bonus_amount,
                'bonus_rate' => $profile->bonus_rate,
                'bonus_calculation_threshold' => intval($profile->bonus_calculation_threshold),
                'achieved_percentage' => $achieved_percentage,
                'achieved_amount' => $achieved_amount,
                'calculation_period' => $profile->calculation_period,
            ];
        });

        return response()->json([
            'bonusProfiles' => $formattedProfiles,
            'totalRecords' => $totalRecords,
            'currentPage' => $profiles->currentPage(),
        ]);
    }

    public function getAgents()
    {
        $users = User::where('role', 'agent')
            ->where('status', 'active')
            ->select('id', 'name')
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'name' => $user->name,
                    'profile_photo' => $user->getFirstMediaUrl('profile_photo')
                ];
            });

        return response()->json([
            'users' => $users,
        ]);
    }

    public function createBonusProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agent' => ['required'],
            'sales_calculation_mode' => ['required'],
            'sales_category' => ['required'],
            'target_amount' => ['required'],
            'bonus' => ['required'],
            'bonus_calculation_threshold' => ['required'],
            'bonus_calculation_period' => ['required'],
        ])->setAttributeNames([
            'agent' => trans('public.agent'),
            'sales_calculation_mode' => trans('public.sales_calculation_mode'),
            'sales_category' => trans('public.sales_category'),
            'target_amount' => trans('public.set_target_amount'),
            'bonus' => trans('public.bonus'),
            'bonus_calculation_threshold' => trans('public.bonus_calculation_threshold'),
            'bonus_calculation_period' => trans('public.bonus_calculation_period'),
        ]);
        $validator->validate();

        $billboard_profile = BillboardProfile::create([
            'user_id' => $request->agent['value'],
            'sales_calculation_mode' => $request->sales_calculation_mode,
            'sales_category' => $request->sales_category,
            'target_amount' => $request->target_amount,
            'bonus_rate' => $request->bonus,
            'bonus_calculation_threshold' => $request->bonus_calculation_threshold,
            'calculation_period' => $request->bonus_calculation_period,
            'edited_by' => \Auth::id()
        ]);

        switch ($billboard_profile->calculation_period) {
            case 'every_sunday':
                $nextPayout = Carbon::now()->next(Carbon::SUNDAY)->startOfDay();
                $billboard_profile->update([
                    'next_payout_at' => $nextPayout
                ]);
                break;

            case 'every_second_sunday':
                $nextPayout = Carbon::now()->next(Carbon::SUNDAY)->addWeek()->startOfDay();
                $billboard_profile->update([
                    'next_payout_at' => $nextPayout
                ]);
                break;

            case 'first_sunday_of_every_month':
                $nextPayout = Carbon::now()->startOfMonth()->addMonth()->firstOfMonth(Carbon::SUNDAY)->startOfDay();
                $billboard_profile->update([
                    'next_payout_at' => $nextPayout
                ]);
                break;

            default:
                return response()->json(['error' => 'Invalid period'], 400);
        }

        $user = User::find($billboard_profile->user_id);

        if (empty($user->bonus_wallet)) {
            Wallet::create([
                'user_id' => $user->id,
                'type' => 'bonus_wallet',
                'address' => str_replace('AID', 'BW', $user->id_number),
                'balance' => 0
            ]);
        }

        return redirect()->back()->with('toast', [
            "title" => trans('public.toast_create_bonus_profile_success'),
            "type" => "success"
        ]);
    }

    public function getBonusWithdrawalData(Request $request)
    {
        $pendingWithdrawals = Transaction::with([
            'user:id,email,name',
            'payment_account:id,payment_account_name,account_no',
        ])
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'processing')
            ->where('category', 'bonus_wallet')
            ->latest()
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'created_at' => $transaction->created_at,
                    'user_name' => $transaction->user->name,
                    'user_email' => $transaction->user->email,
                    'user_profile_photo' => $transaction->user->getFirstMediaUrl('profile_photo'),
                    'from' => 'bonus_wallet',
                    'balance' => $transaction->from_wallet->balance,
                    'amount' => $transaction->amount,
                    'transaction_charges' => $transaction->transaction_charges,
                    'transaction_amount' => $transaction->transaction_amount,
                    'wallet_name' => $transaction->payment_account->payment_account_name,
                    'wallet_address' => $transaction->payment_account->account_no,
                ];
            });

        $totalAmount = $pendingWithdrawals->sum('amount');

        return response()->json([
            'pendingWithdrawals' => $pendingWithdrawals,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function editBonusProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_amount' => ['required'],
            'bonus' => ['required'],
            'bonus_calculation_threshold' => ['required'],
            'bonus_calculation_period' => ['required'],
        ])->setAttributeNames([
            'target_amount' => trans('public.set_target_amount'),
            'bonus' => trans('public.bonus'),
            'bonus_calculation_threshold' => trans('public.bonus_calculation_threshold'),
            'bonus_calculation_period' => trans('public.bonus_calculation_period'),
        ]);
        $validator->validate();

        $profile = BillboardProfile::find($request->profile_id);

        $profile->update([
            'target_amount' => $request->target_amount,
            'bonus_rate' => $request->bonus,
            'bonus_calculation_threshold' => $request->bonus_calculation_threshold,
            'edited_by' => \Auth::id()
        ]);

        if ($profile->calculation_period != $request->bonus_calculation_period) {
            switch ($profile->calculation_period) {
                case 'every_sunday':
                    $nextPayout = Carbon::now()->next(Carbon::SUNDAY)->startOfDay();
                    $profile->update([
                        'calculation_period' => $request->bonus_calculation_period,
                        'next_payout_at' => $nextPayout
                    ]);
                    break;

                case 'every_second_sunday':
                    $nextPayout = Carbon::now()->next(Carbon::SUNDAY)->addWeek()->startOfDay();
                    $profile->update([
                        'calculation_period' => $request->bonus_calculation_period,
                        'next_payout_at' => $nextPayout
                    ]);
                    break;

                case 'first_sunday_of_every_month':
                    $nextPayout = Carbon::now()->startOfMonth()->addMonth()->firstOfMonth(Carbon::SUNDAY)->startOfDay();
                    $profile->update([
                        'calculation_period' => $request->bonus_calculation_period,
                        'next_payout_at' => $nextPayout
                    ]);
                    break;

                default:
                    return response()->json(['error' => 'Invalid period'], 400);
            }
        }

        return redirect()->back()->with('toast', [
            "title" => trans('public.toast_update_bonus_profile_success'),
            "type" => "success"
        ]);
    }

    public function getStatementData(Request $request)
    {
        $bonusQuery = BillboardBonus::where('billboard_profile_id', $request->profile_id);

        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        if ($startDate && $endDate) {
            $start_date = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            $bonusQuery->whereBetween('created_at', [$start_date, $end_date]);
        }

        $bonuses = $bonusQuery
            ->get()
            ->map(function ($bonus) {
                return [
                    'id' => $bonus->id,
                    'target_amount' => $bonus->target_amount,
                    'achieved_amount' => $bonus->achieved_amount,
                    'bonus_rate' => $bonus->bonus_rate,
                    'bonus_amount' => $bonus->bonus_amount,
                    'created_at' => $bonus->created_at,
                ];
            });

        return response()->json([
            'bonuses' => $bonuses,
            'totalBonusAmount' => $bonusQuery->sum('bonus_amount'),
        ]);
    }

    public function getBonusWithdrawalHistories(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        $profile = BillboardProfile::find($request->profile_id);

        $query = Transaction::where('user_id', $profile->user_id)
            ->where('category', 'bonus_wallet')
            ->where('transaction_type', 'withdrawal')
            ->whereNot('status', 'processing');

        if ($startDate && $endDate) {
            $start_date = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
            $end_date = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

            $query->whereBetween('created_at', [$start_date, $end_date]);
        }

        $bonusWithdrawalHistories = $query
            ->latest()
            ->get()
            ->map(function ($transaction) {
                return [
                    'category' => $transaction->category,
                    'transaction_type' => $transaction->transaction_type,
                    'user_name' => $transaction->user->name,
                    'user_email' => $transaction->user->email,
                    'user_profile_photo' => $transaction->user->getFirstMediaUrl('profile_photo'),
                    'from_wallet_name' => $transaction->from_wallet->type,
                    'transaction_number' => $transaction->transaction_number,
                    'payment_account_id' => $transaction->payment_account_id,
                    'from_wallet_address' => $transaction->from_wallet_address,
                    'to_wallet_address' => $transaction->to_wallet_address,
                    'txn_hash' => $transaction->txn_hash,
                    'amount' => $transaction->amount,
                    'transaction_charges' => $transaction->transaction_charges,
                    'transaction_amount' => $transaction->transaction_amount,
                    'status' => $transaction->status,
                    'comment' => $transaction->comment,
                    'remarks' => $transaction->remarks,
                    'created_at' => $transaction->created_at,
                    'approved_at' => $transaction->approved_at,
                    'to_wallet_name' => $transaction->payment_account->payment_account_name ?? '-'
                ];
            });

        return response()->json([
            'bonusWithdrawalHistories' => $bonusWithdrawalHistories,
            'totalApprovedAmount' => $query->where('status', 'successful')->sum('amount'),
        ]);
    }
}
