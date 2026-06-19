# جدول Laravel `users` — unused (v24)

Migration پیش‌فرض Laravel (`0001_01_01_000000_create_users_table.php`) جدول `users` را می‌سازد.

**انحراف آگاهانه:** احراز هویت داشبورد از `dashboard_users` استفاده می‌کند؛ جدول `users` در runtime استفاده نمی‌شود.

**Bearer token (v19+ DONE):** `POST /api/v1/auth/token` + [`BEARER-TOKEN-FA.md`](BEARER-TOKEN-FA.md). Sanctum `personal_access_tokens` فعال است.

**v24 decision:** migration `users` **نگه‌داری می‌شود** (Laravel default؛ حذف breaking برای fresh install). Runtime SSOT = `dashboard_users` only.

Operator / date: 2026-06-13
