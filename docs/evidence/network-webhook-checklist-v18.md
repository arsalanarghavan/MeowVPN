# Network + Webhook Checklist v18 (§17 #13–25) — Production

| # | Step | Evidence |
|---|------|----------|
| 13 | DNS → Laravel | `dig +short api.example.com` → prod LB — [`cutover-preflight-2026-06-12-prod.log`](cutover-preflight-2026-06-12-prod.log) |
| 14 | nginx TLS (certbot) | [`tls-curl-2026-06-12-prod.log`](tls-curl-2026-06-12-prod.log) |
| 15 | TG `setWebhook` + `getWebhookInfo` | [`webhook-getWebhookInfo-2026-06-12-prod.log`](webhook-getWebhookInfo-2026-06-12-prod.log) |
| 16 | Bale webhook | same log |
| 18 | reseller webhook decrypt | [`reseller-webhook-decrypt-2026-06-12-prod.log`](reseller-webhook-decrypt-2026-06-12-prod.log) |
| 19–21 | relay forward/sync/domain | [`relay-forward-2026-06-12-prod.log`](relay-forward-2026-06-12-prod.log) |
| 22–24 | crypto IPN + portal HTML/plain/avatar | [`portal-parity-v18.md`](portal-parity-v18.md) |
| 25 | portal.js CI diff | workflow `portal-parity` green |

Operator / date: 2026-06-12 (production v18)
