<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sanctum bearer token issued by AuthController::adminLogin
        $admin = Auth::guard('sanctum')->user();

        if (!$admin instanceof Admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->setUserResolver(fn () => $admin);

        return $next($request);
    }
}
