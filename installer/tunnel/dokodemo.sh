#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

# Dokodemo-Door (Xray): Iran side runs Xray with dokodemo-door inbound forwarding to main server.
# Ref: Hiddify Dokodemo-Door tutorial.

REMOTE_ADDR="${REMOTE_ADDR:-}"
REMOTE_PORT="${REMOTE_PORT:-443}"
LISTEN_PORT="${LISTEN_PORT:-443}"

XRAY_CONFIG="/usr/local/etc/xray/config.json"

if [ "$TUNNEL_ROLE" = "client" ]; then
    # Iran: Xray dokodemo-door listens and forwards to REMOTE_ADDR:REMOTE_PORT (main server abroad)
    if [ -z "$REMOTE_ADDR" ]; then
        print_error "REMOTE_ADDR تنظیم نشده (آدرس سرور اصلی خارج)."
        exit 1
    fi
    print_step "نصب Xray و پیکربندی Dokodemo-Door (کلاینت / ایران)..."

    if ! command -v xray &>/dev/null; then
        print_info "نصب Xray..."
        sudo bash -c "$(curl -sL https://raw.githubusercontent.com/XTLS/Xray-install/main/install-release.sh)" @ install
    fi

    sudo mkdir -p "$(dirname "$XRAY_CONFIG")"
    sudo tee "$XRAY_CONFIG" >/dev/null <<EOF
{
  "log": { "loglevel": "warning" },
  "inbounds": [
    {
      "tag": "dokodemo-in",
      "port": ${LISTEN_PORT},
      "listen": "0.0.0.0",
      "protocol": "dokodemo-door",
      "settings": {
        "address": "${REMOTE_ADDR}",
        "port": ${REMOTE_PORT},
        "network": "tcp,udp",
        "followRedirect": false
      },
      "sniffing": { "enabled": false }
    }
  ],
  "outbounds": [
    { "protocol": "freedom", "tag": "direct" },
    { "protocol": "blackhole", "tag": "blocked" }
  ],
  "routing": {
    "domainStrategy": "AsIs",
    "rules": [
      { "type": "field", "ip": ["geoip:private"], "outboundTag": "direct" }
    ]
  }
}
EOF

    # systemd
    if [ ! -f /etc/systemd/system/xray.service ]; then
        sudo tee /etc/systemd/system/xray.service >/dev/null <<EOF
[Unit]
Description=Xray Service
After=network.target

[Service]
Type=simple
ExecStart=/usr/local/bin/xray run -c $XRAY_CONFIG
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF
        sudo systemctl daemon-reload
    fi
    sudo systemctl enable xray.service
    sudo systemctl restart xray.service
    print_success "Xray Dokodemo-Door (کلاینت) نصب و راه‌اندازی شد. پورت ${LISTEN_PORT} به ${REMOTE_ADDR}:${REMOTE_PORT} فوروارد می‌شود."
    print_info "در پنل MeowVPN نود تانل ایران را با آدرس این سرور و پورت ${LISTEN_PORT} ثبت کنید."
else
    # Server (foreign) side: usually the main Marzban/Xray is here; dokodemo is on Iran side.
    # So "server" for dokodemo means: this machine listens and is the target. No Xray needed here if Marzban listens.
    print_step "سمت خارج (سرور اصلی): نیازی به نصب Dokodemo روی این سرور نیست؛ ترافیک از ایران به آدرس این سرور (${REMOTE_ADDR:-این ماشین}:${REMOTE_PORT}) می‌آید."
    print_info "فقط مطمئن شوید سرویس اصلی (Marzban/Xray) روی این سرور روی پورت ${REMOTE_PORT} گوش می‌دهد."
    print_info "در پنل MeowVPN این سرور را به‌عنوان نود تانل خارج با آدرس و پورت ${REMOTE_PORT} ثبت کنید."
fi
