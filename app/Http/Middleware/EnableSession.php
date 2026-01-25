<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnableSession
{
    public function handle(Request $request, Closure $next)
    {
        // Enable session without CSRF protection
        config(['session.driver' => 'file']);
        
        return $next($request);
    }
}
