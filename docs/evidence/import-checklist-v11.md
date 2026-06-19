# Import & Cutover Checklist v11 (§17 items 1–12)

اپراتور: این فهرست را روی staging/production اجرا کنید و خروجی را در `docs/evidence/` ذخیره کنید.

| # | Step | Command | Evidence file |
|---|------|---------|---------------|
| 1 | `SVP_MYSQL_DSN` staging (no commit) | env local / secret manager | — |
| 2 | Final WP mysqldump snapshot | `mysqldump ... > wp-final.sql` | secure backup store |
| 3 | Dry-run import | `php artisan wp:import --dry-run` | stdout |
| 4 | Full import | `SVP_MYSQL_DSN=... backend/scripts/ops/import-run.sh` | stdout |
| 5 | Verify log | `backend/scripts/ops/import-verify.sh` | `import-verify-YYYY-MM-DD.log` |
| 6 | Count diff | review `WpImportVerifier` output | in verify log |
| 7 | Post-import | `backend/scripts/ops/post-import-ops.sh` | stdout |
| 8 | Idempotency `--force` | re-run import on real DSN | CI / manual log |
| 9 | Incremental `--since` | `wp:import --since=...` | `WpImportSinceTest` green |
| 10 | `--backups-from` | `wp:import --backups-from=...` | stdout |
| 11 | Rollback drill | `backend/scripts/ops/rollback-drill.sh` | `rollback-drill.log` |
| 12 | Full runbook | `backend/scripts/ops/staging-cutover-runbook.sh` | exit 0 |

PHPUnit (local/CI): `WpImportIdempotentTest`, `WpImportSinceTest`, `WpImportVerifyOnlyTest`.
