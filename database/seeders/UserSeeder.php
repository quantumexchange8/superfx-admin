<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Superfx Admin',
            'email' => 'admin@admin.com',
            'email_verified_at' => now(),
            'password' =>  Hash::make('testtest'),
            'remember_token' => Str::random(10),
            'dial_code' => '60',
            'phone' => '123456789',
            'phone_number' => '60123456789',
            'country_id' => 132,
            'state_id' => 1949,
            'city_id' => 76497,
            'referral_code' => 'MOSx666666',
            'id_number' => 'SID00001',
            'role' => 'super-admin',
        ]);

        User::create([
            'name' => 'Superfx',
            'email' => 'superfx@superfx.com',
            'email_verified_at' => now(),
            'password' =>  Hash::make('testtest'),
            'remember_token' => Str::random(10),
            'dial_code' => '60',
            'phone' => '123334445',
            'phone_number' => '60123334445',
            'country_id' => 132,
            'state_id' => 1949,
            'city_id' => 76497,
            'referral_code' => 'MOSx555666',
            'id_number' => 'AID00000',
            'role' => 'agent',
        ]);
    }
}
