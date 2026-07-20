# Internal Bot API

Base: `/api/v1/internal/bot`

Auth: `X-SVP-Bot-Service-Secret`

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/health` | Liveness |
| POST | `/process-update` | Ingest Telegram/Bale update |
| POST | `/user/resolve` | Resolve platform user |
| GET/POST | `/user/{id}/state` | Bot FSM state |
| POST | `/mutate` | Bot-side mutations |
| GET | `/texts` | Localized bot texts |
| GET | `/settings` | Runtime settings |
| GET | `/reseller/{id}/profile` | Reseller bot profile |

Used by `telegram_bot` and `bale_bot` microservices.
