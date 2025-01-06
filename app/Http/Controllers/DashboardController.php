<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\ForumPost;
use App\Models\AccountType;
use App\Models\AssetMaster;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TradingAccount;
// use App\Services\CTraderService;
use App\Models\TradeRebateSummary;
use App\Services\DropdownOptionService;
use App\Models\AssetMasterProfitDistribution;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard/Dashboard', [
            'postCounts' => ForumPost::count(),
        ]);
    }

    public function getPendingCounts()
    {
        $pendingWithdrawals = Transaction::whereNot('category', 'bonus_wallet')
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'processing')
            ->count();

        $pendingBonusWithdrawal = Transaction::where('category', 'bonus_wallet')
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'processing')
            ->count();

        $pendingKYC = User::where('kyc_status', 'pending')->count();

        $pendingPammAllocate = 0;

        $activeMasters = AssetMaster::where('status', 'active')->get();

        foreach ($activeMasters as $master) {
            $profitDistributionCount = AssetMasterProfitDistribution::where('asset_master_id', $master->id)
                ->whereDate('profit_distribution_date', '>=', today())
                ->count();

            if ($profitDistributionCount <= 3) {
                $pendingPammAllocate += 1;
            }
        }

        return response()->json([
            'pendingWithdrawals' => $pendingWithdrawals,
            'pendingPammAllocate' => $pendingPammAllocate,
            'pendingKYC' => $pendingKYC,
            'pendingBonusWithdrawal' => $pendingBonusWithdrawal,
        ]);
    }

    public function getAccountData()
    {
        $from = '2024-01-01T00:00:00.000';
        $to = now()->format('Y-m-d\TH:i:s.v');

        // Standard Account and Premium Account group IDs
        $groupIds = AccountType::whereNotNull('account_group_id')
            ->pluck('account_group_id')
            ->toArray();

        // foreach ($groupIds as $groupId) {
        //     // Fetch data for each group ID
        //     $response = (new CTraderService)->getMultipleTraders($from, $to, $groupId);

        //     // Find the corresponding AccountType model
        //     $accountType = AccountType::where('account_group_id', $groupId)->first();

        //     // Initialize or reset group balance and equity
        //     $groupBalance = 0;
        //     $groupEquity = 0;

        //     $meta_logins = TradingAccount::where('account_type_id', $accountType->id)->pluck('meta_login')->toArray();

        //     // Assuming the response is an associative array with a 'trader' key
        //     if (isset($response['trader']) && is_array($response['trader'])) {
        //         foreach ($response['trader'] as $trader) {
        //             if (in_array($trader['login'], $meta_logins)) {
        //                 // Determine the divisor based on moneyDigits
        //                 $moneyDigits = isset($trader['moneyDigits']) ? (int)$trader['moneyDigits'] : 0;
        //                 $divisor = $moneyDigits > 0 ? pow(10, $moneyDigits) : 1; // 10^moneyDigits

        //                 // Adjust balance and equity based on the divisor
        //                 $groupBalance += $trader['balance'] / $divisor;
        //                 $groupEquity += $trader['equity'] / $divisor;
        //             }
        //         }

        //         // Update account group balance and equity
        //         $accountType->account_group_balance = $groupBalance;
        //         $accountType->account_group_equity = $groupEquity;
        //         $accountType->save();
        //     }
        // }

        // Recalculate total balance and equity from the updated account types
        $totalBalance = AccountType::sum('account_group_balance');
        $totalEquity = AccountType::sum('account_group_equity');

        // Return the total balance and total equity as a JSON response
        return response()->json([
            'totalBalance' => $totalBalance,
            'totalEquity' => $totalEquity,
        ]);
    }

    public function getPendingData()
    {
        $pendingWithdrawals = Transaction::whereNot('category', 'bonus_wallet')
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'processing');

        return response()->json([
            'pendingAmount' => $pendingWithdrawals->sum('transaction_amount'),
            'pendingCounts' => $pendingWithdrawals->count(),
        ]);
    }

    public function getAssetData(Request $request)
    {
        // Get the month from the request, default to the current month if not provided
        $monthYear = $request->query('month', date('m/Y'));
        [$month, $year] = explode('/', $monthYear);

        // Calculate total deposits
        $totalDeposit = Transaction::where('status', 'successful')
            ->whereIn('transaction_type', ['deposit', 'balance_in'])
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('transaction_amount');

        // Calculate total withdrawals
        $totalWithdrawal = Transaction::where('status', 'successful')
            ->whereIn('transaction_type', ['withdrawal', 'balance_out'])
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('transaction_amount');

        // Calculate total rebate payouts
        $totalRebatePayout = TradeRebateSummary::where('t_status', 'Approved')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->get()
            ->map(function ($record) {
                // Multiply volume by rebate for each record
                return $record->volume * $record->rebate;
            })
            ->sum(); // Sum up all the calculated values

        return response()->json([
            'totalDeposit' => $totalDeposit,
            'totalWithdrawal' => $totalWithdrawal,
            'totalRebatePayout' => $totalRebatePayout,
        ]);
    }

}
