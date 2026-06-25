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
        // ── Ingestion ────────────────────────────────────────────────────────
        // Two limits at once: per-token (one noisy URL can't starve others) AND
        // a per-IP ceiling so an attacker can't bypass the limit by spraying
        // random tokens and hammering the DB with lookups.
        RateLimiter::for('ingest', function (Request $request) {
            return [
                Limit::perMinute((int) config('app.rate_limit_per_minute', 60))
                    ->by('tok:' . $request->route('token')),
                Limit::perMinute((int) config('app.ingest_ip_limit', 300))
                    ->by('ip:' . $request->ip()),
            ];
        });

        // ── General API ──────────────────────────────────────────────────────
        // Generous ceiling that still stops a runaway script. Keyed per user
        // when signed in, otherwise per IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('app.api_limit', 120))
                ->by($request->user()?->id ?: $request->ip());
        });

        // ── Auth ─────────────────────────────────────────────────────────────
        // Tight — blunts brute-force / credential-stuffing on login & register.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute((int) config('app.auth_limit', 10))
                ->by($request->ip());
        });

        // ── Guest URL creation ───────────────────────────────────────────────
        // Caps anonymous endpoint spam that would otherwise fill the database.
        RateLimiter::for('create-endpoint', function (Request $request) {
            return Limit::perMinute((int) config('app.create_limit', 20))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
