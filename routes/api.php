<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);

// Debug endpoint (remove in production)
Route::get('/debug/presence', function () {
    $presence = \Illuminate\Support\Facades\DB::table('presence')
        ->where('expires_at', '>', now())
        ->get()
        ->map(function($p) {
            return [
                'guest_id' => $p->guest_id,
                'role' => $p->role,
                'subject' => $p->subject,
                'availability' => $p->availability ? json_decode($p->availability, true) : null,
                'is_online' => $p->is_online,
                'is_waiting' => $p->is_waiting,
                'expires_at' => $p->expires_at,
                'last_seen_at' => $p->last_seen_at,
            ];
        });
    
    return response()->json([
        'success' => true,
        'data' => [
            'total' => $presence->count(),
            'online' => $presence->where('is_online', true)->count(),
            'waiting' => $presence->where('is_waiting', true)->count(),
            'presence' => $presence->values(),
        ],
    ]);
});

Route::prefix('v1')->group(function () {
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);

    Route::post('/guest/create', [GuestController::class, 'create']);

    Route::middleware(['auth.guest'])->group(function () {
        Route::post('/guest/refresh', [GuestController::class, 'refresh']);
        
        // Presence endpoints with rate limiting
        Route::middleware(['throttle:60,1'])->group(function () {
            Route::post('/presence/opt-in', [PresenceController::class, 'optIn']);
            Route::post('/presence/opt-out', [PresenceController::class, 'optOut']);
            Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat']);
            Route::post('/presence/disconnect', [PresenceController::class, 'disconnect']);
            Route::get('/presence/status', [PresenceController::class, 'status']);
        });

        Route::post('/chat/start', [ChatController::class, 'start']);
        
        Route::post('/chat/end', [ChatController::class, 'end']);
        
        Route::get('/chat/{chat_id}/messages', [ChatController::class, 'messages']);
        
        Route::post('/chat/message', [ChatController::class, 'send']);
        
        Route::post('/chat/typing', [ChatController::class, 'typing']);
        
        Route::post('/report', [ReportController::class, 'store']);
    });

    // Broadcasting authentication for WebSocket/Reverb
    Route::post('/broadcasting/auth', function () {
        return response()->json([
            'success' => false,
            'message' => 'Broadcasting authentication is disabled'
        ], 404);
    })->middleware('auth.guest');

    Route::prefix('admin')->middleware(['auth.admin'])->group(function () {
        Route::get('/metrics', [AdminController::class, 'getMetrics']);
        Route::get('/chats', [AdminController::class, 'getActiveChats']);
        Route::get('/reports', [AdminController::class, 'getReports']);
        Route::post('/ban', [AdminController::class, 'banGuest']);
        Route::post('/unban', [AdminController::class, 'unbanGuest']);
        Route::post('/report/resolve', [AdminController::class, 'resolveReport']);
        Route::get('/banned-guests', [AdminController::class, 'getBannedGuests']);
        Route::get('/flagged-messages', [AdminController::class, 'getFlaggedMessages']);
    });
});