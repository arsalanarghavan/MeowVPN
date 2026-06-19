# Relay VPS Sign-off v13 (§17 #19–21)

| Step | Verify | Evidence |
|------|--------|----------|
| Forward webhook | relay admin UI | screenshot |
| Sync tenant | `telegram_relay_sync` mutate | ops log |
| Per-reseller domain | `telegram_relay_set_webhook_reseller` | getWebhookInfo |

Automated mutate depth: `MutateRelayAdminDepthTest`, `MutateRelayLogsDepthTest` (CI mocked).
