<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data to insert into the payment_gateways table
        DB::table('payment_gateways')->insert([
            [
                'name' => 'VN Bank Live',
                'platform' => 'bank',
                'environment' => 'production',
                'payment_url' => 'https://vi.long77.net',
                'payment_app_name' => 'vn',
                'payment_app_number' => null,
                'payment_app_key' => null,
                'secondary_key' => null,
                'status' => 'active',
                'edited_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null,
            ],
            [
                'name' => 'USDT Crypto Live',
                'platform' => 'crypto',
                'environment' => 'production',
                'payment_url' => 'https://long77.net',
                'payment_app_name' => 'usdt',
                'payment_app_number' => null,
                'payment_app_key' => null,
                'secondary_key' => null,
                'status' => 'active',
                'edited_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null,
            ],
            [
                'name' => 'VN Bank Staging',
                'platform' => 'bank',
                'environment' => 'local',
                'payment_url' => 'http://8.219.116.132:7699/demoBNB',
                'payment_app_name' => 'vn',
                'payment_app_number' => null,
                'payment_app_key' => null,
                'secondary_key' => null,
                'status' => 'active',
                'edited_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null,
            ],
            [
                'name' => 'USDT Crypto Staging',
                'platform' => 'crypto',
                'environment' => 'local',
                'payment_url' => null,
                'payment_app_name' => 'usdt',
                'payment_app_number' => null,
                'payment_app_key' => null,
                'secondary_key' => null,
                'status' => 'active',
                'edited_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null,
            ]
        ]);
    }
}
