<?php

namespace App\Repositories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

class MessageRepository
{
    public function create(int $chatId, string $senderGuestId, string $content): Message
    {
        return Message::create([
            'chat_id' => $chatId,
            'sender_guest_id' => $senderGuestId,
            'content' => $content,
        ]);
    }

    public function findById(int $messageId): ?Message
    {
        return Message::find($messageId);
    }

    public function getMessagesByChatId(int $chatId, int $limit = 100): Collection
    {
        return Message::where('chat_id', $chatId)
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getMessagesPaginated(int $chatId, int $limit = 50, ?string $cursor = null): Collection
    {
        $query = Message::where('chat_id', $chatId)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->limit($limit + 1);

        if ($cursor) {
            $query->where('created_at', '<', $cursor);
        }

        return $query->get();
    }

    public function getRecentMessages(int $limit = 50): Collection
    {
        return Message::with(['chat', 'sender'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function flagMessage(int $messageId): bool
    {
        return Message::where('message_id', $messageId)->update(['is_flagged' => true]) > 0;
    }

    public function getFlaggedMessages(): Collection
    {
        return Message::where('is_flagged', true)
            ->with(['chat:chat_id,guest_id_1,guest_id_2', 'sender:guest_id,ip_address'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function deleteOldMessages(int $hours = 24): int
    {
        return Message::where('created_at', '<', now()->subHours($hours))->delete();
    }

    public function countMessagesByChatId(int $chatId): int
    {
        return Message::where('chat_id', $chatId)->count();
    }
}
