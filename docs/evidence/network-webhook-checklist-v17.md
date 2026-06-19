# Network + Webhook Checklist v17 (§17 #13–25)

| # | Step | Evidence |
|---|------|----------|
| 13 | DNS → Laravel | `dig +short api.staging.example` → staging LB |
| 14 | nginx TLS (certbot) | `curl -I https://api.staging.example/health` → HTTP/2 200 |
| 15 | TG `setWebhook` + `getWebhookInfo` | url matches Laravel ingress |
| 16 | Bale webhook | same |
| 18 | reseller webhook decrypt | operator log (redacted) |
| 19–21 | relay forward/sync/domain | [`relay-forward-2026-06-12-v17.log`](relay-forward-2026-06-12-v17.log) |
| 22–24 | crypto IPN + portal HTML/plain/avatar | [`portal-parity-v17.md`](portal-parity-v17.md) |
| 25 | portal.js CI diff | workflow `portal-parity` |

Operator / date: 2026-06-12
