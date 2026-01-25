<?php

namespace Database\Factories;

use App\Models\Chat;
use App\Models\Guest;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $chat = Chat::factory()->create();
        $guest = $chat->guest1;

        return [
            'chat_id' => $chat->chat_id,
            'sender_guest_id' => $guest->guest_id,
            'content' => fake()->sentence(),
            'is_flagged' => false,
        ];
    }

    public function flagged(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_flagged' => true,
        ]);
    }

    public function forChat(Chat $chat): self
    {
        return $this->state(fn (array $attributes) => [
            'chat_id' => $chat->chat_id,
            'sender_guest_id' => $chat->guest_id_1,
        ]);
    }

    public function forGuest(Guest $guest): self
    {
        return $this->state(fn (array $attributes) => [
            'sender_guest_id' => $guest->guest_id,
        ]);
    }
}
