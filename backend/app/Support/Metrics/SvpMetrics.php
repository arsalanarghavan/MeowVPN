<?php

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Cache;

class SvpMetrics
{
    public static function inc(string $name, float $by = 1): void
    {
        $key = 'svp_metric:'.$name;
        Cache::put($key, (float) Cache::get($key, 0) + $by, now()->addDays(7));
    }

    public static function get(string $name): float
    {
        return (float) Cache::get('svp_metric:'.$name, 0);
    }

    public static function observe(string $name, float $seconds, ?string $label = null): void
    {
        $key = 'svp_metric:'.$name;
        Cache::put($key, $seconds, now()->addDays(7));
        if ($label !== null && $label !== '') {
            Cache::put('svp_metric:'.$name.':'.$label, $seconds, now()->addDays(7));
        }
    }

    /** @return array<string, float> */
    public static function allWithPrefix(string $prefix): array
    {
        // Cache driver does not support scan; export known cron labels via get.
        $out = [];
        $val = self::get($prefix);
        if ($val > 0) {
            $out[$prefix] = $val;
        }

        return $out;
    }
}
