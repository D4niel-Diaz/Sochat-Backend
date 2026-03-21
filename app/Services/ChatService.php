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

    public function __construct(
        GuestRepository $guestRepository,
        ChatRepository $chatRepository,
        PresenceService $presenceService
    ) {
        $this->guestRepository = $guestRepository;
        $this->chatRepository  = $chatRepository;
        $this->presenceService = $presenceService;
    }

    /**
     * Find a match for the given guest and immediately create the chat.
     *
     * CRITICAL: Partner selection AND chat creation happen inside a SINGLE
     * DB transaction with lockForUpdate to prevent the TOCTOU race condition
     * that existed when these were two separate transactions.
     */
    public function findMatch(
        string $guestId,
        ?string $role         = null,
        ?string $subject      = null,
        ?array  $availability = null
    ): ?array {
        // --- Pre-flight validation (cheap, no locks needed) ---------------

        $guest = $this->guestRepository->findByGuestId($guestId);
        if (!$guest || $guest->isBanned()) {
            Log::warning('Match attempt failed: guest not found or banned', ['guest_id' => $guestId]);
            return null;
        }

        if ($guest->expires_at && $guest->expires_at->isPast()) {
            Log::warning('Match attempt failed: guest session expired', ['guest_id' => $guestId]);
            return null;
        }

        if (!$this->presenceService->isInWaitingPool($guestId)) {
            Log::warning('Match attempt failed: user not opted in', ['guest_id' => $guestId]);
            return [
                'status'  => 'not_opted_in',
                'message' => 'You must click "Find Match" to begin matching',
            ];
        }

        if (!$this->presenceService->isUserOnline($guestId)) {
            Log::warning('Match attempt failed: user not online', ['guest_id' => $guestId]);
            return [
                'status'  => 'not_online',
                'message' => 'Connection lost. Please refresh the page.',
            ];
        }

        // --- Defaults -----------------------------------------------------

        if (!$role || !in_array($role, ['tutor', 'learner'])) {
            $role = 'learner';
        }
        if (!$subject || empty(trim($subject))) {
            $subject = 'General';
        }
        if (!$availability || !is_array($availability) || empty($availability)) {
            $availability = [9, 10, 11, 12, 13, 14, 15, 16, 17, 18];
        }

        // --- Already matched? (fast path) ---------------------------------

        $existingChat = $this->chatRepository->findActiveByGuestId($guestId);
        if ($existingChat) {
            Log::info('Guest already in active chat', [
                'guest_id' => $guestId,
                'chat_id'  => $existingChat->chat_id,
            ]);
            return [
                'status'     => 'already_matched',
                'chat_id'    => $existingChat->chat_id,
                'partner_id' => $existingChat->getPartnerId($guestId),
            ];
        }

        // --- Atomic match + create in ONE transaction ---------------------

        try {
            $txResult = DB::transaction(function () use ($guestId, $role, $subject, $availability) {
                // Lock all eligible waiting users to block concurrent picks
                $waitingUsers = DB::table('presence')
                    ->where('is_waiting', true)
                    ->where('is_online', true)
                    ->where('expires_at', '>', now())
                    ->where(function ($q) use ($subject) {
                        $q->whereRaw('LOWER(subject) = LOWER(?)', [$subject])
                          ->orWhere('subject', 'General')
                          ->orWhereRaw('LOWER(?) = LOWER(subject)', ['General']);
                    })
                    ->where('guest_id', '!=', $guestId)
                    ->orderBy('last_seen_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                Log::info('Atomic match scan', [
                    'guest_id'             => $guestId,
                    'subject'              => $subject,
                    'waiting_users_count'  => $waitingUsers->count(),
                ]);

                if ($waitingUsers->isEmpty()) {
                    return ['status' => 'no_candidates'];
                }

                foreach ($waitingUsers as $candidate) {
                    $candidateAvailability = json_decode($candidate->availability, true) ?? [];
                    $overlap               = array_intersect($availability, $candidateAvailability);

                    if (empty($overlap)) {
                        continue; // No schedule overlap
                    }

                    $partnerId    = $candidate->guest_id;
                    $partnerGuest = $this->guestRepository->findByGuestId($partnerId);

                    // Skip banned or missing partner
                    if (!$partnerGuest || $partnerGuest->isBanned()) {
                        $this->presenceService->removeFromWaitingPool($partnerId);
                        continue;
                    }

                    // Skip partner who already has an active chat
                    if ($this->chatRepository->findActiveByGuestId($partnerId)) {
                        $this->presenceService->removeFromWaitingPool($partnerId);
                        continue;
                    }

                    // Concurrent request may have already matched self — check inside lock
                    $selfChat = $this->chatRepository->findActiveByGuestId($guestId);
                    if ($selfChat) {
                        return [
                            'status'     => 'already_matched',
                            'chat_id'    => $selfChat->chat_id,
                            'partner_id' => $selfChat->getPartnerId($guestId),
                        ];
                    }

                    // All checks pass — create chat atomically
                    Log::info('Partner found, creating chat atomically', [
                        'guest_id'             => $guestId,
                        'partner_id'           => $partnerId,
                        'role'                 => $role,
                        'subject'              => $subject,
                        'availability_overlap' => array_values($overlap),
                    ]);

                    $chat = $this->chatRepository->create($guestId, $partnerId);

                    $this->guestRepository->updateStatus($guestId,   'active');
                    $this->guestRepository->updateStatus($partnerId,  'active');
                    $this->presenceService->removeFromWaitingPool($guestId);
                    $this->presenceService->removeFromWaitingPool($partnerId);

                    Log::info('Chat created atomically', [
                        'chat_id'    => $chat->chat_id,
                        'guest_id_1' => $guestId,
                        'guest_id_2' => $partnerId,
                    ]);

                    return [
                        'status' => 'matched',
                        'chat'   => $chat,
                    ];
                }

                return ['status' => 'no_candidates'];
            });

        } catch (\Exception $e) {
            Log::error('Atomic match transaction failed', [
                'guest_id' => $guestId,
                'error'    => $e->getMessage(),
            ]);
            return [
                'status'  => 'waiting',
                'message' => 'Failed to find match. Please try again.',
            ];
        }

        // --- Post-transaction response -----------------------------------

        $txStatus = $txResult['status'] ?? 'no_candidates';

        if ($txStatus === 'matched') {
            $chat = $txResult['chat'];
            event(new UserMatched($chat));

            return [
                'status'     => 'matched',
                'chat_id'    => $chat->chat_id,
                'partner_id' => $chat->getPartnerId($guestId),
                'started_at' => $chat->started_at->toISOString(),
                'message'    => 'You have been matched!',
            ];
        }

        if ($txStatus === 'already_matched') {
            return [
                'status'     => 'already_matched',
                'chat_id'    => $txResult['chat_id'],
                'partner_id' => $txResult['partner_id'],
            ];
        }

        // No partner found — stay in waiting pool
        Log::info('No partner found', ['guest_id' => $guestId, 'role' => $role, 'subject' => $subject]);

        return [
            'status'          => 'waiting',
            'available_users' => 0,
            'message'         => $role === 'learner'
                ? 'No available tutor online for this subject. Please try again later.'
                : 'No available learner online for this subject. Please try again later.',
        ];
    }

    public function endChat(int $chatId, string $guestId): bool
    {
        $chat = $this->chatRepository->findById($chatId);
        if (!$chat || !$chat->isParticipant($guestId)) {
            Log::warning('End chat failed: not found or not participant', [
                'chat_id'  => $chatId,
                'guest_id' => $guestId,
            ]);
            return false;
        }

        if (!$chat->isActive()) {
            Log::info('Chat already ended', [
                'chat_id'  => $chatId,
                'status'   => $chat->status,
                'ended_at' => $chat->ended_at,
            ]);
            return false;
        }

        $result = DB::transaction(function () use ($chatId, $guestId, $chat) {
            $chat->end($guestId);

            $guest1Id = $chat->guest_id_1;
            $guest2Id = $chat->guest_id_2;

            // Set both users back to idle so they can rematch immediately
            $this->guestRepository->updateStatus($guest1Id, 'idle');
            $this->guestRepository->updateStatus($guest2Id, 'idle');

            // Remove from waiting pool so they can opt-in fresh
            $this->presenceService->removeFromWaitingPool($guest1Id);
            $this->presenceService->removeFromWaitingPool($guest2Id);

            // IMPORTANT: Do NOT call markUserOffline() here.
            // Users stay online — their online presence is managed by
            // heartbeats and the disconnect event, not by chat lifecycle.
            // Marking them offline would lock them out of matching for
            // up to the heartbeat interval (~30 s).

            Log::info('Chat ended successfully', [
                'chat_id'    => $chatId,
                'ended_by'   => $guestId,
                'guest_1_id' => $guest1Id,
                'guest_2_id' => $guest2Id,
                'timestamp'  => now(),
            ]);

            return true;
        });

        if ($result) {
            event(new ChatEnded($chat, $guestId));
        }

        return $result;
    }

    public function getOnlineCount(): int
    {
        return $this->presenceService->countOnlineUsers();
    }
}
