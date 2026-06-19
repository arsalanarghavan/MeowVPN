# Relay Setup Sign-off v14 (§14 B.4.4)

Operator checklist for relay VPS deployment order (SETUP-GUIDE parity).

## Prerequisites

- [ ] Laravel API reachable at `SVP_BASE_URL` (HTTPS)
- [ ] `SVP_MODULE_RELAY=true` on app host
- [ ] Relay VPS: Node relay-server built (`relay-server/npm test` green in CI)

## Startup order

1. [ ] Configure main bot webhook → Laravel `/api/v1/webhook/telegram` (or relay forward URL)
2. [ ] Deploy relay-server with `RELAY_UPSTREAM_URL` pointing to Laravel webhook ingress
3. [ ] Run relay sync: dashboard **Relay** tab → save nginx/ssl settings → `relay_sync` mutate or ops script
4. [ ] Per-reseller domain: set `reseller_relay_domain` in reseller bot profile; register DNS A record
5. [ ] Verify forward: `curl -X POST $RELAY_URL/health` and sample Telegram update forwarded (200)

## Evidence

| Step | Command / action | Log path |
|------|------------------|----------|
| Relay unit tests | `cd relay-server && npm test` | CI `relay` job |
| Forward smoke | ops relay forward script | `docs/evidence/relay-forward-YYYY-MM-DD.log` |
| Per-reseller domain | `getWebhookInfo` after `reseller_bot_webhook_set` | operator ticket |

Operator / date: _______________
