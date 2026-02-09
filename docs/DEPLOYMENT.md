# راهنمای دپلوی و تفکیک نود مرکزی

در معماری MeowVPN می‌توانید سرور اصلی، نود مرکزی، نودهای عادی خارج، نودهای تانل خارج و نودهای تانل ایران را جداگانه راه‌اندازی کنید. برای دستور نصب و پیکربندی هر نوع سرور به **[docs/SERVER_INSTALL.md](SERVER_INSTALL.md)** مراجعه کنید. برای نصب تعاملی تانل از **installer/tunnel.sh** استفاده کنید.

## نقش نودها (APP_NODE_ROLE)

| مقدار | توضیح |
|-------|--------|
| `web` | این نود فقط وب/API سرو می‌کند؛ Scheduler jobهای سینک/مانیتورینگ را اجرا **نمی‌کند**. |
| `central` | این نود «نود مرکزی» است؛ فقط Scheduler و Queue برای سینک و مانیتورینگ اجرا می‌شود (همان DB و Redis). |
| `all` | پیش‌فرض؛ همه‌چیز روی یک نود (نصب تکی). |

## سرور اصلی (web)

- در `.env` بک‌اند: `APP_NODE_ROLE=web`
- سرو وب (Nginx + PHP-FPM یا مشابه) و در صورت نیاز worker صف سبک:
  ```bash
  php artisan queue:work --queue=default
  ```
- **نباید** `php artisan schedule:work` اجرا شود (یا اجرا شود؛ در این حالت با نقش `web` هیچ jobی زمان‌بندی نمی‌شود).
- **نباید** صف `sync` روی این سرور پردازش شود تا jobهای سنگین اینجا اجرا نشوند.

## نود مرکزی (central)

- در `.env` بک‌اند: `APP_NODE_ROLE=central`
- همان مقادیر `DB_*` و `REDIS_*` (اتصال به همان دیتابیس و Redis).
- اجرای Scheduler و Queue:
  ```bash
  php artisan schedule:work
  php artisan queue:work --queue=sync,default
  ```
- jobهای سنگین (MonitorServices، SyncMultiServerTraffic، SyncAllServersUserCount، CleanupExpiredServices، GenerateMonthlyInvoices) روی صف `sync` قرار می‌گیرند؛ با `--queue=sync,default` روی نود مرکزی پردازش می‌شوند.

## نصب تکی (all)

- در `.env`: `APP_NODE_ROLE=all` یا بدون تنظیم (پیش‌فرض `all`).
- همان سرور هم وب و هم Scheduler و Queue را اجرا می‌کند (رفتار قبلی).

## دپلوی با Docker

می‌توانید یک سرویس جدا برای نود مرکزی با همان image و env متفاوت تعریف کنید:

```yaml
# مثال در docker-compose
services:
  app:
    image: meowvpn-backend
    environment:
      APP_NODE_ROLE: web
    # ... وب و در صورت نیاز queue:work --queue=default

  central:
    image: meowvpn-backend
    environment:
      APP_NODE_ROLE: central
      # همان DB_* و REDIS_* (از env یا shared config)
    command: >
      sh -c "php artisan schedule:work & php artisan queue:work --queue=sync,default"
    depends_on:
      - postgres
      - redis
```

## خلاصه

- **سرور اصلی**: `APP_NODE_ROLE=web`؛ فقط وب و در صورت نیاز `queue:work --queue=default`.
- **نود مرکزی**: `APP_NODE_ROLE=central`؛ همان DB/Redis؛ `schedule:work` و `queue:work --queue=sync,default`. برای نصب خودکار: `./installer/central-node.sh`.
- **نود عادی خارج**: فقط Marzban (اسکریپت رسمی)؛ در پنل با دسته مستقیم و منطقه خارج اضافه شود.
- **نودهای تانل**: نود تانل خارج (خروج) و نود تانل ایران (ورود) با **installer/tunnel.sh** نصب و پیکربندی می‌شوند؛ جزئیات در [SERVER_INSTALL.md](SERVER_INSTALL.md).
- با این تفکیک، بار سینک و مانیتورینگ روی سرور اصلی نمی‌افتد و کندی یا مشکل ایجاد نمی‌شود.
