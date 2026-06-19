# Staging Infra Checklist v12 (§17 items 26–37)

| # | Step | Verify | Evidence |
|---|------|--------|----------|
| 26 | Docker workers profile | `docker compose --profile workers up -d` | `docker compose ps` |
| 27 | 14 cron jobs | `php artisan schedule:list` | grep `svp:` ×14 |
| 28 | Redis persistence | `redis-cli CONFIG GET appendonly` | AOF or RDB on |
| 29 | MySQL external backup | cron mysqldump off-host | backup ticket |
| 30 | `SVP_MODULE_*` | `.env` matches prod intent | env diff log |
| 31 | `SANCTUM_STATEFUL_DOMAINS` | dashboard host listed | `.env` snippet (redacted) |
| 32 | `APP_KEY` stable | no rotate after import | deploy log |
| 33 | Prometheus scrape | `/metrics` 200 from Prometheus | target UP 48h |
| 34 | Grafana + alert fire | test alert routes | screenshot / ticket |
| 35 | Soak 24h | `SVP_SOAK_DURATION_SEC=86400 SVP_BASE_URL=... soak-24h.sh` | `soak-24h-YYYY-MM-DD.log` |
| 36 | Cutover checklist | `staging-cutover-checklist.sh` | exit 0 |
| 37 | Staging E2E | `staging-e2e.sh` + 6 manual signoffs | `CUTOVER-SIGNOFF-FA.md` |

CI smoke (automated): workers in `docker-smoke` job; soak 30s + load 100 in `ci.yml`; nightly soak 300s.
