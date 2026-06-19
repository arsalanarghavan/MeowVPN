# Observability Checklist v13 (§189–191)

| Item | Automated | Staging |
|------|-----------|---------|
| `/metrics` scrape 48h | `MetricsIncrementTest` | Prometheus UP |
| Grafana `svp.json` | compose profile | import dashboard |
| `mutate_op_total` | metrics test | — |
| BackupFailed alert | `AdminAlertsExtendedTest` | — |
| RelayUnreachable alert | `AdminAlertsExtendedTest` | — |
| Log channels `svp-*` | `LoggingChannelsTest` | file paths |
