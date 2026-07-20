# Admin API

All admin routes require Sanctum auth + dashboard enabled + reseller scope middleware.

Prefix: `/api/v1/admin` (alias `/api/v1/dashboard/admin`).

## GET /admin/state

Aggregated read model for the active dashboard tab.

**Query**

| Param | Description |
|-------|-------------|
| `tab` / `activeTab` | Active tab key (`dashboard`, `payments`, `panel_financial_reports`, …) |
| `page`, `per_page` | Pagination |
| `panel_financial_date_from` | Financial reports range start |
| `panel_financial_date_to` | Financial reports range end |
| `panel_financial_calendar` | `jalali` \| `gregorian` |

## POST /admin/mutate

Central write RPC. Body: `{ "op": "<operation>", ...payload }`.

Operations are gated by role (admin/reseller) and module flags.

## GET /admin/live-stream

Server-Sent Events stream for live monitoring metrics.

Headers: `Accept: text/event-stream`

## Users

- `GET /admin/user-search?q=`
- `GET /admin/user/{id}`
- Bulk: `GET /admin/users-bulk-jobs`, `GET /admin/users-bulk-job-items`

## Panels & configs (XUI / PasarGuard)

- `GET /admin/panel-inbounds`
- `GET /admin/panel-inbound-clients`
- `GET /admin/configs-snapshot`
- `POST /admin/configs-sync`
- Inbound map, rebuild, 51200 traffic repair

## Backup

- `GET /admin/backups`
- `POST /admin/backup/run`
- `GET /admin/backup/download`
- `POST /admin/backup/restore`

## Orphan clients & live traffic

- `GET|POST /admin/panel/orphan-clients/scan`
- `GET|POST /admin/panel/orphan-clients/delete`
- Mutate: `configs_panel_del_orphans`, `panel_merge_preview`, `panel_merge_execute`
- `POST /admin/configs-live-traffic`
- `GET /admin/cron-status`

See [Panel merge & orphan clients](./panel-operations.md) for payloads and UI flow.

## Impersonation

- `POST /api/v1/dashboard/impersonate/start` (alias `/admin/impersonate/start`) — body `target_svp_user_id`
- `POST /api/v1/dashboard/impersonate/stop` (alias `/admin/impersonate/stop`)

See [Authentication](./auth.md#impersonation).

## Auth helpers

- `POST /api/v1/dashboard/login/telegram` — Telegram Login Widget
- `POST /api/v1/dashboard/login/magic/issue` — build magic link
- `POST|GET /api/v1/dashboard/login/magic` — consume magic link
- `POST /api/v1/internal/session-keeper` — panel session keep-alive (internal secret)

## Other

- `GET /admin/audit`
- `GET /admin/logs`
- `GET /admin/purge-expired`
- `POST /admin/media`
- `GET /admin/broadcast-queue`
- `GET /api/v1/portal/usage`
- Payment callbacks: [Payment Callbacks](./payment-callbacks.md)
- Public webhooks / drain: [Webhooks](./webhooks-portal-relay.md)
