<?php

namespace App\Jobs;

use App\Models\TradingUser;
use Illuminate\Bus\Queueable;
use App\Models\TradingAccount;
use App\Services\MetaFourService;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Services\Data\UpdateTradingUser;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Data\UpdateTradingAccount;
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
        $trading_accounts = TradingUser::where('acc_status', 'active')->get();

        foreach ($trading_accounts as $account) {
            try {
                // Attempt to fetch user data
                $accData = (new MetaFourService)->getUser($account->meta_login);

                // If no data or the status is not "success", delete the account
                if (empty($accData) || ($accData['status'] ?? null) !== 'success') {
                    if ($account->acc_status !== 'inactive') {
                        $account->acc_status = 'inactive';
                        $account->save();
                    }

                    $tradingAccount = $account->trading_account;
                    if ($tradingAccount) {
                        $tradingAccount->delete();
                    }
                    
                    $account->delete();
                } else {
                    // Proceed with updating account information
                    (new UpdateTradingUser)->execute($account->meta_login, $accData);
                    (new UpdateTradingAccount)->execute($account->meta_login, $accData);
                }
            } catch (\Exception $e) {
                // Log the error if there was a failure (network issue, server error, etc.)
                Log::error("Error fetching data for account {$account->meta_login}: {$e->getMessage()}");
            }
        }
    }
}
