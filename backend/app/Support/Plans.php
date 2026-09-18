<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Plan limits, editable from the admin panel.
 *
 * config/plans.php holds the shipped defaults (and defines which plan keys
 * exist — free/pro/team). The admin panel can override individual fields per
 * plan; those overrides are stored as a single JSON row in `settings`
 * (key: "plans") via the existing Setting store, and merged on top of the
 * defaults here. Nothing else in the app should read config('plans')
 * directly — go through this class so an edited limit takes effect
 * immediately, with no deploy or cache clear.
 */
class Plans
{
    public static function all(): array
    {
        $defaults = config('plans');
        $overrides = Setting::value('plans');

        if (! is_array($overrides)) {
            return $defaults;
        }

        foreach ($defaults as $key => $planDefaults) {
            if (isset($overrides[$key]) && is_array($overrides[$key])) {
                $defaults[$key] = array_merge($planDefaults, $overrides[$key]);
            }
        }

        return $defaults;
    }

    public static function get(string $planKey): array
    {
        return self::all()[$planKey] ?? [];
    }

    public static function limit(string $planKey, string $key): mixed
    {
        return self::get($planKey)[$key] ?? null;
    }

    /**
     * Persist an edit to one or more plans. Only known plan keys and fields
     * are accepted — this can adjust existing tiers, not add new ones.
     */
    public static function update(array $edits): array
    {
        $current = Setting::value('plans');
        $overrides = is_array($current) ? $current : [];

        foreach ($edits as $planKey => $fields) {
            if (! array_key_exists($planKey, config('plans'))) {
                continue;
            }
            $overrides[$planKey] = array_merge($overrides[$planKey] ?? [], $fields);
        }

        Setting::put('plans', $overrides);

        return self::all();
    }
}
