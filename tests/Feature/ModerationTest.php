<?php

use App\Models\Chat;
use App\Models\Guest;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function activeChat(): array
{
    $a = Guest::factory()->create(['status' => 'active']);
    $b = Guest::factory()->create(['status' => 'active']);
    $chat = Chat::factory()->create(['guest_id_1' => $a->guest_id, 'guest_id_2' => $b->guest_id, 'status' => 'active']);

    return [$a, $b, $chat];
}

test('messages are stored as typed, without HTML entities', function () {
    [$a, , $chat] = activeChat();

    $this->withToken($a->session_token)
        ->postJson('/api/v1/chat/message', ['chat_id' => $chat->chat_id, 'content' => "I'm here & someone = fine"])
        ->assertCreated()
        ->assertJsonPath('data.content', "I'm here & someone = fine");
});

test('text with angle brackets is stored unchanged', function () {
    [$a, $b, $chat] = activeChat();

    $texts = ['i <3 you so much', 'if x<y and y>z', '<b>hi</b>', 'a < b'];

    foreach ($texts as $text) {
        $this->withToken($a->session_token)
            ->postJson('/api/v1/chat/message', ['chat_id' => $chat->chat_id, 'content' => $text])
            ->assertCreated()
            ->assertJsonPath('data.content', $text);
    }

    $stored = $this->withToken($b->session_token)
        ->getJson("/api/v1/chat/{$chat->chat_id}/messages")
        ->assertOk()
        ->json('data.messages.*.content');

    expect($stored)->toEqualCanonicalizing($texts);
});

test('clear personal info is rejected', function () {
    [$a, , $chat] = activeChat();

    $this->withToken($a->session_token)
        ->postJson('/api/v1/chat/message', ['chat_id' => $chat->chat_id, 'content' => 'mail me at juan@example.com'])
        ->assertStatus(400);

    expect(Message::count())->toBe(0);
});

test('social handles are delivered but flagged', function () {
    [$a, , $chat] = activeChat();

    $this->withToken($a->session_token)
        ->postJson('/api/v1/chat/message', ['chat_id' => $chat->chat_id, 'content' => 'add me @juan_dc'])
        ->assertCreated()
        ->assertJsonPath('data.is_flagged', true);

    $this->withToken($a->session_token)
        ->postJson('/api/v1/chat/message', ['chat_id' => $chat->chat_id, 'content' => 'meet @ 5pm'])
        ->assertCreated()
        ->assertJsonPath('data.is_flagged', false);
});

test('banned words are masked as whole words only', function () {
    config(['moderation.banned_words' => ['ass']]);
    [$a, , $chat] = activeChat();

    $this->withToken($a->session_token)
        ->postJson('/api/v1/chat/message', ['chat_id' => $chat->chat_id, 'content' => 'class ass'])
        ->assertCreated()
        ->assertJsonPath('data.content', 'class ***');
});
