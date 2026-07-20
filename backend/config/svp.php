<?php

return [
    'backup_interval_minutes' => max(5, (int) env('SVP_BACKUP_INTERVAL_MINUTES', 60)),
    'health_deep_token' => env('SVP_HEALTH_DEEP_TOKEN', ''),
    'admin_state_rate_limit_per_min' => max(1, (int) env('SVP_ADMIN_STATE_RATE_LIMIT', 60)),
    'panel_down_alert_sustained_sec' => max(60, (int) env('SVP_PANEL_DOWN_ALERT_SUSTAINED_SEC', 300)),
    'admin_mutate_rate_limit_per_min' => max(1, (int) env('SVP_ADMIN_MUTATE_RATE_LIMIT', 300)),
    'login_rate_limit_per_min' => max(1, (int) env('SVP_LOGIN_RATE_LIMIT', 10)),
    'inbound_queue_alert_threshold' => max(100, (int) env('SVP_INBOUND_QUEUE_ALERT_THRESHOLD', 1000)),
    'relay_alert_fail_threshold' => max(1, (int) env('SVP_RELAY_ALERT_FAIL_THRESHOLD', 3)),
    'rate_limit_trust_forwarded_for' => filter_var(env('SVP_RATE_LIMIT_TRUST_FORWARDED_FOR', false), FILTER_VALIDATE_BOOL),
    'queue_drain_key' => env('SVP_QUEUE_DRAIN_KEY', ''),
    'webhook_rate_limit_per_min' => max(0, (int) env('SVP_WEBHOOK_RATE_LIMIT_PER_MIN', 0)),
    'webhook_reseller_rate_limit_per_min' => max(0, (int) env('SVP_WEBHOOK_RESELLER_RATE_LIMIT_PER_MIN', 0)),
    'portal_link_secret' => env('SVP_PORTAL_LINK_SECRET', ''),
    'dashboard_default_locale' => env('SVP_DASHBOARD_DEFAULT_LOCALE', 'fa'),
    'bot_service_secret' => env('SVP_BOT_SERVICE_SECRET', ''),
    'bot_service_allowed_ips' => env('SVP_BOT_SERVICE_ALLOWED_IPS', ''),
    'legacy_webhook_on_backend' => filter_var(env('SVP_LEGACY_WEBHOOK_ON_BACKEND', true), FILTER_VALIDATE_BOOL),
    'xray_base_config_json' => env('SVP_XRAY_BASE_CONFIG_JSON', ''),
    'xray_agent_url' => env('SVP_XRAY_AGENT_URL', 'http://xray-agent:8444'),
    'xray_public_ip' => env('SVP_XRAY_PUBLIC_IP', ''),
];
