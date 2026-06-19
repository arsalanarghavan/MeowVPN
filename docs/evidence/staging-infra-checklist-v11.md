# Staging Infra Checklist v11 (§5 items 26–37)

| # | Check | Notes |
|---|-------|-------|
| 26 | `docker compose --profile workers` | scheduler + queue-worker |
| 27 | `schedule:list` 14 jobs | CI grep smoke |
| 28 | Redis persistence + memory | operator |
| 29 | MySQL external backup | operator |
| 30 | `SVP_MODULE_*` match prod | operator |
| 31 | `SANCTUM_STATEFUL_DOMAINS` | operator |
| 32 | Stable `APP_KEY` between deploys | operator |
| 33 | Prometheus/Grafana staging | PARTIAL |
| 34 | Alert fire panel/backup/queue | `admin-alerts-fire-smoke.sh` |
| 35 | Soak 86400s | `SVP_SOAK_DURATION_SEC=86400 soak-24h.sh` → `soak-24h-YYYY-MM-DD.log` |
| 36 | `staging-cutover-checklist.sh` + `staging-e2e.sh` | automated HTTP |
| 37 | 6 manual signoffs | `CUTOVER-SIGNOFF-FA.md` |

Observability load: `broadcast-load-smoke.sh` (1000+ in nightly workflow).
