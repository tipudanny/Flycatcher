<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BlockedEntity extends Model
{
    protected $fillable = ['type', 'value', 'is_locked', 'reason', 'locked_by', 'locked_at', 'unlocked_at'];

    protected $casts = [
        'is_locked'   => 'boolean',
        'locked_at'   => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Cached membership check — ingestion is high-throughput and can't
     * afford a query per request. The active-blocked-values set per type is
     * small in practice and cached briefly, invalidated immediately on any
     * lock/unlock so admin action takes effect without waiting out the TTL.
     */
    public static function isBlocked(string $type, string $value): bool
    {
        return in_array($value, static::activeValues($type), true);
    }

    public static function activeValues(string $type): array
    {
        return Cache::remember(
            "blocked-entities:{$type}",
            30,
            fn () => static::where('type', $type)->where('is_locked', true)->pluck('value')->all()
        );
    }

    public static function forgetCache(string $type): void
    {
        Cache::forget("blocked-entities:{$type}");
    }

    public static function lock(string $type, string $value, ?string $reason, ?string $lockedBy): self
    {
        $entity = static::updateOrCreate(
            ['type' => $type, 'value' => $value],
            [
                'is_locked'   => true,
                'reason'      => $reason,
                'locked_by'   => $lockedBy,
                'locked_at'   => now(),
                'unlocked_at' => null,
            ]
        );

        static::forgetCache($type);

        return $entity;
    }

    public static function unlock(string $type, string $value): void
    {
        static::where('type', $type)->where('value', $value)->update([
            'is_locked'   => false,
            'unlocked_at' => now(),
        ]);

        static::forgetCache($type);
    }
}
