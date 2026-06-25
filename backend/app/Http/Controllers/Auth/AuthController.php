<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Models\User;
use App\Models\WebhookRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * If the request includes a `guest_session_id` cookie, we claim all
     * guest endpoints belonging to that session — transitioning them to
     * the newly created user. Cookie value is the credential; we never
     * trust a guest_session_id sent in the request body.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'email'         => $request->email,
            'password_hash' => Hash::make($request->password),
        ]);

        // Claim guest endpoints if the session cookie is present
        $this->claimGuestEndpoints($user, $request->cookie('guest_session_id'));

        // Sends the verification email (User implements MustVerifyEmail).
        event(new Registered($user));

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $this->userPayload($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Verify an email address from the signed link in the verification email.
     * No bearer auth — the signature + hash are the credential. Redirects back
     * to the SPA when done.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $front = rtrim(config('app.frontend_url'), '/');
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect("{$front}/login?verified=0");
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect("{$front}/login?verified=1");
    }

    /**
     * Resend the verification email to the current user.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    /**
     * Account overview for the logged-in dashboard: plan, limits, and usage.
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $plan = config("plans.{$user->plan}", []);
        $endpointIds = $user->endpoints()->pluck('id');

        return response()->json([
            'data' => [
                'plan'           => $user->plan,
                'plan_label'     => $plan['label'] ?? ucfirst($user->plan),
                'email_verified' => $user->hasVerifiedEmail(),
                'limits'         => [
                    'max_endpoints'    => $plan['max_endpoints'] ?? null,
                    'request_limit'    => $plan['request_limit'] ?? null,
                    'retention_days'   => $plan['retention_days'] ?? null,
                    'custom_responses' => $plan['custom_responses'] ?? false,
                ],
                'usage' => [
                    'endpoints'      => $endpointIds->count(),
                    'requests_total' => (int) $user->endpoints()->sum('request_count'),
                    'requests_today' => WebhookRequest::whereIn('endpoint_id', $endpointIds)
                        ->where('received_at', '>=', now()->startOfDay())
                        ->count(),
                ],
            ],
        ]);
    }

    /**
     * Authenticate and issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Account suspended.'], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    /**
     * Revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function claimGuestEndpoints(User $user, ?string $guestSessionId): void
    {
        if (! $guestSessionId) {
            return;
        }

        // Re-assign only endpoints whose guest_session_id matches the cookie.
        // This prevents claiming someone else's URL by supplying a forged body value.
        Endpoint::where('guest_session_id', $guestSessionId)
            ->whereNull('owner_user_id')
            ->update([
                'owner_user_id'    => $user->id,
                'guest_session_id' => null,
                'expires_at'       => null, // claimed endpoints no longer auto-delete
            ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id'             => $user->id,
            'email'          => $user->email,
            'plan'           => $user->plan,
            'is_admin'       => (bool) $user->is_admin,
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }
}
