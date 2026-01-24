<?php

use App\Models\Chat;
use App\Models\Guest;
use App\Repositories\GuestRepository;
use App\Repositories\ChatRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('can submit a report with valid reason', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $response = $this->withToken($guest1->session_token)
        ->postJson('/api/v1/report', [
            'chat_id' => $chat->chat_id,
            'reason' => 'This user was inappropriate and rude during our conversation.',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'data' => ['report_id', 'auto_banned'],
            'message',
        ]);
});

test('cannot submit report with reason less than 10 characters', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $response = $this->withToken($guest1->session_token)
        ->postJson('/api/v1/report', [
            'chat_id' => $chat->chat_id,
            'reason' => 'Short',
        ]);

    $response->assertStatus(422);
});

test('cannot submit report as non-participant', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $chatRepository = app(ChatRepository::class);

    $guest1 = $guestRepository->create('127.0.0.1');
    $guest2 = $guestRepository->create('127.0.0.2');
    $guest3 = $guestRepository->create('127.0.0.3');

    $chat = $chatRepository->create($guest1->guest_id, $guest2->guest_id);

    $response = $this->withToken($guest3->session_token)
        ->postJson('/api/v1/report', [
            'chat_id' => $chat->chat_id,
            'reason' => 'This user was inappropriate.',
        ]);

    $response->assertStatus(404);
});
