# Staging Infra v15 (§17 #26–37)

- [ ] `docker compose --profile workers up` — app, web, scheduler, queue-worker, mysql, redis
- [ ] `php artisan schedule:list` — all 14 `svp:*` when modules on
- [ ] Redis AOF/RDB persistence configured on host
- [ ] MySQL off-host backup cron (mysqldump + retention)
- [ ] `SVP_MODULE_*` flags match production intent
- [ ] `SANCTUM_STATEFUL_DOMAINS` includes dashboard origin
- [ ] `APP_KEY` stable across deploys (document rotation procedure)
- [ ] Prometheus scrape `/metrics` 48h — `docs/evidence/observability-checklist-v15.md`
- [ ] Soak `SVP_SOAK_DURATION_SEC=86400` — `docs/evidence/soak-24h-YYYY-MM-DD.log`

Operator / date: _______________
