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

Route::prefix('v1')->group(function () {
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:admin-login');

    Route::post('/guest/create', [GuestController::class, 'create'])->middleware('throttle:guest-create');

    Route::middleware(['auth.guest', 'throttle:guest-api'])->group(function () {
        // Read-only session check used by the WebSocket server on every handshake
        Route::get('/guest/validate', [GuestController::class, 'validate']);
        Route::post('/guest/refresh', [GuestController::class, 'refresh']);

        Route::middleware(['throttle:presence'])->group(function () {
            Route::post('/presence/opt-in', [PresenceController::class, 'optIn']);
            Route::post('/presence/opt-out', [PresenceController::class, 'optOut']);
            Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat']);
            Route::post('/presence/disconnect', [PresenceController::class, 'disconnect']);
            Route::get('/presence/status', [PresenceController::class, 'status']);
        });

        Route::post('/chat/start', [ChatController::class, 'start'])->middleware('throttle:chat-start');
        Route::post('/chat/end', [ChatController::class, 'end']);
        Route::get('/chat/{chat_id}/messages', [ChatController::class, 'messages'])->whereNumber('chat_id');
        Route::post('/chat/message', [ChatController::class, 'send'])->middleware('throttle:send-message');

        Route::post('/report', [ReportController::class, 'store'])->middleware('throttle:report-submit');
    });

    Route::prefix('admin')->middleware(['auth.admin', 'throttle:admin-api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'adminLogout']);
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
