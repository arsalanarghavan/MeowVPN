# Relay Setup Sign-off v17 (§14 B.4.4)

CI: `relay-server/npm test` (workflow `relay` job).

## Checklist

| # | Step | Evidence |
|---|------|----------|
| 1 | Laravel webhook ingress reachable | `network-webhook-checklist-v17.md` |
| 2 | Deploy relay-server with `RELAY_UPSTREAM_URL` | staging compose profile `relay` |
| 3 | Dashboard relay sync / nginx/ssl mutates | `MutateRelayAdminNginxSslTest` |
| 4 | Per-reseller domain DNS + `reseller_bot_webhook_set` | operator ticket |
| 5 | Forward smoke | [`relay-forward-2026-06-12-v17.log`](relay-forward-2026-06-12-v17.log) |

## Staging sign-off

- Forward curl: HTTP 200 `{"ok":true}`
- Relay health: upstream reachable
- Operator: **signed** 2026-06-12
