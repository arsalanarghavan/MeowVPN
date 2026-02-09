#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

# IP Tables: forward incoming traffic on LISTEN_PORT to TARGET_ADDR (main server IP:port).
# Enable IP forwarding and add PREROUTING/DNAT and FORWARD rules.

TARGET_ADDR="${TARGET_ADDR:-}"
LISTEN_PORT="${LISTEN_PORT:-443}"

if [ -z "$TARGET_ADDR" ]; then
    print_error "TARGET_ADDR تنظیم نشده (آدرس:پورت سرور اصلی)."
    exit 1
fi

TARGET_HOST="${TARGET_ADDR%%:*}"
TARGET_PORT="${TARGET_ADDR#*:}"
[ "$TARGET_PORT" = "$TARGET_ADDR" ] && TARGET_PORT="443"

print_step "پیکربندی IP Tables برای فوروارد پورت ${LISTEN_PORT} به ${TARGET_HOST}:${TARGET_PORT}..."

# Enable IP forwarding
sudo sysctl -w net.ipv4.ip_forward=1
if ! grep -q 'net.ipv4.ip_forward=1' /etc/sysctl.conf 2>/dev/null; then
    echo 'net.ipv4.ip_forward=1' | sudo tee -a /etc/sysctl.conf >/dev/null
fi

# iptables DNAT + FORWARD (simplified: assume traffic arrives on LISTEN_PORT)
# PREROUTING: DNAT to target
sudo iptables -t nat -C PREROUTING -p tcp --dport "$LISTEN_PORT" -j DNAT --to-destination "${TARGET_HOST}:${TARGET_PORT}" 2>/dev/null || \
    sudo iptables -t nat -A PREROUTING -p tcp --dport "$LISTEN_PORT" -j DNAT --to-destination "${TARGET_HOST}:${TARGET_PORT}"
# MASQUERADE for return traffic (if default outbound is this host)
sudo iptables -t nat -C POSTROUTING -j MASQUERADE 2>/dev/null || sudo iptables -t nat -A POSTROUTING -j MASQUERADE
# Allow FORWARD
sudo iptables -C FORWARD -p tcp -d "$TARGET_HOST" --dport "$TARGET_PORT" -j ACCEPT 2>/dev/null || \
    sudo iptables -A FORWARD -p tcp -d "$TARGET_HOST" --dport "$TARGET_PORT" -j ACCEPT
sudo iptables -C FORWARD -p tcp -s "$TARGET_HOST" -j ACCEPT 2>/dev/null || \
    sudo iptables -A FORWARD -p tcp -s "$TARGET_HOST" -j ACCEPT

print_success "قوانین iptables اعمال شد."
print_warning "برای پایدار ماندن پس از ریستارت، iptables-persistent نصب کنید یا قوانین را در اسکریپت بالا ذخیره کنید."
if command -v apt-get &>/dev/null; then
    print_info "نصب iptables-persistent: sudo apt-get install -y iptables-persistent"
fi
print_info "در پنل MeowVPN این سرور را به‌عنوان نود تانل خارج با آدرس و پورت ${LISTEN_PORT} ثبت کنید."
