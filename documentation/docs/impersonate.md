# Admin impersonation

Admins can open the dashboard **as a reseller** to verify scope, tabs, and mutate policy without sharing passwords.

## Endpoints

| Method | Path | Alias |
|--------|------|-------|
| `POST` | `/api/v1/dashboard/impersonate/start` | `/api/v1/admin/impersonate/start` |
| `POST` | `/api/v1/dashboard/impersonate/stop` | `/api/v1/admin/impersonate/stop` |

Requires Sanctum session + admin role (`EnsureAdmin`).

## Start

**Body**

```json
{
  "targetSvpUserId": 101
}
```

Legacy alias: `target_svp_user_id`.

**Responses**

| Status | Meaning |
|--------|---------|
| 200 | `{ "ok": true, ... }` — session now scoped as the target reseller |
| 400 | Invalid target (not a reseller, missing user, etc.) |
| 403 | Non-admin, or `https_required` outside `local` / `testing` |
| 429 | Rate limited (login bucket) |

## Stop

No body required. Restores the original admin session.

## Mutate policy while impersonating

`MutatePolicyService` restricts writes to reseller-allowed operations and permissions. Admin-only ops (e.g. global site settings, other resellers) are blocked until impersonation stops.

## Audit

Successful start/stop events are recorded via `ImpersonationService::recordAudit()` (`impersonation.start` / `impersonation.stop`).

## UI (Next dashboard)

- **Start:** Resellers list and Reseller reports show “View as reseller” when the actor is a site admin and not already impersonating (`onImpersonateReseller` → `POST …/impersonate/start`).
- **Banner:** `ImpersonationBanner` (`data-testid="impersonation-banner"`) mounts in the dashboard shell while `me/state` reports `impersonating`.
- **Stop:** Banner button → `POST …/impersonate/stop` then reload `/dashboard`.
- Nav is filtered as the target reseller (`resellerAllowedTabs`).
