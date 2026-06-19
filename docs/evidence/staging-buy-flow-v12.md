# Staging Buy Flow E2E v12

Script: `backend/scripts/e2e/e2e-staging-buy-flow.sh`

| Step | Action |
|------|--------|
| 1 | Login dashboard (`e2e-dashboard-api.sh`) |
| 2 | `GET /api/v1/admin/state?tab=receipts` pending filter |
| 3 | `receipt_action` approve (mutate) |
| 4 | Bot buy flow | manual Telegram on staging |

Requires `SVP_BASE_URL` pointing at staging with workers running.
