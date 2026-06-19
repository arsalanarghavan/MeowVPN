# Observability staging — Prometheus + Grafana

## راه‌اندازی

```bash
cd backend
docker compose --profile observability up -d prometheus grafana
```

| سرویس | URL | نقش |
|--------|-----|-----|
| Prometheus | http://localhost:9090 | scrape `/metrics` از Laravel |
| Grafana | http://localhost:3000 | dashboard `svp.json` |

## پیکربندی

- Prometheus: [`docker/prometheus/prometheus.yml`](../../docker/prometheus/prometheus.yml)
- Grafana provisioning: [`docker/grafana/provisioning/`](../../docker/grafana/provisioning/)
- Dashboard: [`docker/grafana/dashboards/svp.json`](../../docker/grafana/dashboards/svp.json)

## تأیید

```bash
curl -sS http://localhost:8080/metrics | head -20
curl -sS http://localhost:9090/-/healthy
curl -sS http://localhost:3000/api/health
```

## Production

Profile `observability` اختیاری است. Production از همان `/metrics` + external Prometheus/Grafana یا alert-smoke استفاده می‌کند.

Evidence: [`observability-48h-2026-06-16-prod-v23.log`](evidence/observability-48h-2026-06-16-prod-v23.log)

Operator / date: 2026-06-13
