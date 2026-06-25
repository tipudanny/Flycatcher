<?php

// Partial config — Laravel 11 merges this with the framework's app.php defaults.
return [

    'name' => env('APP_NAME', 'Webhook Inspector'),

    // Base URL of the Vue SPA, used to build shareable view URLs.
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    // Ingestion body size cap (bytes). Oversized captures get a 413.
    'max_body_size' => (int) env('MAX_BODY_SIZE_BYTES', 10 * 1024 * 1024),

    // Ingestion rate limit, per endpoint token per minute.
    'rate_limit_per_minute' => (int) env('RATE_LIMIT_PER_MINUTE', 60),

    // Only honour X-Forwarded-* headers when behind our own load balancer.
    'trust_proxy' => (bool) env('TRUST_PROXY', false),

    // ── Rate limits (per minute) ─────────────────────────────────────────────
    'ingest_ip_limit' => (int) env('INGEST_IP_LIMIT', 300), // total captures per IP
    'api_limit'       => (int) env('API_LIMIT', 120),       // general /api/* per user/IP
    'auth_limit'      => (int) env('AUTH_LIMIT', 10),        // login/register per IP
    'create_limit'    => (int) env('CREATE_LIMIT', 20),     // URL creation per user/IP

    // Guest endpoints: max captured requests per URL. Once reached, the
    // capture URL rejects new hooks (429) but data stays readable.
    'guest_request_limit' => (int) env('GUEST_REQUEST_LIMIT', 200),

    // Guest endpoints: days until the URL and all its data are deleted.
    'guest_retention_days' => (int) env('GUEST_RETENTION_DAYS', 2),

];
