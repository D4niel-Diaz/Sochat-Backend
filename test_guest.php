<?php

require __DIR__ . '/vendor/autoload.php';

use App\Repositories\GuestRepository;

try {
    $repo = new GuestRepository();
    $guest = $repo->create('127.0.0.1');
    
    echo "SUCCESS!\n";
    echo "Guest ID: " . $guest->guest_id . "\n";
    echo "Session Token: " . $guest->session_token . "\n";
    echo "Status: " . $guest->status . "\n";
    echo "Expires At: " . $guest->expires_at . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
