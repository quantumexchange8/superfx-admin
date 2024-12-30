<?php

namespace App\Console\Commands;

use App\Models\BillboardBonus;
use App\Models\BillboardProfile;
use App\Models\TradeBrokerHistory;
use App\Models\TradingAccount;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DistributeSalesBonusCommand extends Command
{
    protected $signature = 'distribute:sales-bonus';

    protected $description = 'Calculate and distribute sales bonus';

    public function handle(): void
    {
        $billboardProfiles = BillboardProfile::whereDate('next_payout_at', today())
            ->get();

        foreach ($billboardProfiles as $profile) {
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

            $bonus = BillboardBonus::create([
                'user_id' => $profile->user_id,
                'billboard_profile_id' => $profile->id,
                'target_amount' => $profile->target_amount,
                'achieved_amount' => $achieved_amount,
                'achieved_percentage' => $achieved_percentage,
                'bonus_rate' => $profile->bonus_rate,
                'bonus_amount' => $achieved_percentage >= $profile->bonus_calculation_threshold ? $bonus_amount : 0,
            ]);

            if ($bonus->bonus_amount > 0) {
                $bonus_wallet = Wallet::where('user_id', $profile->user_id)
                    ->where('type', 'bonus_wallet')
                    ->first();

                Transaction::create([
                    'user_id' => $profile->user_id,
                    'category' => 'bonus_wallet',
                    'transaction_type' => 'bonus',
                    'to_wallet_id' => $bonus_wallet->id,
                    'transaction_number' => RunningNumberService::getID('transaction'),
                    'amount' => $bonus->bonus_amount,
                    'transaction_charges' => 0,
                    'transaction_amount' => $bonus->bonus_amount,
                    'old_wallet_amount' => $bonus_wallet->balance,
                    'new_wallet_amount' => $bonus_wallet->balance += $bonus->bonus_amount,
                    'status' => 'successful',
                    'approved_at' => now(),
                ]);

                $bonus_wallet->save();
            }

            switch ($profile->calculation_period) {
                case 'every_sunday':
                    $nextPayout = Carbon::now()->next(Carbon::SUNDAY)->startOfDay();
                    $profile->update([
                        'next_payout_at' => $nextPayout
                    ]);

                    $bonus->update([
                        'bonus_month' => $nextPayout->subWeek()->month
                    ]);
                    break;

                case 'every_second_sunday':
                    $nextPayout = Carbon::now()->next(Carbon::SUNDAY)->addWeek()->startOfDay();
                    $profile->update([
                        'next_payout_at' => $nextPayout
                    ]);

                    $bonus->update([
                        'bonus_month' => $nextPayout->subWeeks(2)->month
                    ]);
                    break;

                case 'first_sunday_of_every_month':
                    $nextPayout = Carbon::now()->startOfMonth()->addMonth()->firstOfMonth(Carbon::SUNDAY)->startOfDay();
                    $profile->update([
                        'next_payout_at' => $nextPayout
                    ]);

                    $bonus->update([
                        'bonus_month' => $nextPayout->subMonth()->month
                    ]);
                    break;

                default:
                    $nextPayout = Carbon::now()->startOfMonth()->addMonth()->firstOfMonth(Carbon::SUNDAY)->startOfDay();
                    $profile->update([
                        'next_payout_at' => $nextPayout
                    ]);

                    $bonus->update([
                        'bonus_month' => $nextPayout->subMonth()->month
                    ]);
            }
        }
    }
}
