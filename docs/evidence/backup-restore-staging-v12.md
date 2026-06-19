# Backup Restore Staging E2E v12

| Step | Command | Evidence |
|------|---------|----------|
| Manual backup | mutate `backup_run` or UI | `svp_backups` row |
| Download | `GET /api/v1/admin/backup/download` | file bytes |
| Restore dry-run | `BackupRestoreTest` (service) | PHPUnit green |
| Staging full restore | ops runbook | operator sign-off |

Service-level restore covered in `backend/tests/Feature/Backup/BackupRestoreTest.php`.
