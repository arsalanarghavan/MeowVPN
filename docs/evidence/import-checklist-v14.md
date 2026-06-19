# Import Checklist v14 (§17 #1–12)

| # | Step | Command | Evidence |
|---|------|---------|----------|
| 1 | `SVP_MYSQL_DSN` staging | secret manager | ticket |
| 2 | WP mysqldump | `mysqldump ... > wp-final-YYYY-MM-DD.sql` | secure store |
| 3–12 | See v13 | `backend/scripts/ops/*` | `import-verify-*.log` |

PHPUnit: `WpImportForceTest`, `WpImportBackupsFromTest`, `WpResellerPermsImportTest`.
