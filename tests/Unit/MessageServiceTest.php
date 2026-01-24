<?php

namespace Tests\Unit;

use App\Services\MessageService;
use App\Repositories\MessageRepository;
use App\Repositories\ChatRepository;
use App\Models\Message;
use App\Models\Chat;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessageService $messageService;
    private MessageRepository $messageRepository;
    private ChatRepository $chatRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messageRepository = new MessageRepository();
        $this->chatRepository = new ChatRepository();
        $this->messageService = new MessageService($this->messageRepository, $this->chatRepository);
    }

    public function test_send_message_success(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active']);
        $guest2 = Guest::factory()->create(['status' => 'active']);
        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        $result = $this->messageService->sendMessage($chat->chat_id, $guest1->guest_id, 'Hello world');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('Hello world', $result['content']);
        $this->assertArrayHasKey('is_flagged', $result);
    }

    public function test_send_message_sanitizes_xss(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active']);
        $guest2 = Guest::factory()->create(['status' => 'active']);
        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        $maliciousContent = '<script>alert("XSS")</script>Hello';
        $result = $this->messageService->sendMessage($chat->chat_id, $guest1->guest_id, $maliciousContent);

        $this->assertNotNull($result);
        $this->assertStringNotContainsString('<script>', $result['content']);
        $this->assertStringContainsString('Hello', $result['content']);
    }

    public function test_send_message_detects_personal_info(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active']);
        $guest2 = Guest::factory()->create(['status' => 'active']);
        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        $result = $this->messageService->sendMessage($chat->chat_id, $guest1->guest_id, 'Call me at 555-123-4567');

        $this->assertNotNull($result);
        $this->assertTrue($result['is_flagged']);
    }

    public function test_send_message_fails_for_non_participant(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active']);
        $guest2 = Guest::factory()->create(['status' => 'active']);
        $guest3 = Guest::factory()->create(['status' => 'active']);
        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        $result = $this->messageService->sendMessage($chat->chat_id, $guest3->guest_id, 'Hello');

        $this->assertNull($result);
    }

    public function test_send_message_fails_for_ended_chat(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active']);
        $guest2 = Guest::factory()->create(['status' => 'active']);
        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        $result = $this->messageService->sendMessage($chat->chat_id, $guest1->guest_id, 'Hello');

        $this->assertNull($result);
    }

    public function test_get_messages_returns_formatted_messages(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active']);
        $guest2 = Guest::factory()->create(['status' => 'active']);
        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        Message::factory()->create([
            'chat_id' => $chat->chat_id,
            'sender_guest_id' => $guest1->guest_id,
            'content' => 'Hello',
        ]);

        $result = $this->messageService->getMessages($chat->chat_id, $guest1->guest_id);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertCount(1, $result['messages']);
        $this->assertEquals('you', $result['messages'][0]['sender']);
    }

    public function test_get_messages_paginated(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active']);
        $guest2 = Guest::factory()->create(['status' => 'active']);
        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        // Create 15 messages
        Message::factory()->count(15)->create([
            'chat_id' => $chat->chat_id,
            'sender_guest_id' => $guest1->guest_id,
        ]);

        $result = $this->messageService->getMessagesPaginated($chat->chat_id, $guest1->guest_id, 10);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('next_cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertTrue($result['has_more']);
        $this->assertCount(10, $result['messages']);
    }
}
