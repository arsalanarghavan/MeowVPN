# ستون‌های Legacy وردپرس (WP → Laravel)

> **v29:** ستون‌های زیر از schema وردپرس حفظ شده‌اند؛ runtime لاراول از آن‌ها استفاده نمی‌کند مگر import.

## `svp_users.wp_user_id`

| موضوع | جزئیات |
|-------|--------|
| وضعیت v29+ | **حذف شده** — migration [`2026_06_14_000002_drop_wp_legacy_columns.php`](../backend/database/migrations/2026_06_14_000002_drop_wp_legacy_columns.php) |
| جایگزین | `dashboard_users` + `has_dashboard_login` در admin state |
| Import | `wp:import` دیگر این ستون را نمی‌نویسد |

## `svp_audit_log.actor_wp_user_id`

| موضوع | جزئیات |
|-------|--------|
| وضعیت v29+ | **حذف شده** — همان migration |
| Laravel | [`AuditLogService`](../backend/app/Services/AuditLogService.php) از `actor_svp_user_id` / `dashboard_users.id` استفاده می‌کند |

## `portal_page_id` / `portal_pages`

| موضوع | جزئیات |
|-------|--------|
| منبع WP | ID صفحه وردپرس برای shortcode portal |
| Laravel | [`PortalPagesBuilder`](../backend/app/Services/PortalPagesBuilder.php) — صفحات native: `/info`, `/info?svp_p=1` |
| API | `admin/state` → `portalPages` + alias `wpPages` |
| مهاجرت | تنظیم `portal_pages` JSON در `svp_settings` |

## mutate ops حذف‌شده (v29)

- `link_wp_user` → `user_merge`
- `reseller_bot_secret_rotate` → `bot_reseller_secret_rotate`

Operator / date: 2026-06-14
