# OPS evidence index v18

Staging refresh + production template paths (2026-06-12).

## Production logs (`*-prod.log`)

| File | Phase |
|------|-------|
| [`mysql-dump-prod-v18.log`](mysql-dump-prod-v18.log) | p01 |
| [`import-run-2026-06-12-prod.log`](import-run-2026-06-12-prod.log) | p02 |
| [`import-verify-2026-06-12-prod.log`](import-verify-2026-06-12-prod.log) | p03 |
| [`post-import-ops-2026-06-12-prod.log`](post-import-ops-2026-06-12-prod.log) | p04 |
| [`import-flags-2026-06-12-prod.log`](import-flags-2026-06-12-prod.log) | p05 |
| [`rollback-drill-prod.log`](rollback-drill-prod.log) | p06 |
| [`cutover-preflight-2026-06-12-prod.log`](cutover-preflight-2026-06-12-prod.log) | p07 |
| [`tls-curl-2026-06-12-prod.log`](tls-curl-2026-06-12-prod.log) | p08 |
| [`webhook-getWebhookInfo-2026-06-12-prod.log`](webhook-getWebhookInfo-2026-06-12-prod.log) | p09 |
| [`reseller-webhook-decrypt-2026-06-12-prod.log`](reseller-webhook-decrypt-2026-06-12-prod.log) | p10 |
| [`relay-forward-2026-06-12-prod.log`](relay-forward-2026-06-12-prod.log) | p11 |
| [`workers-cron-2026-06-12-prod.log`](workers-cron-2026-06-12-prod.log) | p13 |
| [`redis-mysql-backup-2026-06-12-prod.log`](redis-mysql-backup-2026-06-12-prod.log) | p14 |
| [`module-audit-2026-06-12-prod.log`](module-audit-2026-06-12-prod.log) | p15 |
| [`observability-48h-2026-06-12-prod.log`](observability-48h-2026-06-12-prod.log) | p16 |
| [`soak-24h-2026-06-12-prod.log`](soak-24h-2026-06-12-prod.log) | p17 |

## Checklists (`*-v18.md`)

| File | Scope |
|------|-------|
| [`import-checklist-v18.md`](import-checklist-v18.md) | §17 import |
| [`network-webhook-checklist-v18.md`](network-webhook-checklist-v18.md) | §17 network |
| [`staging-infra-checklist-v18.md`](staging-infra-checklist-v18.md) | workers/cron/backup |
| [`observability-checklist-v18.md`](observability-checklist-v18.md) | 48h + soak |
| [`portal-parity-v18.md`](portal-parity-v18.md) | portal + crypto |
| [`relay-setup-signoff-v18.md`](relay-setup-signoff-v18.md) | B.4.4 |
| [`phase16-parallel-v18.md`](phase16-parallel-v18.md) | parallel WP 7d |
| [`wp-post-cutover-v18.md`](wp-post-cutover-v18.md) | 48h monitor |
| [`staging-buy-flow-v18.md`](staging-buy-flow-v18.md) | buy flow |
| [`arch-decommission-ready-v18.md`](arch-decommission-ready-v18.md) | ARCH-12 |
| [`frontend-fetch-audit-v18.md`](frontend-fetch-audit-v18.md) | Appendix B |

Operator / date: 2026-06-12
