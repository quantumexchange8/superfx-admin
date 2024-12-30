<?php

namespace App\Jobs;

use App\Models\TradingAccount;
use App\Services\CTraderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateCTraderAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->queue = 'refresh_accounts';
    }

    public function handle(): void
    {
        $trading_accounts = TradingAccount::where('account_type_id', 1)->get();

        // foreach ($trading_accounts as $account) {
        //     (new CTraderService())->getUserInfo($account->meta_login);
        // }
    }
}
