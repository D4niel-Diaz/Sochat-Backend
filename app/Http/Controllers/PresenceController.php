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
        private PresenceService  $presenceService,
        private GuestRepository  $guestRepository,
        private ChatRepository   $chatRepository,
    ) {}

    /**
     * Shared helper — reads the authenticated guest from the request attributes
     * set by the auth.guest middleware. Falls back to a DB lookup only if the
     * attribute is missing (shouldn't happen in normal flow).
     *
     * FIX: Removes the redundant DB round-trip that existed in every method body
     * (manual BearerToken + findBySessionToken calls). The middleware already did
     * this once — no reason to do it 5 more times.
     */
    private function getGuest(Request $request)
    {
        return $request->attributes->get('guest')
            ?? $this->guestRepository->findBySessionToken($request->bearerToken() ?? '');
    }

    public function optIn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role'           => 'required|in:tutor,learner',
            'subject'        => 'required|string|max:100',
            'availability'   => 'required|array|min:1',
            'availability.*' => 'integer|min:0|max:23',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()->toArray(),
            ], 422);
        }

        $guest = $this->getGuest($request);
        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Invalid session'], 401);
        }

        if ($guest->isBanned()) {
            return response()->json(['success' => false, 'message' => 'Guest is banned'], 403);
        }

        // Check if user already has an active chat
        $existingChat = $this->chatRepository->findActiveByGuestId($guest->guest_id);
        if ($existingChat) {
            return response()->json([
                'success' => false,
                'message' => 'You are already in an active chat',
                'data'    => [
                    'chat_id'    => $existingChat->chat_id,
                    'partner_id' => $existingChat->getPartnerId($guest->guest_id),
                    'status'     => 'already_matched',
                ],
            ], 409);
        }

        // Persist matching criteria on the guest record
        $guest->update([
            'role'         => $request->input('role'),
            'subject'      => $request->input('subject'),
            'availability' => $request->input('availability'),
        ]);

        $this->presenceService->markUserOnline($guest->guest_id);
        $this->presenceService->addToWaitingPool(
            $guest->guest_id,
            $request->input('role'),
            $request->input('subject'),
            $request->input('availability')
        );
        $this->guestRepository->updateStatus($guest->guest_id, 'waiting');

        $oppositeRole = $request->input('role') === 'tutor' ? 'learner' : 'tutor';
        $waitingCount = $this->presenceService->countWaitingUsers($oppositeRole, $request->input('subject'));

        return response()->json([
            'success' => true,
            'message' => 'Opted in for matching',
            'data'    => [
                'guest_id'      => $guest->guest_id,
                'waiting_users' => $waitingCount,
            ],
        ]);
    }

    public function optOut(Request $request): JsonResponse
    {
        $guest = $this->getGuest($request);
        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Invalid session'], 401);
        }

        $this->presenceService->removeFromWaitingPool($guest->guest_id);
        $this->guestRepository->updateStatus($guest->guest_id, 'idle');

        return response()->json(['success' => true, 'message' => 'Opted out from matching']);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $guest = $this->getGuest($request);
        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Invalid session'], 401);
        }

        $this->presenceService->refreshPresence($guest->guest_id);

        $activeChat = $this->chatRepository->findActiveByGuestId($guest->guest_id);

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat received',
            'data'    => [
                'has_active_chat' => $activeChat !== null,
                'chat_id'         => $activeChat?->chat_id,
            ],
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $guest = $this->getGuest($request);
        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Invalid session'], 401);
        }

        $this->presenceService->markUserOffline($guest->guest_id);
        $this->guestRepository->updateStatus($guest->guest_id, 'idle');

        return response()->json(['success' => true, 'message' => 'Disconnected']);
    }

    public function status(Request $request): JsonResponse
    {
        $guest = $this->getGuest($request);
        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Invalid session'], 401);
        }

        $isOnline  = $this->presenceService->isUserOnline($guest->guest_id);
        $isWaiting = $this->presenceService->isInWaitingPool($guest->guest_id);

        return response()->json([
            'success' => true,
            'data'    => [
                'guest_id'      => $guest->guest_id,
                'is_online'     => $isOnline,
                'is_waiting'    => $isWaiting,
                'online_users'  => $this->presenceService->countOnlineUsers(),
                'waiting_users' => $this->presenceService->countWaitingUsers(),
            ],
        ]);
    }
}
