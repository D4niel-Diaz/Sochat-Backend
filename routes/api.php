<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Public health check (unauthenticated — required by Render health checks)
Route::get('/health', [HealthController::class, 'check']);

// SECURITY: Debug presence endpoint removed — was exposing all user presence
// data without authentication. Admin-only debug is at /v1/admin/debug/presence.

Route::prefix('v1')->group(function () {
    // Auth routes (no guest token required)
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/guest/create', [GuestController::class, 'create']);

    // Guest-authenticated routes
    Route::middleware(['auth.guest'])->group(function () {
        Route::post('/guest/refresh',   [GuestController::class, 'refresh']);
        Route::get('/guest/validate',   [GuestController::class, 'validate']);

        // Presence (60 req/min per IP)
        Route::middleware(['throttle:60,1'])->group(function () {
            Route::post('/presence/opt-in',     [PresenceController::class, 'optIn']);
            Route::post('/presence/opt-out',    [PresenceController::class, 'optOut']);
            Route::post('/presence/heartbeat',  [PresenceController::class, 'heartbeat']);
            Route::post('/presence/disconnect', [PresenceController::class, 'disconnect']);
            Route::get('/presence/status',      [PresenceController::class, 'status']);
        });

        // Chat — match lifecycle
        Route::post('/chat/start',             [ChatController::class, 'start']);
        Route::post('/chat/end',               [ChatController::class, 'end']);
        Route::get('/chat/{chat_id}/messages', [ChatController::class, 'messages']);

        // FIX: Rate-limit message send to 30/min per user.
        // Without this, attackers bypass the WebSocket rate limiter by sending
        // directly to the HTTP endpoint at high frequency.
        Route::middleware(['throttle:30,1'])->group(function () {
            Route::post('/chat/message', [ChatController::class, 'send']);
        });

        Route::post('/chat/typing', [ChatController::class, 'typing']);

        // Reporting
        Route::post('/report', [ReportController::class, 'store']);
    });

    // Broadcasting auth is disabled (using standalone Socket.IO server)
    Route::post('/broadcasting/auth', function () {
        return response()->json([
            'success' => false,
            'message' => 'Broadcasting authentication is disabled',
        ], 404);
    })->middleware('auth.guest');

    // Admin-only routes
    Route::prefix('admin')->middleware(['auth.admin'])->group(function () {
        Route::get('/metrics',          [AdminController::class, 'getMetrics']);
        Route::get('/chats',            [AdminController::class, 'getActiveChats']);
        Route::get('/reports',          [AdminController::class, 'getReports']);
        Route::post('/ban',             [AdminController::class, 'banGuest']);
        Route::post('/unban',           [AdminController::class, 'unbanGuest']);
        Route::post('/report/resolve',  [AdminController::class, 'resolveReport']);
        Route::get('/banned-guests',    [AdminController::class, 'getBannedGuests']);
        Route::get('/flagged-messages', [AdminController::class, 'getFlaggedMessages']);

        // SECURITY: Admin-only debug endpoint (was previously unauthenticated)
        Route::get('/debug/presence', function () {
            $presence = \Illuminate\Support\Facades\DB::table('presence')
                ->where('expires_at', '>', now())
                ->get()
                ->map(fn ($p) => [
                    'guest_id'    => $p->guest_id,
                    'role'        => $p->role,
                    'subject'     => $p->subject,
                    'is_online'   => $p->is_online,
                    'is_waiting'  => $p->is_waiting,
                    'expires_at'  => $p->expires_at,
                    'last_seen_at'=> $p->last_seen_at,
                ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'total'    => $presence->count(),
                    'online'   => $presence->where('is_online', true)->count(),
                    'waiting'  => $presence->where('is_waiting', true)->count(),
                    'presence' => $presence->values(),
                ],
            ]);
        });
    });
});