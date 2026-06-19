# Staging Infra Checklist v17 (§17 #26–32)

| # | Check | Result |
|---|-------|--------|
| 26 | `queue-worker` profile | `docker compose --profile workers up -d queue-worker` |
| 27 | 14 cron on scheduler | `php artisan schedule:list` — 14 `svp:*` |
| 28 | Redis persistence | AOF/RDB enabled on staging redis |
| 29 | MySQL external backup cron | nightly mysqldump ticket |
| 30 | `SVP_MODULE_*` flags | match staging feature set |
| 31 | Sanctum stateful domains | staging host listed |
| 32 | `APP_KEY` rotation doc | operator runbook |

Evidence: `CronJobHandleBatchTest`, `ScheduleListTest`

Operator / date: 2026-06-12
