<?php

namespace App\Services;

use App\Exceptions\MatchClaimFailed;
use App\Repositories\GuestRepository;
use App\Repositories\ChatRepository;
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

    public function findMatch(string $guestId, ?string $role = null, ?string $subject = null, ?array $availability = null): ?array
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

        // STEP 2: Already matched? Matching removes both guests from the waiting
        // pool, so this must come before the opt-in check or the partner who is
        // polling /chat/start would be told "not_opted_in" forever.
        $existingChat = $this->chatRepository->findActiveByGuestId($guestId);
        if ($existingChat) {
            return [
                'chat_id' => $existingChat->chat_id,
                'partner_id' => $existingChat->getPartnerId($guestId),
                'status' => 'already_matched',
            ];
        }

        // STEP 3: CRITICAL - Verify user has opted in (mutual intent)
        if (!$this->presenceService->isInWaitingPool($guestId)) {
            Log::warning('Match attempt failed: User has not opted in', ['guest_id' => $guestId]);
            return [
                'status' => 'not_opted_in',
                'message' => 'You must click "Find Match" to begin matching'
            ];
        }

        // STEP 4: CRITICAL - Verify user is online (real-time presence)
        if (!$this->presenceService->isUserOnline($guestId)) {
            Log::warning('Match attempt failed: User not online', ['guest_id' => $guestId]);
            return [
                'status' => 'not_online',
                'message' => 'Connection lost. Please refresh the page.'
            ];
        }

        // STEP 5: Validate matching parameters (with defaults for simplified matching)
        // Role is optional now (simplified matching - any user can match with any user)
        if ($role && !in_array($role, ['tutor', 'learner'])) {
            $role = 'learner'; // Default to learner if invalid
        }
        if (!$role) {
            $role = 'learner'; // Default to learner if not provided
        }

        if (!$subject || empty(trim($subject))) {
            $subject = 'General'; // Default to General if not provided
        }

        if (!$availability || !is_array($availability) || empty($availability)) {
            $availability = [9, 10, 11, 12, 13, 14, 15, 16, 17, 18]; // Default availability
        }

        // STEP 6: Read candidates without locks, then claim the pair atomically
        // (claimPair). Locking candidate rows here is not enough: two requests
        // (A looking at B, B looking at A) lock different rows and both create a
        // chat. NOTE: any role combination matches (simplified anonymous chat).
        // Called every couple of seconds per waiting user by the WebSocket
        // server, so no per-attempt logging here.
        try {
            $candidates = DB::table('presence')
                ->where('is_waiting', true)
                ->where('is_online', true)
                ->where('expires_at', '>', now())
                ->where(function ($query) use ($subject) {
                    // Same subject (case-insensitive), or either side is "General"
                    $query->whereRaw('LOWER(subject) = LOWER(?)', [$subject])
                        ->orWhere('subject', 'General')
                        ->orWhereRaw('LOWER(?) = LOWER(subject)', ['General']);
                })
                ->where('guest_id', '!=', $guestId)
                ->orderBy('last_seen_at', 'asc')
                ->limit(20)
                ->get();

            foreach ($candidates as $candidate) {
                $candidateAvailability = json_decode($candidate->availability, true) ?? [];
                if (empty(array_intersect($availability, $candidateAvailability))) {
                    continue;
                }

                $partnerGuestId = $candidate->guest_id;
                $partnerGuest = $this->guestRepository->findByGuestId($partnerGuestId);
                if (!$partnerGuest || $partnerGuest->isBanned()) {
                    $this->presenceService->removeFromWaitingPool($partnerGuestId);
                    Log::warning('Invalid partner found, removed from pool', ['partner_guest_id' => $partnerGuestId]);
                    continue;
                }

                $chat = $this->claimPair($guestId, $partnerGuestId);
                if ($chat) {
                    return [
                        'chat_id' => $chat->chat_id,
                        'partner_id' => $partnerGuestId,
                        'status' => 'matched',
                        'started_at' => $chat->started_at->toISOString(),
                        'message' => 'You have been matched!'
                    ];
                }

                // Claim lost. If a concurrent request matched *us*, hand that chat back.
                $existingChat = $this->chatRepository->findActiveByGuestId($guestId);
                if ($existingChat) {
                    return [
                        'chat_id' => $existingChat->chat_id,
                        'partner_id' => $existingChat->getPartnerId($guestId),
                        'status' => 'already_matched',
                    ];
                }
                if (!$this->presenceService->isInWaitingPool($guestId)) {
                    break; // opted out meanwhile
                }
                // Otherwise the candidate was taken; try the next one.
            }
        } catch (\Throwable $e) {
            Log::error('Error during matching', [
                'guest_id' => $guestId,
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 'waiting',
                'message' => 'Failed to find match. Please try again.'
            ];
        }

        $message = $role === 'learner'
            ? 'No available tutor online for this subject. Please try again later.'
            : 'No available learner online for this subject. Please try again later.';

        return [
            'status' => 'waiting',
            'available_users' => 0,
            'message' => $message
        ];
    }

    /**
     * Atomically take both guests out of the waiting pool and create their chat.
     *
     * The conditional UPDATE is the lock: concurrent requests touching the same
     * presence row wait for each other, and the loser re-checks is_waiting after
     * the winner commits (MySQL, Postgres and SQLite all behave this way), so it
     * updates fewer than 2 rows and rolls back. A guest can therefore never be
     * claimed by two chats. Deadlocks between crossing claims are retried.
     */
    private function claimPair(string $guestId, string $partnerGuestId): ?\App\Models\Chat
    {
        try {
            return DB::transaction(function () use ($guestId, $partnerGuestId) {
                $claimed = DB::table('presence')
                    ->whereIn('guest_id', [$guestId, $partnerGuestId])
                    ->where('is_waiting', true)
                    ->where('is_online', true)
                    ->where('expires_at', '>', now())
                    ->update(['is_waiting' => false]);

                if ($claimed !== 2) {
                    throw new MatchClaimFailed();
                }

                // Defensive: opt-in already refuses guests who are in a chat
                if ($this->chatRepository->findActiveByGuestId($guestId) ||
                    $this->chatRepository->findActiveByGuestId($partnerGuestId)) {
                    throw new MatchClaimFailed();
                }

                $chat = $this->chatRepository->create($guestId, $partnerGuestId);
                $this->guestRepository->updateStatus($guestId, 'active');
                $this->guestRepository->updateStatus($partnerGuestId, 'active');

                Log::info('Chat created successfully', [
                    'chat_id' => $chat->chat_id,
                    'guest_id_1' => $guestId,
                    'guest_id_2' => $partnerGuestId
                ]);

                return $chat;
            }, 3);
        } catch (MatchClaimFailed) {
            return null;
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

        return $result;
    }
}
