<?php

namespace App\Console\Commands;

use App\Models\JobRunLog;
use App\Models\TradingUser;
use Illuminate\Console\Command;
use App\Services\MetaFourService;
use Illuminate\Support\Facades\Log;
use App\Services\Data\UpdateTradingUser;
use App\Services\Data\UpdateTradingAccount;

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

        $trading_accounts = TradingUser::where('acc_status', 'active')->get();

        foreach ($trading_accounts as $account) {
            try {
                // Attempt to fetch user data
                $accData = (new MetaFourService)->getUser($account->meta_login);

                // If no data is returned (null or empty), mark the account as inactive and delete associated records
                if (empty($accData) || ($accData['status'] ?? null) !== 'success') {
                    if ($account->acc_status !== 'inactive') {
                        $account->acc_status = 'inactive';
                        $account->save();
                    }

                    // $tradingAccount = $account->trading_account;
                    // if ($tradingAccount) {
                    //     $tradingAccount->delete();
                    // }
                    
                    // $account->delete();
                } else {
                    // Proceed with updating account information
                    (new UpdateTradingUser)->execute($account->meta_login, $accData);
                    (new UpdateTradingAccount)->execute($account->meta_login, $accData);
                }
            } catch (\Exception $e) {
                // Log the error if there was a failure
                Log::error("Error fetching data for account {$account->meta_login}: {$e->getMessage()}");
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
