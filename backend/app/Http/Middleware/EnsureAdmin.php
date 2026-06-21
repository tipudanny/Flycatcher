<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Allow only authenticated admin users through. Runs after auth:sanctum,
     * so a missing/invalid token is already a 401 by the time we get here.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
