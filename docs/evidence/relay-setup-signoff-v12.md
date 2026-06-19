# Relay Setup Sign-off v12

Reference: `relay-server/SETUP-GUIDE.md`

| Check | Automated test |
|-------|----------------|
| Health | `RelayMutateTest::test_telegram_relay_test` |
| Config sync | `telegram_relay_sync` |
| Main webhook | `telegram_relay_set_webhook` |
| Reseller webhook | `MutateResellerBotDepthTest::test_telegram_relay_set_webhook_reseller_happy_path` |
| Proxy egress | `TelegramProxyEgressTest` |

Operator: confirm relay node TLS + domain in production.
