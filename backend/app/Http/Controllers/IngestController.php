<?php

namespace App\Http\Controllers;

use App\Models\Endpoint;
use App\Models\WebhookRequest;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class IngestController extends Controller
{
    public function __construct(private FirebaseService $firebase) {}

    /**
     * Capture ANY request to /i/{token} or /i/{token}/{any-sub-path}
     *
     * Design rules:
     *  - No authentication. Capture path is always public.
     *  - Never parse / validate the body. Pure observer.
     *  - Enforce body size cap. Drop oversized requests with 413.
     *  - Respond quickly. Firebase push is fire-and-forget.
     */
    public function capture(Request $request, string $token, string $path = ''): Response
    {
        // ── 1. Look up the endpoint ────────────────────────────────────────────
        $endpoint = Endpoint::where('token', $token)
            ->whereNull('deleted_at')
            ->with('owner:id,plan') // plan drives the per-URL request limit
            ->first();

        if (! $endpoint) {
            // 404, not 401 — don't leak existence patterns
            return response('Not found.', 404);
        }

        if ($endpoint->isExpired()) {
            return response('Gone.', 410);
        }

        // Guest URLs stop capturing at their quota. Data already captured
        // stays readable in the inspector; only the hook goes dead.
        if ($endpoint->hasReachedLimit()) {
            return response('Request limit reached for this URL.', 429);
        }

        // ── 2. Enforce body size cap ───────────────────────────────────────────
        $maxBytes = (int) config('app.max_body_size', 10 * 1024 * 1024); // default 10 MB

        $rawBody = $request->getContent();

        if (strlen($rawBody) > $maxBytes) {
            return response('Payload too large.', 413);
        }

        // ── 3. Capture headers ─────────────────────────────────────────────────
        // Store as [ 'header-name' => ['value1', 'value2'] ] — HTTP allows repeats.
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            // Skip internal Laravel/proxy headers that senders didn't send
            if (str_starts_with($name, 'x-forwarded-') && ! $this->trustProxy()) {
                continue;
            }
            $headers[strtolower($name)] = $values;
        }

        // ── 4. Extract convenience fields (display only — never used for logic) ─
        $contentType = $request->header('Content-Type');

        // ── 5. Resolve client IP ───────────────────────────────────────────────
        // Only trust X-Forwarded-For if coming through our own load balancer.
        $ip = $this->resolveClientIp($request);

        // ── 6. Persist ────────────────────────────────────────────────────────
        $webhookRequest = WebhookRequest::create([
            'endpoint_id'  => $endpoint->id,
            'method'       => $request->method(),
            'path'         => '/' . ltrim($path, '/'),
            'query_string' => $request->server->get('QUERY_STRING') ?: null,
            'headers'      => $headers,
            'body'         => $rawBody ?: null,
            'body_size'    => strlen($rawBody),
            'content_type' => $contentType,
            'ip_address'   => $ip,
            'user_agent'   => $request->userAgent(),
            'received_at'  => now(),
        ]);

        // ── 7. Update denormalized counters (async-safe increment) ─────────────
        // Avoid hot-row contention: use a raw increment instead of read-modify-write.
        $endpoint->incrementQuietly('request_count');
        $endpoint->updateQuietly(['last_activity_at' => now()]);

        // ── 8. Push live-tail event to Firebase ───────────────────────────────
        // Fire-and-forget. A failure here should not fail the capture response.
        try {
            $this->firebase->pushNewRequest($endpoint->id, [
                'id'           => $webhookRequest->id,
                'method'       => $webhookRequest->method,
                'path'         => $webhookRequest->path,
                'content_type' => $webhookRequest->content_type,
                'body_size'    => $webhookRequest->body_size,
                'received_at'  => $webhookRequest->received_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Firebase push failed', ['error' => $e->getMessage(), 'endpoint' => $endpoint->id]);
        }

        // ── 9. Respond to the sender ───────────────────────────────────────────
        // Default: 200. Future: read per-endpoint custom response from settings.
        $settings = $endpoint->settings ?? [];
        $statusCode  = $settings['response_status'] ?? 200;
        $responseBody = $settings['response_body']  ?? 'OK';
        $responseHeaders = $settings['response_headers'] ?? [];

        $response = response($responseBody, $statusCode);

        foreach ($responseHeaders as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function trustProxy(): bool
    {
        return (bool) config('app.trust_proxy', false);
    }

    private function resolveClientIp(Request $request): string
    {
        if ($this->trustProxy()) {
            // Read only the first (leftmost = original client) hop from X-Forwarded-For.
            $forwarded = $request->header('X-Forwarded-For');
            if ($forwarded) {
                return trim(explode(',', $forwarded)[0]);
            }
        }

        return $request->ip();
    }
}
