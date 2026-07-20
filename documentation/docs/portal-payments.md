# Portal & Payments

## Subscription portal

Signed HMAC links verify customer access without dashboard login. Parameter details (`uid` / `exp` / `sig`, admin `svp_adm`): [Signed portal links](./portal-signed-links.md).

Themes (Next.js):

- `modern`
- `pasarguard_builtin`
- `pasarguard_v1`
- `pasarguard_v2`
- `xui`

Route: `/{locale}/portal`

Token endpoints (no dashboard session): [Subscription Endpoints](./subscription-endpoints.md) — `GET /sub/{token}`, `GET /info`.

## Payments hub

Admin tab `payments` aggregates:

1. **Receipts** — card-to-card review
2. **Payments** — gateway/crypto transactions
3. **Orders** — commerce orders

State loaded via `GET /admin/state?tab=payments`.

Cards / gateway credentials: admin tab `cards` (mutate `rial_settings`, `crypto_settings`).

## Gateway callbacks

Rial (ZarinPal / Zibal / AqayePardakht) and crypto (NOWPayments IPN, TetraPay): [Payment Callbacks](./payment-callbacks.md).

## Panel financial reports

Tab `panel_financial_reports` with Jalali/Gregorian calendar ranges.
