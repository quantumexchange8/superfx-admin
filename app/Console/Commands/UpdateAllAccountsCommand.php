<?php

namespace App\Console\Commands;

use App\Models\JobRunLog;
use App\Models\TradingUser;
use App\Services\TradingPlatform\TradingPlatformFactory;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAllAccountsCommand extends Command
{
    protected $signature = 'refresh_accounts';

    protected $description = 'Update accounts for active trading accounts';

    // Disable the Laravel command timeout
    protected $timeout = null;

    public function handle(): void
    {
        // Disable PHP execution timeout (unlimited time)
        ini_set('max_execution_time', 0);  // No timeout for PHP script execution

        $trading_accounts = TradingUser::with([
            'accountType:id,trading_platform_id',
            'accountType.trading_platform:id,slug'
        ])
            ->where('acc_status', 'active')
            ->get();

        foreach ($trading_accounts as $account) {
            try {
                $service = TradingPlatformFactory::make($account->accountType->trading_platform->slug);

                $service->getUserInfo($account->meta_login);
            } catch (Exception $e) {
                // Log the error if there was a failure
                Log::error("Error fetching data in cronjob - refresh_accounts for account {$account->meta_login}: {$e->getMessage()}");
            }
        }
        // Log this job's latest successful run
        JobRunLog::updateOrCreate([
            'queue' => 'refresh_accounts'
        ],[
            'last_ran_at' => now()
        ]);
    }
}
