# Import & Cutover Checklist v12 (§17 items 1–12)

اپراتور: این فهرست را روی staging/production اجرا کنید. خروجی را در `docs/evidence/` ذخیره کنید — **بدون commit کردن secrets**.

| # | Step | Command | Evidence file |
|---|------|---------|---------------|
| 1 | `SVP_MYSQL_DSN` staging (secret manager) | env / vault | operator ticket |
| 2 | Final WP mysqldump snapshot | `mysqldump ... > wp-final-YYYY-MM-DD.sql` | secure backup store |
| 3 | Dry-run import | `cd backend && php artisan wp:import --dry-run` | stdout → `import-dry-run-YYYY-MM-DD.log` |
| 4 | Full import | `SVP_MYSQL_DSN=... backend/scripts/ops/import-run.sh` | stdout |
| 5 | Verify log | `SVP_MYSQL_DSN=... backend/scripts/ops/import-verify.sh` | `import-verify-YYYY-MM-DD.log` |
| 6 | Count diff | review `WpImportVerifier` output | in verify log |
| 7 | Post-import | `backend/scripts/ops/post-import-ops.sh` | stdout |
| 8 | Idempotency `--force` | re-run import on real DSN | log |
| 9 | Incremental `--since` | `wp:import --since=...` | `WpImportSinceTest` green |
| 10 | `--backups-from` | `wp:import --backups-from=...` | stdout |
| 11 | Rollback drill | `backend/scripts/ops/rollback-drill.sh` | `rollback-drill.log` |
| 12 | Full runbook | `SVP_MYSQL_DSN=... backend/scripts/ops/staging-cutover-runbook.sh` | exit 0 |

PHPUnit (CI/local): `WpImportIdempotentTest`, `WpImportSinceTest`, `WpImportVerifyOnlyTest`.

**v12:** steps 1–2 = env + snapshot only (no repo commit). Steps 3–12 require live staging DSN.
