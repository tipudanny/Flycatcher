<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $this->userPayload($user),
            'token' => $token,
        ], 201);
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
            'id'    => $user->id,
            'email' => $user->email,
            'plan'  => $user->plan,
        ];
    }
}
