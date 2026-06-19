<?php

namespace App\Services;

/**
 * WP-compatible notify setting keys (notify_user_*) with Laravel legacy fallbacks.
 */
class NotifySettings
{
    public function __construct(protected SettingsStore $settings) {}

    public function expiryOn(): bool
    {
        return $this->bool('notify_user_expiry', 'notify_expiry_on', true);
    }

    public function volumeOn(): bool
    {
        return $this->bool('notify_user_volume', 'notify_volume_on', true);
    }

    public function usersOn(): bool
    {
        return $this->bool('notify_user_users', 'notify_users_on', true);
    }

    public function afterExpireOn(): bool
    {
        return $this->bool('notify_user_after_expire', 'notify_after_expire_on', true);
    }

    /** @return list<int> */
    public function globalExpiryDays(): array
    {
        $raw = $this->settings->get('notify_expiry_days');
        if ($raw === null) {
            $raw = $this->settings->get('alert_expiry_days', [3, 1]);
        }
        if (! is_array($raw)) {
            return [3, 1, 0];
        }

        return array_values(array_filter(array_map(
            static fn ($x) => (($d = (int) $x) >= -3650 && $d <= 3650) ? $d : null,
            $raw
        )));
    }

    public function globalLowTrafficPct(): int
    {
        $v = $this->settings->get('notify_low_traffic_percent');
        if ($v === null) {
            $v = $this->settings->get('alert_low_traffic_pct', 10);
        }

        return max(1, min(99, (int) $v));
    }

    protected function bool(string $wpKey, string $legacyKey, bool $default): bool
    {
        if ($this->settings->get($wpKey) !== null) {
            return (bool) $this->settings->get($wpKey);
        }
        if ($this->settings->get($legacyKey) !== null) {
            return (bool) $this->settings->get($legacyKey);
        }

        return $default;
    }
}
