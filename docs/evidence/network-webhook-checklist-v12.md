# Network & Webhook Checklist v12 (§17 items 13–25)

| # | Step | Command / check | Evidence |
|---|------|-----------------|----------|
| 13 | DNS cutover | A/AAAA → Laravel nginx | DNS ticket |
| 14 | TLS prod | `backend/docker/nginx/ssl.example.conf` + certbot | HTTPS curl |
| 15 | TG webhook direct | `bot_set_webhook` or relay | `getWebhookInfo` |
| 16 | Bale webhook | `bot_set_webhook` platform=bale | Bale API |
| 17 | Reseller webhooks | per-reseller `reseller_bot_webhook_set` | decrypt token log |
| 18 | Webhook secret decrypt | `PanelSecretCipher` / settings | ops note |
| 19 | Relay forward URL | `telegram_relay_sync` | relay tenant id |
| 20 | Relay tenant sync | `pushConfigToRelay` steps | mutate log |
| 21 | Per-reseller relay domain | `telegram_relay_set_webhook_reseller` | relay admin UI |
| 22 | Crypto IPN live | NOWPayments test payment | tx row + fulfill job |
| 23 | Portal sign-off | `/info?svp_p=1` signed link | HTML + plain |
| 24 | Portal avatar | `/portal/tg-avatar/{id}` | 200/404 |
| 25 | Relay health | `telegram_relay_test` | CI `RelayMutateTest` |

Automated: `RelayMutateTest`, `cutover-preflight.sh`, `PortalSubscriptionAcceptanceTest`.
