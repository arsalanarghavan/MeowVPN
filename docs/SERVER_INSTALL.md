# راهنمای نصب هر مدل سرور

این مستند دستور نصب و پیکربندی هر نوع سرور در معماری MeowVPN را شرح می‌دهد.

## خلاصه: کدام سرور چه چیزی نصب می‌کند

| مدل سرور | نقش | نصب/پیکربندی |
|----------|-----|----------------|
| **سرور اصلی** | ربات + پنل (وب/API) | MeowVPN با `APP_NODE_ROLE=web` |
| **نود مرکزی** | کران جاب، سینک تعداد کاربر، ترافیک، انقضا و… | همان بک‌اند با `APP_NODE_ROLE=central` و اجرای `schedule:work` + `queue:work --queue=sync,default` |
| **نود عادی خارج** | اتصال مستقیم کاربر | فقط Marzban (اسکریپت رسمی) |
| **نود تانل خارج** | خروج تانل (سرور تانل) | Marzban + سمت سرور تانل (بسته به نوع) |
| **نود تانل ایران** | ورود تانل (کلاینت تانل) | سمت کلاینت تانل (بدون Marzban برای Rathole/Backhaul/GOST/HA Proxy/iptables؛ برای Reverse هر دو سرور Marzban) |

برای نصب تعاملی تانل‌ها از **اینستالر تانل** استفاده کنید:

```bash
chmod +x installer/tunnel.sh
./installer/tunnel.sh
```

---

## سرور اصلی (ربات + پنل)

- **استفاده از نصب‌کننده:** [installer/install.sh](../installer/install.sh) (نصب با Docker مثل حالت استاندارد).
- **پس از نصب** در `.env` بک‌اند مقدار زیر را تنظیم کنید:
  ```env
  APP_NODE_ROLE=web
  ```
- **اجرای وب** و در صورت نیاز صف سبک:
  ```bash
  php artisan queue:work --queue=default
  ```
- برای تفکیک از نود مرکزی و جزئیات بیشتر به [docs/DEPLOYMENT.md](DEPLOYMENT.md) مراجعه کنید.

---

## نود مرکزی

- **نصب:** همان بک‌اند MeowVPN (کپی پروژه یا همان مخزن) روی سرور جدا.
- **تنظیمات:** همان `DB_*` و `REDIS_*` (اتصال به دیتابیس و Redis سرور اصلی یا مشترک).
- در **`.env`**:
  ```env
  APP_NODE_ROLE=central
  ```
- **دستورات اجرا:**
  ```bash
  php artisan schedule:work
  php artisan queue:work --queue=sync,default
  ```
- **اختیاری:** اسکریپت [installer/central-node.sh](../installer/central-node.sh) وابستگی‌ها را چک می‌کند، `.env` نمونه را کپی می‌کند و همین دو دستور را (با systemd یا nohup) راه‌اندازی می‌کند.

---

## نود عادی خارج (اتصال مستقیم)

- **فقط نصب Marzban** با اسکریپت رسمی:
  ```bash
  sudo bash -c "$(curl -sL https://github.com/Gozargah/Marzban-scripts/raw/master/marzban.sh)" @ install
  ```
- پس از نصب، در **پنل MeowVPN** این سرور را با دسته «مستقیم» (direct) و منطقه «خارج» (foreign) اضافه کنید (دامنه/IP، یوزر/پس ادمین مرزبان).

---

## نود تانل خارج (خروج تانل)

- **نصب Marzban** (همان دستور بالا).
- **نصب و کانفیگ سمت سرور تانل** (بسته به نوع: Rathole server، Backhaul server، Xray dokodemo، GOST، HA Proxy، یا iptables).
- این بخش توسط **اینستالر تانل** با انتخاب نوع تانل و نقش «خارج (سرور)» انجام می‌شود:
  ```bash
  ./installer/tunnel.sh
  ```

---

## نود تانل ایران (ورود تانل)

- **نصب و کانفیگ فقط سمت کلاینت تانل** (Rathole client، Backhaul client، و غیره).
- برای **تانل ریورس** (Marzban روی هر دو): نصب Marzban روی ایران هم با همان اسکریپت رسمی.
- بقیه انواع: بدون Marzban روی ایران؛ فقط سرویس تانل.
- این هم توسط **اینستالر تانل** با انتخاب نوع و نقش «ایران (کلاینت)» انجام می‌شود:
  ```bash
  ./installer/tunnel.sh
  ```

---

## لینک‌های مرتبط

- [اینستالر تانل تعاملی](../installer/tunnel.sh) — انتخاب نوع تانل و نقش سرور، نصب و کانفیگ خودکار.
- [راهنمای دپلوی و تفکیک نود مرکزی](DEPLOYMENT.md) — سرور اصلی، نود مرکزی، و تفکیک نقش‌ها.
