<?php

namespace App\Jobs;

use App\Services\TradingPlatform\TradingPlatformFactory;
use App\Models\JobRunLog;
use App\Models\TradingUser;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateAllAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Set the job timeout to null to make it run indefinitely
    public $timeout = null;

    public function __construct()
    {
        $this->queue = 'refresh_accounts';
    }

    public function handle(): void
    {
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
                Log::error("Error fetching data in job - refresh_accounts for account {$account->meta_login}: {$e->getMessage()}");
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
