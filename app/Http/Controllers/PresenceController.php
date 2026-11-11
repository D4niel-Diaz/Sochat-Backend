<?php

namespace App\Http\Controllers;

use App\Repositories\ChatRepository;
use App\Repositories\GuestRepository;
use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PresenceController extends Controller
{
    public function __construct(
        private PresenceService $presenceService,
        private GuestRepository $guestRepository,
        private ChatRepository $chatRepository
    ) {}

    public function optIn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:tutor,learner',
            'subject' => 'required|string|max:100',
            'availability' => 'required|array|min:1',
            'availability.*' => 'integer|min:0|max:23', // Hours 0-23
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $guest = $request->attributes->get('guest');

        $existingChat = $this->chatRepository->findActiveByGuestId($guest->guest_id);
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

        $role = $request->input('role');
        $subject = $request->input('subject');
        $availability = $request->input('availability');

        $guest->update([
            'role' => $role,
            'subject' => $subject,
            'availability' => $availability,
        ]);

        $this->presenceService->addToWaitingPool($guest->guest_id, $role, $subject, $availability);
        $this->guestRepository->updateStatus($guest->guest_id, 'waiting');

        $oppositeRole = $role === 'tutor' ? 'learner' : 'tutor';

        return response()->json([
            'success' => true,
            'message' => 'Opted in for matching',
            'data' => [
                'guest_id' => $guest->guest_id,
                'waiting_users' => $this->presenceService->countWaitingUsers($oppositeRole, $subject),
            ],
        ]);
    }

    public function optOut(Request $request): JsonResponse
    {
        $guest = $request->attributes->get('guest');

        // Remove from waiting pool but keep online
        $this->presenceService->removeFromWaitingPool($guest->guest_id);
        $this->guestRepository->updateStatus($guest->guest_id, 'idle');

        return response()->json([
            'success' => true,
            'message' => 'Opted out from matching',
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $guest = $request->attributes->get('guest');

        // markUserOnline upserts, so a heartbeat also restores a guest whose
        // presence row was cleaned up
        $this->presenceService->markUserOnline($guest->guest_id);

        $activeChat = $this->chatRepository->findActiveByGuestId($guest->guest_id);

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
        $guest = $request->attributes->get('guest');

        $this->presenceService->markUserOffline($guest->guest_id);
        $this->guestRepository->updateStatus($guest->guest_id, 'idle');

        return response()->json([
            'success' => true,
            'message' => 'Disconnected',
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $guest = $request->attributes->get('guest');

        return response()->json([
            'success' => true,
            'data' => [
                'guest_id' => $guest->guest_id,
                'is_online' => $this->presenceService->isUserOnline($guest->guest_id),
                'is_waiting' => $this->presenceService->isInWaitingPool($guest->guest_id),
                'online_users' => $this->presenceService->countOnlineUsers(),
                'waiting_users' => $this->presenceService->countWaitingUsers(),
            ],
        ]);
    }
}
