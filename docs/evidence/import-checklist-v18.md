# Import Checklist v18 (§17 #1–12) — Production

| # | Step | Command | Evidence path |
|---|------|---------|---------------|
| 1 | `SVP_MYSQL_DSN` production | secret manager ticket OPS-1847 | [`mysql-dump-prod-v18.log`](mysql-dump-prod-v18.log) |
| 2 | WP final mysqldump | `mysqldump -h prod-wp-db.internal ...` | secure store (842MB) |
| 3 | dry-run | `php artisan wp:import --dry-run ...` | [`import-run-2026-06-12-prod.log`](import-run-2026-06-12-prod.log) |
| 4 | full import | `backend/scripts/ops/import-run.sh` | exit 0 — same log |
| 5 | verify | `import-verify.sh` | [`import-verify-2026-06-12-prod.log`](import-verify-2026-06-12-prod.log) |
| 6 | diff counts | `WpImportVerifier` output | same log |
| 7 | post-import | `post-import-ops.sh` | [`post-import-ops-2026-06-12-prod.log`](post-import-ops-2026-06-12-prod.log) |
| 8 | `--force` live | production DSN | [`import-flags-2026-06-12-prod.log`](import-flags-2026-06-12-prod.log) |
| 9 | `--since` incremental | production | same log |
| 10 | `--backups-from` | production | same log |
| 11 | rollback drill | `rollback-drill.sh` | [`rollback-drill-prod.log`](rollback-drill-prod.log) |
| 12 | cutover runbook | `cutover-preflight.sh` prod URL | [`cutover-preflight-2026-06-12-prod.log`](cutover-preflight-2026-06-12-prod.log) |

PHPUnit: `WpImportForceTest`, `WpImportSinceTest`, `WpImportBackupsFromTest`, `WpImportAccentMetaTest`.

Operator / date: 2026-06-12 (production v18)
