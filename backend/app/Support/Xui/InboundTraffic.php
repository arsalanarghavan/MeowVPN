<?php

namespace App\Support\Xui;

class InboundTraffic
{
    private const MAX_CLIENT_TRAFFIC_BYTES = 54975581388800;

    private const BYTES_PER_GB = 1073741824;

    private const MISCALE_CAP_MARKER_GB = 51200;

    private const WRONG_FALLBACK_GB = 50;

    public static function capTrafficBytes(int $bytes): int
    {
        $b = $bytes;
        if ($b <= 0) {
            return 0;
        }
        if ($b > self::MAX_CLIENT_TRAFFIC_BYTES) {
            return self::MAX_CLIENT_TRAFFIC_BYTES;
        }

        return $b;
    }

    public static function panelClientTotalgbJsonValue(int $totalTrafficBytes): int
    {
        $b = self::capTrafficBytes($totalTrafficBytes);

        return $b > 0 ? $b : 0;
    }

    public static function totalgbToBytes(mixed $raw): int
    {
        if (! is_numeric($raw)) {
            return 0;
        }
        $n = (float) $raw;
        if ($n <= 0) {
            return 0;
        }

        return self::capTrafficBytes((int) round($n));
    }

    public static function is51200CapBugBytes(int $bytes): bool
    {
        $b = $bytes;
        if ($b <= 0) {
            return false;
        }
        if ($b === self::MISCALE_CAP_MARKER_GB) {
            return true;
        }
        $exact = self::MISCALE_CAP_MARKER_GB * self::BYTES_PER_GB;
        if ($b === $exact) {
            return true;
        }
        if ($b > $exact && $b < $exact + self::BYTES_PER_GB) {
            return true;
        }

        return (int) floor($b / self::BYTES_PER_GB) === self::MISCALE_CAP_MARKER_GB;
    }

    public static function isWrong50gbFallbackBytes(int $bytes): bool
    {
        return $bytes === self::WRONG_FALLBACK_GB * self::BYTES_PER_GB;
    }
}
