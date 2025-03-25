<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MarkupProfile;
use App\Models\MarkupProfileToAccountType;
use App\Models\AccountType;

class MarkupProfileSeeder extends Seeder
{
    public function run()
    {
        // Define the markup profiles with only name and active status
        $profiles = [
            ['name' => 'Markup0', 'status' => 'active'],
            ['name' => 'Markup5', 'status' => 'active'],
            ['name' => 'Markup10', 'status' => 'active'],
            ['name' => 'Markup15', 'status' => 'active'],
        ];

        // Define corresponding account types for each profile in specific order
        $accountTypeNames = [
            'Markup0' => ['Standard', 'Cent', 'ECN', 'PRIME'],
            'Markup5' => ['STD5', 'Cent5', 'ECN', 'PRIME'],
            'Markup10' => ['STD10', 'Cent10', 'ECN', 'PRIME'],
            'Markup15' => ['STD15', 'Cent15', 'ECN', 'PRIME'],
        ];

        foreach ($profiles as $profileData) {
            // Create the markup profile
            $markupProfile = MarkupProfile::create($profileData);

            // Fetch all matching account types
            $accountTypes = AccountType::whereIn('name', $accountTypeNames[$profileData['name']])
                ->get()
                ->keyBy('name');

            // Map account type IDs in the exact order of provided names
            $accountTypeIds = array_map(function($name) use ($accountTypes) {
                return $accountTypes[$name]->id;
            }, $accountTypeNames[$profileData['name']]);

            // Seed related account types for this markup profile in order
            foreach ($accountTypeIds as $accountTypeId) {
                MarkupProfileToAccountType::create([
                    'markup_profile_id' => $markupProfile->id,
                    'account_type_id' => $accountTypeId,
                ]);
            }
        }
    }
}
