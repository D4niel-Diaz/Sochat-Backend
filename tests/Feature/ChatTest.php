<?php

use App\Models\Chat;
use App\Models\Guest;
use App\Repositories\GuestRepository;
use App\Repositories\ChatRepository;
use App\Repositories\MessageRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can send a message in active chat', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $response = $this->withToken($guest1->session_token)
        ->postJson('/api/v1/chat/message', [
            'chat_id' => $chat->chat_id,
            'content' => 'Hello there!',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'data' => ['message_id', 'content', 'created_at', 'is_flagged'],
            'message',
        ]);
});

test('cannot send message exceeding 1000 characters', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $response = $this->withToken($guest1->session_token)
        ->postJson('/api/v1/chat/message', [
            'chat_id' => $chat->chat_id,
            'content' => str_repeat('a', 1001),
        ]);

    $response->assertStatus(422);
});

test('can retrieve messages from chat', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);
    $messageRepository = app(MessageRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $messageRepository->create($chat->chat_id, $guest1->guest_id, 'Hello there!');

    $response = $this->withToken($guest1->session_token)
        ->getJson("/api/v1/chat/{$chat->chat_id}/messages");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'messages',
            ],
            'message',
        ]);
});

test('cannot retrieve messages from non-participant chat', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');
    $guest3 = $guestRepository->create('127.0.0.3');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $response = $this->withToken($guest3->session_token)
        ->getJson("/api/v1/chat/{$chat->chat_id}/messages");

    $response->assertStatus(404);
});
