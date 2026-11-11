<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Create the first admin from ADMIN_EMAIL / ADMIN_PASSWORD.
     * There is deliberately no default password.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (!$email || !$password || strlen($password) < 12) {
            $this->command?->warn('Skipping admin seed: set ADMIN_EMAIL and ADMIN_PASSWORD (12+ characters).');
            return;
        }

        Admin::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'System Administrator'),
                'password' => $password, // hashed by the model cast
                'role' => 'super_admin',
            ]
        );
    }
}
