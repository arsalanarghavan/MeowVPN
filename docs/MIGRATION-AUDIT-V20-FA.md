# Migration audit v20 — §17.2 steps 4–5

| Spec step | Spec target | v20 runtime |
|-----------|-------------|-------------|
| 4 | `wp_usermeta.svp_dashboard_accent` → `users.meta` | `dashboard_users.ui_accent` |
| 5 | `wp_users` admins → `users` | `dashboard_users` (Sanctum auth) |

Orphan Laravel `users` migration retained for framework compatibility; not used for dashboard login.

Import command: `php artisan wp:import` — maps accent to `dashboard_users` per [`WpImportCommand.php`](../backend/app/Console/Commands/WpImportCommand.php).

Evidence: [`import-verify-2026-06-13-prod-v20.log`](evidence/import-verify-2026-06-13-prod-v20.log)

Operator / date: 2026-06-13
