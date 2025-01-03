<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'ib']);
        Role::firstOrCreate(['name' => 'member']);

        // Create the Superfx Admin
        $superfxAdmin = User::create([
            'name' => 'Superfx Admin',
            'email' => 'admin@admin.com',
            'email_verified_at' => now(),
            'password' => Hash::make('testtest'),
            'remember_token' => Str::random(10),
            'dial_code' => '60',
            'phone' => '123456789',
            'phone_number' => '60123456789',
            'country_id' => 132,
            'state_id' => 1949,
            'city_id' => 76497,
            'referral_code' => 'MOSx666666',
            'id_number' => 'SID00001',
        ]);
        // Assign super-admin role
        $superfxAdmin->assignRole('super-admin');

        // Create the Superfx company
        $superfxCompany = User::create([
            'name' => 'Superfx',
            'email' => 'superfx@superfx.com',
            'email_verified_at' => now(),
            'password' => Hash::make('testtest'),
            'remember_token' => Str::random(10),
            'dial_code' => '60',
            'phone' => '123334445',
            'phone_number' => '60123334445',
            'country_id' => 132,
            'state_id' => 1949,
            'city_id' => 76497,
            'referral_code' => 'MOSx555666',
            'id_number' => 'IB00000',
        ]);
        // Assign ib role
        $superfxCompany->assignRole('ib');

        // Create a rebate wallet for Superfx
        Wallet::create([
            'user_id' => $superfxCompany->id,
            'type' => 'rebate_wallet',
            'address' => str_replace('IB', 'RB', $superfxCompany->id_number),
            'balance' => 0
        ]);

        // Create the TestMember user
        $testMember = User::create([
            'name' => 'TestMember',
            'email' => 'testmember@testmember.com',
            'email_verified_at' => now(),
            'password' => Hash::make('testtest'),
            'remember_token' => Str::random(10),
            'dial_code' => '60',
            'phone' => '121215155',
            'phone_number' => '60121215155',
            'country_id' => 132,
            'state_id' => 1949,
            'city_id' => 76497,
            'referral_code' => 'MOSx555555',
            'id_number' => 'MB00003',
        ]);
        // Assign member role
        $testMember->assignRole('member');
    }
}
