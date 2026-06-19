# MIGRATION-AUDIT v19 — §17.2 steps 4–5

## Spec vs implementation

| Step | Spec | Implementation v19 |
|------|------|-------------------|
| 4 | `wp_usermeta.svp_dashboard_accent` → `users.meta` | → `dashboard_users.ui_accent` via [`WpDashboardUserImporter`](../backend/app/Services/Migration/WpDashboardUserImporter.php) |
| 5 | `wp_users` (admins) → `users` | → `dashboard_users` (Sanctum auth model) |

## Orphan `users` table

Laravel default [`users`](../backend/database/migrations/0001_01_01_000000_create_users_table.php) exists but is **unused** at runtime. Auth model: `DashboardUser`.

## Decision v19

- Keep `dashboard_users` (documented deviation)
- Orphan `users` migration retained for Laravel compatibility; no runtime writes
- Import tests: `WpImportDashboardUsersTest`, `WpImportAccentMetaTest`

Operator / date: 2026-06-12
