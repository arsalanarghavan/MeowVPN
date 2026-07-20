# Health & Observability

| Path | Auth | Purpose |
|------|------|---------|
| `GET /health` | none | Liveness |
| `GET /health/ready` | none | DB/Redis ready |
| `GET /health/deep` | `health.metrics.auth` | Deep checks |
| `GET /metrics` | `health.metrics.auth` | Prometheus |

## Crypto callbacks (module `crypto`)

| Path | Auth | Purpose |
|------|------|---------|
| `POST /api/v1/crypto-ipn/{secret}` | path secret | NOWPayments IPN webhook |
| `POST /api/v1/tetra-callback/{secret}` | path secret | TetraPay callback |

The `{secret}` must match the configured path secret. Wrong secrets return `403`; module off returns `503`.

Full payment callback catalog (including rial gateways): [Payment Callbacks](./payment-callbacks.md).

See also OpenAPI at `/docs/api` (Scramble).
