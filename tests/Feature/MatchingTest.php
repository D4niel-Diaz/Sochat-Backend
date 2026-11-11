<?php

use App\Models\Chat;
use App\Models\Guest;
use App\Repositories\GuestRepository;
use App\Repositories\ChatRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can start a chat', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');

    foreach ([$guest1, $guest2] as $guest) {
        $this->withToken($guest->session_token)->postJson('/api/v1/presence/opt-in', [
            'role' => 'learner',
            'subject' => 'General',
            'availability' => [9, 10],
        ])->assertOk();
    }

    $response = $this->withToken($guest1->session_token)
        ->postJson('/api/v1/chat/start');

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'matched')
        ->assertJsonPath('data.partner_id', $guest2->guest_id);
});

test('the matched partner polling /chat/start gets the same chat', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');

    foreach ([$guest1, $guest2] as $guest) {
        $this->withToken($guest->session_token)->postJson('/api/v1/presence/opt-in', [
            'role' => 'learner',
            'subject' => 'General',
            'availability' => [9, 10],
        ])->assertOk();
    }

    $chatId = $this->withToken($guest1->session_token)
        ->postJson('/api/v1/chat/start')
        ->assertJsonPath('data.status', 'matched')
        ->json('data.chat_id');

    // REST-fallback partner: matching removed them from the waiting pool
    $this->withToken($guest2->session_token)
        ->postJson('/api/v1/chat/start')
        ->assertOk()
        ->assertJsonPath('data.status', 'already_matched')
        ->assertJsonPath('data.chat_id', $chatId)
        ->assertJsonPath('data.partner_id', $guest1->guest_id);
});

test('cannot start chat with invalid session token', function () {
    /** @var Tests\TestCase $this */
    $response = $this->withToken('invalid_token')
        ->postJson('/api/v1/chat/start');

    $response->assertStatus(401);
});

test('can end a chat as participant', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $response = $this->withToken($guest1->session_token)
        ->postJson('/api/v1/chat/end', [
            'chat_id' => $chat->chat_id,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});

test('cannot end chat as non-participant', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');
    $guest3 = $guestRepository->create('127.0.0.3');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $response = $this->withToken($guest3->session_token)
        ->postJson('/api/v1/chat/end', [
            'chat_id' => $chat->chat_id,
        ]);

    $response->assertStatus(404);
});
