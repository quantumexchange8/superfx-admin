<?php

namespace App\Services\Data;

use App\Models\AccountType;
use App\Models\TradingUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateTradingUser
{
    public function execute($meta_login, $data): TradingUser
    {
        return $this->updateTradingUser($meta_login, $data);
    }

    public function updateTradingUser($meta_login, $data): TradingUser
    {
        $tradingUser = TradingUser::query()
            ->where('meta_login', $meta_login)
            ->first();

        if ($data['status'] == 'success') {
            $accountType = AccountType::query()
                ->where('account_group', $data['group'])
                ->first();

            $tradingUser->meta_group = $data['group'];
            $tradingUser->account_type_id = $accountType->id;
            $tradingUser->leverage = $data['leverage'];
            $tradingUser->registration = $data['registration_date'];
            $tradingUser->last_ip = $data['last_ip'];
            if (isset($data['last_login'])) {
                $tradingUser->last_access = $data['last_login'];
            }

            $tradingUser->balance = $data['balance'];
            $tradingUser->credit = $data['credit'];

            DB::transaction(function () use ($tradingUser) {
                $tradingUser->save();
            });
        } else {
            if ($tradingUser->balance <= 0) {
                $tradingUser->update([
                    'acc_status' => 'inactive',
                ]);

                // $tradingUser->delete();
            }
        }

        return $tradingUser;
    }
}
