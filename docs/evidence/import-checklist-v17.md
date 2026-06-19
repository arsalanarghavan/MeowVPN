# Import Checklist v17 (§17 #1–12)

| # | Step | Command | Evidence path |
|---|------|---------|---------------|
| 1 | `SVP_MYSQL_DSN` staging | secret manager | ticket (redacted in logs) |
| 2 | WP final mysqldump | `mysqldump -h... wordpress > wp-final-2026-06-12.sql` | secure store |
| 3 | dry-run | `php artisan wp:import --dry-run ...` | [`import-run-2026-06-12-v17.log`](import-run-2026-06-12-v17.log) |
| 4 | full import | `backend/scripts/ops/import-run.sh` | exit 0 — same log |
| 5 | verify | `import-verify.sh` | [`import-verify-2026-06-12-v17.log`](import-verify-2026-06-12-v17.log) |
| 6 | diff counts | `WpImportVerifier` output | same log |
| 7 | post-import | `post-import-ops.sh` | closure + webhooks + schedule:list |
| 8 | `--force` live | staging DSN | excerpt in import-run log |
| 9 | `--since` incremental | staging | excerpt in import-run log |
| 10 | `--backups-from` | staging | excerpt in import-run log |
| 11 | rollback drill | `rollback-drill.sh` | [`rollback-drill-v17.log`](rollback-drill-v17.log) |
| 12 | cutover runbook | `staging-cutover-runbook.sh` | exit 0 |

PHPUnit: `WpImportForceTest`, `WpImportSinceTest`, `WpImportBackupsFromTest`, `WpImportAccentMetaTest`.

Operator / date: 2026-06-12
