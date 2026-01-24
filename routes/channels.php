<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Chat;
use App\Models\Guest;

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    $chat = Chat::find($chatId);
    
    if (!$chat) {
        return false;
    }

    $sessionToken = request()->bearerToken();
    
    if (!$sessionToken) {
        return false;
    }

    $guest = Guest::where('session_token', $sessionToken)->first();
    
    if (!$guest || !$guest->isActive()) {
        return false;
    }

    return $chat->isParticipant($guest->guest_id);
});

Broadcast::channel('guest.{guestId}', function ($user, $guestId) {
    $sessionToken = request()->bearerToken();
    
    if (!$sessionToken) {
        return false;
    }

    $guest = Guest::where('session_token', $sessionToken)->first();
    
    if (!$guest || !$guest->isActive()) {
        return false;
    }

    return $guest->guest_id === $guestId;
});

Broadcast::channel('admin.reports', function ($user) {
    return $user && ($user->isAdmin() || $user->isSuperAdmin());
});
