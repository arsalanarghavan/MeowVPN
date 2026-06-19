# Network & Webhook v14 (§17 #13–25)

| # | Step | Evidence |
|---|------|----------|
| 13–14 | DNS + TLS HTTPS | ticket + curl |
| 15–18 | TG/Bale/reseller webhooks | getWebhookInfo / ops log |
| 19–21 | Relay VPS | relay admin UI |
| 22–24 | Crypto IPN + portal | tx log + manual signoff |

Tests: `WebhookResellerRateLimitTest`, `WebhookBaleIngressTest` (v14).
