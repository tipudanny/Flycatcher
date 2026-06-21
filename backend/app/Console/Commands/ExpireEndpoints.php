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

        // 1. Guests: delete the whole endpoint (and its requests via cascade).
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

        // 2. Registered users: keep the endpoint, prune requests older than the
        //    owner's plan retention window. request_count is decremented so the
        //    per-URL quota frees up as old data rolls off.
        $pruned = $this->pruneRegisteredRequests();

        $this->info("Pruned {$pruned} expired request(s) from registered endpoints.");

        return self::SUCCESS;
    }

    private function pruneRegisteredRequests(): int
    {
        $pruned = 0;

        foreach (config('plans') as $plan => $limits) {
            $days = $limits['retention_days'] ?? null;
            if ($days === null) {
                continue; // unlimited retention
            }

            $cutoff = now()->subDays($days);

            Endpoint::whereNotNull('owner_user_id')
                ->whereHas('owner', fn ($q) => $q->where('plan', $plan))
                ->chunkById(100, function ($endpoints) use ($cutoff, &$pruned) {
                    foreach ($endpoints as $endpoint) {
                        $count = $endpoint->webhookRequests()
                            ->where('received_at', '<', $cutoff)
                            ->delete();

                        if ($count > 0) {
                            $endpoint->decrement('request_count', $count);
                            $pruned += $count;
                        }
                    }
                });
        }

        return $pruned;
    }
}
