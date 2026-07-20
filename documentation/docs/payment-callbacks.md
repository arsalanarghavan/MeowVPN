# Payment Callbacks (Rial & Crypto)

Gateway return URLs and IPN endpoints use a **path secret** stored in settings. Wrong secrets return `403`. Module-disabled returns `503`.

## Rial gateways (module `rial`)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/zarinpal-callback/{secret}` | Query: `Status`, `Authority`, `svp_tx` |
| GET | `/api/v1/zibal-callback/{secret}` | Query: `success`, `trackId`, `svp_tx` |
| GET | `/api/v1/aqayepardakht-callback/{secret}` | Query: `transid`, `svp_tx` |

Flow:

1. Checkout creates a pending transaction and redirects the user to the gateway.
2. Gateway redirects (GET) to the callback URL with the path secret.
3. Laravel verifies the payment with the gateway API, then dispatches `RialFulfillJob`.

Admin mutate `rial_settings` stores merchant credentials, sandbox flags, and regenerates callback path secrets when needed. Full callback URLs appear in admin state / cards settings.

Sandbox flags: `zarinpal_sandbox`, `zibal_sandbox`, `aqayepardakht_sandbox`.

## Crypto (module `crypto`)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/v1/crypto-ipn/{secret}` | NOWPayments IPN |
| POST | `/api/v1/tetra-callback/{secret}` | TetraPay payment callback |

Configure via mutate `crypto_settings` (`crypto_nowpayments_*`, `crypto_tetra_api_key`, IPN/callback path secrets). Derived URLs are exposed on site settings / cards admin state.

See also [Health & Crypto](./health-crypto.md).
