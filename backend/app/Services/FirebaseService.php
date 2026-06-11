<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Factory;

class FirebaseService
{
    private ?Database $database = null;

    private bool $resolved = false;

    /**
     * Publish a "new request" event to the endpoint's live-tail path.
     *
     * Clients subscribed to /endpoints/{endpointId}/latest will receive
     * this push immediately. We do NOT store the full request in Firebase —
     * MySQL is the source of truth. We only push a lightweight signal
     * so the Vue client knows to fetch the new row.
     *
     * No-ops when Firebase isn't configured — capture must never depend on it.
     */
    public function pushNewRequest(string $endpointId, array $summary): void
    {
        $database = $this->database();

        if (! $database) {
            return;
        }

        $database
            ->getReference("endpoints/{$endpointId}/latest")
            ->set([
                'id'          => $summary['id'],
                'method'      => $summary['method'],
                'path'        => $summary['path'] ?? null,
                'content_type' => $summary['content_type'],
                'body_size'   => $summary['body_size'],
                'received_at' => $summary['received_at'],
                'ts'          => time(), // forces a change even if two requests arrive in the same second
            ]);
    }

    /**
     * Lazily connect on first use. Returns null (and logs once) when the
     * credentials file or database URL is missing, so local setups without
     * Firebase still capture requests — they just lose live-tail.
     */
    private function database(): ?Database
    {
        if ($this->resolved) {
            return $this->database;
        }

        $this->resolved = true;

        $credentialsPath = base_path(config('firebase.credentials'));
        $databaseUrl     = config('firebase.database_url');

        if (! $databaseUrl || ! is_file($credentialsPath)) {
            Log::info('Firebase not configured — live-tail pushes disabled.');

            return null;
        }

        $this->database = (new Factory)
            ->withServiceAccount($credentialsPath)
            ->withDatabaseUri($databaseUrl)
            ->createDatabase();

        return $this->database;
    }
}
