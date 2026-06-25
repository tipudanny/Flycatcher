<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EndpointController;
use App\Http\Controllers\ExtensionController;
use App\Http\Controllers\RequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Two concerns kept separate:
|
|   1. Ingestion — no auth, public, high-throughput. Lives in web.php
|      so it doesn't get the "api" middleware group overhead (throttle etc.)
|      and can have its own rate limits.
|
|   2. App API — auth-gated, tenant-scoped reads. Lives here.
|
*/

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    // Tight throttle on credential endpoints to blunt brute force.
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

// ── Email verification & account overview ─────────────────────────────────────────
// The verify link carries a signed signature + hash — no bearer auth needed.
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/overview',      [AuthController::class, 'overview']);
    Route::post('/email/resend', [AuthController::class, 'resend'])->middleware('throttle:auth');
});

// ── Admin ───────────────────────────────────────────────────────────────────────
// Admin-only: manage users, plans, and inspect system-wide state.
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/stats',           [AdminController::class, 'stats']);
    Route::get('/plans',           [AdminController::class, 'plans']);
    Route::get('/users',           [AdminController::class, 'users']);
    Route::patch('/users/{id}',    [AdminController::class, 'updateUser']);
    Route::get('/endpoints',       [AdminController::class, 'endpoints']);
    Route::get('/settings',        [AdminController::class, 'settings']);
    Route::put('/settings',        [AdminController::class, 'updateSettings']);
});

// ── Browser extension ────────────────────────────────────────────────────────────
// Public config the Chrome extension reads before the user connects.
Route::get('/extension/config', [ExtensionController::class, 'config']);

// ── Endpoints ─────────────────────────────────────────────────────────────────
// Accessible by authenticated users AND guests (with a valid cookie).
// Auth is optional — the controllers handle both principals.
// Creation is open to guests: an unauthenticated request mints (or reuses)
// a cookie-bound guest endpoint. Authenticated users create owned endpoints.
Route::post('/endpoints', [EndpointController::class, 'store'])->middleware('throttle:create-endpoint');

Route::middleware('auth:sanctum')->group(function () {
    // Endpoint management — owner only (rename, settings, delete the URL itself).
    Route::put('/endpoints/{token}',  [EndpointController::class, 'update']);
    Route::delete('/endpoints/{token}', [EndpointController::class, 'destroy']);
});

// Request deletion — owner OR the guest who owns the URL (authorized in the
// controller via the session cookie / X-Guest-Session header).
Route::delete('/endpoints/{token}/requests', [RequestController::class, 'destroyAll']);
Route::delete('/endpoints/{token}/requests/{requestId}', [RequestController::class, 'destroy']);

// Read-only endpoints — accessible by owner OR guest-by-cookie
Route::get('/endpoints',        [EndpointController::class, 'index']);
Route::get('/endpoints/{token}', [EndpointController::class, 'show']);
Route::get('/endpoints/{token}/requests', [RequestController::class, 'index']);
Route::get('/endpoints/{token}/requests/{requestId}', [RequestController::class, 'show']);
