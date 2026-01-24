<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class BlockAbusiveIP
{
    public function handle(Request $request, Closure $next): Response
    {
        $ipAddress = $request->ip();
        $key = "blocked_ip:{$ipAddress}";

        if (Cache::has($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Your IP has been temporarily blocked due to suspicious activity',
            ], 403);
        }

        return $next($request);
    }
}
