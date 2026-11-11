<?php

use App\Models\Admin;
use App\Models\Chat;
use App\Models\Guest;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminToken($test): string
{
    Admin::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'correct-horse-battery',
        'role' => 'admin',
    ]);

    return $test->postJson('/api/v1/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk()->json('data.token');
}

test('admin token from login authorizes admin endpoints', function () {
    $token = adminToken($this);

    $this->withToken($token)->getJson('/api/v1/admin/metrics')
        ->assertOk()
        ->assertJsonStructure(['data' => ['online_users', 'active_chats', 'total_reports', 'pending_reports', 'banned_users']]);
});

test('admin login rejects wrong password without leaking details', function () {
    adminToken($this);

    $this->postJson('/api/v1/admin/login', ['email' => 'admin@example.com', 'password' => 'nope'])
        ->assertStatus(401)
        ->assertJson(['success' => false, 'message' => 'Invalid credentials']);
});

test('admin endpoints reject missing and guest tokens', function () {
    $guest = Guest::factory()->create();

    $this->getJson('/api/v1/admin/metrics')->assertStatus(401);
    $this->withToken($guest->session_token)->getJson('/api/v1/admin/metrics')->assertStatus(401);
});

test('logout revokes the admin token', function () {
    $token = adminToken($this);

    $this->withToken($token)->postJson('/api/v1/admin/logout')->assertOk();
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/admin/metrics')->assertStatus(401);
});

test('banned guests list never exposes session tokens', function () {
    $token = adminToken($this);
    Guest::factory()->create(['status' => 'banned']);

    $response = $this->withToken($token)->getJson('/api/v1/admin/banned-guests')->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('session_token');
});

test('admin active chats and flagged messages load their relations', function () {
    $token = adminToken($this);
    $a = Guest::factory()->create(['status' => 'active']);
    $b = Guest::factory()->create(['status' => 'active']);
    $chat = Chat::factory()->create(['guest_id_1' => $a->guest_id, 'guest_id_2' => $b->guest_id, 'status' => 'active']);
    Message::factory()->create(['chat_id' => $chat->chat_id, 'sender_guest_id' => $a->guest_id, 'is_flagged' => true]);

    $this->withToken($token)->getJson('/api/v1/admin/chats')
        ->assertOk()
        ->assertJsonPath('data.0.guest1.guest_id', $a->guest_id);

    $this->withToken($token)->getJson('/api/v1/admin/flagged-messages')
        ->assertOk()
        ->assertJsonPath('data.0.chat.chat_id', $chat->chat_id);
});
