# Staging infra checklist v18 — production host verify

| # | Step | Evidence |
|---|------|----------|
| 26 | workers profile | [`workers-cron-2026-06-12-prod.log`](workers-cron-2026-06-12-prod.log) |
| 27 | schedule:list 14 jobs | same log |
| 28 | Redis AOF/RDB | [`redis-mysql-backup-2026-06-12-prod.log`](redis-mysql-backup-2026-06-12-prod.log) |
| 29 | MySQL external backup | same log (OPS-1848) |
| 30–32 | module + Sanctum + APP_KEY | [`module-audit-2026-06-12-prod.log`](module-audit-2026-06-12-prod.log) |

Operator / date: 2026-06-12 (production v18)
