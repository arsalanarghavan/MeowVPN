# MeowVPN - سیستم مدیریت VPN حرفه‌ای

سیستم کامل مدیریت VPN با قابلیت مدیریت 300,000 کاربر، نمایندگان فروش، بازاریاب‌ها و مشتریان نهایی.

## معماری

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: React.js + Shadcn/ui + Tailwind CSS
- **Telegram Bot**: Python (Aiogram 3.x)
- **Database**: PostgreSQL 15+
- **Cache/Queue**: Redis
- **VPN Core**: Marzban Panel

## نصب خودکار (یک دستور)

کافیست روی سرور اوبونتو ۲۰+ یا دبیان ۱۱+ دستور زیر را اجرا کنید تا همه چیز به‌طور خودکار از گیت‌هاب دانلود و نصب شود:

```bash
curl -sSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/installer/bootstrap.sh | bash
```

یا اگر ترجیح می‌دهید ابتدا مخزن را کلون کنید:

```bash
git clone https://github.com/arsalanarghavan/MeowVPN.git && cd MeowVPN && bash installer/install.sh
```

> **نکته:** اسکریپت نصب به‌صورت خودکار Docker، Docker Compose و تمام وابستگی‌ها را نصب می‌کند. نیازی به نصب دستی پیش‌نیازها نیست.

پس از نصب، به آدرس `https://panel.yourdomain.com` مراجعه کنید و Setup Wizard را تکمیل کنید.

### آپدیت

اگر قبلاً نصب کرده‌اید (پوشه `/opt/MeowVPN` موجود است):

```bash
cd /opt/MeowVPN && git pull && bash installer/update.sh
```

### نصب تانل (تعاملی)

```bash
cd /opt/MeowVPN && bash installer/tunnel.sh
```

## ساختار پروژه

```
MeowVPN/
├── backend/              # Laravel 11 API
├── frontend/             # React Dashboard
├── telegram-bot/         # Python Bot
├── docker/               # Docker configurations
├── installer/            # Installer scripts
└── docker-compose.yml    # Main Docker Compose
```

## تفکیک سرور اصلی و نود مرکزی

برای جلوگیری از کندی سرور اصلی، می‌توانید سینک و مانیتورینگ را روی یک **نود مرکزی** جدا اجرا کنید.

- **سرور اصلی (web)**: فقط وب و API؛ دیتابیس و Redis با نود مرکزی مشترک است.
- **نود مرکزی (central)**: فقط Scheduler و Queue برای jobهای سینک و مانیتورینگ؛ به همان DB و Redis متصل است.

### تنظیمات

**سرور اصلی** در `.env` بک‌اند:

```env
APP_NODE_ROLE=web
```

اجرای وب (و در صورت نیاز فقط صف سبک):

```bash
php artisan queue:work --queue=default
```

**نود مرکزی** در `.env` همان پروژه (با اتصال به همان `DB_*` و `REDIS_*`):

```env
APP_NODE_ROLE=central
```

اجرای Scheduler و Queue برای سینک/مانیتورینگ:

```bash
php artisan schedule:work
php artisan queue:work --queue=sync,default
```

**نصب تکی (همه‌چیز روی یک سرور)** — پیش‌فرض:

```env
APP_NODE_ROLE=all
```

برای جزئیات بیشتر [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) را ببینید.

## نصب به تفکیک نوع سرور

برای هر نوع سرور (سرور اصلی، نود مرکزی، نود عادی خارج، نود تانل خارج، نود تانل ایران) دستور نصب و پیکربندی در مستند زیر آمده است:

- **[docs/SERVER_INSTALL.md](docs/SERVER_INSTALL.md)** — راهنمای نصب هر مدل سرور

برای **نصب تعاملی تانل** (Rathole، Backhaul، Dokodemo، GOST، HA Proxy، IP Tables و تانل ریورس):

```bash
chmod +x installer/tunnel.sh
./installer/tunnel.sh
```

## مستندات

برای جزئیات کامل معماری، به فایل [ARCHITECTURE.MD](ARCHITECTURE.MD) مراجعه کنید.

## لایسنس

Proprietary - All rights reserved

