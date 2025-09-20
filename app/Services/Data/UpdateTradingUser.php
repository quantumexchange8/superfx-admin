<?php

namespace App\Services\Data;

use App\Models\AccountType;
use App\Models\TradingUser;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateTradingUser
{
    /**
     * @throws Throwable
     */
    public function execute($meta_login, $data): TradingUser
    {
        return $this->updateTradingUser($meta_login, $data);
    }

    /**
     * @throws Throwable
     */
    public function updateTradingUser($meta_login, $data): TradingUser
    {
        $tradingUser = TradingUser::query()
            ->where('meta_login', $meta_login)
            ->first();

        // Safely pick status
        $status = !empty($data['status'])
            ? $data['status']
            : ($data['requestStatus'] ?? null);

        if ($status == 'success') {
            $currentAccType = AccountType::find($tradingUser->account_type_id);

            $accountType = AccountType::query()
                ->where('account_group', $data['group'])
                ->where('trading_platform_id', $currentAccType->trading_platform_id)
                ->first();

            $tradingUser->meta_group = $data['group'];
            $tradingUser->account_type_id = $accountType->id;
            $tradingUser->leverage = $data['leverage'];
            if (isset($data['registration'])) {
                $tradingUser->registration = $data['registration'];
            }

            if (isset($data['registration_date'])) {
                $tradingUser->registration = $data['registration_date'];
            }

            if (isset($data['last_ip'])) {
                $tradingUser->last_ip = $data['last_ip'];
            }
            if (isset($data['last_login'])) {
                $tradingUser->last_access = $data['last_login'];
            }
            if (isset($data['balance'])) {
                $tradingUser->balance = $data['balance'];
            }
            if (isset($data['credit'])) {
                $tradingUser->credit = $data['credit'];
            }

            DB::transaction(function () use ($tradingUser) {
                $tradingUser->save();
            });
        }

        return $tradingUser;
    }
}
