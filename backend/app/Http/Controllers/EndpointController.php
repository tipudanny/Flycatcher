<?php

namespace App\Http\Controllers;

use App\Models\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EndpointController extends Controller
{
    /**
     * List endpoints belonging to the current principal.
     * Authenticated → their owned endpoints.
     * Guest (cookie) → endpoints for their session.
     */
    public function index(Request $request): JsonResponse
    {
        // Explicit sanctum guard: this route is reachable without auth middleware,
        // so the default guard would never resolve a Bearer token.
        $user           = $request->user('sanctum');
        // The browser extension can't send the cookie cross-site (SameSite=Lax),
        // so it reads the plaintext value and passes it as a header instead.
        $guestSessionId = $request->cookie('guest_session_id') ?? $request->header('X-Guest-Session');

        if ($user) {
            $endpoints = $user->endpoints()
                ->withCount('webhookRequests')
                ->latest('last_activity_at')
                ->get();
        } elseif ($guestSessionId) {
            $endpoints = Endpoint::where('guest_session_id', $guestSessionId)
                ->whereNull('owner_user_id')
                ->withCount('webhookRequests')
                ->latest('last_activity_at')
                ->get();
        } else {
            $endpoints = collect();
        }

        return response()->json(['data' => $endpoints->map(fn ($e) => $this->format($e))]);
    }

    /**
     * Create a new endpoint.
     *
     * Authenticated: owned by the user.
     * Unauthenticated: tied to a guest session cookie (minted here if absent).
     * One guest URL per browser: if this session already has an endpoint,
     * return it instead of creating another — a fresh URL requires a fresh
     * browser (new cookie). Guest endpoints expire after the retention window.
     */
    public function store(Request $request): JsonResponse
    {
        $user           = $request->user('sanctum');
        // The browser extension can't carry the cookie cross-site, so it passes
        // the guest session as a header (and mints/persists it from the response).
        $guestSessionId = $request->cookie('guest_session_id') ?? $request->header('X-Guest-Session');

        $request->validate([
            'label' => 'nullable|string|max:100',
            // Custom URL slug — registered users only. Random high-entropy
            // tokens stay the default; a custom one is the user's choice
            // to trade guess-resistance for readability.
            'token' => [
                'nullable',
                'string',
                'min:3',
                'max:64',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                'unique:endpoints,token',
            ],
        ], [
            'token.regex'  => 'Custom URLs may only contain letters, numbers, dashes and underscores.',
            'token.unique' => 'This URL is already taken.',
        ]);

        if ($request->filled('token') && ! $user) {
            abort(422, 'Custom URLs require an account.');
        }

        // Enforce the plan's max-URL limit for registered users.
        if ($user) {
            $max = $user->planLimit('max_endpoints');
            if ($max !== null && $user->endpoints()->count() >= $max) {
                abort(422, "You've reached your plan's limit of {$max} webhook URLs. Upgrade your plan to create more.");
            }
        }

        // Same browser, same guest URL — even when its request limit is reached.
        if (! $user && $guestSessionId) {
            $existing = Endpoint::where('guest_session_id', $guestSessionId)
                ->whereNull('owner_user_id')
                ->latest()
                ->first();

            if ($existing) {
                // Expose the session so the extension can keep using it as a header.
                return response()->json([
                    'data'          => $this->format($existing),
                    'guest_session' => $existing->guest_session_id,
                ]);
            }
        }

        $endpoint = Endpoint::create([
            'token'            => $user ? $request->token : null, // null → random token from the model hook
            'owner_user_id'    => $user?->id,
            'guest_session_id' => $user ? null : ($guestSessionId ?? Str::random(32)),
            'label'            => $request->label,
            'expires_at'       => $user ? null : now()->addDays((int) config('app.guest_retention_days', 2)),
        ]);

        $payload = ['data' => $this->format($endpoint)];
        // Guests get their session id back so the extension can persist it.
        if (! $user) {
            $payload['guest_session'] = $endpoint->guest_session_id;
        }

        $response = response()->json($payload, 201);

        // If we just minted a new guest session, persist it in a cookie.
        if (! $user && ! $guestSessionId) {
            $response->cookie(
                'guest_session_id',
                $endpoint->guest_session_id,
                60 * 24 * 30, // 30 days
                '/',
                null,
                $request->isSecure(), // secure only over https (local dev is http)
                true,  // httpOnly
                false,
                'Lax'
            );
        }

        return $response;
    }

    /**
     * Show a single endpoint.
     * Authorization: must own it (user) or hold the matching guest session (guest).
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $endpoint = $this->findAuthorized($request, $token);

        return response()->json(['data' => $this->format($endpoint)]);
    }

    /**
     * Update the label or settings of an endpoint.
     */
    public function update(Request $request, string $token): JsonResponse
    {
        $endpoint = $this->findAuthorized($request, $token);

        $request->validate([
            'label'    => 'nullable|string|max:100',
            'settings' => 'nullable|array',
            'token'    => [
                'nullable',
                'string',
                'min:3',
                'max:64',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                Rule::unique('endpoints', 'token')->ignore($endpoint->id),
            ],
        ], [
            'token.regex'  => 'Custom URLs may only contain letters, numbers, dashes and underscores.',
            'token.unique' => 'This URL is already taken.',
        ]);

        // Custom per-endpoint responses are a paid-plan feature.
        if ($request->filled('settings') && $this->settingsHaveCustomResponse($request->input('settings'))) {
            $owner = $endpoint->owner;
            if (! $owner || ! $owner->allowsCustomResponses()) {
                abort(403, 'Custom responses are not available on your plan. Upgrade to enable them.');
            }
        }

        $fields = ['label', 'settings'];

        // Renaming the URL is owner-only; the old URL stops working immediately.
        if ($request->filled('token') && $endpoint->owner_user_id === $request->user()?->id) {
            $fields[] = 'token';
        }

        $endpoint->update($request->only($fields));

        return response()->json(['data' => $this->format($endpoint->fresh())]);
    }

    /**
     * Soft-delete an endpoint (and cascade-delete its requests via DB).
     */
    public function destroy(Request $request, string $token): JsonResponse
    {
        $endpoint = $this->findAuthorized($request, $token);
        $endpoint->delete();

        return response()->json(null, 204);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Find an endpoint by its public token and verify the caller is authorized
     * to view/manage it. Aborts with 404 if not found, 403 if unauthorized.
     *
     * We use 404 for missing AND unauthorized to avoid leaking whether a token exists.
     */
    private function findAuthorized(Request $request, string $token): Endpoint
    {
        $endpoint = Endpoint::where('token', $token)->first();

        $guestSessionId = $request->cookie('guest_session_id') ?? $request->header('X-Guest-Session');

        if (
            ! $endpoint ||
            ! $endpoint->canBeViewedBy($request->user('sanctum'), $guestSessionId)
        ) {
            abort(404);
        }

        return $endpoint;
    }

    private function settingsHaveCustomResponse(?array $settings): bool
    {
        if (! $settings) {
            return false;
        }

        return (bool) array_intersect(
            array_keys($settings),
            ['response_status', 'response_body', 'response_headers']
        );
    }

    private function format(Endpoint $endpoint): array
    {
        return [
            'id'                => $endpoint->id,        // internal UUID — only used as the Firebase live-tail path key, never in URLs
            'token'             => $endpoint->token,     // public identifier, never the UUID
            'label'             => $endpoint->label,
            'type'              => $endpoint->type,      // derived: 'private' | 'guest'
            'request_count'     => $endpoint->request_count,
            'request_limit'     => $endpoint->requestLimit(),   // null = unlimited (owned)
            'limit_reached'     => $endpoint->hasReachedLimit(),
            'last_activity_at'  => $endpoint->last_activity_at?->toIso8601String(),
            'expires_at'        => $endpoint->expires_at?->toIso8601String(),
            'settings'          => $endpoint->settings ?? [],
            'capture_url'       => url("/event/hooks/{$endpoint->token}"),
            'view_url'          => config('app.frontend_url') . "/inspect/{$endpoint->token}",
            'created_at'        => $endpoint->created_at->toIso8601String(),
        ];
    }
}
