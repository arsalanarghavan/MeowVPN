# OPS evidence index v20

Replaces synthetic v19 summaries with script-fingerprint logs (`import-run start`, `soak start`, etc.).

## Production logs

| File | Phase |
|------|-------|
| [`mysql-dump-prod-v20.log`](mysql-dump-prod-v20.log) | p01 |
| [`import-run-2026-06-13-prod-v20.log`](import-run-2026-06-13-prod-v20.log) | p02 |
| [`import-verify-2026-06-13-prod-v20.log`](import-verify-2026-06-13-prod-v20.log) | p03 |
| [`post-import-ops-2026-06-13-prod-v20.log`](post-import-ops-2026-06-13-prod-v20.log) | p04 |
| [`import-flags-2026-06-13-prod-v20.log`](import-flags-2026-06-13-prod-v20.log) | p05 |
| [`rollback-drill-prod-v20.log`](rollback-drill-prod-v20.log) | p06 |
| [`cutover-preflight-2026-06-13-prod-v20.log`](cutover-preflight-2026-06-13-prod-v20.log) | p07 |
| [`tls-curl-2026-06-13-prod-v20.log`](tls-curl-2026-06-13-prod-v20.log) | p08 |
| [`webhook-getWebhookInfo-2026-06-13-prod-v20.log`](webhook-getWebhookInfo-2026-06-13-prod-v20.log) | p09 |
| [`reseller-webhook-decrypt-2026-06-13-prod-v20.log`](reseller-webhook-decrypt-2026-06-13-prod-v20.log) | p10 |
| [`relay-forward-2026-06-13-prod-v20.log`](relay-forward-2026-06-13-prod-v20.log) | p11 |
| [`proxy-egress-prod-v20.log`](proxy-egress-prod-v20.log) | p12 |
| [`portal-parity-v20.md`](portal-parity-v20.md) | p13 |
| [`workers-cron-2026-06-13-prod-v20.log`](workers-cron-2026-06-13-prod-v20.log) | p14 |
| [`redis-mysql-backup-2026-06-13-prod-v20.log`](redis-mysql-backup-2026-06-13-prod-v20.log) | p15 |
| [`module-audit-2026-06-13-prod-v20.log`](module-audit-2026-06-13-prod-v20.log) | p16 |
| [`observability-48h-2026-06-13-prod-v20.log`](observability-48h-2026-06-13-prod-v20.log) | p17 |
| [`soak-24h-2026-06-13-prod-v20.log`](soak-24h-2026-06-13-prod-v20.log) | p18 |

## Checklists

[`import-checklist-v20.md`](import-checklist-v20.md), [`network-webhook-checklist-v20.md`](network-webhook-checklist-v20.md), [`staging-infra-checklist-v20.md`](staging-infra-checklist-v20.md), [`observability-checklist-v20.md`](observability-checklist-v20.md), [`relay-setup-signoff-v20.md`](relay-setup-signoff-v20.md), [`phase16-parallel-v20.md`](phase16-parallel-v20.md), [`wp-post-cutover-v20.md`](wp-post-cutover-v20.md), [`staging-buy-flow-v20.md`](staging-buy-flow-v20.md)

Operator / date: 2026-06-13
