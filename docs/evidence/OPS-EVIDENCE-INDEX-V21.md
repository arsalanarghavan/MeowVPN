# OPS evidence index v21

Live operator runs replace v20 templates. Each log must match script `tee` output (no markdown tables, no ellipses).

## Production logs

| File | Phase |
|------|-------|
| [`mysql-dump-prod-v21.log`](mysql-dump-prod-v21.log) | p01 |
| [`import-run-2026-06-14-prod-v21.log`](import-run-2026-06-14-prod-v21.log) | p02 |
| [`import-verify-2026-06-14-prod-v21.log`](import-verify-2026-06-14-prod-v21.log) | p03 |
| [`post-import-ops-2026-06-14-prod-v21.log`](post-import-ops-2026-06-14-prod-v21.log) | p04 |
| [`import-flags-2026-06-14-prod-v21.log`](import-flags-2026-06-14-prod-v21.log) | p05 |
| [`rollback-drill-prod-v21.log`](rollback-drill-prod-v21.log) | p06 |
| [`cutover-preflight-2026-06-14-prod-v21.log`](cutover-preflight-2026-06-14-prod-v21.log) | p07 |
| [`tls-curl-2026-06-14-prod-v21.log`](tls-curl-2026-06-14-prod-v21.log) | p08 |
| [`webhook-getWebhookInfo-2026-06-14-prod-v21.log`](webhook-getWebhookInfo-2026-06-14-prod-v21.log) | p09 |
| [`reseller-webhook-decrypt-2026-06-14-prod-v21.log`](reseller-webhook-decrypt-2026-06-14-prod-v21.log) | p10 |
| [`relay-forward-2026-06-14-prod-v21.log`](relay-forward-2026-06-14-prod-v21.log) | p11 |
| [`proxy-egress-prod-v21.log`](proxy-egress-prod-v21.log) | p12 |
| [`portal-parity-v21.md`](portal-parity-v21.md) | p13 |
| [`portal-parity-2026-06-14-prod-v21.log`](portal-parity-2026-06-14-prod-v21.log) | p13 |
| [`workers-cron-2026-06-14-prod-v21.log`](workers-cron-2026-06-14-prod-v21.log) | p14 |
| [`redis-mysql-backup-2026-06-14-prod-v21.log`](redis-mysql-backup-2026-06-14-prod-v21.log) | p15 |
| [`module-audit-2026-06-14-prod-v21.log`](module-audit-2026-06-14-prod-v21.log) | p16 |
| [`observability-48h-2026-06-14-prod-v21.log`](observability-48h-2026-06-14-prod-v21.log) | p17 |
| [`soak-24h-2026-06-14-prod-v21.log`](soak-24h-2026-06-14-prod-v21.log) | p18 |
| [`phase16-parallel-2026-06-14-staging-v21.log`](phase16-parallel-2026-06-14-staging-v21.log) | p19 |
| [`load-smoke-2026-06-14-prod-v21.log`](load-smoke-2026-06-14-prod-v21.log) | p20 |
| [`admin-alerts-fire-2026-06-14-prod-v21.log`](admin-alerts-fire-2026-06-14-prod-v21.log) | p20 |
| [`wp-disable-2026-06-14-prod-v21.log`](wp-disable-2026-06-14-prod-v21.log) | p20 |
| [`staging-cutover-runbook-2026-06-14-v21.log`](staging-cutover-runbook-2026-06-14-v21.log) | p20 |
| [`wp-post-cutover-monitor-2026-06-14-prod-v21.log`](wp-post-cutover-monitor-2026-06-14-prod-v21.log) | p20 |
| [`staging-buy-flow-v21.md`](staging-buy-flow-v21.md) | p20 |

Operator / date: 2026-06-14
