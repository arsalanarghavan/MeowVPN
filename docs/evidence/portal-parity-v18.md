# Portal parity v18 — production sign-off

| Surface | URL pattern | Status | Evidence |
|---------|-------------|--------|----------|
| HTML sub | `/sub/{token}` | 200 signed HTML | curl prod 2026-06-12 |
| Plain sub | `/sub/{token}?format=plain` | 200 text | curl prod 2026-06-12 |
| Avatar | `/api/v1/portal/tg-avatar/{id}` | 200 image/signed | curl prod 2026-06-12 |
| Crypto IPN | `/api/v1/crypto-ipn/{secret}` | HMAC verify OK | `CryptoModuleAcceptanceTest` + prod log |
| Admin portal | `?svp_adm=1` | stats/membership | manual signoff 2026-06-12 |

CI: workflow `portal-parity` — `diff assets/portal.js` green.

Operator / date: 2026-06-12 (production v18)
