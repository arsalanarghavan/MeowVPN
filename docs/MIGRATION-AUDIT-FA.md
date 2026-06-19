# Audit migration — spec §11

جدول‌های `svp_*` در [`backend/database/migrations/2026_06_11_000003_create_svp_wp_parity_schema.php`](../backend/database/migrations/2026_06_11_000003_create_svp_wp_parity_schema.php) از DDL آرشیو WP (`archive/wp-plugin` → `includes/class-activator.php`) mirror شده‌اند. مرجع واحد: [`backend/database/schema/svp_wp_parity.sql`](../backend/database/schema/svp_wp_parity.sql).

## Indexes حیاتی (§11.1)

| Table | Index | Status |
|-------|-------|--------|
| `svp_users` | UNIQUE `tg_user_id`, `bale_user_id`, `wp_user_id` | در parity schema |
| `svp_discount_codes` | UNIQUE `owner_svp_user_id, code` | در parity schema |
| `svp_reseller_bot_profiles` | UNIQUE `reseller_svp_user_id` | در parity schema |
| `svp_transactions` | KEY `billing_reseller_svp_id` | در parity schema |

## Models

۲۷ مدل Eloquent اضافه شده در `backend/app/Models/` — نگاشت کامل spec §11.

## انحراف آگاهانه

۴۳ migration جدا → یک migration parity + `database/schema/svp_wp_parity.sql` مرجع DDL.
