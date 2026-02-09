#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

# GOST: relay -L=tcp://:PORT/TARGET_IP:TARGET_PORT. Install ginuerzh/gost, systemd.

GOST_VERSION="${GOST_VERSION:-2.11.5}"
INSTALL_DIR="/usr/local/bin"
ARCH=$(uname -m)
[ "$ARCH" = "x86_64" ] && ARCH="amd64" || true

RELAY_PORT="${RELAY_PORT:-443}"
TARGET_ADDR="${TARGET_ADDR:-}"
REMOTE_ADDR="${REMOTE_ADDR:-}"
REMOTE_PORT="${REMOTE_PORT:-443}"

print_step "نصب GOST (نقش: $TUNNEL_ROLE)..."

if ! command -v gost &>/dev/null; then
    DOWNLOAD_URL="https://github.com/ginuerzh/gost/releases/download/v${GOST_VERSION}/gost_${GOST_VERSION}_linux_${ARCH}.tar.gz"
    if ! curl -sLI "$DOWNLOAD_URL" | head -1 | grep -q 200; then
        DOWNLOAD_URL="https://github.com/ginuerzh/gost/releases/download/v${GOST_VERSION}/gost-linux-${ARCH}-${GOST_VERSION}.gz"
        TMPDIR=$(mktemp -d)
        curl -sL "$DOWNLOAD_URL" -o "$TMPDIR/gost.gz"
        gunzip -c "$TMPDIR/gost.gz" | sudo tee "$INSTALL_DIR/gost" >/dev/null
        rm -rf "$TMPDIR"
    else
        TMPDIR=$(mktemp -d)
        curl -sL "$DOWNLOAD_URL" -o "$TMPDIR/gost.tar.gz"
        tar -xzf "$TMPDIR/gost.tar.gz" -C "$TMPDIR"
        sudo cp "$TMPDIR/gost" "$INSTALL_DIR/gost" 2>/dev/null || sudo cp "$TMPDIR/gost_${GOST_VERSION}_linux_${ARCH}/gost" "$INSTALL_DIR/gost"
        rm -rf "$TMPDIR"
    fi
    sudo chmod +x "$INSTALL_DIR/gost"
    print_success "GOST نصب شد."
fi

if [ "$TUNNEL_ROLE" = "server" ]; then
    # Relay: listen on RELAY_PORT, forward to TARGET_ADDR (e.g. main server IP:port)
    if [ -z "$TARGET_ADDR" ]; then
        print_error "TARGET_ADDR تنظیم نشده (آدرس:پورت مقصد)."
        exit 1
    fi
    EXEC_ARGS="-L=tcp://:${RELAY_PORT}/${TARGET_ADDR}"
else
    # Client: listen on REMOTE_PORT, forward to REMOTE_ADDR:REMOTE_PORT
    if [ -z "$REMOTE_ADDR" ]; then
        print_error "REMOTE_ADDR تنظیم نشده."
        exit 1
    fi
    EXEC_ARGS="-L=tcp://:${REMOTE_PORT}/${REMOTE_ADDR}:${REMOTE_PORT}"
fi

sudo tee /etc/systemd/system/gost.service >/dev/null <<EOF
[Unit]
Description=GO Simple Tunnel
After=network.target

[Service]
Type=simple
ExecStart=$INSTALL_DIR/gost $EXEC_ARGS
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable gost.service
sudo systemctl restart gost.service
print_success "سرویس GOST فعال شد."

if [ "$TUNNEL_ROLE" = "server" ]; then
    print_info "رله: پورت ${RELAY_PORT} به ${TARGET_ADDR} فوروارد می‌شود. در پنل MeowVPN این سرور را به‌عنوان نود تانل خارج ثبت کنید."
else
    print_info "کلاینت به ${REMOTE_ADDR}:${REMOTE_PORT} متصل است. در پنل MeowVPN نود تانل ایران را با آدرس این سرور ثبت کنید."
fi
