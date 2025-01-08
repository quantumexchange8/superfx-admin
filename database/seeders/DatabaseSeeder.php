<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AccountTypeSeeder::class,
            PermissionSeeder::class,
            PaymentGateWaySeeder::class,
            CurrencyConversionRateSeeder::class,
            CountrySeeder::class,
            StateSeeder::class,
            CitySeeder::class,
            SettingLeverageSeeder::class,
            RunningNumberSeeder::class,
        ]);
    }
}
