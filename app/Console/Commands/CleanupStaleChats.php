<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PresenceService;
use App\Repositories\ChatRepository;
use App\Repositories\GuestRepository;
use App\Services\ChatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupStaleChats extends Command
{
    protected $signature = 'chats:cleanup-stale';
    protected $description = 'Clean up stale chats where users have been offline for too long';

    private const CHAT_TIMEOUT_MINUTES = 10;

    public function __construct(
        private PresenceService $presenceService,
        private ChatRepository $chatRepository,
        private GuestRepository $guestRepository,
        private ChatService $chatService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting stale chat cleanup...');

        $timeout = now()->subMinutes(self::CHAT_TIMEOUT_MINUTES);

        // Find all active chats
        $activeChats = $this->chatRepository->getActiveChats();

        $cleanedCount = 0;

        foreach ($activeChats as $chat) {
            $guest1Id = $chat->guest_id_1;
            $guest2Id = $chat->guest_id_2;

            // Check if either user is offline or expired
            $guest1Online = $this->presenceService->isUserOnline($guest1Id);
            $guest2Online = $this->presenceService->isUserOnline($guest2Id);

            $guest1Expired = $chat->guest1 && $chat->guest1->expires_at && $chat->guest1->expires_at->isPast();
            $guest2Expired = $chat->guest2 && $chat->guest2->expires_at && $chat->guest2->expires_at->isPast();

            // End chat if either user is offline or expired
            if (!$guest1Online || !$guest2Online || $guest1Expired || $guest2Expired) {
                Log::info('Ending stale chat', [
                    'chat_id' => $chat->chat_id,
                    'guest1_online' => $guest1Online,
                    'guest2_online' => $guest2Online,
                    'guest1_expired' => $guest1Expired,
                    'guest2_expired' => $guest2Expired,
                ]);

                try {
                    // Use either user to end the chat
                    $endedBy = $guest1Online ? $guest1Id : $guest2Id;
                    $this->chatService->endChat($chat->chat_id, $endedBy);
                    $cleanedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to end stale chat', [
                        'chat_id' => $chat->chat_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Clean up stale presence entries
        $stalePresence = $this->presenceService->cleanupStaleUsers();

        $this->info("Cleanup complete. Ended {$cleanedCount} stale chats and removed {$stalePresence} stale presence entries.");

        return Command::SUCCESS;
    }
}
