<?php

namespace App\Http\Controllers;

use App\Models\Endpoint;
use App\Models\WebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /**
     * List captured requests for an endpoint, newest first.
     *
     * IMPORTANT: authorization goes through the endpoint — we never query
     * webhook_requests directly by request id without first verifying the
     * caller can access the parent endpoint. This is the IDOR guard.
     */
    public function index(Request $request, string $token): JsonResponse
    {
        $endpoint = $this->findAuthorized($request, $token);

        $perPage = min((int) ($request->query('per_page', 25)), 100);

        $requests = $endpoint->webhookRequests()
            ->orderByDesc('received_at')
            ->paginate($perPage);

        return response()->json([
            'data'  => $requests->map(fn ($r) => $this->formatSummary($r)),
            'meta'  => [
                'current_page'  => $requests->currentPage(),
                'last_page'     => $requests->lastPage(),
                'total'         => $requests->total(),
                'per_page'      => $requests->perPage(),
            ],
        ]);
    }

    /**
     * Return the full detail of a single request.
     *
     * Authorization flows through the endpoint — the endpoint_id on the
     * request row must match the endpoint the caller already has access to.
     */
    public function show(Request $request, string $token, string $requestId): JsonResponse
    {
        $endpoint = $this->findAuthorized($request, $token);

        // Scope to the authorized endpoint — never fetch by ID alone.
        $webhookRequest = $endpoint->webhookRequests()
            ->where('id', $requestId)
            ->firstOrFail();

        return response()->json(['data' => $this->formatDetail($webhookRequest)]);
    }

    /**
     * Delete a single request.
     */
    public function destroy(Request $request, string $token, string $requestId): JsonResponse
    {
        $endpoint = $this->findAuthorized($request, $token);

        $endpoint->webhookRequests()
            ->where('id', $requestId)
            ->delete();

        return response()->json(null, 204);
    }

    /**
     * Delete all requests for an endpoint.
     */
    public function destroyAll(Request $request, string $token): JsonResponse
    {
        $endpoint = $this->findAuthorized($request, $token);

        $endpoint->webhookRequests()->delete();

        $endpoint->update(['request_count' => 0]);

        return response()->json(null, 204);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

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

    /**
     * Lightweight summary shown in the request list panel.
     */
    private function formatSummary(WebhookRequest $r): array
    {
        return [
            'id'           => $r->id,
            'method'       => $r->method,
            'path'         => $r->path,
            'content_type' => $r->content_type,
            'body_size'    => $r->body_size,
            'ip_address'   => $r->ip_address,
            'received_at'  => $r->received_at->toIso8601String(),
        ];
    }

    /**
     * Full detail for the right-side inspector pane.
     */
    private function formatDetail(WebhookRequest $r): array
    {
        return [
            'id'            => $r->id,
            'method'        => $r->method,
            'path'          => $r->path,
            'query_string'  => $r->query_string,
            'query_params'  => $r->parsed_query,          // display-only parsed
            'headers'       => $r->headers,
            'body_raw'      => $r->body,                  // always the original bytes
            'body_pretty'   => $r->pretty_body,           // best-effort formatted version
            'body_size'     => $r->body_size,
            'content_type'  => $r->content_type,
            'ip_address'    => $r->ip_address,
            'user_agent'    => $r->user_agent,
            'received_at'   => $r->received_at->toIso8601String(),
        ];
    }
}
