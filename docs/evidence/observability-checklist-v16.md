# Observability v16 (§18)

- [ ] Prometheus scrapes `/metrics` for 48h staging
- [ ] Grafana dashboard `docker/grafana/dashboards/svp.json` panels match counters
- [ ] Alert smoke: `admin-alerts-fire-smoke.sh` exit 0
- [ ] `webhook_received_total` increments on webhook POST (CI `MetricsWebhookTest`)
- [ ] `cron_job_duration_seconds` labels for 14 jobs (CI `CronJobMetricsTest` extended)
- [ ] Log channels `svp`, `svp-webhook`, `svp-panel`, `svp-relay` — no secrets in sample lines

Evidence log: `docs/evidence/observability-48h-YYYY-MM-DD.log`

Operator / date: _______________
