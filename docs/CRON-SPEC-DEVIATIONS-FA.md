# انحراف‌های زمان‌بندی Cron (v5)

مبنا: `docs/LARAVEL-BACKEND-SPEC-FA.md` §12

## Intervalها (هم‌تراز spec)

| Job | Laravel (`routes/console.php`) | Spec |
|-----|-------------------------------|------|
| Expiry | hourly | hourly |
| Autorenew | hourly | hourly |
| PanelEconomics | hourly (if `xui_panel`) | hourly |
| Marketing | hourly (if module) | hourly |
| AdminAlerts | every 10 minutes | every 10 minutes |
| IdleOffers | hourly (if module) | hourly |
| PurgeExpired | hourly (if `xui_panel`) | hourly |

## Intervalهای عمدی متفاوت

| Job | Laravel | دلیل |
|-----|---------|------|
| Backup | `*/N` دقیقه از `BackupIntervalResolver` (settings یا env) | gate: `backup` module؛ clamp 5–1440 |
| Broadcast / users_bulk / inbound_queue | every minute | worker queues |
| panel_online / panel_service_sync / inbound_clients_cache | every 10 minutes | فقط اگر `xui_panel` enabled |

## Module gating (§6.2)

| Job | Gate |
|-----|------|
| `svp:backup` | `backup` module |
| `svp:marketing`, `svp:idle_offers` | `marketing` module |
| `svp:panel_online`, `svp:panel_service_sync`, `svp:inbound_clients_cache`, `svp:panel_economics_renewal` | `xui_panel` module |
| `svp:purge_expired` | `xui_panel` module — [`PurgeExpiredService.php`](../backend/app/Modules/XuiPanel/Services/PurgeExpiredService.php) |
| `svp:broadcast` | `marketing` module — [`BroadcastWorkerJob`](../backend/app/Modules/Marketing/Jobs/BroadcastWorkerJob.php) |
| `svp:inbound_queue_drain` | core — [`InboundQueueDrainJob`](../backend/app/Modules/Core/Jobs/InboundQueueDrainJob.php) |

## Purge expired parity (v5)

| قابلیت | Laravel | WP |
|--------|---------|-----|
| enabled gate | `purge_expired_enabled` (default false) | همان |
| grace days | `effectiveGraceDays()` | `effective_grace_days()` |
| warn + notify | `maybeNotifyPurge` + dedup | `maybe_notify_purge` |
| skip L2TP | `L2tpProvisionerService::isL2tp` | `is_l2tp` |
| stats | `purged/warned/failed/grace` | همان |

## Notify / Expiry parity (v4)

| قابلیت | Laravel | WP |
|--------|---------|-----|
| کلیدهای global notify | `notify_user_*` + fallback `notify_*_on` — [`NotifySettings.php`](../backend/app/Services/NotifySettings.php) | `notify_user_expiry/volume/users/after_expire` |
| warn days per service | `ServiceAlertsHelper::effectiveExpiryDays()` | `effective_expiry_days()` |
| low traffic % per service | `effectiveLowTrafficPct()` | `effective_low_traffic_pct()` |
| per-service toggles | `alerts_volume/expiry/users` + legacy columns | همان |
| `notify_after_expire` | daily bucket `svc{id}:expired:{date}` | همان |
| IP-fill live API | `XuiClient::clientIps()` + cache `client_ips_json` on sync | `client_ips()` |
| IP-fill fallback | `svp_service_ip_log` | ip_log |

## Backup interval SSOT (v4)

- UI save: `settings_tab` tab=`backup` → `backup.backup_interval_minutes` + mirror `backup_interval_minutes`
- Scheduler: `BackupIntervalResolver` (settings → env fallback)
- Stale alert: `AdminAlertsService` via resolver

## Metrics

Cron jobs از `CronTimer` برای `cron_job_duration_seconds{job="..."}` استفاده می‌کنند.
