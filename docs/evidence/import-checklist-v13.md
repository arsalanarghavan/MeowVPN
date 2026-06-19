# Import & Cutover Checklist v13 (§17 items 1–12)

اپراتور: اجرا روی staging/production؛ secrets در repo commit نشوند.

| # | Step | Command | Evidence |
|---|------|---------|----------|
| 1 | `SVP_MYSQL_DSN` staging | secret manager | ticket |
| 2 | WP mysqldump snapshot | `mysqldump ... > wp-final-YYYY-MM-DD.sql` | secure store |
| 3 | Dry-run | `cd backend && php artisan wp:import --dry-run` | `import-dry-run-YYYY-MM-DD.log` |
| 4 | Full import | `SVP_MYSQL_DSN=... backend/scripts/ops/import-run.sh` | stdout |
| 5 | Verify | `SVP_MYSQL_DSN=... backend/scripts/ops/import-verify.sh` | `import-verify-YYYY-MM-DD.log` |
| 6 | Count diff | WpImportVerifier | in verify log |
| 7 | Post-import | `backend/scripts/ops/post-import-ops.sh` | stdout |
| 8 | `--force` live | re-run import on DSN | log |
| 9 | `--since` | `wp:import --since=...` | `WpImportSinceTest` |
| 10 | `--backups-from` | `wp:import --backups-from=...` | `WpImportBackupsFromTest` |
| 11 | Rollback drill | `backend/scripts/ops/rollback-drill.sh` | `rollback-drill.log` |
| 12 | Runbook | `staging-cutover-runbook.sh` | exit 0 |

PHPUnit: `WpImportForceTest`, `WpImportIdempotentTest`, `WpImportVerifyOnlyTest`.
