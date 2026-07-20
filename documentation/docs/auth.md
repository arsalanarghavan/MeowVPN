# Authentication API

## POST /api/v1/auth/login

Session login for the dashboard.

**Body**

```json
{
  "log": "admin",
  "pwd": "secret",
  "remember": true,
  "redirect_to": "/fa/dashboard"
}
```

**Responses**

| Status | Meaning |
|--------|---------|
| 200 | `{ "ok": true, "redirect": "..." }` |
| 401 | `invalid_credentials` |
| 429 | `rate_limited` |

Rate limit: configurable via `SVP_LOGIN_RATE_LIMIT_PER_MIN` (default 10/min per IP).

## POST /api/v1/auth/token

Issues a Sanctum personal access token for API clients.

## POST /api/v1/auth/logout

Invalidates the web session (requires auth).

## GET /api/v1/bootstrap

Public/auth bootstrap payload for the SPA (locale hints, feature flags).

## GET /api/v1/me/state

Authenticated session state (persona, features, impersonation flags, user summary, `resellerAllowedTabs` when reseller).

## POST /api/v1/dashboard/persona

Switch active persona when the user has multiple (`admin` / `reseller` / `user`).

**Body**

```json
{ "persona": "admin" }
```

Stored in session as `svp_active_persona`. While **impersonating**, prefer stopping impersonation instead of switching persona.

## POST /api/v1/dashboard/ui-preferences

Persist toolbar prefs on the dashboard user row.

**Body** (any subset)

```json
{
  "ui_accent": "default",
  "ui_theme": "system",
  "ui_sidebar": "expanded",
  "ui_lang": "fa"
}
```

Next dashboard toolbar calls this when accent/theme/locale change (also keeps `localStorage` for accent).

Aliases: `/api/v1/admin/ui-preferences` when registered under the admin prefix.

## Dashboard magic link (Telegram / Bale)

One-time signed links let a linked bot user open the dashboard without typing a password. The bot (or issue API) builds the link; the SPA consumes it on:

`/{locale}/dashboard/auth/magic?svp_dl=1&svp_p=…&svp_uid=…&svp_e=…&svp_n=…&svp_s=…`

### POST /api/v1/dashboard/login/magic/issue

Builds a signed magic URL for a linked platform user.

**Body**

```json
{
  "platform": "telegram",
  "platform_user_id": 123456789,
  "locale": "fa"
}
```

**Responses**

| Status | Meaning |
|--------|---------|
| 200 | `{ "ok": true, "url": "https://…", "ttl": 300 }` |
| 400 | `invalid` (missing `platform_user_id`) |
| 429 | `rate_limited` |

Rate limit key is per-IP (`SVP_LOGIN_RATE_LIMIT_PER_MIN`).

### POST /api/v1/dashboard/login/magic

Also accepts **GET**. Establishes a web session when query/body params verify.

**Auth:** HMAC over `dash_login|{platform}|{uid}|{exp}|{nonce}` using the dashboard magic key (derived from portal signing key).

| Param | Description |
|-------|-------------|
| `svp_dl` | Must be present (`1`) |
| `svp_p` | `telegram` or `bale` |
| `svp_uid` | Platform user id |
| `svp_e` | Unix expiry (TTL 300s) |
| `svp_n` | Random nonce (single-use via cache) |
| `svp_s` | Hex HMAC signature |

**Body (optional):** `{ "remember": true }`

**Responses**

| Status | `code` | Meaning |
|--------|--------|---------|
| 200 | — | `{ "ok": true, "redirect": "/dashboard/" }` — session cookie set |
| 401 | `invalid_link` | Bad signature, expiry, or params |
| 401 | `used_link` | Nonce already consumed |
| 401 | `not_linked` | No dashboard user linked to platform id |
| 429 | `rate_limited` | Per-IP limit (`SVP_LOGIN_RATE_LIMIT_PER_MIN`) |

**Frontend:** `GET /api/v1/dashboard/login` bootstrap includes `magic_consume_url` and `magic_issue_url` for widget wiring.

See [Magic link issue](./magic-link-issue.md) for `POST /api/v1/dashboard/login/magic/issue`.

**Security:** Links are short-lived, nonce is burned on success, and the platform user must already be linked to a `DashboardUser` row.

## Impersonation

Admins can open the dashboard as another SVP user (HTTPS required outside `local` / `testing`).

| Method | Path | Alias |
|--------|------|-------|
| POST | `/api/v1/dashboard/impersonate/start` | `/api/v1/admin/impersonate/start` |
| POST | `/api/v1/dashboard/impersonate/stop` | `/api/v1/admin/impersonate/stop` |

**Start body:** `{ "target_svp_user_id": 42 }` (also accepts `targetSvpUserId`).

**Auth:** Sanctum session + `EnsureAdmin`.

**Responses:** `200` with `{ "ok": true, … }` on success; `400` on policy failure; `403` if not admin / HTTPS required.
