<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\Admin::first();
$admin->password = bcrypt('password123');
$admin->save();

echo "Admin password reset successfully!\n";
echo "Email: " . $admin->email . "\n";
echo "Password: password123\n";
