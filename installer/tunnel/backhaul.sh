#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

# Backhaul: download binary from Musixal/Backhaul releases, create config.toml, systemd service.

LISTEN_PORT="${LISTEN_PORT:-443}"
TOKEN="${TOKEN:-}"
REMOTE_ADDR="${REMOTE_ADDR:-}"
REMOTE_PORT="${REMOTE_PORT:-443}"

ARCH=$(uname -m)
case "$ARCH" in
    x86_64)  ARCH="amd64" ;;
    aarch64|arm64) ARCH="arm64" ;;
    *) print_error "معماری پشتیبانی نشده: $ARCH"; exit 1 ;;
esac

BACKHAUL_VERSION="${BACKHAUL_VERSION:-0.6.5}"
INSTALL_DIR="/usr/local/bin"
CONFIG_DIR="/etc/backhaul"
CONFIG_FILE="$CONFIG_DIR/config.toml"

print_step "نصب Backhaul (نقش: $TUNNEL_ROLE)..."

# Download (try versioned and unversioned asset names)
DOWNLOAD_URL="https://github.com/Musixal/Backhaul/releases/download/v${BACKHAUL_VERSION}/backhaul_linux_${ARCH}.tar.gz"
print_info "دانلود از $DOWNLOAD_URL..."
sudo mkdir -p "$CONFIG_DIR"
TMPDIR=$(mktemp -d)
if ! curl -sLf "$DOWNLOAD_URL" -o "$TMPDIR/backhaul.tar.gz"; then
    DOWNLOAD_URL="https://github.com/Musixal/Backhaul/releases/download/v${BACKHAUL_VERSION}/backhaul_${BACKHAUL_VERSION}_linux_${ARCH}.tar.gz"
    curl -sLf "$DOWNLOAD_URL" -o "$TMPDIR/backhaul.tar.gz" || true
fi
tar -xzf "$TMPDIR/backhaul.tar.gz" -C "$TMPDIR"
BINARY=$(find "$TMPDIR" -maxdepth 3 -type f -name 'backhaul' 2>/dev/null | head -1)
[ -z "$BINARY" ] && BINARY=$(find "$TMPDIR" -maxdepth 3 -type f -executable 2>/dev/null | head -1)
[ -n "$BINARY" ] && sudo cp "$BINARY" "$INSTALL_DIR/backhaul"
rm -rf "$TMPDIR"
if [ ! -x "$INSTALL_DIR/backhaul" ]; then
    print_error "باینری Backhaul پس از استخراج یافت نشد. لطفاً دستی از GitHub Releases نصب کنید."
    exit 1
fi
sudo chmod +x "$INSTALL_DIR/backhaul"

if [ "$TUNNEL_ROLE" = "server" ]; then
    sudo tee "$CONFIG_FILE" >/dev/null <<EOF
[server]
bind_addr = "0.0.0.0:${LISTEN_PORT}"
transport = "tcp"
token = "${TOKEN}"
keepalive_period = 75
heartbeat = 40
channel_size = 2048
log_level = "info"
ports = ["${LISTEN_PORT}"]
EOF
    print_success "پیکربندی سرور در $CONFIG_FILE نوشته شد."
else
    sudo tee "$CONFIG_FILE" >/dev/null <<EOF
[client]
remote_addr = "${REMOTE_ADDR}:${REMOTE_PORT}"
transport = "tcp"
token = "${TOKEN}"
connection_pool = 8
keepalive_period = 75
dial_timeout = 10
retry_interval = 3
log_level = "info"
EOF
    print_success "پیکربندی کلاینت در $CONFIG_FILE نوشته شد."
fi

# systemd
sudo tee /etc/systemd/system/backhaul.service >/dev/null <<EOF
[Unit]
Description=Backhaul Tunnel
After=network.target

[Service]
Type=simple
ExecStart=$INSTALL_DIR/backhaul -c $CONFIG_FILE
Restart=always
RestartSec=3
LimitNOFILE=1048576

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable backhaul.service
sudo systemctl start backhaul.service
print_success "سرویس backhaul فعال شد."

if [ "$TUNNEL_ROLE" = "server" ]; then
    print_info "در پنل MeowVPN این سرور را به‌عنوان نود تانل خارج با آدرس و پورت $LISTEN_PORT ثبت کنید."
    [ -n "$TOKEN" ] && print_info "توکن کلاینت: $TOKEN"
else
    print_info "در پنل MeowVPN نود تانل ایران را با آدرس این سرور (ایران) برای سابسکریپشن ثبت کنید."
fi
