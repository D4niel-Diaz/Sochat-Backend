<?php

namespace App\Services;

use App\Repositories\GuestRepository;
use App\Repositories\ChatRepository;
use App\Events\UserMatched;
use App\Events\ChatEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatService
{
    protected GuestRepository $guestRepository;
    protected ChatRepository $chatRepository;
    protected PresenceService $presenceService;

    public function __construct(GuestRepository $guestRepository, ChatRepository $chatRepository, PresenceService $presenceService)
    {
        $this->guestRepository = $guestRepository;
        $this->chatRepository = $chatRepository;
        $this->presenceService = $presenceService;
    }

    public function findMatch(string $guestId): ?array
    {
        // STEP 1: Validate current user
        $guest = $this->guestRepository->findByGuestId($guestId);
        if (!$guest || $guest->isBanned()) {
            Log::warning('Match attempt failed: Guest not found or banned', ['guest_id' => $guestId]);
            return null;
        }

        if ($guest->expires_at && $guest->expires_at->isPast()) {
            Log::warning('Match attempt failed: Guest session expired', ['guest_id' => $guestId]);
            return null;
        }

        // STEP 2: CRITICAL - Verify user has opted in (mutual intent)
        if (!$this->presenceService->isInWaitingPool($guestId)) {
            Log::warning('Match attempt failed: User has not opted in', ['guest_id' => $guestId]);
            return [
                'status' => 'not_opted_in',
                'message' => 'You must click "Start Chat" to begin matching'
            ];
        }

        // STEP 3: CRITICAL - Verify user is online (real-time presence)
        if (!$this->presenceService->isUserOnline($guestId)) {
            Log::warning('Match attempt failed: User not online', ['guest_id' => $guestId]);
            return [
                'status' => 'not_online',
                'message' => 'Connection lost. Please refresh the page.'
            ];
        }

        // STEP 4: Check for existing active chat BEFORE any state changes
        $existingChat = $this->chatRepository->findActiveByGuestId($guestId);
        if ($existingChat) {
            Log::info('Guest already in active chat', [
                'guest_id' => $guestId,
                'chat_id' => $existingChat->chat_id
            ]);
            return [
                'chat_id' => $existingChat->chat_id,
                'partner_id' => $existingChat->getPartnerId($guestId),
                'status' => 'already_matched',
            ];
        }

        // STEP 5: Find and lock available match from presence waiting pool
        // CRITICAL: Use atomic operation with FOR UPDATE to prevent race conditions
        $partnerGuestId = null;
        
        try {
            $partnerGuestId = DB::transaction(function () use ($guestId) {
                // Get waiting users with row-level locking
                $waitingUsers = DB::table('presence')
                    ->where('is_waiting', true)
                    ->where('is_online', true)
                    ->where('expires_at', '>', now())
                    ->where('guest_id', '!=', $guestId)
                    ->orderBy('last_seen_at', 'asc')
                    ->lockForUpdate()
                    ->pluck('guest_id')
                    ->toArray();

                Log::info('Match attempt initiated with lock', [
                    'guest_id' => $guestId,
                    'waiting_users' => count($waitingUsers)
                ]);

                if (empty($waitingUsers)) {
                    Log::info('No match available', [
                        'guest_id' => $guestId,
                        'available_users' => 0
                    ]);
                    return null;
                }

                // Find first valid partner
                foreach ($waitingUsers as $potentialPartnerId) {
                    $partnerGuest = $this->guestRepository->findByGuestId($potentialPartnerId);

                    // Skip if partner is invalid or banned
                    if (!$partnerGuest || $partnerGuest->isBanned()) {
                        $this->presenceService->removeFromWaitingPool($potentialPartnerId);
                        Log::warning('Invalid partner found, removed from pool', ['partner_guest_id' => $potentialPartnerId]);
                        continue;
                    }

                    // CRITICAL: Check if partner is still online and in waiting pool
                    if (!$this->presenceService->isUserOnline($potentialPartnerId) ||
                        !$this->presenceService->isInWaitingPool($potentialPartnerId)) {
                        $this->presenceService->removeFromWaitingPool($potentialPartnerId);
                        Log::info('Partner no longer available, removed from pool', ['partner_guest_id' => $potentialPartnerId]);
                        continue;
                    }

                    // CRITICAL: Check if partner already has an active chat
                    $partnerExistingChat = $this->chatRepository->findActiveByGuestId($potentialPartnerId);
                    if ($partnerExistingChat) {
                        $this->presenceService->removeFromWaitingPool($potentialPartnerId);
                        Log::info('Partner already in active chat, removed from pool', [
                            'partner_guest_id' => $potentialPartnerId,
                            'chat_id' => $partnerExistingChat->chat_id
                        ]);
                        continue;
                    }

                    // Found a valid partner
                    Log::info('Valid partner found', ['partner_guest_id' => $potentialPartnerId]);
                    return $potentialPartnerId;
                }

                return null;
            });
        } catch (\Exception $e) {
            Log::error('Error during partner selection', [
                'guest_id' => $guestId,
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 'waiting',
                'message' => 'Failed to find match. Please try again.'
            ];
        }

        if (!$partnerGuestId) {
            Log::info('No valid partner found after search', [
                'guest_id' => $guestId
            ]);

            return [
                'status' => 'waiting',
                'available_users' => 0,
                'message' => 'Waiting for another active user to start a chat...'
            ];
        }

        // STEP 6: Match confirmed - create chat with transaction and proper locking
        try {
            $chat = DB::transaction(function () use ($guestId, $partnerGuestId) {
                // Final verification: both users must still be in waiting pool
                if (!$this->presenceService->isInWaitingPool($guestId) || 
                    !$this->presenceService->isInWaitingPool($partnerGuestId)) {
                    Log::info('One or both users opted out during transaction', [
                        'guest_id' => $guestId,
                        'partner_guest_id' => $partnerGuestId
                    ]);
                    throw new \Exception('Users opted out during transaction');
                }

                // CRITICAL: Check if a chat already exists for either user (prevent duplicates)
                $existingChat1 = $this->chatRepository->findActiveByGuestId($guestId);
                $existingChat2 = $this->chatRepository->findActiveByGuestId($partnerGuestId);
                if ($existingChat1 || $existingChat2) {
                    Log::info('Chat already created by concurrent request', [
                        'guest_id' => $guestId,
                        'partner_guest_id' => $partnerGuestId,
                        'existing_chat_1' => $existingChat1?->chat_id,
                        'existing_chat_2' => $existingChat2?->chat_id
                    ]);
                    throw new \Exception('Chat already exists');
                }

                // Create the chat
                $chat = $this->chatRepository->create($guestId, $partnerGuestId);
                
                // Update both users' status to active
                $this->guestRepository->updateStatus($guestId, 'active');
                $this->guestRepository->updateStatus($partnerGuestId, 'active');
                
                // Remove both from waiting pool (they're now matched)
                $this->presenceService->removeFromWaitingPool($guestId);
                $this->presenceService->removeFromWaitingPool($partnerGuestId);

                Log::info('Chat created successfully', [
                    'chat_id' => $chat->chat_id,
                    'guest_id_1' => $guestId,
                    'guest_id_2' => $partnerGuestId
                ]);

                return $chat;
            });

            // STEP 7: Broadcast match event to both users
            event(new UserMatched($chat));

            return [
                'chat_id' => $chat->chat_id,
                'partner_id' => $chat->getPartnerId($guestId),
                'status' => 'matched',
                'message' => 'You have been matched!'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create chat', [
                'guest_id' => $guestId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'waiting',
                'message' => 'Failed to create match. Please try again.'
            ];
        }
    }

    public function endChat(int $chatId, string $guestId): bool
    {
        $chat = $this->chatRepository->findById($chatId);
        if (!$chat || !$chat->isParticipant($guestId)) {
            Log::warning('End chat failed: Chat not found or user not participant', [
                'chat_id' => $chatId,
                'guest_id' => $guestId
            ]);
            return false;
        }

        // CRITICAL: Verify chat is still active before ending
        if (!$chat->isActive()) {
            Log::info('Chat already ended, skipping', [
                'chat_id' => $chatId,
                'status' => $chat->status,
                'ended_at' => $chat->ended_at
            ]);
            return false;
        }

        // Use transaction to ensure atomic state updates
        $result = DB::transaction(function () use ($chatId, $guestId, $chat) {
            // End the chat
            $chat->end($guestId);

            // Get both participant IDs
            $partnerId = $chat->getPartnerId($guestId);
            $guest1Id = $chat->guest_id_1;
            $guest2Id = $chat->guest_id_2;

            // CRITICAL: Set both users to 'idle' status
            $this->guestRepository->updateStatus($guest1Id, 'idle');
            $this->guestRepository->updateStatus($guest2Id, 'idle');

            // CRITICAL: Remove both users from waiting pool
            // This allows them to opt-in again and be matched with new users
            $this->presenceService->removeFromWaitingPool($guest1Id);
            $this->presenceService->removeFromWaitingPool($guest2Id);

            // CRITICAL: Mark both users as offline to prevent ghost users
            $this->presenceService->markUserOffline($guest1Id);
            $this->presenceService->markUserOffline($guest2Id);

            Log::info('Chat ended successfully with full cleanup', [
                'chat_id' => $chatId,
                'ended_by' => $guestId,
                'guest_1_id' => $guest1Id,
                'guest_2_id' => $guest2Id,
                'timestamp' => now()
            ]);

            return true;
        });

        // Broadcast chat ended event to both participants
        if ($result) {
            event(new ChatEnded($chat, $guestId));
        }

        return $result;
    }

    public function getOnlineCount(): int
    {
        return 0;
    }
}
