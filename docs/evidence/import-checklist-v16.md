# Import Checklist v16 (§17 #1–12)

| # | Step | Command | Evidence path |
|---|------|---------|---------------|
| 1 | `SVP_MYSQL_DSN` staging | secret manager | ticket ID |
| 2 | WP final mysqldump | `mysqldump -h... wordpress > wp-final-YYYY-MM-DD.sql` | secure store |
| 3 | dry-run | `php artisan wp:import --dry-run ...` | stdout capture |
| 4 | full import | `backend/scripts/ops/import-run.sh` | exit 0 |
| 5 | verify | `import-verify.sh` | `docs/evidence/import-verify-YYYY-MM-DD.log` |
| 6 | diff counts | `WpImportVerifier` output in verify log | same log |
| 7 | post-import | `post-import-ops.sh` | closure + webhooks + schedule:list |
| 8 | `--force` live | operator on staging DSN | log excerpt |
| 9 | `--since` incremental | operator | log excerpt |
| 10 | `--backups-from` | operator | log excerpt |
| 11 | rollback drill | `rollback-drill.sh` | `rollback-drill.log` |
| 12 | cutover runbook | `staging-cutover-runbook.sh` | exit 0 |

PHPUnit: `WpImportForceTest`, `WpImportSinceTest`, `WpImportBackupsFromTest`.

Operator / date: _______________
