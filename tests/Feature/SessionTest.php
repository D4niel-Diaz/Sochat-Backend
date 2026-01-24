<?php

use App\Models\Guest;
use App\Repositories\GuestRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('can create a guest session', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/v1/guest/create');

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'data' => ['guest_id', 'session_token', 'expires_at'],
            'message',
        ])
        ->assertJson([
            'success' => true,
        ]);
});

test('can refresh a valid guest session', function () {
    /** @var Tests\TestCase $this */
    $guestRepository = app(GuestRepository::class);
    $guest = $guestRepository->create('127.0.0.1');

    $response = $this->withToken($guest->session_token)
        ->postJson('/api/v1/guest/refresh');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});

test('cannot refresh an invalid session', function () {
    /** @var Tests\TestCase $this */
    $response = $this->withToken('invalid_token')
        ->postJson('/api/v1/guest/refresh');

    $response->assertStatus(401);
});

test('cannot access protected routes without token', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/v1/chat/start');

    $response->assertStatus(401);
});
