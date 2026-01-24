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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        RateLimiter::for('session-create', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('send-message', function (Request $request) {
            $sessionId = $request->attributes->get('session_id');
            return Limit::perMinute(10)->by($sessionId ?? $request->ip());
        });

        RateLimiter::for('report-submit', function (Request $request) {
            $sessionId = $request->attributes->get('session_id');
            return Limit::perHour(3)->by($sessionId ?? $request->ip());
        });
    }
}
