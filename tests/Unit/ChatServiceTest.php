<?php

namespace Tests\Unit;

use App\Services\ChatService;
use App\Repositories\ChatRepository;
use App\Repositories\GuestRepository;
use App\Services\PresenceService;
use App\Models\Chat;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChatService $chatService;
    private ChatRepository $chatRepository;
    private GuestRepository $guestRepository;
    private PresenceService $presenceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guestRepository = new GuestRepository();
        $this->chatRepository = new ChatRepository();
        $this->presenceService = new PresenceService();
        $this->chatService = new ChatService(
            $this->guestRepository,
            $this->chatRepository,
            $this->presenceService
        );
    }

    public function test_find_match_returns_waiting_when_no_partner_available(): void
    {
        $guest = Guest::factory()->create(['status' => 'waiting', 'expires_at' => now()->addHours(24)]);

        $this->presenceService->markUserOnline($guest->guest_id);
        $this->presenceService->addToWaitingPool($guest->guest_id);

        $result = $this->chatService->findMatch($guest->guest_id);

        $this->assertNotNull($result);
        $this->assertEquals('waiting', $result['status']);
    }

    public function test_find_match_creates_chat_when_partner_available(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'waiting', 'expires_at' => now()->addHours(24)]);
        $guest2 = Guest::factory()->create(['status' => 'waiting', 'expires_at' => now()->addHours(24)]);

        $this->presenceService->markUserOnline($guest1->guest_id);
        $this->presenceService->markUserOnline($guest2->guest_id);
        $this->presenceService->addToWaitingPool($guest1->guest_id);
        $this->presenceService->addToWaitingPool($guest2->guest_id);

        $result = $this->chatService->findMatch($guest1->guest_id);

        $this->assertNotNull($result);
        $this->assertEquals('matched', $result['status']);
        $this->assertArrayHasKey('chat_id', $result);
        $this->assertArrayHasKey('partner_id', $result);
    }

    public function test_find_match_fails_for_banned_guest(): void
    {
        $guest = Guest::factory()->create(['status' => 'banned']);

        $result = $this->chatService->findMatch($guest->guest_id);

        $this->assertNull($result);
    }

    public function test_find_match_fails_for_expired_guest(): void
    {
        $guest = Guest::factory()->create([
            'status' => 'waiting',
            'expires_at' => now()->subHours(1),
        ]);

        $result = $this->chatService->findMatch($guest->guest_id);

        $this->assertNull($result);
    }

    public function test_find_match_fails_for_guest_not_online(): void
    {
        $guest = Guest::factory()->create(['status' => 'waiting', 'expires_at' => now()->addHours(24)]);

        // Guest not marked online

        $result = $this->chatService->findMatch($guest->guest_id);

        $this->assertNotNull($result);
        $this->assertEquals('not_opted_in', $result['status']);
        $this->assertEquals('You must click "Start Chat" to begin matching', $result['message']);
    }

    public function test_find_match_fails_for_guest_not_in_waiting_pool(): void
    {
        $guest = Guest::factory()->create(['status' => 'idle', 'expires_at' => now()->addHours(24)]);

        $this->presenceService->markUserOnline($guest->guest_id);

        $result = $this->chatService->findMatch($guest->guest_id);

        $this->assertNotNull($result);
        $this->assertEquals('not_opted_in', $result['status']);
        $this->assertEquals('You must click "Start Chat" to begin matching', $result['message']);
    }

    public function test_find_match_fails_for_guest_with_active_chat(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);
        $guest2 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);

        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        $result = $this->chatService->findMatch($guest1->guest_id);

        $this->assertNotNull($result);
        $this->assertEquals('not_opted_in', $result['status']);
        $this->assertEquals('You must click "Start Chat" to begin matching', $result['message']);
    }

    public function test_end_chat_success(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);
        $guest2 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);

        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        $result = $this->chatService->endChat($chat->chat_id, $guest1->guest_id);

        $this->assertTrue($result);

        $chat->refresh();
        $this->assertEquals('ended', $chat->status);
        $this->assertNotNull($chat->ended_at);

        $guest1->refresh();
        $guest2->refresh();
        $this->assertEquals('idle', $guest1->status);
        $this->assertEquals('idle', $guest2->status);
    }

    public function test_end_chat_fails_for_non_participant(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);
        $guest2 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);
        $guest3 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);

        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'active',
        ]);

        $result = $this->chatService->endChat($chat->chat_id, $guest3->guest_id);

        $this->assertFalse($result);
    }

    public function test_end_chat_fails_for_ended_chat(): void
    {
        $guest1 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);
        $guest2 = Guest::factory()->create(['status' => 'active', 'expires_at' => now()->addHours(24)]);

        $chat = Chat::factory()->create([
            'guest_id_1' => $guest1->guest_id,
            'guest_id_2' => $guest2->guest_id,
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        $result = $this->chatService->endChat($chat->chat_id, $guest1->guest_id);

        $this->assertFalse($result);
    }

    public function test_find_match_prevents_self_matching(): void
    {
        $guest = Guest::factory()->create(['status' => 'waiting', 'expires_at' => now()->addHours(24)]);

        $this->presenceService->markUserOnline($guest->guest_id);
        $this->presenceService->addToWaitingPool($guest->guest_id);

        $result = $this->chatService->findMatch($guest->guest_id);

        $this->assertEquals('waiting', $result['status']);
    }
}
