# تقویم چرخش Secret — Production

مرجع: spec §9، [`RUNBOOK-PRODUCTION-FA.md`](RUNBOOK-PRODUCTION-FA.md)

| Secret | Storage | چرخش پیشنهادی | Mutate / اقدام |
|--------|---------|---------------|----------------|
| `SVP_RELAY_SHARED_SECRET` | `.env` + relay VPS | هر ۹۰ روز | `telegram_relay_rotate_secret` |
| `SVP_PORTAL_LINK_SECRET` | `svp_settings` encrypted | هر ۱۸۰ روز | settings_tab whitelabel/portal |
| `SVP_QUEUE_DRAIN_KEY` | `.env` | هر deploy major | deploy-time rotate |
| `SVP_HEALTH_DEEP_TOKEN` | `.env` | هر ۹۰ روز | ops rotate + update LB probe |
| Reseller `webhook_secret` | `svp_reseller_bot_profiles` | per reseller policy | `bot_reseller_secret_rotate` |
| Bot tokens | `svp_settings` encrypted | on compromise | bots tab / env hydrate |
| NOWPayments IPN | `crypto_nowpayments_ipn_secret` | NOWPayments dashboard | `crypto_settings` |
| TLS certificates | nginx / certbot | auto-renew 30d before expiry | `tls-curl-*.log` |

## چک‌لیست چرخش

```bash
# 1. Relay
# dashboard → site_settings → relay → rotate secret
# sync relay VPS RELAY_MASTER_SECRET

# 2. Portal
# regenerate portal_link_secret in settings

# 3. Queue drain
# update SVP_QUEUE_DRAIN_KEY in .env + relay shutdown hook

# 4. Deep health
# update SVP_HEALTH_DEEP_TOKEN + load balancer config
```

## Evidence

لاگ هر چرخش: `docs/evidence/secret-rotation-YYYY-MM-DD.log`

Operator / date: 2026-06-13
