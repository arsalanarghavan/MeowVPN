# Relay Setup Sign-off v16 (§14 B.4.4)

CI: `relay-server/npm test` (workflow `relay` job).

Operator order:
1. Laravel webhook ingress reachable
2. Deploy relay-server with `RELAY_UPSTREAM_URL`
3. Dashboard relay sync / nginx/ssl mutates
4. Per-reseller domain DNS + `reseller_bot_webhook_set`
5. Forward smoke curl log → `docs/evidence/relay-forward-YYYY-MM-DD.log`

Operator / date: _______________
