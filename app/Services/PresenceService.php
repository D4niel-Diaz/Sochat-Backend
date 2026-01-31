<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PresenceService
{
    private const TTL_SECONDS = 300; // 5 minutes

    public function markUserOnline(string $guestId): void
    {
        DB::table('presence')->updateOrInsert(
            ['guest_id' => $guestId],
            [
                'last_seen_at' => now(),
                'is_online' => true,
                'expires_at' => now()->addSeconds(self::TTL_SECONDS),
            ]
        );
    }

    public function markUserOffline(string $guestId): void
    {
        DB::table('presence')
            ->where('guest_id', $guestId)
            ->update([
                'is_online' => false,
                'is_waiting' => false,
            ]);
    }

    public function isUserOnline(string $guestId): bool
    {
        return DB::table('presence')
            ->where('guest_id', $guestId)
            ->where('is_online', true)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function addToWaitingPool(string $guestId, ?string $role = null, ?string $subject = null, ?array $availability = null): void
    {
        $data = [
            'last_seen_at' => now(),
            'is_online' => true,
            'is_waiting' => true,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ];

        if ($role !== null) {
            $data['role'] = $role;
        }
        if ($subject !== null) {
            $data['subject'] = $subject;
        }
        if ($availability !== null) {
            $data['availability'] = json_encode($availability);
        }

        DB::table('presence')->updateOrInsert(
            ['guest_id' => $guestId],
            $data
        );
    }

    public function removeFromWaitingPool(string $guestId): void
    {
        DB::table('presence')
            ->where('guest_id', $guestId)
            ->update(['is_waiting' => false]);
    }

    public function isInWaitingPool(string $guestId): bool
    {
        return DB::table('presence')
            ->where('guest_id', $guestId)
            ->where('is_waiting', true)
            ->where('is_online', true)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function getWaitingUsers(?string $role = null, ?string $subject = null): array
    {
        $query = DB::table('presence')
            ->where('is_waiting', true)
            ->where('is_online', true)
            ->where('expires_at', '>', now());

        if ($role !== null) {
            $query->where('role', $role);
        }
        if ($subject !== null) {
            $query->where('subject', $subject);
        }

        return $query->pluck('guest_id')->toArray();
    }

    public function countWaitingUsers(?string $role = null, ?string $subject = null): int
    {
        $query = DB::table('presence')
            ->where('is_waiting', true)
            ->where('is_online', true)
            ->where('expires_at', '>', now());

        if ($role !== null) {
            $query->where('role', $role);
        }
        if ($subject !== null) {
            $query->where('subject', $subject);
        }

        return $query->count();
    }

    public function getOnlineUsers(): array
    {
        return DB::table('presence')
            ->where('is_online', true)
            ->where('expires_at', '>', now())
            ->pluck('guest_id')
            ->toArray();
    }

    public function countOnlineUsers(): int
    {
        return DB::table('presence')
            ->where('is_online', true)
            ->where('expires_at', '>', now())
            ->count();
    }

    public function refreshPresence(string $guestId): void
    {
        DB::table('presence')
            ->where('guest_id', $guestId)
            ->update([
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds(self::TTL_SECONDS),
            ]);
    }

    public function cleanupStaleUsers(): int
    {
        return DB::table('presence')
            ->where('expires_at', '<=', now())
            ->delete();
    }
}
