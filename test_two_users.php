<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST 2: Two Users (Match Scenario) ===\n\n";

// Step 1: Create two guests
echo "Step 1: Creating two guests...\n";
$guest1 = \App\Models\Guest::create([
    'ip_address' => '127.0.0.1',
    'status' => 'waiting',
    'expires_at' => now()->addHours(24),
]);

$guest2 = \App\Models\Guest::create([
    'ip_address' => '127.0.0.2',
    'status' => 'waiting',
    'expires_at' => now()->addHours(24),
]);

echo "✓ Guest 1 created\n";
echo "  Guest ID: " . $guest1->guest_id . "\n";
echo "  Status: " . $guest1->status . "\n\n";

echo "✓ Guest 2 created\n";
echo "  Guest ID: " . $guest2->guest_id . "\n";
echo "  Status: " . $guest2->status . "\n\n";

// Step 2: Guest 2 attempts to find match
echo "Step 2: Guest 2 attempts to find match...\n";
$chatService = app(\App\Services\ChatService::class);
$result = $chatService->findMatch($guest2->guest_id);

echo "✓ Match attempt completed\n";
echo "  Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Step 3: Verify database state
echo "Step 3: Verifying database state...\n";
$guest1->refresh();
$guest2->refresh();
$chat = \App\Models\Chat::first();

echo "  Guest 1 Status: " . $guest1->status . "\n";
echo "  Guest 2 Status: " . $guest2->status . "\n";
echo "  Total Guests: " . \App\Models\Guest::count() . "\n";
echo "  Total Chats: " . \App\Models\Chat::count() . "\n";

if ($chat) {
    echo "  Chat ID: " . $chat->chat_id . "\n";
    echo "  Chat guest_id_1: " . $chat->guest_id_1 . "\n";
    echo "  Chat guest_id_2: " . $chat->guest_id_2 . "\n";
    echo "  Chat Status: " . $chat->status . "\n";
} else {
    echo "  Chat: NULL\n";
}

echo "\n";

// Step 4: Verify results
echo "Step 4: Verification\n";
$tests = [
    'Result status is "matched"' => isset($result['status']) && $result['status'] === 'matched',
    'Result has chat_id' => isset($result['chat_id']) && is_numeric($result['chat_id']),
    'Result has partner_id' => isset($result['partner_id']) && $result['partner_id'] === $guest1->guest_id,
    'Guest 1 status is "active"' => $guest1->status === 'active',
    'Guest 2 status is "active"' => $guest2->status === 'active',
    'One chat created' => \App\Models\Chat::count() === 1,
    'Chat has both guest IDs' => $chat && 
        (($chat->guest_id_1 === $guest1->guest_id && $chat->guest_id_2 === $guest2->guest_id) ||
         ($chat->guest_id_1 === $guest2->guest_id && $chat->guest_id_2 === $guest1->guest_id)),
    'Chat status is "active"' => $chat && $chat->status === 'active',
    'No self-match (guest_id_1 != guest_id_2)' => $chat && $chat->guest_id_1 !== $chat->guest_id_2,
    'Message mentions successful match' => isset($result['message']) && str_contains($result['message'], 'Successfully matched'),
];

foreach ($tests as $test => $passed) {
    echo "  " . ($passed ? '✓' : '✗') . " " . $test . "\n";
}

$allPassed = array_reduce($tests, fn($carry, $passed) => $carry && $passed, true);
echo "\n" . ($allPassed ? "✓ TEST 2 PASSED\n" : "✗ TEST 2 FAILED\n");

echo "\n=== END TEST 2 ===\n";
