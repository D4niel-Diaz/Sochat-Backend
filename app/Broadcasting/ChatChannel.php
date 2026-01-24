<?php

namespace App\Broadcasting;

use App\Models\Chat;

class ChatChannel
{
    public function join(Chat $chat, string $guestId): bool|array
    {
        if (!$chat->isParticipant($guestId)) {
            return false;
        }

        if (!$chat->isActive()) {
            return false;
        }

        return [
            'chat_id' => $chat->chat_id,
            'partner_id' => $chat->getPartnerId($guestId),
            'status' => $chat->status,
        ];
    }
}
