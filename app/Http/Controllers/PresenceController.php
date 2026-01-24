<?php

namespace App\Http\Controllers;

use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function __construct(
        private PresenceService $presenceService
    ) {}

    public function optIn(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken();
        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session token required',
            ], 401);
        }

        $guest = app(\App\Repositories\GuestRepository::class)->findBySessionToken($sessionToken);
        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session',
            ], 401);
        }

        if ($guest->isBanned()) {
            return response()->json([
                'success' => false,
                'message' => 'Guest is banned',
            ], 403);
        }

        // CRITICAL: Check if user already has an active chat
        $chatRepository = app(\App\Repositories\ChatRepository::class);
        $existingChat = $chatRepository->findActiveByGuestId($guest->guest_id);
        if ($existingChat) {
            return response()->json([
                'success' => false,
                'message' => 'You are already in an active chat',
                'data' => [
                    'chat_id' => $existingChat->chat_id,
                    'partner_id' => $existingChat->getPartnerId($guest->guest_id),
                    'status' => 'already_matched',
                ],
            ], 409);
        }

        // Mark user as online and add to waiting pool
        $this->presenceService->markUserOnline($guest->guest_id);
        $this->presenceService->addToWaitingPool($guest->guest_id);

        // Update guest status to waiting
        app(\App\Repositories\GuestRepository::class)->updateStatus($guest->guest_id, 'waiting');

        return response()->json([
            'success' => true,
            'message' => 'Opted in for matching',
            'data' => [
                'guest_id' => $guest->guest_id,
                'waiting_users' => $this->presenceService->countWaitingUsers(),
            ],
        ]);
    }

    public function optOut(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken();
        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session token required',
            ], 401);
        }

        $guest = app(\App\Repositories\GuestRepository::class)->findBySessionToken($sessionToken);
        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session',
            ], 401);
        }

        // Remove from waiting pool but keep online
        $this->presenceService->removeFromWaitingPool($guest->guest_id);

        // Update guest status to idle
        app(\App\Repositories\GuestRepository::class)->updateStatus($guest->guest_id, 'idle');

        return response()->json([
            'success' => true,
            'message' => 'Opted out from matching',
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken();
        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session token required',
            ], 401);
        }

        $guest = app(\App\Repositories\GuestRepository::class)->findBySessionToken($sessionToken);
        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session',
            ], 401);
        }

        // CRITICAL: Check if user has an active chat and refresh chat timestamp
        $chatRepository = app(\App\Repositories\ChatRepository::class);
        $activeChat = $chatRepository->findActiveByGuestId($guest->guest_id);
        if ($activeChat) {
            // Update the chat's last activity timestamp (add a column for this if needed)
            // For now, we'll just refresh presence
        }

        // Refresh presence TTL
        $this->presenceService->refreshPresence($guest->guest_id);

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat received',
            'data' => [
                'has_active_chat' => $activeChat !== null,
                'chat_id' => $activeChat?->chat_id,
            ],
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken();
        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session token required',
            ], 401);
        }

        $guest = app(\App\Repositories\GuestRepository::class)->findBySessionToken($sessionToken);
        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session',
            ], 401);
        }

        // Mark user as offline and remove from waiting pool
        $this->presenceService->markUserOffline($guest->guest_id);

        // Update guest status to idle
        app(\App\Repositories\GuestRepository::class)->updateStatus($guest->guest_id, 'idle');

        return response()->json([
            'success' => true,
            'message' => 'Disconnected',
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken();
        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session token required',
            ], 401);
        }

        $guest = app(\App\Repositories\GuestRepository::class)->findBySessionToken($sessionToken);
        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session',
            ], 401);
        }

        $isOnline = $this->presenceService->isUserOnline($guest->guest_id);
        $isWaiting = $this->presenceService->isInWaitingPool($guest->guest_id);

        return response()->json([
            'success' => true,
            'data' => [
                'guest_id' => $guest->guest_id,
                'is_online' => $isOnline,
                'is_waiting' => $isWaiting,
                'online_users' => $this->presenceService->countOnlineUsers(),
                'waiting_users' => $this->presenceService->countWaitingUsers(),
            ],
        ]);
    }
}
