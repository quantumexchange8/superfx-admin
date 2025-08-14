<?php

namespace App\Services\Data;

use App\Models\AccountType;
use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;

class UpdateTradingAccount
{
    public function execute($meta_login, $data): TradingAccount
    {
        return $this->updateTradingAccount($meta_login, $data);
    }

    public function updateTradingAccount($meta_login, $data): TradingAccount
    {
        $tradingAccount = TradingAccount::query()
            ->where('meta_login', $meta_login)
            ->first();

        if ($data['status'] == 'success') {
            $accountType = AccountType::query()
                ->where('account_group', $data['group'])
                ->first();

            $tradingAccount->balance = $data['balance'];
            $tradingAccount->credit = $data['credit'];
            $tradingAccount->margin_leverage = $data['leverage'];

            $tradingAccount->account_type_id = $accountType->id;
            DB::transaction(function () use ($tradingAccount) {
                $tradingAccount->save();
            });
        } else {
            $tradingAccount->delete();
        }

        return $tradingAccount;
    }
}
