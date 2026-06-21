<?php

use App\Http\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ingestion routes — public, no auth, no session middleware
|--------------------------------------------------------------------------
|
| These live in web.php (not api.php) so they avoid the default API
| middleware group and get their own rate-limiter configuration.
|
| We catch ALL HTTP methods on /i/{token} and /i/{token}/{path}
| so sub-paths like /i/<token>/github/push are captured too.
|
*/

Route::any('/event/hooks/{token}', [IngestController::class, 'capture'])
    ->middleware('throttle:ingest');

Route::any('/event/hooks/{token}/{path}', [IngestController::class, 'capture'])
    ->where('path', '.*')
    ->middleware('throttle:ingest');

// Health check — used by Docker/load-balancer probes
Route::get('/up', fn () => response()->json(['status' => 'ok']));

Route::fallback(function () {
    return response()->file(public_path('index.html'));
});