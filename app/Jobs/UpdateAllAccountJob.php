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

    public function __construct()
    {
        $this->queue = 'refresh_accounts';
    }

    public function handle(): void
    {
        $trading_accounts = TradingAccount::all();

        foreach ($trading_accounts as $account) {
            try {
                // Attempt to fetch user data
                $accData = (new MetaFourService)->getUser($account->meta_login);

                // If no data is returned (null or empty), mark the account as inactive
                if (empty($accData)) {
                    if ($account->acc_status !== 'inactive') {
                        TradingUser::where('meta_login', $account->meta_login)
                            ->update(['acc_status' => 'inactive']);
                    }
                } else {
                    // If valid data is fetched, update account to active and proceed with further updates
                    if ($account->acc_status !== 'active') {
                        TradingUser::where('meta_login', $account->meta_login)
                            ->update(['acc_status' => 'active']);
                    }

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
