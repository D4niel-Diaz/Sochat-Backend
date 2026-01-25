<?php

namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'guest_id' => Str::uuid(),
            'session_token' => bin2hex(random_bytes(32)),
            'ip_address' => fake()->ipv4(),
            'status' => fake()->randomElement(['idle', 'waiting', 'active', 'banned']),
            'expires_at' => now()->addHours(24),
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function waiting(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'waiting',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function idle(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'idle',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function banned(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'banned',
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => now()->subHours(1),
        ]);
    }
}
