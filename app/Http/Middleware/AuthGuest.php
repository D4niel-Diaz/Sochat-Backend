<?php

namespace App\Http\Middleware;

use App\Repositories\GuestRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthGuest
{
    public function __construct(
        private GuestRepository $guestRepository
    ) {}

    public function handle(Request $request, Closure $next): Response
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
                'message' => 'Your session has been banned',
            ], 403);
        }

        if ($guest->expires_at && $guest->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired',
            ], 401);
        }

        $request->attributes->set('guest', $guest);

        return $next($request);
    }
}
