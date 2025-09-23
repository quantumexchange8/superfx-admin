<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TradingPlatform\TradingPlatformFactory;
use Inertia\Inertia;
use App\Models\ForumPost;
use App\Models\AccountType;
use App\Models\AssetMaster;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TradingAccount;
use App\Services\MetaFourService;
use App\Models\TradeRebateSummary;
use App\Models\AssetMasterProfitDistribution;

class DashboardController extends Controller
{
    public function index()
    {
        $totalBalance = AccountType::sum('account_group_balance');
        $totalEquity = AccountType::sum('account_group_equity');

        return Inertia::render('Dashboard/Dashboard', [
            'postCounts' => ForumPost::count(),
            'totalAccountBalance' => (float) $totalBalance,
            'totalAccountEquity' => (float) $totalEquity,
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
        // Get all account types
        $accountTypes = AccountType::with('trading_platform:id,slug')
            ->where('status', 'active')
            ->get();

        // meta_logins grouped by account_type_id
        $tradingAccountsByType = TradingAccount::select(['account_type_id', 'meta_login'])
            ->whereIn('account_type_id', $accountTypes->pluck('id'))
            ->get()
            ->groupBy('account_type_id');

        $totalBalance = 0;
        $totalEquity = 0;

        foreach ($accountTypes as $type) {
            $service = TradingPlatformFactory::make($type->trading_platform->slug);

            $accounts = $service->getAccountByGroup($type->account_group);

            // get meta_logins for this type from grouped acc type
            $meta_logins = $tradingAccountsByType->get($type->id)?->pluck('meta_login')->toArray() ?? [];

            // calculate group totals
            $groupBalance = 0;
            $groupEquity = 0;

            foreach ($accounts as $account) {
                if (in_array($account['meta_login'], $meta_logins)) {
                    $groupBalance += (float) $account['balance'];
                    $groupEquity += isset($account['equity']) ? (float) $account['equity'] : $account['balance'];
                }
            }

            // update model only if values changed
            $type->account_group_balance = $groupBalance;
            $type->account_group_equity = $groupEquity;
            $type->save();

            // accumulate total
            $totalBalance += $groupBalance;
            $totalEquity += $groupEquity;
        }

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
            ->sum('rebate');

        return response()->json([
            'totalDeposit' => $totalDeposit,
            'totalWithdrawal' => $totalWithdrawal,
            'totalRebatePayout' => $totalRebatePayout,
        ]);
    }

}
