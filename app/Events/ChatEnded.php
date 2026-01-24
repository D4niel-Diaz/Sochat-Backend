<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Chat $chat,
        public string $endedBy
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('guest.' . $this->chat->guest_id_1),
            new PrivateChannel('guest.' . $this->chat->guest_id_2),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chat->chat_id,
            'ended_by' => $this->endedBy,
            'ended_at' => $this->chat->ended_at->toIso8601String(),
        ];
    }
}
