<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimitByIP
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $ipAddress = $request->ip();
        $routeName = $request->route()->getName();
        $routePath = $request->path();

        // Use route name if available, otherwise use route path
        $routeIdentifier = $routeName ?: $routePath;
        $key = "rate_limit:{$ipAddress}:{$routeIdentifier}";

        if (Cache::has($key)) {
            $attempts = Cache::get($key);
            if ($attempts >= $maxAttempts) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                ], 429);
            }
            Cache::increment($key);
        } else {
            Cache::put($key, 1, now()->addMinutes($decayMinutes));
        }

        return $next($request);
    }
}
