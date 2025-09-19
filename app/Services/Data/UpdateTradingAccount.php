<?php

namespace App\Services\Data;

use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateTradingAccount
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
        $tradingAccount = TradingAccount::with('trading_user')
            ->where('meta_login', $meta_login)
            ->first();

        // Safely pick status
        $status = $data['status'] ?? $data['requestStatus'] ?? null;
        if ($status == 'success') {
            $tradingAccount->balance = $data['balance'];
            $tradingAccount->credit = $data['credit'];

            if (isset($data['leverage'])) {
                $tradingAccount->margin_leverage = $data['leverage'];
            }

            if (isset($data['marginLeverage'])) {
                $tradingAccount->margin_leverage = $data['marginLeverage'];
            }

            if (isset($data['margin'])) {
                $tradingAccount->margin = $data['margin'];
            }

            if (isset($data['marginFree'])) {
                $tradingAccount->margin_free = $data['marginFree'];
            }

            if (isset($data['marginLevel'])) {
                $tradingAccount->margin_level = $data['marginLevel'];
            }

            if (isset($data['equity'])) {
                $tradingAccount->equity = $data['equity'];
            }

            if (isset($data['floating'])) {
                $tradingAccount->floating = $data['floating'];
            }

            $tradingAccount->account_type_id = $tradingAccount->trading_user->account_type_id;
            DB::transaction(function () use ($tradingAccount) {
                $tradingAccount->save();
            });
        } else {
            if ($tradingAccount->balance <= 0) {
                // $tradingAccount->delete();
            }
        }

        return $tradingAccount;
    }
}
