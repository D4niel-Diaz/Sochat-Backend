<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\Admin::where('email', 'admin@sorsutalk.local')->first();

if ($admin) {
    echo "Name: " . $admin->name . "\n";
    echo "Email: " . $admin->email . "\n";
    echo "Role: " . $admin->role . "\n";
    echo "Password hash: " . substr($admin->password, 0, 20) . "...\n";

    $check = Hash::check('admin123', $admin->password);
    echo "Password valid: " . ($check ? "YES" : "NO") . "\n";
} else {
    echo "No admin found\n";
}
