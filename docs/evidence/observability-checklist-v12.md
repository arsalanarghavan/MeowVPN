# Observability Checklist v12 (§189–191)

| Item | Staging / prod | Automated |
|------|----------------|-----------|
| Prometheus scrape 48h | `/metrics` UP | `MetricsControllerTest` |
| Grafana panels | dashboard import JSON | manual |
| `cron_job_duration` histogram | custom metric if enabled | `MetricsController` |
| Alert routing external | PagerDuty / Telegram ops | `alert-smoke.sh` CI |
| Log channels `svp-*` | daily files under `storage/logs/` | `LoggingChannelsTest` |

Evidence: `soak-nightly-*.log`, `cutover-preflight-*.log` artifacts from GitHub Actions.
