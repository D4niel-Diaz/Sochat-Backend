<?php

use App\Models\Chat;
use App\Models\Message;
use App\Models\Report;
use App\Repositories\ChatRepository;
use App\Repositories\GuestRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A chat with two messages; `endedAgo` null = still active. */
function chatWithMessages(?\DateInterval $endedAgo, ?string $reportStatus = null): Chat
{
    $guests = app(GuestRepository::class);
    $guest1 = $guests->create('127.0.0.1');
    $guest2 = $guests->create('127.0.0.2');
    $chat = app(ChatRepository::class)->create($guest1->guest_id, $guest2->guest_id);

    foreach ([$guest1, $guest2] as $guest) {
        Message::create([
            'chat_id' => $chat->chat_id,
            'sender_guest_id' => $guest->guest_id,
            'content' => 'hello',
        ]);
    }

    if ($endedAgo) {
        $chat->forceFill([
            'status' => 'ended',
            'ended_at' => now()->sub($endedAgo),
            'ended_by' => $guest1->guest_id,
        ])->save();
    }

    if ($reportStatus) {
        Report::create([
            'chat_id' => $chat->chat_id,
            'reporter_guest_id' => $guest1->guest_id,
            'reported_guest_id' => $guest2->guest_id,
            'reason' => 'Harassment or bullying',
            'status' => $reportStatus,
        ]);
    }

    return $chat;
}

function messageCount(Chat $chat): int
{
    return Message::where('chat_id', $chat->chat_id)->count();
}

test('deletes messages from chats that ended over an hour ago', function () {
    $old = chatWithMessages(new DateInterval('PT2H'));

    $this->artisan('chats:prune-messages')->assertSuccessful();

    expect(messageCount($old))->toBe(0);
    expect(Chat::find($old->chat_id))->not->toBeNull(); // the chat record itself stays
});

test('keeps messages from recent and active chats', function () {
    $recent = chatWithMessages(new DateInterval('PT30M'));
    $active = chatWithMessages(null);

    $this->artisan('chats:prune-messages')->assertSuccessful();

    expect(messageCount($recent))->toBe(2);
    expect(messageCount($active))->toBe(2);
});

test('keeps messages while a report is open, deletes them once resolved', function () {
    $pending = chatWithMessages(new DateInterval('PT2H'), 'pending');
    $reviewing = chatWithMessages(new DateInterval('PT2H'), 'reviewing');
    $resolved = chatWithMessages(new DateInterval('PT2H'), 'resolved');

    $this->artisan('chats:prune-messages')->assertSuccessful();

    expect(messageCount($pending))->toBe(2);
    expect(messageCount($reviewing))->toBe(2);
    expect(messageCount($resolved))->toBe(0);
});

test('deletes reported chat messages after the hard limit', function () {
    $stale = chatWithMessages(new DateInterval('P31D'), 'pending');

    $this->artisan('chats:prune-messages')->assertSuccessful();

    expect(messageCount($stale))->toBe(0);
});
