#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

# Reverse tunnel: Marzban on both sides. This script installs Marzban on current server
# and shows instructions for the other server.

print_step "نصب Marzban روی این سرور (تانل ریورس)..."

if ! command -v docker &>/dev/null; then
    print_info "نصب Docker از اسکریپت رسمی..."
    curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
    sudo sh /tmp/get-docker.sh
    rm -f /tmp/get-docker.sh
fi

print_info "اجرای اسکریپت رسمی Marzban..."
sudo bash -c "$(curl -sL https://github.com/Gozargah/Marzban-scripts/raw/master/marzban.sh)" @ install

print_success "Marzban روی این سرور نصب شد."
print_info "روی سرور دوم (طرف دیگر تانل) همین دستور را اجرا کنید:"
echo ""
echo "  sudo bash -c \"\$(curl -sL https://github.com/Gozargah/Marzban-scripts/raw/master/marzban.sh)\" @ install"
echo ""
print_info "سپس در پنل MeowVPN هر دو سرور را با دسته «تانل» و منطقه مناسب (ایران / خارج) اضافه کنید."
