# Errors & Rate Limits

## Error envelope

```json
{
  "ok": false,
  "code": "invalid_credentials",
  "message": "…"
}
```

Helper: `svp_err('code')` / `svp_ok([...])`.

## Common codes

| Code | HTTP | Meaning |
|------|------|---------|
| `invalid_credentials` | 401 | Bad username/password |
| `rate_limited` | 429 | Too many attempts |
| `forbidden` | 403 | Role/module gate |
| `not_found` | 404 | Missing resource |
| `validation_failed` | 422 | Invalid validation |

## Rate limits

- Login / token: per-IP (`SVP_LOGIN_RATE_LIMIT_PER_MIN`)
- Admin mutate: `AdminDashboardRateLimit` middleware
- Webhooks: platform-specific secrets

## Health

| Path | Purpose |
|------|---------|
| `/health` | Liveness |
| `/health/ready` | Readiness (DB/redis) |
| `/health/deep` | Deep checks (auth header) |
| `/metrics` | Prometheus metrics |
