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
        // Ingestion limiter — keyed per endpoint token so one noisy endpoint
        // can't starve the others; falls back to IP when no token matched.
        RateLimiter::for('ingest', function (Request $request) {
            return Limit::perMinute((int) config('app.rate_limit_per_minute', 60))
                ->by($request->route('token') ?? $request->ip());
        });
    }
}
