#!/usr/bin/env bash
set -euo pipefail

# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

NON_INTERACTIVE=0
INSTALL_MODE=""
SSL_METHOD="${SSL_METHOD:-certbot}"
SSL_EMAIL="${SSL_EMAIL:-}"

prompt_domain() {
  local title="$1"
  local default="${2:-}"
  local result=""
  if [[ "$NON_INTERACTIVE" == "1" ]]; then
    echo "$default"
    return
  fi
  if command -v whiptail >/dev/null 2>&1; then
    result="$(whiptail --backtitle "MeowVPN" --title "MeowVPN Install" --inputbox "$title\n(leave empty to use server IP)" 10 70 "$default" 3>&1 1>&2 2>&3 || true)"
  else
    read -r -p "$title [empty=IP]: " result
  fi
  echo "$result"
}

prompt_ssl_method() {
  if [[ "$NON_INTERACTIVE" == "1" ]]; then
    echo "${SSL_METHOD:-certbot}"
    return
  fi
  if command -v whiptail >/dev/null 2>&1; then
    whiptail --backtitle "MeowVPN" --title "SSL" --menu "Certificate issuer" 12 60 2 \
      certbot "Let's Encrypt (certbot)" \
      acme "acme.sh" \
      3>&1 1>&2 2>&3 || echo certbot
  else
    read -r -p "SSL method [certbot|acme]: " SSL_METHOD
    echo "${SSL_METHOD:-certbot}"
  fi
}

prompt_email() {
  if [[ "$NON_INTERACTIVE" == "1" ]]; then
    echo "$SSL_EMAIL"
    return
  fi
  if command -v whiptail >/dev/null 2>&1; then
    whiptail --backtitle "MeowVPN" --title "SSL Email" --inputbox "Email for Let's Encrypt (certbot)" 8 60 "$SSL_EMAIL" 3>&1 1>&2 2>&3 || true
  else
    read -r -p "SSL email: " SSL_EMAIL
    echo "$SSL_EMAIL"
  fi
}

prompt_core_url() {
  local default="${1:-}"
  if [[ "$NON_INTERACTIVE" == "1" ]]; then
    echo "$default"
    return
  fi
  if command -v whiptail >/dev/null 2>&1; then
    whiptail --backtitle "MeowVPN" --title "Backend URL" --inputbox "Dashboard Core URL (API base, e.g. https://api.example.com)" 10 70 "$default" 3>&1 1>&2 2>&3 || true
  else
    read -r -p "Dashboard Core URL: " default
    echo "$default"
  fi
}

collect_all_domains() {
  local ip
  ip="$(detect_server_ip)"
  SERVER_IP="$ip"
  if [[ "$NON_INTERACTIVE" == "1" ]]; then
    CORE_HOST="$(resolve_host "${CORE_DOMAIN:-}" "$ip")"
    DASHBOARD_HOST="$(resolve_host "${DASHBOARD_DOMAIN:-}" "$ip")"
    TELEGRAM_HOST="$(resolve_host "${TELEGRAM_DOMAIN:-}" "$ip")"
    BALE_HOST="$(resolve_host "${BALE_DOMAIN:-}" "$ip")"
    RELAY_HOST="$(resolve_host "${RELAY_DOMAIN:-}" "$ip")"
    SSL_METHOD="${SSL_METHOD:-certbot}"
    SSL_EMAIL="${SSL_EMAIL:-}"
  else
    CORE_HOST="$(resolve_host "$(prompt_domain "Enter Dashboard Core Domain:" "${CORE_DOMAIN:-}")" "$ip")"
    DASHBOARD_HOST="$(resolve_host "$(prompt_domain "Enter Dashboard Domain:" "${DASHBOARD_DOMAIN:-}")" "$ip")"
    TELEGRAM_HOST="$(resolve_host "$(prompt_domain "Enter Telegram Bot Domain:" "${TELEGRAM_DOMAIN:-}")" "$ip")"
    BALE_HOST="$(resolve_host "$(prompt_domain "Enter Bale Bot Domain:" "${BALE_DOMAIN:-}")" "$ip")"
    RELAY_HOST="$(resolve_host "$(prompt_domain "Enter Relay Domain:" "${RELAY_DOMAIN:-}")" "$ip")"
    SSL_METHOD="$(prompt_ssl_method)"
    SSL_EMAIL="$(prompt_email)"
  fi
  persist_domain_state
}

