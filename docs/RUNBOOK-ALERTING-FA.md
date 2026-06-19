# قوانین هشدار — spec §18.6

| Alert | Condition | Action |
|-------|-----------|--------|
| PanelDown | `AdminAlertsJob` detects unreachable panel > 5min | Telegram admin notify |
| WebhookQueueBacklog | `svp_inbound_queue` pending > 1000 | Scale workers / drain queue |
| BackupFailed | last backup > 2× `backup_interval_minutes` | Check scheduler + disk |
| RelayUnreachable | relay health fails 3× consecutive | Fix VPS / shared secret |

Prometheus scrape: `GET /metrics` on Laravel app.

Grafana template: [`docker/grafana/dashboards/svp.json`](../docker/grafana/dashboards/svp.json).

Horizon (optional): install `laravel/horizon` when queue depth is consistently high.
