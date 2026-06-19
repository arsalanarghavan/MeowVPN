# Observability Checklist v11 (§18)

| Item | Command / target | Evidence |
|------|------------------|----------|
| Prometheus scrape 48h staging | scrape `/metrics` | Grafana dashboard |
| Grafana panels vs `SvpMetrics` | compare counters | screenshot / export |
| `cron_job_duration` populated | run scheduled jobs | metrics sample |
| PanelDown alert fire | `admin-alerts-fire-smoke.sh` | log |
| Backup alert fire | same script | log |
| Queue backlog alert | same script | log |
| External routing (optional) | PagerDuty / Telegram | operator config |
| Broadcast 1000+ load | `broadcast-load-smoke.sh` / nightly | CI artifact |
| Soak 86400s | `soak-24h.sh` | `soak-24h-YYYY-MM-DD.log` |
| Log channels prod | audit `config/logging.php` usage | operator |
