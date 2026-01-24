<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Chat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_chat_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/chat/start');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Session token required',
            ]);
    }

    public function test_start_chat_returns_waiting_status(): void
    {
        $guest = Guest::factory()->create(['status' => 'waiting', 'expires_at' => now()->addHours(24)]);

        $response = $this->withToken($guest->session_token)
            ->postJson('/api/v1/chat/start');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                ],
                'message',
            ]);
    }

    public function test_send_message_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/chat/message', [
            'chat_id' => 1,
            'content' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_send_message_validates_input(): void
    {
        $guest = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);

        $response = $this->withToken($guest->session_token)
            ->postJson('/api/v1/chat/message', [
                'chat_id' => 1,
                'content' => '', // Empty content
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_send_message_content_too_long(): void
    {
        $guest = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);

        $response = $this->withToken($guest->session_token)
            ->postJson('/api/v1/chat/message', [
                'chat_id' => 1,
                'content' => str_repeat('a', 1001), // Over 1000 characters
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_end_chat_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/chat/end', [
            'chat_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_get_messages_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/chat/1/messages');

        $response->assertStatus(401);
    }

    public function test_typing_indicator_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/chat/typing', [
            'chat_id' => 1,
            'is_typing' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_rate_limiting_applies(): void
    {
        $guest = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);

        // Make many requests to trigger rate limit
        for ($i = 0; $i < 70; $i++) {
            $this->withToken($guest->session_token)
                ->postJson('/api/v1/chat/start');
        }

        $response = $this->withToken($guest->session_token)
            ->postJson('/api/v1/chat/start');

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ]);
    }

    public function test_banned_guest_cannot_access(): void
    {
        $guest = Guest::factory()->create(['status' => 'banned']);

        $response = $this->withToken($guest->session_token)
            ->postJson('/api/v1/chat/start');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your session has been banned',
            ]);
    }

    public function test_expired_session_cannot_access(): void
    {
        $guest = Guest::factory()->create([
            'status' => 'active',
            'expires_at' => now()->subHours(1),
        ]);

        $response = $this->withToken($guest->session_token)
            ->postJson('/api/v1/chat/start');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Session expired',
            ]);
    }
}
