<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Guest requests are keyed by session token, not IP: the WebSocket server
        // calls this API on behalf of every connected user from a single IP, and
        // campus users often share one NAT address.
        $byGuest = fn (Request $request) => $request->bearerToken()
            ? 'guest:' . hash('sha256', $request->bearerToken())
            : 'ip:' . $request->ip();

        RateLimiter::for('guest-create', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('guest-api', fn (Request $request) => Limit::perMinute(120)->by($byGuest($request)));
        RateLimiter::for('presence', fn (Request $request) => Limit::perMinute(60)->by($byGuest($request)));
        // The WebSocket server retries /chat/start every 2s (30/min) while a guest waits
        RateLimiter::for('chat-start', fn (Request $request) => Limit::perMinute(60)->by($byGuest($request)));
        RateLimiter::for('send-message', fn (Request $request) => Limit::perMinute(30)->by($byGuest($request)));
        RateLimiter::for('report-submit', fn (Request $request) => Limit::perHour(5)->by($byGuest($request)));

        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip() . '|' . strtolower((string) $request->input('email'))));
        RateLimiter::for('admin-api', fn (Request $request) => Limit::perMinute(120)->by($byGuest($request)));
    }
}