collect_dashboard_domains() {
  local ip
  ip="$(detect_server_ip)"
  SERVER_IP="$ip"
  if [[ "$NON_INTERACTIVE" == "1" ]]; then
    CORE_HOST="$(resolve_host "${CORE_DOMAIN:-}" "$ip")"
    DASHBOARD_HOST="$(resolve_host "${DASHBOARD_DOMAIN:-}" "$ip")"
    SSL_METHOD="${SSL_METHOD:-certbot}"
    SSL_EMAIL="${SSL_EMAIL:-}"
  else
    CORE_HOST="$(resolve_host "$(prompt_domain "Enter Dashboard Core Domain:" "${CORE_DOMAIN:-}")" "$ip")"
    DASHBOARD_HOST="$(resolve_host "$(prompt_domain "Enter Dashboard Domain:" "${DASHBOARD_DOMAIN:-}")" "$ip")"
    SSL_METHOD="$(prompt_ssl_method)"
    SSL_EMAIL="$(prompt_email)"
  fi
  persist_domain_state
}

collect_single_domain() {
  local var_name="$1"
  local prompt_text="$2"
  local ip
  ip="$(detect_server_ip)"
  SERVER_IP="$ip"
  local val
  val="$(resolve_host "$(prompt_domain "$prompt_text" "")" "$ip")"
  printf -v "$var_name" '%s' "$val"
  SSL_METHOD="$(prompt_ssl_method)"
  SSL_EMAIL="$(prompt_email)"
  save_state_kv "SERVER_IP" "$ip"
  save_state_kv "$var_name" "$val"
  save_state_kv "SSL_METHOD" "$SSL_METHOD"
  save_state_kv "SSL_EMAIL" "$SSL_EMAIL"
}

persist_domain_state() {
  save_state_kv "SERVER_IP" "$SERVER_IP"
  save_state_kv "CORE_HOST" "$CORE_HOST"
  save_state_kv "DASHBOARD_HOST" "$DASHBOARD_HOST"
  save_state_kv "TELEGRAM_HOST" "$TELEGRAM_HOST"
  save_state_kv "BALE_HOST" "$BALE_HOST"
  save_state_kv "RELAY_HOST" "$RELAY_HOST"
  save_state_kv "SSL_METHOD" "$SSL_METHOD"
  save_state_kv "SSL_EMAIL" "$SSL_EMAIL"
  save_state
}

parse_cli_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --mode) INSTALL_MODE="$2"; shift 2 ;;
      --core-domain) CORE_DOMAIN="$2"; shift 2 ;;
      --dashboard-domain) DASHBOARD_DOMAIN="$2"; shift 2 ;;
      --telegram-domain) TELEGRAM_DOMAIN="$2"; shift 2 ;;
      --bale-domain) BALE_DOMAIN="$2"; shift 2 ;;
      --relay-domain) RELAY_DOMAIN="$2"; shift 2 ;;
      --ssl) SSL_METHOD="$2"; shift 2 ;;
      --email) SSL_EMAIL="$2"; shift 2 ;;
      --core-url) CORE_URL="$2"; shift 2 ;;
      --non-interactive) NON_INTERACTIVE=1; shift ;;
      -h|--help)
        cat <<'EOF'
MeowVPN installer
  sudo bash backend/scripts/ops/install.sh
  sudo bash backend/scripts/ops/install.sh --mode all --core-domain api.example.com ...
Modes: all, dashboard, backend, frontend, telegram, bale, relay
EOF
        exit 0
        ;;
      *) die "Unknown argument: $1" ;;
    esac
  done
}
