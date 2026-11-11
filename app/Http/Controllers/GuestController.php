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
        $guest = $this->guestRepository->create($request->ip());

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

    /**
     * Read-only session check (the auth.guest middleware has already rejected
     * missing, invalid, banned and expired tokens). Does not extend the session.
     */
    public function validate(Request $request): JsonResponse
    {
        $guest = $request->attributes->get('guest');

        return response()->json([
            'success' => true,
            'data' => [
                'guest_id' => $guest->guest_id,
                'status' => $guest->status,
                'expires_at' => $guest->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $guest = $request->attributes->get('guest');

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
