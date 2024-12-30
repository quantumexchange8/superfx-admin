<?php

namespace Database\Seeders;

use App\Models\RunningNumber;
use Illuminate\Database\Seeder;

class RunningNumberSeeder extends Seeder
{
    public function run(): void
    {
        RunningNumber::create([
            'type' => 'transaction',
            'prefix' => 'TXN',
            'digits' => 7,
            'last_number' => 0,
        ]);
    }
}
