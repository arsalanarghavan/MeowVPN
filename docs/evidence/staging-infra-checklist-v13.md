# Staging Infra Checklist v13 (§17 #26–37)

| # | Step | Verify | Evidence |
|---|------|--------|----------|
| 26 | `docker compose --profile workers up -d` | `docker compose ps` | screenshot |
| 27 | 14 cron jobs | `php artisan schedule:list \| grep svp:` | 14 lines |
| 28 | Redis AOF/RDB | `redis-cli CONFIG GET appendonly` | config dump |
| 29 | MySQL off-host backup | cron mysqldump | ticket |
| 30 | `SVP_MODULE_*` | diff vs prod `.env` | redacted snippet |
| 31 | `SANCTUM_STATEFUL_DOMAINS` | dashboard host listed | env |
| 32 | `APP_KEY` stable | no rotate post-import | deploy log |
| 33 | Prometheus 48h UP | target health | Grafana |
| 34 | Alert fire test | panel down smoke | `admin-alerts-fire-smoke.sh` log |
| 35 | Soak 86400 | `SVP_SOAK_DURATION_SEC=86400 soak-24h.sh` | `soak-24h-YYYY-MM-DD.log` |
| 36 | Cutover checklist | `staging-cutover-checklist.sh` | exit 0 |
| 37 | 6 manual signoffs | [`CUTOVER-SIGNOFF-FA.md`](CUTOVER-SIGNOFF-FA.md) | dated |
