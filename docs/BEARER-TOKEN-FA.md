# Bearer Sanctum token — پیاده‌سازی v19 (DONE)

## Endpoint

`POST /api/v1/auth/token`

```json
{ "username": "admin", "password": "changeme" }
```

Response:

```json
{ "ok": true, "token": "...", "token_type": "Bearer" }
```

## Usage

```http
Authorization: Bearer {token}
GET /api/v1/me/state
```

## Tests

- [`BearerTokenTest.php`](../backend/tests/Feature/Auth/BearerTokenTest.php)

## Notes

- Session SPA remains primary for dashboard
- Rate limit shared with login (`svp.login_rate_limit_per_min`)
- Token abilities: `['*']`

Operator / date: 2026-06-12
