<?php

use App\Models\Chat;
use App\Models\Guest;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function chatBetween(Guest $a, Guest $b, array $attrs = []): Chat
{
    return Chat::factory()->create(array_merge([
        'guest_id_1' => $a->guest_id,
        'guest_id_2' => $b->guest_id,
        'status' => 'active',
    ], $attrs));
}

test('guest validate returns the guest without extending the session', function () {
    $guest = Guest::factory()->create(['expires_at' => now()->addHour()]);
    $before = $guest->expires_at->toIso8601String();

    $this->withToken($guest->session_token)->getJson('/api/v1/guest/validate')
        ->assertOk()
        ->assertJsonPath('data.guest_id', $guest->guest_id);

    expect($guest->fresh()->expires_at->toIso8601String())->toBe($before);
});

test('guest validate rejects banned guests', function () {
    $guest = Guest::factory()->create(['status' => 'banned']);

    $this->withToken($guest->session_token)->getJson('/api/v1/guest/validate')->assertStatus(403);
});

test('one reporter cannot report the same chat twice', function () {
    $a = Guest::factory()->create(['status' => 'active']);
    $b = Guest::factory()->create(['status' => 'active']);
    $chat = chatBetween($a, $b);
    $body = ['chat_id' => $chat->chat_id, 'reason' => 'spam spam spam spam'];

    $this->withToken($a->session_token)->postJson('/api/v1/report', $body)->assertCreated();
    $this->withToken($a->session_token)->postJson('/api/v1/report', $body)->assertStatus(409);

    expect($b->fresh()->status)->not->toBe('banned');
});

test('auto-ban needs reports from distinct users', function () {
    $target = Guest::factory()->create(['status' => 'active']);

    foreach (range(1, 3) as $i) {
        $reporter = Guest::factory()->create(['status' => 'active']);
        $chat = chatBetween($reporter, $target, $i < 3 ? ['status' => 'ended', 'ended_at' => now()] : []);

        $response = $this->withToken($reporter->session_token)
            ->postJson('/api/v1/report', ['chat_id' => $chat->chat_id, 'reason' => 'harassment in chat'])
            ->assertCreated();
    }

    $response->assertJsonPath('data.auto_banned', true);
    expect($target->fresh()->status)->toBe('banned');
});

test('ending a chat sets both guests idle', function () {
    $a = Guest::factory()->create(['status' => 'active']);
    $b = Guest::factory()->create(['status' => 'active']);
    $chat = chatBetween($a, $b);

    $this->withToken($a->session_token)->postJson('/api/v1/chat/end', ['chat_id' => $chat->chat_id])->assertOk();

    expect($a->fresh()->status)->toBe('idle')
        ->and($b->fresh()->status)->toBe('idle');
});

test('messages report chat status so polling clients see the chat end', function () {
    $a = Guest::factory()->create(['status' => 'active']);
    $b = Guest::factory()->create(['status' => 'active']);
    $chat = chatBetween($a, $b, ['status' => 'ended', 'ended_at' => now()]);

    $this->withToken($a->session_token)->getJson("/api/v1/chat/{$chat->chat_id}/messages")
        ->assertOk()
        ->assertJsonPath('data.chat_status', 'ended');
});

test('message pagination uses an id cursor and keeps same-second messages', function () {
    $a = Guest::factory()->create(['status' => 'active']);
    $b = Guest::factory()->create(['status' => 'active']);
    $chat = chatBetween($a, $b);
    $sameSecond = now();
    foreach (range(1, 5) as $i) {
        Message::factory()->create([
            'chat_id' => $chat->chat_id,
            'sender_guest_id' => $a->guest_id,
            'content' => "m{$i}",
            'created_at' => $sameSecond,
        ]);
    }

    $page1 = $this->withToken($a->session_token)->getJson("/api/v1/chat/{$chat->chat_id}/messages?limit=3")->assertOk();
    $cursor = $page1->json('data.next_cursor');
    $page2 = $this->withToken($a->session_token)->getJson("/api/v1/chat/{$chat->chat_id}/messages?limit=3&cursor={$cursor}")->assertOk();

    $contents = array_merge(
        array_column($page2->json('data.messages'), 'content'),
        array_column($page1->json('data.messages'), 'content'),
    );
    expect($contents)->toBe(['m1', 'm2', 'm3', 'm4', 'm5']);
});

test('non-numeric chat id is a 404, not a 500', function () {
    $guest = Guest::factory()->create();

    $this->withToken($guest->session_token)->getJson('/api/v1/chat/abc/messages')->assertNotFound();
});

test('cors preflight from an unknown origin is refused without crashing', function () {
    $response = $this->call('OPTIONS', '/api/v1/guest/create', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($response->getStatusCode())->toBeLessThan(500)
        ->and($response->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});

test('cors allows this project\'s vercel previews only', function () {
    $allowed = $this->call('OPTIONS', '/api/v1/guest/create', [], [], [], [
        'HTTP_ORIGIN' => 'https://sochat-git-main-abc.vercel.app',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);
    $other = $this->call('OPTIONS', '/api/v1/guest/create', [], [], [], [
        'HTTP_ORIGIN' => 'https://attacker.vercel.app',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($allowed->headers->get('Access-Control-Allow-Origin'))->toBe('https://sochat-git-main-abc.vercel.app')
        ->and($other->headers->get('Access-Control-Allow-Origin'))->toBeNull();
});
