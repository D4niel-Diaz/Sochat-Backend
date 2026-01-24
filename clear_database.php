<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Clear database
\App\Models\Message::query()->delete();
\App\Models\Chat::query()->delete();
\App\Models\Guest::query()->delete();

echo "Database cleared successfully!\n";
echo "Guests: " . \App\Models\Guest::count() . "\n";
echo "Chats: " . \App\Models\Chat::count() . "\n";
echo "Messages: " . \App\Models\Message::count() . "\n";
