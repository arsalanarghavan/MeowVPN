# Staging Buy Flow v13 (§17 #55)

| Step | Tool | Evidence |
|------|------|----------|
| API chain | `BuyFlowApproveDeliverTest` (CI) | PHPUnit green |
| Staging script | `backend/scripts/e2e/e2e-staging-buy-flow.sh` | operator log |
| Telegram manual | buy → receipt → approve in bot | ticket |

Automated: `backend/tests/Feature/Commerce/BuyFlowApproveDeliverTest.php`
