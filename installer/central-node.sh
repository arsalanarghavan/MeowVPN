#!/bin/bash
set -euo pipefail

# Central Node installer: set up this server as the central node (scheduler + queue for sync/monitoring).
# Prerequisites: PHP, Composer, access to same DB and Redis as main panel.

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_error()   { echo -e "${RED}✗ $1${NC}"; }
print_info()    { echo -e "${YELLOW}ℹ $1${NC}"; }
print_step()    { echo -e "${BLUE}▶ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠ $1${NC}"; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
BACKEND_DIR="${1:-$PROJECT_ROOT/backend}"

if [ ! -d "$BACKEND_DIR" ]; then
    print_error "پوشه بک‌اند یافت نشد: $BACKEND_DIR"
    print_info "استفاده: $0 [مسیر_پوشه_بک‌اند]"
    exit 1
fi

if [ ! -f "$BACKEND_DIR/artisan" ]; then
    print_error "فایل artisan در $BACKEND_DIR یافت نشد."
    exit 1
fi

print_step "نصب نود مرکزی — مسیر بک‌اند: $BACKEND_DIR"

# PHP
if ! command -v php &>/dev/null; then
    print_error "PHP نصب نیست. لطفاً PHP 8.2+ نصب کنید."
    exit 1
fi
print_success "PHP موجود است: $(php -r 'echo PHP_VERSION;')"

# Composer
if ! command -v composer &>/dev/null; then
    print_info "نصب Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi
print_success "Composer موجود است"

# .env
if [ ! -f "$BACKEND_DIR/.env" ]; then
    print_info "کپی .env.example به .env..."
    cp "$BACKEND_DIR/.env.example" "$BACKEND_DIR/.env"
    print_warning "لطفاً در $BACKEND_DIR/.env تنظیم کنید: APP_NODE_ROLE=central و DB_* و REDIS_* (همان مقادیر سرور اصلی)."
else
    print_info "فایل .env وجود دارد."
fi

if ! grep -q '^APP_NODE_ROLE=central' "$BACKEND_DIR/.env" 2>/dev/null; then
    print_warning "در .env مقدار APP_NODE_ROLE=central را تنظیم کنید (الان ممکن است web یا all باشد)."
    sed -i.bak 's/^APP_NODE_ROLE=.*/APP_NODE_ROLE=central/' "$BACKEND_DIR/.env" 2>/dev/null || true
fi

# Dependencies
print_step "نصب وابستگی‌های PHP..."
(cd "$BACKEND_DIR" && composer install --no-dev --no-interaction) || {
    print_error "composer install با خطا مواجه شد."
    exit 1
}
print_success "وابستگی‌ها نصب شد."

# APP_KEY
if ! grep -q '^APP_KEY=base64:.*' "$BACKEND_DIR/.env" 2>/dev/null; then
    print_info "تولید APP_KEY..."
    (cd "$BACKEND_DIR" && php artisan key:generate --force)
fi

# Systemd: two services — schedule:work and queue:work
print_step "ایجاد سرویس‌های systemd..."

sudo tee /etc/systemd/system/meowvpn-central-schedule.service >/dev/null <<EOF
[Unit]
Description=MeowVPN Central Node - Scheduler
After=network.target

[Service]
Type=simple
User=$(whoami)
WorkingDirectory=$BACKEND_DIR
ExecStart=$(which php) artisan schedule:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo tee /etc/systemd/system/meowvpn-central-queue.service >/dev/null <<EOF
[Unit]
Description=MeowVPN Central Node - Queue (sync,default)
After=network.target

[Service]
Type=simple
User=$(whoami)
WorkingDirectory=$BACKEND_DIR
ExecStart=$(which php) artisan queue:work --queue=sync,default
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable meowvpn-central-schedule meowvpn-central-queue
print_success "سرویس‌ها فعال شدند. برای شروع: sudo systemctl start meowvpn-central-schedule meowvpn-central-queue"

print_info "برای راه‌اندازی اکنون: sudo systemctl start meowvpn-central-schedule meowvpn-central-queue"
print_info "وضعیت: sudo systemctl status meowvpn-central-schedule meowvpn-central-queue"
print_info "مستندات بیشتر: docs/SERVER_INSTALL.md و docs/DEPLOYMENT.md"
