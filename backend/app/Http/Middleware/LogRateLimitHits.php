<?php

namespace App\Http\Middleware;

use App\Models\RateLimitHit;
use App\Support\ClientIp;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every 429 so the admin panel can show which users/IPs/endpoint
 * tokens are triggering rate limits, for lock/unlock action. Must run
 * *inside* the 'web'/'api' groups (after routing) so the route's token
 * parameter is available, and *outside* the throttle:* middleware so it
 * can observe the 429 it produces.
 */
class LogRateLimitHits
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (ThrottleRequestsException $e) {
            $this->record($request);
            throw $e;
        }

        if ($response->getStatusCode() === 429) {
            $this->record($request);
        }

        return $response;
    }

    private function record(Request $request): void
    {
        try {
            $route = $request->route();
            [$keyType, $keyValue] = $this->resolveKey($request, $route);

            RateLimitHit::record($this->limiterName($route), $keyType, $keyValue, $route?->uri());
        } catch (\Throwable $e) {
            // Visibility into rate limiting must never be able to break the
            // rate-limited response itself.
            Log::warning('Failed to record rate limit hit', ['error' => $e->getMessage()]);
        }
    }

    private function limiterName(?Route $route): string
    {
        foreach ($route?->gatherMiddleware() ?? [] as $middleware) {
            if (str_starts_with($middleware, 'throttle:')) {
                return substr($middleware, strlen('throttle:'));
            }
        }

        return 'api';
    }

    /** @return array{0: string, 1: string} [key_type, key_value] */
    private function resolveKey(Request $request, ?Route $route): array
    {
        $token = $route?->parameter('token');
        if ($token) {
            return ['token', (string) $token];
        }

        if ($request->user()) {
            return ['user', (string) $request->user()->id];
        }

        return ['ip', ClientIp::resolve($request)];
    }
}
