#!/bin/bash

set -euo pipefail

# Colors (same style as install.sh)
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
TUNNEL_DIR="$SCRIPT_DIR/tunnel"
LOG_FILE="/tmp/meowvpn_tunnel_install.log"

# Ensure tunnel sub-scripts exist
if [ ! -d "$TUNNEL_DIR" ]; then
    print_error "Tunnel scripts directory not found: $TUNNEL_DIR"
    exit 1
fi

# Banner
clear
echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════╗"
echo "║     MeowVPN Tunnel Installer                       ║"
echo "║     نصب و پیکربندی تانل                            ║"
echo "╚══════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# Step 1: Choose tunnel type
print_step "نوع تانل را انتخاب کنید:"
echo "  1) تانل ریورس (Marzban روی هر دو سرور)"
echo "  2) Rathole (Musixal/Rathole-Tunnel)"
echo "  3) Backhaul (Musixal/Backhaul)"
echo "  4) Dokodemo-Door (Xray)"
echo "  5) GOST"
echo "  6) HA Proxy"
echo "  7) IP Tables"
echo ""
read -rp "شماره (1-7): " TUNNEL_TYPE_NUM

case "$TUNNEL_TYPE_NUM" in
    1) TUNNEL_TYPE="reverse" ;;
    2) TUNNEL_TYPE="rathole" ;;
    3) TUNNEL_TYPE="backhaul" ;;
    4) TUNNEL_TYPE="dokodemo" ;;
    5) TUNNEL_TYPE="gost" ;;
    6) TUNNEL_TYPE="haproxy" ;;
    7) TUNNEL_TYPE="iptables" ;;
    *)
        print_error "انتخاب نامعتبر."
        exit 1
        ;;
esac

# Step 2: Choose role
print_step "نقش این سرور چیست؟"
echo "  1) ایران (ورود تانل / کلاینت)"
echo "  2) خارج (خروج تانل / سرور)"
echo ""
read -rp "شماره (1 یا 2): " ROLE_NUM

case "$ROLE_NUM" in
    1) TUNNEL_ROLE="client" ;;
    2) TUNNEL_ROLE="server" ;;
    *)
        print_error "انتخاب نامعتبر."
        exit 1
        ;;
esac

export TUNNEL_TYPE TUNNEL_ROLE

# Step 3: Minimal inputs based on type and role
case "$TUNNEL_TYPE" in
    reverse)
        # No extra input; both sides get Marzban
        ;;
    rathole|backhaul)
        if [ "$TUNNEL_ROLE" = "server" ]; then
            read -rp "پورت گوش دادن [443]: " LISTEN_PORT
            LISTEN_PORT="${LISTEN_PORT:-443}"
            read -rp "توکن (خالی = تولید تصادفی): " TOKEN
            if [ -z "$TOKEN" ]; then
                TOKEN=$(openssl rand -hex 16 2>/dev/null || head -c 32 /dev/urandom | xxd -p -c 32)
            fi
            export LISTEN_PORT TOKEN
        else
            read -rp "آدرس سرور خارج (IP یا دامنه): " REMOTE_ADDR
            read -rp "پورت سرور خارج [443]: " REMOTE_PORT
            REMOTE_PORT="${REMOTE_PORT:-443}"
            read -rp "توکن: " TOKEN
            export REMOTE_ADDR REMOTE_PORT TOKEN
        fi
        ;;
    dokodemo)
        if [ "$TUNNEL_ROLE" = "client" ]; then
            # Iran side: need main server (foreign) address
            read -rp "آدرس سرور اصلی خارج (IP یا دامنه): " REMOTE_ADDR
            read -rp "پورت سرور اصلی [443]: " REMOTE_PORT
            REMOTE_PORT="${REMOTE_PORT:-443}"
            export REMOTE_ADDR REMOTE_PORT
        else
            read -rp "پورت گوش دادن [443]: " LISTEN_PORT
            LISTEN_PORT="${LISTEN_PORT:-443}"
            export LISTEN_PORT
        fi
        ;;
    gost)
        if [ "$TUNNEL_ROLE" = "server" ]; then
            read -rp "پورت رله [443]: " RELAY_PORT
            RELAY_PORT="${RELAY_PORT:-443}"
            read -rp "آدرس:پورت مقصد (مثلاً 1.2.3.4:443): " TARGET_ADDR
            export RELAY_PORT TARGET_ADDR
        else
            read -rp "آدرس سرور خارج: " REMOTE_ADDR
            read -rp "پورت سرور خارج [443]: " REMOTE_PORT
            REMOTE_PORT="${REMOTE_PORT:-443}"
            read -rp "آدرس:پورت مقصد محلی (مثلاً 127.0.0.1:443): " TARGET_ADDR
            export REMOTE_ADDR REMOTE_PORT TARGET_ADDR
        fi
        ;;
    haproxy)
        # Typically server side (foreign) forwards to main server
        read -rp "آدرس:پورت سرور اصلی (Backend، مثلاً 10.0.0.1:443): " BACKEND_ADDR
        read -rp "پورت فرانت (گوش دادن) [443]: " FRONTEND_PORT
        FRONTEND_PORT="${FRONTEND_PORT:-443}"
        export BACKEND_ADDR FRONTEND_PORT
        ;;
    iptables)
        # Relay: forward to main server IP:port
        read -rp "آدرس:پورت سرور اصلی (مقصد فوروارد، مثلاً 10.0.0.1:443): " TARGET_ADDR
        read -rp "پورت گوش دادن [443]: " LISTEN_PORT
        LISTEN_PORT="${LISTEN_PORT:-443}"
        export TARGET_ADDR LISTEN_PORT
        ;;
esac

# Step 4: Run the sub-script
SUB_SCRIPT="$TUNNEL_DIR/${TUNNEL_TYPE}.sh"
if [ ! -f "$SUB_SCRIPT" ]; then
    print_error "اسکریپت یافت نشد: $SUB_SCRIPT"
    exit 1
fi

print_step "در حال اجرای نصب/پیکربندی ($TUNNEL_TYPE، نقش: $TUNNEL_ROLE)..."
echo ""

if [ -x "$SUB_SCRIPT" ]; then
    "$SUB_SCRIPT" 2>&1 | tee -a "$LOG_FILE"
else
    bash "$SUB_SCRIPT" 2>&1 | tee -a "$LOG_FILE"
fi

EXIT_CODE=${PIPESTATUS[0]}
if [ "$EXIT_CODE" -eq 0 ]; then
    print_success "نصب تانل انجام شد."
    print_info "برای ثبت در پنل MeowVPN از آدرس/دامنه و پورت این سرور استفاده کنید."
    print_info "لاگ: $LOG_FILE"
else
    print_error "نصب با خطا مواجه شد (خروج: $EXIT_CODE)."
    exit "$EXIT_CODE"
fi
