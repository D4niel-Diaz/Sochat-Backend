<?php

namespace App\Broadcasting;

use App\Models\Guest;

class GuestChannel
{
    public function join(Guest $guest, string $guestId): bool|array
    {
        $currentGuest = Guest::where('guest_id', $guestId)->first();

        if (!$currentGuest) {
            return false;
        }

        if ($currentGuest->isBanned()) {
            return false;
        }

        if ($currentGuest->expires_at && $currentGuest->expires_at->isPast()) {
            return false;
        }

        return [
            'guest_id' => $currentGuest->guest_id,
            'status' => $currentGuest->status,
        ];
    }
}
