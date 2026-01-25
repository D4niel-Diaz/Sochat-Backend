<?php

namespace Database\Factories;

use App\Models\Chat;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatFactory extends Factory
{
    protected $model = Chat::class;

    public function definition(): array
    {
        $guest1 = Guest::factory()->create();
        $guest2 = Guest::factory()->create();

        return [
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'started_at' => now(),
            'ended_at' => null,
            'status' => 'active',
            'ended_by' => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'ended_at' => null,
            'ended_by' => null,
        ]);
    }

    public function ended(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'ended_at' => now()->subMinutes(5),
            'ended_by' => 'guest',
        ]);
    }

    public function forGuests(Guest $guest1, Guest $guest2): self
    {
        return $this->state(fn (array $attributes) => [
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
        ]);
    }
}
