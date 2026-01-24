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
            'status' => 'idle',
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

    public function findActiveByGuestId(string $guestId): ?Guest
    {
        return Guest::where('guest_id', $guestId)->active()->first();
    }

    public function findWaitingGuest(): ?Guest
    {
        return Guest::waiting()
            ->where('status', '!=', 'banned')
            ->where('status', '!=', 'idle')
            ->orderBy('created_at', 'asc')
            ->first();
    }

    public function findWaitingGuestExcluding(string $excludeGuestId): ?Guest
    {
        return Guest::waiting()
            ->where('guest_id', '!=', $excludeGuestId)
            ->where('status', '!=', 'banned')
            ->where('status', '!=', 'idle')
            ->where('expires_at', '>', now())
            ->orderBy('updated_at', 'asc')
            ->lockForUpdate()
            ->first();
    }

    public function countWaitingGuestsExcluding(string $excludeGuestId): int
    {
        return Guest::waiting()
            ->where('guest_id', '!=', $excludeGuestId)
            ->where('status', '!=', 'banned')
            ->where('status', '!=', 'idle')
            ->where('expires_at', '>', now())
            ->count();
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

    public function deleteExpired(): int
    {
        return Guest::where('expires_at', '<', now())->delete();
    }

    public function getBannedGuests(): Collection
    {
        return Guest::banned()->get();
    }
}
