<?php

namespace App\Services;

use App\Models\WebhookRequest;
use Illuminate\Support\Facades\Http;

class ReplayService
{
    // Headers that describe the original hop, not the payload — forwarding
    // them to a different host is meaningless or actively wrong.
    private const STRIPPED_HEADERS = [
        'host', 'content-length', 'connection', 'keep-alive',
        'transfer-encoding', 'upgrade', 'te', 'trailer',
        'proxy-connection', 'proxy-authenticate', 'proxy-authorization',
    ];

    /**
     * Resend a captured request to a real URL, exactly as originally received
     * (same method, headers, body). Used to retest a fix without waiting for
     * the third party to fire the webhook again.
     *
     * Returns a result array on completion (including non-2xx target
     * responses — that's a legitimate outcome, not a failure) or
     * ['ok' => false, 'error' => ...] if the request never went out.
     */
    public function replay(WebhookRequest $webhookRequest, string $targetUrl): array
    {
        $guard = $this->guardTarget($targetUrl);
        if ($guard !== null) {
            return ['ok' => false, 'error' => $guard];
        }

        $parts = parse_url($targetUrl);
        $host  = $parts['host'];
        $port  = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $ip    = $this->safeIp($host);

        if (! $ip) {
            return ['ok' => false, 'error' => "Could not resolve a safe address for host \"{$host}\"."];
        }

        $timeout = (int) config('app.replay_timeout_seconds', 10);

        $started = microtime(true);

        try {
            $response = Http::withHeaders($this->forwardableHeaders($webhookRequest))
                ->withBody($webhookRequest->body ?? '', $webhookRequest->content_type ?: 'application/octet-stream')
                ->withOptions([
                    'allow_redirects' => false, // show us what actually happened, don't chase it past our SSRF check
                    'connect_timeout' => $timeout,
                    'timeout'         => $timeout,
                    'stream'          => true,  // read the body ourselves so we can cap it
                    // Pin the connection to the IP we just validated — the DNS
                    // lookup above and the actual connection must hit the same
                    // address, or a rebinding attacker could swap them between
                    // the two (TOCTOU). The Host header still reads $host, so
                    // name-based vhosts and TLS SNI keep working normally.
                    'curl' => [
                        CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
                    ],
                ])
                ->send($webhookRequest->method, $targetUrl);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Request failed: ' . $e->getMessage()];
        }

        [$body, $truncated] = $this->readCapped($response);
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        return [
            'ok'         => true,
            'status'     => $response->status(),
            'headers'    => $response->headers(),
            'body'       => $body,
            'truncated'  => $truncated,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Validate the target is an http(s) URL that doesn't point at internal
     * infrastructure. Returns null when safe, or an error string when not.
     */
    private function guardTarget(string $targetUrl): ?string
    {
        $parts = parse_url($targetUrl);

        if (! $parts || empty($parts['host']) || empty($parts['scheme'])) {
            return 'Enter a full URL, e.g. https://example.com/webhook.';
        }

        if (! in_array($parts['scheme'], ['http', 'https'], true)) {
            return 'Only http:// and https:// targets are allowed.';
        }

        return null;
    }

    /**
     * Resolve the host to an IP and confirm it's publicly routable — never
     * loopback, private, link-local, or otherwise reserved (this is what
     * keeps replay from being usable as a probe against internal services,
     * including the cloud metadata endpoint at 169.254.169.254).
     *
     * Checks every A/AAAA record, not just the first, so an attacker can't
     * hide a private-range answer behind a public-looking first record.
     * Returns the first safe IP to connect to, or null if none qualify.
     */
    private function safeIp(string $host): ?string
    {
        // A literal IP in the URL — validate it directly, no DNS involved.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host) ? $host : null;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records));

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return null;
            }
        }

        return $ips[0] ?? null;
    }

    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * The stored headers are shaped as { name: [values...] } (HTTP allows
     * repeats). Flatten to single values for the outgoing request and drop
     * hop-by-hop headers that only made sense on the original connection.
     */
    private function forwardableHeaders(WebhookRequest $webhookRequest): array
    {
        $headers = [];

        foreach ($webhookRequest->headers ?? [] as $name => $values) {
            if (in_array(strtolower($name), self::STRIPPED_HEADERS, true)) {
                continue;
            }
            $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $headers;
    }

    /**
     * Read the response body up to the configured cap, then stop — a
     * malicious or misbehaving target could otherwise stream gigabytes at
     * an app server that only asked to see the first few KB.
     */
    private function readCapped(\Illuminate\Http\Client\Response $response): array
    {
        $max    = (int) config('app.replay_max_response_bytes', 65536);
        $stream = $response->toPsrResponse()->getBody();

        $body = '';
        while (! $stream->eof() && strlen($body) < $max) {
            $chunk = $stream->read(min(8192, $max - strlen($body)));
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        $truncated = ! $stream->eof();
        $stream->close();

        return [$body, $truncated];
    }
}
