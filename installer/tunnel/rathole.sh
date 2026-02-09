#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

# Rathole: use Musixal/Rathole-Tunnel rathole_v2.sh (interactive) or install rapiz1/rathole manually.
# We run the v2 script which has its own menu; we pass role via env for user guidance.

LISTEN_PORT="${LISTEN_PORT:-443}"
TOKEN="${TOKEN:-}"
REMOTE_ADDR="${REMOTE_ADDR:-}"
REMOTE_PORT="${REMOTE_PORT:-443}"

if [ "$TUNNEL_ROLE" = "server" ]; then
    print_step "نصب Rathole (سرور) روی پورت $LISTEN_PORT..."
    print_info "در حال دانلود و اجرای rathole_v2.sh (منوی تعاملی Musixal)..."
    sudo bash -c "$(curl -sL --ipv4 https://raw.githubusercontent.com/Musixal/rathole-tunnel/main/rathole_v2.sh)" || true
    print_success "پس از اتمام منوی rathole_v2، سرور Rathole آماده است."
    if [ -n "$TOKEN" ]; then
        print_info "توکن برای کلاینت: $TOKEN"
    fi
    print_info "در پنل MeowVPN این سرور را به‌عنوان نود تانل خارج (خروج تانل) با آدرس/پورت این ماشین ثبت کنید."
else
    print_step "نصب Rathole (کلاینت) — اتصال به $REMOTE_ADDR:$REMOTE_PORT..."
    print_info "در حال دانلود و اجرای rathole_v2.sh (منوی تعاملی Musixal)..."
    sudo bash -c "$(curl -sL --ipv4 https://raw.githubusercontent.com/Musixal/rathole-tunnel/main/rathole_v2.sh)" || true
    print_success "پس از اتمام منوی rathole_v2 و وارد کردن آدرس سرور ($REMOTE_ADDR:$REMOTE_PORT) و توکن، کلاینت آماده است."
    print_info "در پنل MeowVPN نود تانل ایران (ورود تانل) را با آدرس/دامنه این سرور (ایران) برای سابسکریپشن ثبت کنید."
fi
