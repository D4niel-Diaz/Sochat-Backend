<?php

use App\Models\Chat;
use App\Repositories\GuestRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function optedInGuests(int $count): array
{
    $guests = [];
    for ($i = 1; $i <= $count; $i++) {
        $guest = app(GuestRepository::class)->create("127.0.0.$i");
        test()->withToken($guest->session_token)->postJson('/api/v1/presence/opt-in', [
            'role' => 'learner',
            'subject' => 'General',
            'availability' => [9, 10],
        ])->assertOk();
        $guests[] = $guest;
    }

    return $guests;
}

test('every guest polling /chat/start ends up in exactly one chat', function () {
    $guests = optedInGuests(5);

    // Each guest polls twice, like the WebSocket retry loop does
    foreach ([1, 2] as $_) {
        foreach ($guests as $guest) {
            $this->withToken($guest->session_token)->postJson('/api/v1/chat/start')->assertOk();
        }
    }

    expect(Chat::where('status', 'active')->count())->toBe(2);

    foreach ($guests as $guest) {
        $chats = Chat::where('status', 'active')
            ->where(fn ($q) => $q->where('guest_id_1', $guest->guest_id)->orWhere('guest_id_2', $guest->guest_id))
            ->count();
        expect($chats)->toBeLessThanOrEqual(1);
    }

    expect(DB::table('presence')->where('is_waiting', true)->count())->toBe(1);
});

test('a candidate claimed by someone else is skipped, not double-matched', function () {
    [$a, $b, $c] = optedInGuests(3);

    // B was just claimed by a concurrent request (left the waiting pool)
    DB::table('presence')->where('guest_id', $b->guest_id)->update(['is_waiting' => false]);

    $this->withToken($a->session_token)->postJson('/api/v1/chat/start')
        ->assertOk()
        ->assertJsonPath('data.status', 'matched')
        ->assertJsonPath('data.partner_id', $c->guest_id);

    expect(Chat::where('guest_id_1', $b->guest_id)->orWhere('guest_id_2', $b->guest_id)->count())->toBe(0);
});

test('a forged X-Forwarded-For from an untrusted client does not bypass the guest limit', function () {
    config(['app.guest_create_per_minute' => 2]);

    $statuses = [];
    foreach (['198.51.100.1', '198.51.100.2', '198.51.100.3'] as $fakeIp) {
        $statuses[] = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->withHeader('X-Forwarded-For', $fakeIp)
            ->postJson('/api/v1/guest/create')
            ->status();
    }

    expect($statuses)->toBe([201, 201, 429]);
});

test('X-Forwarded-For from a trusted private proxy is used as the client IP', function () {
    config(['app.guest_create_per_minute' => 1]);

    foreach (['198.51.100.10', '198.51.100.11'] as $clientIp) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeader('X-Forwarded-For', $clientIp)
            ->postJson('/api/v1/guest/create')
            ->assertCreated();
    }
});
