<?php

namespace App\Repositories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

class MessageRepository
{
    public function create(int $chatId, string $senderGuestId, string $content, bool $isFlagged = false): Message
    {
        return Message::create([
            'chat_id' => $chatId,
            'sender_guest_id' => $senderGuestId,
            'content' => $content,
            'is_flagged' => $isFlagged,
        ]);
    }

    public function findById(int $messageId): ?Message
    {
        return Message::find($messageId);
    }

    public function getMessagesByChatId(int $chatId, int $limit = 100): Collection
    {
        return Message::where('chat_id', $chatId)
            ->orderBy('message_id', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Newest first. Uses message_id as the cursor: created_at has one-second
     * precision, so a timestamp cursor skips messages sent in the same second.
     */
    public function getMessagesPaginated(int $chatId, int $limit = 50, ?int $cursor = null): Collection
    {
        $query = Message::where('chat_id', $chatId)
            ->orderBy('message_id', 'desc')
            ->limit($limit + 1);

        if ($cursor) {
            $query->where('message_id', '<', $cursor);
        }

        return $query->get();
    }

    public function flagMessage(int $messageId): bool
    {
        return Message::where('message_id', $messageId)->update(['is_flagged' => true]) > 0;
    }

    public function getFlaggedMessages(): Collection
    {
        // The review queue: flagged messages an admin hasn't looked at yet
        return Message::where('is_flagged', true)
            ->whereNull('reviewed_at')
            ->with(['chat:chat_id,guest_id_1,guest_id_2', 'sender:guest_id,ip_address'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /** Mark a flagged message reviewed so retention can delete it. */
    public function markReviewed(int $messageId): bool
    {
        return Message::where('message_id', $messageId)
            ->where('is_flagged', true)
            ->whereNull('reviewed_at')
            ->update(['reviewed_at' => now()]) > 0;
    }
}
