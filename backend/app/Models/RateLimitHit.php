<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateLimitHit extends Model
{
    protected $fillable = ['limiter', 'key_type', 'key_value', 'route', 'hit_count', 'first_hit_at', 'last_hit_at'];

    protected $casts = [
        'first_hit_at' => 'datetime',
        'last_hit_at'  => 'datetime',
    ];

    /**
     * Record one 429 against (limiter, key) — one row per pair, incremented
     * on every hit, so a sustained flood doesn't itself flood this table.
     */
    public static function record(string $limiter, string $keyType, string $keyValue, ?string $route): void
    {
        $row = static::firstOrNew([
            'limiter'   => $limiter,
            'key_type'  => $keyType,
            'key_value' => $keyValue,
        ]);

        $row->route       = $route;
        $row->last_hit_at = now();
        $row->hit_count   = $row->exists ? $row->hit_count + 1 : 1;

        if (! $row->exists) {
            $row->first_hit_at = now();
        }

        $row->save();
    }
}
