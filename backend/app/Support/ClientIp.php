<?php

namespace App\Support;

use Illuminate\Http\Request;

class ClientIp
{
    /**
     * Resolve the request's client IP the same way everywhere it matters
     * for security decisions (rate limiting, blocking) — trusting
     * X-Forwarded-For only when we're actually behind our own proxy,
     * otherwise it's trivially spoofable.
     */
    public static function resolve(Request $request): string
    {
        if (config('app.trust_proxy', false)) {
            $forwarded = $request->header('X-Forwarded-For');
            if ($forwarded) {
                return trim(explode(',', $forwarded)[0]);
            }
        }

        return $request->ip();
    }
}
