# Portal parity v20 — production sign-off — 2026-06-13

| Check | URL | Result |
|-------|-----|--------|
| Portal HTML subscription | `/portal/sub/{token}` | 200 HTML |
| Portal plain config | `/portal/config/{token}` | 200 text/plain |
| Portal avatar | `/portal/tg-avatar/{id}` | 200 image/png |
| Portal admin JSON | `/portal/admin/*` | `{success,data}` shape |
| Crypto IPN | `POST /api/v1/crypto/ipn` | 200 acknowledged |

Operator: prod OK 2026-06-13
