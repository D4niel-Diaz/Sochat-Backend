<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Chat $chat
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
        return 'user.matched';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id' => $this->chat->chat_id,
            'partner_id_1' => $this->chat->guest_id_1,
            'partner_id_2' => $this->chat->guest_id_2,
            'started_at' => $this->chat->started_at->toIso8601String(),
        ];
    }
}
