<?php

namespace App\Http\Controllers;

use App\Repositories\GuestRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GuestController extends Controller
{
    public function __construct(
        private GuestRepository $guestRepository
    ) {}

    public function create(Request $request): JsonResponse
    {
        $ipAddress = $request->ip();
        $guest = $this->guestRepository->create($ipAddress);

        return response()->json([
            'success' => true,
            'data' => [
                'guest_id' => $guest->guest_id,
                'session_token' => $guest->session_token,
                'expires_at' => $guest->expires_at->toIso8601String(),
            ],
            'message' => 'Guest session created successfully',
        ], 201);
    }

    public function refresh(Request $request): JsonResponse
    {
        $sessionToken = $request->bearerToken();
        if (!$sessionToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session token required',
            ], 401);
        }

        $guest = $this->guestRepository->findBySessionToken($sessionToken);
        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session',
            ], 401);
        }

        if ($guest->isBanned()) {
            return response()->json([
                'success' => false,
                'message' => 'Session has been banned',
            ], 403);
        }

        if ($guest->expires_at && $guest->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired',
            ], 401);
        }

        $guest->update(['expires_at' => now()->addHours(24)]);

        return response()->json([
            'success' => true,
            'data' => [
                'guest_id' => $guest->guest_id,
                'expires_at' => $guest->expires_at->toIso8601String(),
            ],
            'message' => 'Session refreshed successfully',
        ]);
    }
}
