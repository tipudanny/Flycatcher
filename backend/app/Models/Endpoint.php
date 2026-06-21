<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Endpoint extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'token',
        'owner_user_id',
        'guest_session_id',
        'label',
        'settings',
        'expires_at',
        'last_activity_at', // server-set on ingest; never accepted from user input
    ];

    protected $casts = [
        'settings'        => 'array',
        'last_activity_at' => 'datetime',
        'expires_at'      => 'datetime',
    ];

    // Token is generated here, not in the controller.
    protected static function booted(): void
    {
        static::creating(function (Endpoint $endpoint) {
            if (empty($endpoint->token)) {
                // 128 bits of entropy, base62 → ~22 chars. Guess-proof.
                $endpoint->token = Str::random(22);
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function webhookRequests()
    {
        return $this->hasMany(WebhookRequest::class);
    }

    // ── Derived state ──────────────────────────────────────────────────────────

    /**
     * "private" if owned by a user, "guest" if not.
     * Derived — never stored — so the two fields can't disagree.
     */
    public function getTypeAttribute(): string
    {
        return $this->owner_user_id ? 'private' : 'guest';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Max captured requests for this endpoint. Guests use the global guest cap;
     * owned endpoints use their owner's plan limit (null = unlimited).
     */
    public function requestLimit(): ?int
    {
        if (! $this->owner_user_id) {
            return (int) config('app.guest_request_limit', 200);
        }

        $plan = $this->owner?->plan ?? 'free';

        return config("plans.{$plan}.request_limit");
    }

    /**
     * Has this endpoint used up its capture quota? Reads stay allowed —
     * only new captures are rejected.
     */
    public function hasReachedLimit(): bool
    {
        $limit = $this->requestLimit();

        return $limit !== null && $this->request_count >= $limit;
    }

    // ── Authorization helpers ──────────────────────────────────────────────────

    /**
     * Can the given principal view this endpoint's requests?
     *
     * @param  \App\Models\User|null  $user
     * @param  string|null  $guestSessionId  from the signed cookie
     */
    public function canBeViewedBy(?User $user, ?string $guestSessionId = null): bool
    {
        // Owner of a private endpoint
        if ($user && $this->owner_user_id === $user->id) {
            return true;
        }

        // Guest: session must match
        if (! $this->owner_user_id && $guestSessionId && $this->guest_session_id === $guestSessionId) {
            return true;
        }

        return false;
    }
}
