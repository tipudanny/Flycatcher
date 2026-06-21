<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    // 'json' cast round-trips scalars (true/15/"str") as well as arrays.
    protected $casts = ['value' => 'json'];

    public $timestamps = true;

    /**
     * Application defaults. Stored rows override these, so adding a new key
     * here makes it available immediately without a seed/migration.
     */
    public const DEFAULTS = [
        'extension_enabled'       => true,   // master toggle for the browser extension
        'extension_notifications' => true,   // desktop notifications default
        'extension_badge'         => true,   // unread-count badge default
        'extension_poll_interval' => 30,     // seconds between checks (Chrome floors at ~30s)
        'app_url'                 => null,    // falls back to config('app.frontend_url')
    ];

    public static function value(string $key): mixed
    {
        $row = static::where('key', $key)->first();

        return $row ? $row->value : (self::DEFAULTS[$key] ?? null);
    }

    /**
     * All settings merged over defaults, with app_url resolved.
     */
    public static function map(): array
    {
        $out = self::DEFAULTS;

        foreach (static::pluck('value', 'key') as $key => $value) {
            $out[$key] = $value;
        }

        if (empty($out['app_url'])) {
            $out['app_url'] = config('app.frontend_url');
        }

        return $out;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
