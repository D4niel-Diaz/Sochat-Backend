<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;

class InterestSeeder extends Seeder
{
    public function run(): void
    {
        $interests = [
            ['name' => 'study_help', 'icon' => 'book'],
            ['name' => 'hobbies', 'icon' => 'palette'],
            ['name' => 'sports', 'icon' => 'trophy'],
            ['name' => 'gaming', 'icon' => 'gamepad'],
            ['name' => 'music', 'icon' => 'music'],
            ['name' => 'campus_life', 'icon' => 'campus'],
        ];

        foreach ($interests as $interest) {
            Interest::firstOrCreate(
                ['name' => $interest['name']],
                ['icon' => $interest['icon']]
            );
        }
    }
}
