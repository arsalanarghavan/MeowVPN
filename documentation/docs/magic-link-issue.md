# Dashboard magic link — issue & consume

Passwordless dashboard login for Telegram/Bale users already linked to a `DashboardUser` row.

## Issue (bot / automation)

### POST /api/v1/dashboard/login/magic/issue

Creates a short-lived signed URL the bot sends to the user (e.g. in `/start` or account menu).

**Body**

```json
{
  "platform": "telegram",
  "platform_user_id": 123456789,
  "locale": "fa"
}
```

| Field | Description |
|-------|-------------|
| `platform` | `telegram` or `bale` |
| `platform_user_id` | Platform user id (required, &gt; 0) |
| `locale` | Optional; defaults to app locale |

**Response (200)**

```json
{
  "ok": true,
  "url": "https://example.com/fa/dashboard/auth/magic?svp_dl=1&...",
  "ttl": 300
}
```

**Errors:** `400 invalid`, `429 rate_limited`.

Bootstrap payload from `GET /api/v1/dashboard/login` includes `magic_issue_url` for widget wiring.

Rate limit: `SVP_LOGIN_RATE_LIMIT_PER_MIN` (default 10/min per IP).

## Consume (browser)

### GET or POST /api/v1/dashboard/login/magic

Query params (also accepted in JSON body):

| Param | Description |
|-------|-------------|
| `svp_dl` | Must be `1` |
| `svp_p` | `telegram` or `bale` |
| `svp_uid` | Platform user id |
| `svp_e` | Unix expiry (TTL 300s) |
| `svp_n` | Random nonce (single-use) |
| `svp_s` | Hex HMAC signature |

HMAC payload: `dash_login|{platform}|{uid}|{exp}|{nonce}` using the dashboard magic key (derived from portal signing key).

**SPA route:** `/{locale}/dashboard/auth/magic?...` forwards to the API consume endpoint.

See [Authentication](./auth.md) for response codes (`invalid_link`, `used_link`, `not_linked`).
