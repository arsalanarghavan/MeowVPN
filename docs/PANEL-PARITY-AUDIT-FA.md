# Panel Parity Audit (WP → Laravel) — v5

مبنا: `includes/admin/services/class-service-admin-ops.php` vs Laravel panel services.

## PurgeExpiredService (v5)

| WP `class-cron-purge-expired.php` | Laravel | وضعیت |
|-----------------------------------|---------|--------|
| `purge_expired_enabled` gate | `PurgeExpiredService::isEnabled()` | OK |
| `purge_expired_grace_days` | `effectiveGraceDays()` (+ hours fallback) | OK |
| warn days `[7,3,1,0]` + notify | `maybeNotifyPurge` + dedup cache | OK |
| skip L2TP | `L2tpProvisionerService::isL2tp` | OK |
| batch 30 + stats | `BATCH_LIMIT=30`, `purged/warned/failed` | OK |
| `purge_ready_batch` | `purgeReadyBatch()` | OK |

## PanelRebuildService

| WP | Laravel | وضعیت |
|----|---------|--------|
| batch offset/limit | `rebuildAll` opts | OK |
| inbound_map per panel | `inboundMap()` | OK |
| dry_run / confirm | `PanelMaintenanceService` | OK |
| error aggregation (max 20) | `$errors` capped | OK |
| post-rebuild configs sync | `configs->syncInboundsAfterMutation` | OK |

## PanelTraffic51200RepairService

| WP | Laravel | وضعیت |
|----|---------|--------|
| batch processing | `run()` opts | OK |
| inbound_map | supported | OK |
| traffic cap 51200 GB | repair logic | verify on staging panel |

## service_panel_transfer

| WP | Laravel v3 | وضعیت |
|----|------------|--------|
| batch `service_ids` (≤20) | `ServicePanelTransferService::transferFromPayload` | OK |
| `target_plan_id` | `resolveTargetPlan` | OK |
| remaining quota from cache | `remainingQuotaBytes` | OK |
| new email/uuid/sub_id | `ServiceNaming` + panel add | OK |
| L2TP guard | reject `bad_service` | OK |
| target compensation on fail | `deleteTargetClient` | OK |
| post-sync both panels | `configs->syncInboundsAfterMutation` | OK |
| user notify | `UserBotNotifyService` | OK |

## inbound_autolink

| WP | Laravel | وضعیت |
|----|---------|--------|
| fuzzy remark match | partial | documented in SPEC-DEVIATIONS |
| plan assignment | supported | OK |
| audit log | MutateAudit | OK |

## purge_expired

| WP | Laravel | وضعیت |
|----|---------|--------|
| default status `all` | `PurgeExpiredQueryService` | OK |
| ready/grace/warned filters | query params | OK |
| run_cron dispatchSync | `PurgeExpiredJob::dispatchSync` | OK |
