<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestSizeLimit
{
    public function handle(Request $request, Closure $next, int $maxSizeMB = 10): Response
    {
        $contentLength = $request->header('Content-Length');
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;

        if ($contentLength && $contentLength > $maxSizeBytes) {
            return response()->json([
                'success' => false,
                'message' => "Request body too large. Maximum size is {$maxSizeMB}MB.",
            ], 413);
        }

        return $next($request);
    }
}
