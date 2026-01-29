# MeowVPN - سیستم مدیریت VPN حرفه‌ای

سیستم کامل مدیریت VPN با قابلیت مدیریت 300,000 کاربر، نمایندگان فروش، بازاریاب‌ها و مشتریان نهایی.

## معماری

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: React.js + Shadcn/ui + Tailwind CSS
- **Telegram Bot**: Python (Aiogram 3.x)
- **Database**: PostgreSQL 15+
- **Cache/Queue**: Redis
- **VPN Core**: Marzban Panel

## نصب سریع

```bash
chmod +x installer/install.sh
./installer/install.sh
```

پس از نصب، به آدرس `https://panel.yourdomain.com` مراجعه کنید و Setup Wizard را تکمیل کنید.

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

## مستندات

برای جزئیات کامل معماری، به فایل [ARCHITECTURE.MD](ARCHITECTURE.MD) مراجعه کنید.

## آپدیت

```bash
chmod +x installer/update.sh
./installer/update.sh
```

## لایسنس

Proprietary - All rights reserved

