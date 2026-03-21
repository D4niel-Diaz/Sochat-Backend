<?php

namespace App\Http\Controllers;

use App\Repositories\GuestRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function __construct(
        private GuestRepository $guestRepository
    ) {}

    public function create(Request $request): JsonResponse
    {
        $ipAddress = $request->ip();
        $guest = $this->guestRepository->create($ipAddress);

        // SECURITY: $guest->session_token now holds the SHA-256 hash (stored in DB).
        // The raw (un-hashed) token is on the transient $guest->rawToken property —
        // it was set in Guest::boot() and is returned to the client exactly ONCE here.
        // The client uses this raw token as the Bearer token in all future requests.
        // auth.guest hashes it again before querying, so plaintext never enters the DB.
        return response()->json([
            'success' => true,
            'data'    => [
                'guest_id'      => $guest->guest_id,
                'session_token' => $guest->rawToken,    // Return the raw token, NOT the hash
                'expires_at'    => $guest->expires_at->toIso8601String(),
            ],
            'message' => 'Guest session created successfully',
        ], 201);
    }


    /**
     * Refresh (extend) the session.
     * Used by the frontend to keep the session alive.
     */
    public function refresh(Request $request): JsonResponse
    {
        // auth.guest middleware already validated the token — fetch from request attributes
        $guest = $request->attributes->get('guest');

        if (!$guest) {
            // Fallback: manual lookup (should not be reached when middleware is applied)
            $sessionToken = $request->bearerToken();
            if (!$sessionToken) {
                return response()->json(['success' => false, 'message' => 'Session token required'], 401);
            }
            $guest = $this->guestRepository->findBySessionToken($sessionToken);
        }

        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Invalid session'], 401);
        }

        // Extend session by 24 hours
        $guest->update(['expires_at' => now()->addHours(24)]);

        return response()->json([
            'success' => true,
            'data'    => [
                'guest_id'   => $guest->guest_id,
                'expires_at' => $guest->expires_at->toIso8601String(),
            ],
            'message' => 'Session refreshed successfully',
        ]);
    }

    /**
     * Validate session without mutating any state.
     *
     * SECURITY FIX: The WebSocket server previously called /guest/refresh on
     * every socket handshake, which is a state-changing POST. This read-only
     * GET endpoint lets the WS server verify a session without side-effects.
     */
    public function validate(Request $request): JsonResponse
    {
        // auth.guest middleware already validated the token
        $guest = $request->attributes->get('guest');

        if (!$guest) {
            return response()->json(['success' => false, 'message' => 'Invalid session'], 401);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'guest_id'   => $guest->guest_id,
                'expires_at' => $guest->expires_at->toIso8601String(),
                'status'     => $guest->status,
            ],
            'message' => 'Session valid',
        ]);
    }
}
