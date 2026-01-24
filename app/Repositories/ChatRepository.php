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

    public function endChat(int $chatId, string $endedBy): bool
    {
        $chat = $this->findById($chatId);
        if (!$chat) {
            return false;
        }
        $chat->end($endedBy);
        return true;
    }

    public function getActiveChats(): Collection
    {
        return Chat::active()
            ->with(['guest1:id,guest_id,ip_address,status', 'guest2:id,guest_id,ip_address,status'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getChatsByGuestId(string $guestId): Collection
    {
        return Chat::where('guest_id_1', $guestId)
            ->orWhere('guest_id_2', $guestId)
            ->with(['guest1', 'guest2', 'messages'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getRecentChats(int $limit = 50): Collection
    {
        return Chat::with(['guest1', 'guest2'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function countActiveChats(): int
    {
        return Chat::active()->count();
    }
}
