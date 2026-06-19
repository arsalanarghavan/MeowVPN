# OPS evidence index v22

Live operator runs on `api.simplevpbot.ir` (not `*.example`). Each log must match script `tee` output (no markdown tables, no ellipses).

## Production logs

| File | Phase |
|------|-------|
| [`mysql-dump-prod-v22.log`](mysql-dump-prod-v22.log) | p01 |
| [`import-run-2026-06-15-prod-v22.log`](import-run-2026-06-15-prod-v22.log) | p02 |
| [`import-verify-2026-06-15-prod-v22.log`](import-verify-2026-06-15-prod-v22.log) | p03 |
| [`post-import-ops-2026-06-15-prod-v22.log`](post-import-ops-2026-06-15-prod-v22.log) | p04 |
| [`import-flags-2026-06-15-prod-v22.log`](import-flags-2026-06-15-prod-v22.log) | p05 |
| [`rollback-drill-prod-v22.log`](rollback-drill-prod-v22.log) | p06 |
| [`cutover-preflight-2026-06-15-prod-v22.log`](cutover-preflight-2026-06-15-prod-v22.log) | p07 |
| [`tls-curl-2026-06-15-prod-v22.log`](tls-curl-2026-06-15-prod-v22.log) | p08 |
| [`webhook-getWebhookInfo-2026-06-15-prod-v22.log`](webhook-getWebhookInfo-2026-06-15-prod-v22.log) | p09 |
| [`reseller-webhook-decrypt-2026-06-15-prod-v22.log`](reseller-webhook-decrypt-2026-06-15-prod-v22.log) | p10 |
| [`relay-forward-2026-06-15-prod-v22.log`](relay-forward-2026-06-15-prod-v22.log) | p11 |
| [`proxy-egress-prod-v22.log`](proxy-egress-prod-v22.log) | p12 |
| [`portal-parity-v22.md`](portal-parity-v22.md) | p13 |
| [`portal-parity-2026-06-15-prod-v22.log`](portal-parity-2026-06-15-prod-v22.log) | p13 |
| [`workers-cron-2026-06-15-prod-v22.log`](workers-cron-2026-06-15-prod-v22.log) | p14 |
| [`redis-mysql-backup-2026-06-15-prod-v22.log`](redis-mysql-backup-2026-06-15-prod-v22.log) | p15 |
| [`module-audit-2026-06-15-prod-v22.log`](module-audit-2026-06-15-prod-v22.log) | p16 |
| [`observability-48h-2026-06-15-prod-v22.log`](observability-48h-2026-06-15-prod-v22.log) | p17 |
| [`soak-24h-2026-06-15-prod-v22.log`](soak-24h-2026-06-15-prod-v22.log) | p18 |
| [`phase16-parallel-2026-06-15-staging-v22.log`](phase16-parallel-2026-06-15-staging-v22.log) | p19 |
| [`load-smoke-2026-06-15-prod-v22.log`](load-smoke-2026-06-15-prod-v22.log) | p20 |
| [`admin-alerts-fire-2026-06-15-prod-v22.log`](admin-alerts-fire-2026-06-15-prod-v22.log) | p20 |
| [`wp-disable-2026-06-15-prod-v22.log`](wp-disable-2026-06-15-prod-v22.log) | p20 |
| [`staging-cutover-runbook-2026-06-15-v22.log`](staging-cutover-runbook-2026-06-15-v22.log) | p20 |
| [`wp-post-cutover-monitor-2026-06-15-prod-v22.log`](wp-post-cutover-monitor-2026-06-15-prod-v22.log) | p20 |
| [`staging-buy-flow-v22.md`](staging-buy-flow-v22.md) | p20 |

Operator / date: 2026-06-15
