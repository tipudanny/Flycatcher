<?php

namespace App\Console\Commands;

use App\Models\Endpoint;
use Illuminate\Console\Command;

class ExpireEndpoints extends Command
{
    protected $signature = 'endpoints:expire';

    protected $description = 'Hard-delete expired guest endpoints and all their captured requests';

    /**
     * Guest data retention: every guest endpoint gets expires_at on creation
     * (now + guest_retention_days). Past that moment the capture URL already
     * returns 410; this command removes the rows for real. Requests cascade
     * via the webhook_requests.endpoint_id foreign key.
     *
     * Owned endpoints are never touched — claiming clears expires_at.
     */
    public function handle(): int
    {
        $deleted = 0;

        Endpoint::withTrashed()
            ->whereNull('owner_user_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($endpoints) use (&$deleted) {
                foreach ($endpoints as $endpoint) {
                    $endpoint->forceDelete();
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} expired guest endpoint(s).");

        return self::SUCCESS;
    }
}
