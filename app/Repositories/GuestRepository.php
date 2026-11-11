<?php

namespace App\Repositories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Collection;

class GuestRepository
{
    public function create(string $ipAddress, int $ttlHours = 24): Guest
    {
        return Guest::create([
            'ip_address' => $ipAddress,
            'status' => 'waiting',
            'expires_at' => now()->addHours($ttlHours),
        ]);
    }

    public function findBySessionToken(string $token): ?Guest
    {
        return Guest::where('session_token', $token)->first();
    }

    public function findByGuestId(string $guestId): ?Guest
    {
        return Guest::where('guest_id', $guestId)->first();
    }

    public function updateStatus(string $guestId, string $status): bool
    {
        return Guest::where('guest_id', $guestId)
            ->where('expires_at', '>', now())
            ->update(['status' => $status]) > 0;
    }

    public function banGuest(string $guestId): bool
    {
        return Guest::where('guest_id', $guestId)->update(['status' => 'banned', 'updated_at' => now()]) > 0;
    }

    public function unbanGuest(string $guestId): bool
    {
        return Guest::where('guest_id', $guestId)->where('status', 'banned')->update(['status' => 'idle', 'updated_at' => now()]) > 0;
    }

    public function getBannedGuests(): Collection
    {
        return Guest::banned()->orderBy('updated_at', 'desc')->get();
    }

    public function countBannedGuests(): int
    {
        return Guest::banned()->count();
    }
}
