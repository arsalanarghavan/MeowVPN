# راهنمای Cutover و Staging — WP → Laravel

این سند مراحل import داده از وردپرس و cutover به بک‌اند Laravel را در محیط staging/production توضیح می‌دهد.

## ۱. اجرای موازی (Parallel Run)

در staging، وردپرس و Laravel **روی دیتابیس جدا** اجرا می‌شوند:

| محیط | دیتابیس | URL نمونه |
|------|---------|-----------|
| WordPress (فعلی) | MySQL وردپرس | `https://wp.example.com` |
| Laravel (جدید) | `docker compose` MySQL | `http://localhost:8080` |

```bash
cd backend
docker compose up -d mysql redis app web scheduler
docker compose exec app php artisan migrate
```

فرانت React را build کنید و در nginx mount شود (`frontend/dist`).

## ۲. Export از وردپرس

```bash
mysqldump -u root -p wordpress \
  wp_svp_users wp_svp_services wp_svp_panels \
  $(mysql -N -e "SHOW TABLES LIKE 'wp_svp_%'" wordpress) \
  wp_options wp_users wp_usermeta \
  > /tmp/svp-wp-export.sql
```

یا dump کامل:

```bash
mysqldump -u root -p wordpress > /tmp/wordpress-full.sql
```

## ۳. Import به Laravel

```bash
docker compose exec app php artisan wp:import /tmp/svp-wp-export.sql \
  --prefix=wp_ \
  --default-password='رمز-موقت-قوی'

# کپی بکاپ‌های on-site (اختیاری)
docker compose exec app php artisan wp:import /tmp/svp-wp-export.sql \
  --prefix=wp_ \
  --backups-from=/path/to/wp-content/uploads/simplevpbot-backups
```

### فلگ‌ها

| فلگ | کاربرد |
|-----|--------|
| `--dry-run` | فقط parse و گزارش، بدون write |
| `--force` | overwrite ردیف‌های موجود (by id) |
| `--verify-only` | مقایسه تعداد ردیف dump با DB فعلی |
| `--default-password` | رمز bcrypt اپراتورهای import‌شده |

**توجه:** hash پسورد وردپرس (phpass) قابل انتقال نیست. همه اپراتورهای داشبورد با `--default-password` ساخته می‌شوند و باید پس از اولین login رمز را عوض کنند.

## ۴. اعتبارسنجی

```bash
docker compose exec app php artisan wp:import /tmp/svp-wp-export.sql --verify-only
```

خروجی جدول per-table باید همه `match=yes` باشد.

## ۵. Post-Import Checklist

```bash
docker compose exec app php artisan svp:rebuild-reseller-closure
docker compose exec app php artisan svp:register-webhooks --platform=both
docker compose exec app php artisan schedule:list
```

تست دستی:

- login داشبورد با کاربر import‌شده (`wpadmin` / رمز موقت)
- bootstrap و admin/state
- webhook تلگرام/بله (یک پیام تست به ربات)
- خرید تست / approve receipt

## ۶. Cutover (Production)

1. **Snapshot MySQL** وردپرس و Laravel
2. آخرین `mysqldump` و `wp:import` روی Laravel
3. `svp:rebuild-reseller-closure` + `svp:register-webhooks`
4. DNS / nginx: `api` و `dashboard` → Laravel
5. relay: `telegram_relay_laravel_forward_url` (یا `telegram_relay_wp_forward_url`) → base URL Laravel؛ relay forward به `/api/v1/webhook/telegram/*`
6. اجرای `backend/scripts/ops/staging-cutover-checklist.sh` و soak 24h (`backend/scripts/ops/soak-24h.sh`)
7. مانیتور ۲۴–۴۸ ساعت — [`WP-DECOMMISSION-FA.md`](WP-DECOMMISSION-FA.md)

## ۷. Rollback

1. DNS revert به سرور وردپرس
2. relay `laravel_base_url` → URL وردپرس (موقت)
3. restore snapshot MySQL در صورت نیاز

```bash
backend/scripts/ops/rollback-drill.sh
```

Evidence: `docs/evidence/rollback-drill.log`

## ۸. Production cutover

پس از sign-off staging ([`evidence/CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md)):

1. DNS production → Laravel
2. `wp-disable-staging.sh` equivalent on production WP
3. Monitor 48h — [`WP-DECOMMISSION-FA.md`](WP-DECOMMISSION-FA.md)

## ۹. خارج از scope

- import panel SQLite از داخل zip بکاپ
- migrate خودکار hash پسورد WP
- خاموش کردن وردپرس (فاز ۱۲ — Production Hardening)
