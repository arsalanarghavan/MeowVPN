# Network & Webhook Checklist v11 (§13 items 13–25)

| # | Check | Status |
|---|-------|--------|
| 13 | DNS API + dashboard → Laravel | OPEN — operator |
| 14 | nginx `/dashboard/`, `/api/v1/*`, `/info`, `/health/*`, `/metrics` | PARTIAL — `backend/docker/nginx/default.conf` |
| 15 | Telegram webhook live | OPEN |
| 16 | Bale webhook live | OPEN |
| 17 | Rate limit 120/min | DONE |
| 18 | Reseller webhook + decrypt | PARTIAL |
| 19 | Relay VPS `laravel_forward_url` | OPEN |
| 20 | Relay sync tenant live | PARTIAL |
| 21 | Relay set webhook per-reseller domain | OPEN |
| 22 | Crypto IPN NOWPayments live | PARTIAL |
| 23 | Portal `?svp_adm=1` + `?svp_p=1` | PARTIAL |
| 24 | Portal avatar live | PARTIAL |
| 25 | `portal.js` sync | DONE |

Smoke: `SVP_BASE_URL=... backend/scripts/ops/staging-cutover-checklist.sh`
