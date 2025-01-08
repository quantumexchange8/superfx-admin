<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencyConversionRateSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('currency_conversion_rates')->insert([
            [
                'base_currency' => 'VND',
                'target_currency' => 'USD',
                'deposit_charge_type' => 'percentage',
                'deposit_charge_amount' => 0,
                'withdrawal_charge_type' => 'percentage',
                'withdrawal_charge_amount' => 5.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'base_currency' => 'CNY',
                'target_currency' => 'USD',
                'deposit_charge_type' => 'percentage',
                'deposit_charge_amount' => 0,
                'withdrawal_charge_type' => 'percentage',
                'withdrawal_charge_amount' => 5.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'base_currency' => 'MYR',
                'target_currency' => 'USD',
                'deposit_charge_type' => 'percentage',
                'deposit_charge_amount' => 0,
                'withdrawal_charge_type' => 'percentage',
                'withdrawal_charge_amount' => 5.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
