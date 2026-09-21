<?php

namespace App\Http\Middleware;

use App\Models\BlockedEntity;
use App\Support\ClientIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies requests from an IP or endpoint token an admin has locked from the
 * rate-limit activity panel. Runs before throttle:* so a blocked abuser
 * doesn't even consume the rate limiter's budget.
 */
class EnforceBlocklist
{
    public function handle(Request $request, Closure $next): Response
    {
        // An authenticated admin is never blocked by IP/token rules — the
        // whole point of this panel is to manage blocks, including against
        // IPs the admin might also be sharing (office NAT, VPN, etc.), and
        // an admin who blocks their own current IP must still be able to
        // reach the panel to undo it.
        if ($request->user()?->is_admin) {
            return $next($request);
        }

        if (BlockedEntity::isBlocked('ip', ClientIp::resolve($request))) {
            abort(403, 'Blocked.');
        }

        $token = $request->route()?->parameter('token');
        if ($token && BlockedEntity::isBlocked('token', (string) $token)) {
            abort(403, 'Blocked.');
        }

        return $next($request);
    }
}
