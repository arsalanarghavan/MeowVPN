# Network & Webhook v15 (§17 #13–25)

| # | Step | Evidence |
|---|------|----------|
| 13 | DNS A/AAAA API + dashboard → Laravel | ticket + `dig` output |
| 14 | nginx TLS (certbot) | `curl -I https://...` |
| 15–16 | TG/Bale webhook set + `getWebhookInfo` | API response log |
| 17 | Rate 120/min | CI `WebhookRateLimitTest` |
| 18 | Reseller webhook live + decrypt | `ResellerWebhookTest` + operator log |
| 19–21 | Relay forward/sync/per-reseller | `relay-setup-signoff-v15.md` |
| 22 | Crypto IPN live | NOWPayments test tx |
| 23–24 | Portal HTML/plain/avatar | `portal-parity-v15.md` |
| 25 | `portal.js` sync | CI diff |

Operator / date: _______________
