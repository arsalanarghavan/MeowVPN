# Queue & Horizon — انحراف آگاهانه (v18)

## تصمیم

| موضوع | Spec | پیاده‌سازی v18 |
|-------|------|----------------|
| Queue driver | Redis + Horizon | Redis + `queue-worker` container (`docker compose --profile workers`) |
| Local dev | `php artisan queue:listen` | `database` driver acceptable in `.env.example` for CI |
| Horizon UI | Laravel Horizon | **استفاده نمی‌شود** — worker ساده + `schedule:work` در scheduler container |
| Broadcast queue | spec §2.5 `database` | **Redis** — throughput + existing worker infra |
| Bulk jobs queue | spec §2.5 `database` | **Redis** — `UsersBulkWorkerJob` on default redis queue |

## broadcast/bulk — تصمیم نهایی v18

Spec §2.5 پیشنهاد `database` driver برای broadcast/bulk را برای سادگی ops داده بود. با scale فعلی:

- **Redis نگه‌داری می‌شود** — `svp_broadcast_queue` table برای persistence/targets؛ dispatch روی Redis queue
- Bulk: `svp_users_bulk_jobs` + Redis worker (`UsersBulkWorkerJob`)
- CI/PHPUnit: `sync` یا `database` queue — tests independent of Redis

دلیل: worker profile از قبل Redis دارد؛ جدا کردن broadcast به database driver پیچیدگی docker بدون سود فوری.

## عملیاتی

```bash
docker compose --profile workers up -d queue-worker scheduler
php artisan schedule:list   # 14 svp:* jobs
```

ثبت در [`SPEC-DEVIATIONS-FA.md`](SPEC-DEVIATIONS-FA.md) v24. **تصمیم نهایی v24:** Redis `queue-worker` — Horizon نصب نمی‌شود.
