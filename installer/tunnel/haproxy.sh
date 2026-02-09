#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

# HA Proxy: install HAProxy, add frontend (listen) and backend (main server IP:port).

BACKEND_ADDR="${BACKEND_ADDR:-}"
FRONTEND_PORT="${FRONTEND_PORT:-443}"
HAPROXY_CFG="/etc/haproxy/haproxy.cfg"

if [ -z "$BACKEND_ADDR" ]; then
    print_error "BACKEND_ADDR تنظیم نشده (آدرس:پورت سرور اصلی)."
    exit 1
fi

print_step "نصب و پیکربندی HA Proxy..."

if ! command -v haproxy &>/dev/null; then
    if command -v apt-get &>/dev/null; then
        sudo apt-get update -qq
        sudo apt-get install -y haproxy
    else
        print_error "فقط apt پشتیبانی می‌شود. haproxy را دستی نصب کنید."
        exit 1
    fi
fi

# Parse backend: host:port
BACKEND_HOST="${BACKEND_ADDR%%:*}"
BACKEND_PORT="${BACKEND_ADDR#*:}"
[ "$BACKEND_PORT" = "$BACKEND_ADDR" ] && BACKEND_PORT="443"

# Append frontend/backend if not already present
if ! grep -q "frontend meowvpn_tunnel" "$HAPROXY_CFG" 2>/dev/null; then
    sudo tee -a "$HAPROXY_CFG" >/dev/null <<EOF

# MeowVPN tunnel (added by installer)
frontend meowvpn_tunnel
    bind *:${FRONTEND_PORT}
    default_backend meowvpn_backend

backend meowvpn_backend
    mode tcp
    balance roundrobin
    server main ${BACKEND_HOST} ${BACKEND_PORT} check
EOF
    print_success "پیکربندی HA Proxy اضافه شد."
else
    print_warning "بخش meowvpn_tunnel از قبل در $HAPROXY_CFG وجود دارد؛ تغییری اعمال نشد."
fi

sudo systemctl enable haproxy 2>/dev/null || true
sudo systemctl restart haproxy
print_success "HA Proxy ریستارت شد. پورت ${FRONTEND_PORT} به ${BACKEND_ADDR} فوروارد می‌شود."
print_info "در پنل MeowVPN این سرور را به‌عنوان نود تانل خارج با آدرس و پورت ${FRONTEND_PORT} ثبت کنید."
