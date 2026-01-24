<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default admin user if none exists
        Admin::firstOrCreate(
            ['email' => 'admin@sorsutalk.local'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@sorsutalk.local',
                'password' => 'admin123',
                'role' => 'super_admin',
            ]
        );
    }
}
