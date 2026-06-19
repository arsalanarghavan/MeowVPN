# Network & Webhook Checklist v13 (§17 #13–25)

| # | Step | Evidence |
|---|------|----------|
| 13 | DNS → Laravel | ticket |
| 14 | TLS HTTPS curl | certbot + `ssl.example.conf` |
| 15–16 | TG/Bale webhook live | `getWebhookInfo` / Bale API |
| 17 | Rate 120/min | `WebhookRateLimitTest` CI |
| 18 | Reseller webhooks + decrypt | ops log |
| 19–21 | Relay forward/sync/reseller domain | relay admin UI |
| 22 | Crypto IPN live | tx + fulfill job |
| 23–24 | Portal plain/HTML + avatar | manual signoff |
| 25 | portal.js sync | CI diff |
