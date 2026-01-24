<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST 1: Single User (No Match Scenario) ===\n\n";

// Step 1: Create one guest
echo "Step 1: Creating guest...\n";
$guest = \App\Models\Guest::create([
    'ip_address' => '127.0.0.1',
    'status' => 'waiting',
    'expires_at' => now()->addHours(24),
]);

echo "✓ Guest created\n";
echo "  Guest ID: " . $guest->guest_id . "\n";
echo "  Session Token: " . $guest->session_token . "\n";
echo "  Status: " . $guest->status . "\n\n";

// Step 2: Attempt to find match
echo "Step 2: Attempting to find match...\n";
$chatService = app(\App\Services\ChatService::class);
$result = $chatService->findMatch($guest->guest_id);

echo "✓ Match attempt completed\n";
echo "  Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Step 3: Verify database state
echo "Step 3: Verifying database state...\n";
$guest->refresh();
echo "  Guest Status: " . $guest->status . "\n";
echo "  Total Guests: " . \App\Models\Guest::count() . "\n";
echo "  Total Chats: " . \App\Models\Chat::count() . "\n";
echo "  Waiting Guests (excluding self): " . app(\App\Repositories\GuestRepository::class)->countWaitingGuestsExcluding($guest->guest_id) . "\n\n";

// Step 4: Verify results
echo "Step 4: Verification\n";
$tests = [
    'Guest status is "waiting"' => $guest->status === 'waiting',
    'Result status is "waiting"' => isset($result['status']) && $result['status'] === 'waiting',
    'available_users is 0' => isset($result['available_users']) && $result['available_users'] === 0,
    'No chats created' => \App\Models\Chat::count() === 0,
    'Message mentions no users' => isset($result['message']) && str_contains($result['message'], 'No users available'),
];

foreach ($tests as $test => $passed) {
    echo "  " . ($passed ? '✓' : '✗') . " " . $test . "\n";
}

$allPassed = array_reduce($tests, fn($carry, $passed) => $carry && $passed, true);
echo "\n" . ($allPassed ? "✓ TEST 1 PASSED\n" : "✗ TEST 1 FAILED\n");

echo "\n=== END TEST 1 ===\n";
