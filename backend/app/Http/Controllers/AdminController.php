<?php

namespace App\Http\Controllers;

use App\Models\Endpoint;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Global overview numbers for the dashboard cards.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users_total'        => User::count(),
                'users_by_plan'      => [
                    'free' => User::where('plan', 'free')->count(),
                    'pro'  => User::where('plan', 'pro')->count(),
                    'team' => User::where('plan', 'team')->count(),
                ],
                'users_suspended'    => User::where('status', 'suspended')->count(),
                'endpoints_total'    => Endpoint::count(),
                'endpoints_guest'    => Endpoint::whereNull('owner_user_id')->count(),
                'requests_total'     => WebhookRequest::count(),
                'requests_today'     => WebhookRequest::where('received_at', '>=', now()->startOfDay())->count(),
            ],
        ]);
    }

    /**
     * All users with their usage. Searchable by email.
     */
    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where('email', 'like', "%{$term}%"))
            ->withCount('endpoints')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'data' => $users->map(fn (User $u) => $this->formatUser($u)),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
            ],
        ]);
    }

    /**
     * Update a user's plan and/or status. The plan drives all limits.
     */
    public function updateUser(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'plan'   => ['sometimes', Rule::in(array_keys(config('plans')))],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]);

        $user->update($validated);

        return response()->json(['data' => $this->formatUser($user->loadCount('endpoints'))]);
    }

    /**
     * Every endpoint in the system, with owner and traffic.
     */
    public function endpoints(Request $request): JsonResponse
    {
        $endpoints = Endpoint::query()
            ->with('owner:id,email')
            ->when($request->query('q'), fn ($q, $term) =>
                $q->where('token', 'like', "%{$term}%")->orWhere('label', 'like', "%{$term}%"))
            ->orderByDesc('last_activity_at')
            ->paginate(50);

        return response()->json([
            'data' => $endpoints->map(fn (Endpoint $e) => [
                'token'            => $e->token,
                'label'            => $e->label,
                'type'             => $e->type,
                'owner_email'      => $e->owner?->email,
                'request_count'    => $e->request_count,
                'last_activity_at' => $e->last_activity_at?->toIso8601String(),
                'expires_at'       => $e->expires_at?->toIso8601String(),
                'created_at'       => $e->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $endpoints->currentPage(),
                'last_page'    => $endpoints->lastPage(),
                'total'        => $endpoints->total(),
            ],
        ]);
    }

    /**
     * The plan catalogue + limits, so the UI can show what each tier grants.
     */
    public function plans(): JsonResponse
    {
        return response()->json(['data' => config('plans')]);
    }

    /**
     * Read application settings (extension config etc.) for the backoffice.
     */
    public function settings(): JsonResponse
    {
        return response()->json(['data' => Setting::map()]);
    }

    /**
     * Update application settings from the backoffice panel.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'extension_enabled'       => 'sometimes|boolean',
            'extension_notifications' => 'sometimes|boolean',
            'extension_badge'         => 'sometimes|boolean',
            'extension_poll_interval' => 'sometimes|integer|min:15|max:3600',
            'app_url'                 => 'sometimes|nullable|url',
        ]);

        foreach ($validated as $key => $value) {
            Setting::put($key, $value);
        }

        return response()->json(['data' => Setting::map()]);
    }

    private function formatUser(User $user): array
    {
        $plan = config("plans.{$user->plan}");

        return [
            'id'              => $user->id,
            'email'           => $user->email,
            'plan'            => $user->plan,
            'status'          => $user->status,
            'is_admin'        => (bool) $user->is_admin,
            'endpoints_count' => $user->endpoints_count ?? 0,
            'limits'          => [
                'max_endpoints'    => $plan['max_endpoints'] ?? null,
                'request_limit'    => $plan['request_limit'] ?? null,
                'retention_days'   => $plan['retention_days'] ?? null,
                'custom_responses' => $plan['custom_responses'] ?? false,
            ],
            'created_at'      => $user->created_at->toIso8601String(),
        ];
    }
}
