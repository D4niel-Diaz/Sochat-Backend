<?php

namespace App\Repositories;

use App\Models\Chat;
use Illuminate\Database\Eloquent\Collection;

class ChatRepository
{
    public function create(string $guestId1, string $guestId2): Chat
    {
        return Chat::create([
            'guest_id_1' => $guestId1,
            'guest_id_2' => $guestId2,
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    public function findById(int $chatId): ?Chat
    {
        return Chat::find($chatId);
    }

    public function findActiveByGuestId(string $guestId): ?Chat
    {
        return Chat::active()
            ->where(function ($query) use ($guestId) {
                $query->where('guest_id_1', $guestId)
                    ->orWhere('guest_id_2', $guestId);
            })
            ->first();
    }

    public function getActiveChats(): Collection
    {
        // guests has no "id" column; its primary key is guest_id
        return Chat::active()
            ->with(['guest1:guest_id,ip_address,status,expires_at', 'guest2:guest_id,ip_address,status,expires_at'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countActiveChats(): int
    {
        return Chat::active()->count();
    }
}
