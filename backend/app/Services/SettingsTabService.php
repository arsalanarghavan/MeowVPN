<?php

namespace App\Services;

class SettingsTabService
{
    /** Tabs whose keys are also mirrored flat (WP `svp_settings` parity). */
    /** @var list<string> */
    protected const FLAT_MIRROR_TABS = [
        'general',
        'referral',
        'whitelabel',
        'resellers_defaults',
        'finance',
        'proxy',
        'relay',
        'service_naming',
        'bots',
        'plans_catalog',
        'cards',
        'force_join',
        'receipts',
        'landing',
        'subscription_portal',
        'cron',
    ];
    /** @var list<string> */
    protected const ALLOWED_TABS = [
        'general',
        'referral',
        'whitelabel',
        'notifications',
        'purge_expired',
        'resellers_defaults',
        'finance',
        'proxy',
        'relay',
        'service_naming',
        'logs',
        'backup',
        'bots',
        'plans_catalog',
        'cards',
        'force_join',
        'receipts',
        'landing',
        'subscription_portal',
        'cron',
    ];

    /** Keys also stored flat (WP parity) when saved from a tab. */
    /** @var array<string, list<string>> */
    protected const FLAT_MIRROR_KEYS = [
        'notifications' => [
            'notify_low_traffic_percent',
            'notify_expiry_days',
            'notify_user_volume',
            'notify_user_expiry',
            'notify_user_users',
            'notify_user_after_expire',
            'notify_idle_enabled',
            'notify_idle_after_days',
            'notify_idle_cooldown_days',
            'notify_admin_panel_down',
            'notify_admin_panel_down_cooldown',
            'notify_panel_cost_expiry',
            'alert_ip_warn_min_distinct',
            'alert_ip_warn_hysteresis',
            'alert_ip_warn_cooldown_minutes',
            'traffic_stale_days',
        ],
        'purge_expired' => [
            'purge_expired_enabled',
            'purge_expired_grace_days',
            'purge_expired_warn_days',
            'purge_expired_notify_user',
        ],
        'landing' => [
            'landing_enabled',
            'landing_hero_title',
            'landing_hero_subtitle',
            'landing_promo_title',
            'landing_promo_code',
        ],
        'subscription_portal' => [
            'portal_subscription_template',
            'portal_theme_brand_name',
            'portal_theme_brand_tagline',
            'portal_modern_brand_name',
            'portal_modern_brand_tagline',
            'portal_datepicker',
            'portal_theme_primary_light',
            'portal_theme_primary_dark',
            'portal_theme_radius',
        ],
        'cron' => [
            'internal_cron_secret',
            'internal_cron_allowed_ips',
            'live_metrics_poll_seconds',
            'live_sse_push_seconds',
        ],
    ];

    public function __construct(protected SettingsStore $settings) {}

    /** @param  array<string, mixed>  $values */
    public function save(string $tab, array $values): bool
    {
        $tab = preg_replace('/[^a-z0-9_]/', '', strtolower($tab)) ?? '';
        if ($tab === 'panel') {
            $tab = 'logs';
        }
        if ($tab === '' || ! in_array($tab, self::ALLOWED_TABS, true)) {
            return false;
        }

        unset($values['tab'], $values['op'], $values['settings_tab']);
        $flatMirror = self::FLAT_MIRROR_KEYS[$tab] ?? [];
        foreach ($values as $key => $value) {
            $safeKey = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key)) ?? '';
            if ($safeKey === '') {
                continue;
            }
            $this->settings->set("{$tab}.{$safeKey}", $value);
            if (in_array($safeKey, $flatMirror, true) || in_array($tab, self::FLAT_MIRROR_TABS, true)) {
                $this->settings->set($safeKey, $value);
            }
            if ($tab === 'backup' && $safeKey === 'backup_interval_minutes') {
                $this->settings->set('backup_interval_minutes', max(5, min(1440, (int) $value)));
            }
            if ($tab === 'resellers_defaults' && $safeKey === 'permissions') {
                $this->settings->set('resellers_defaults', ['permissions' => $value]);
            }
        }

        if ($tab === 'whitelabel') {
            $this->settings->set('whitelabel', BrandingResolver::packFromSettings($this->settings));
        }

        return true;
    }
}
