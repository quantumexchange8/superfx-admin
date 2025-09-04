<?php

namespace App\Services\Data;

use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateAccountBalance
{
    /**
     * @throws Throwable
     */
    public function execute($meta_login, $data): TradingAccount
    {
        return $this->updateTradingAccount($meta_login, $data);
    }

    /**
     * @throws Throwable
     */
    public function updateTradingAccount($meta_login, $data): TradingAccount
    {
        $tradingAccount = TradingAccount::firstWhere('meta_login', $meta_login);

        $tradingAccount->balance = $data['balance'];
        $tradingAccount->equity = $data['equity'];
        $tradingAccount->floating = $data['floating'];
        $tradingAccount->margin = $data['margin'];
        $tradingAccount->margin_free = $data['free_margin'];
        $tradingAccount->margin_level = $data['margin_level'];

        DB::transaction(function () use ($tradingAccount) {
            $tradingAccount->save();
        });

        return $tradingAccount;
    }
}
