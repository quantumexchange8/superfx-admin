<?php

namespace App\Services\Data;

use App\Models\AccountType;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTradingAccount
{
    public function execute(User $user, $data, $group): TradingAccount
    {
        return $this->storeNewAccount($user, $data, $group);
    }

    public function storeNewAccount(User $user, $data, $group): TradingAccount
    {
        $accountType = AccountType::firstWhere('name', $group);

        $tradingAccount = new TradingAccount();
        $tradingAccount->user_id = $user->id;
        $tradingAccount->meta_login = $data['meta_login'];
        $tradingAccount->account_type_id = $accountType->id;
        $tradingAccount->margin_leverage = $data['leverage'];
        DB::transaction(function () use ($tradingAccount) {
            $tradingAccount->save();
        });

        return $tradingAccount;
    }
}
