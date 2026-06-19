#!/usr/bin/env bash
# MeowVPN install — shared helpers.
set -euo pipefail

_INSTALL_LIB="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() { echo "[meowvpn-install] $*"; }
warn() { echo "[meowvpn-install] WARN: $*" >&2; }
die() { echo "[meowvpn-install] ERROR: $*" >&2; exit 1; }

# shellcheck source=system.sh
source "$_INSTALL_LIB/system.sh"

resolve_install_paths() {
  INSTALL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
  local up4 up3
  up4="$(cd "$INSTALL_ROOT/../../../.." && pwd)"
  up3="$(cd "$INSTALL_ROOT/../../.." && pwd)"

  if [[ -f "$up4/backend/docker-compose.yml" ]]; then
    REPO_ROOT="$up4"
    BACKEND_DIR="$up4/backend"
  elif [[ -f "$up3/docker-compose.yml" ]]; then
    REPO_ROOT="$(cd "$up3/.." && pwd)"
    BACKEND_DIR="$up3"
  else
    die "Cannot locate MeowVPN backend (expected docker-compose.yml under backend/)"
  fi
  STATE_DIR="$BACKEND_DIR/.install"
  STATE_FILE="$STATE_DIR/state.env"
}

resolve_install_paths
TEMPLATE_DIR="$INSTALL_ROOT/templates/nginx"
NGINX_SITES_AVAILABLE="${NGINX_SITES_AVAILABLE:-/etc/nginx/sites-available}"
NGINX_SITES_ENABLED="${NGINX_SITES_ENABLED:-/etc/nginx/sites-enabled}"
CERTBOT_WEBROOT="${CERTBOT_WEBROOT:-/var/www/certbot}"

require_root() {
  if [[ "$(id -u)" -ne 0 ]]; then
    die "Run as root: sudo bash backend/scripts/ops/install.sh"
  fi
}

detect_server_ip() {
  local ip=""
  ip="$(curl -4 -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
  if [[ -z "$ip" ]]; then
    ip="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
  fi
  [[ -n "$ip" ]] || die "Could not detect server IP"
  echo "$ip"
}

is_ip_address() {
  local h="${1%%:*}"
  [[ "$h" =~ ^[0-9]+(\.[0-9]+){3}$ ]] && return 0
  [[ "$1" =~ ^\[.*\]$ ]] && return 0
  return 1
}

is_valid_hostname() {
  local h="$1"
  [[ -z "$h" ]] && return 1
  is_ip_address "$h" && return 1
  [[ "$h" =~ ^[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?$ ]]
}

resolve_host() {
  local input="$1"
  local fallback_ip="$2"
  if [[ -z "$input" ]]; then
    echo "$fallback_ip"
  else
    echo "$input"
  fi
}

public_url_for_host() {
  local host="$1"
  local use_ssl="$2"
  if is_ip_address "$host"; then
    echo "http://${host}"
  elif [[ "$use_ssl" == "1" ]]; then
    echo "https://${host}"
  else
    echo "http://${host}"
  fi
}

apex_domain() {
  local host="$1"
  if is_ip_address "$host"; then
    echo ""
    return
  fi
  local parts=(${host//./ })
  local n=${#parts[@]}
  if (( n >= 2 )); then
    echo ".${parts[$((n-2))]}.${parts[$((n-1))]}"
  fi
}

host_use_ssl() {
  local host="$1"
  is_valid_hostname "$host"
}

rand_hex() {
  openssl rand -hex "${1:-16}"
}

rand_secret() {
  openssl rand -hex 24
}

load_state() {
  mkdir -p "$STATE_DIR"
  if [[ -f "$STATE_FILE" ]]; then
    # shellcheck disable=SC1090
    set -a
    source "$STATE_FILE"
    set +a
  fi
}

save_state_kv() {
  local key="$1"
  local val="$2"
  mkdir -p "$STATE_DIR"
  touch "$STATE_FILE"
  if grep -q "^${key}=" "$STATE_FILE" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$STATE_FILE"
  else
    echo "${key}=${val}" >>"$STATE_FILE"
  fi
}

save_state() {
  save_state_kv "MEOWVPN_INSTALL_DATE" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
}

compose_cmd() {
  local -a extra=()
  if [[ "${SVP_INSTALL_BOTS_STANDALONE:-0}" == "1" ]]; then
    extra+=(-f "$INSTALL_ROOT/docker-compose.install.bots-standalone.override.yml")
  fi
  docker compose -f "$BACKEND_DIR/docker-compose.yml" \
    -f "$INSTALL_ROOT/docker-compose.install.override.yml" \
    "${extra[@]}" "$@"
}

load_domain_state() {
  load_state
  SERVER_IP="${SERVER_IP:-$(detect_server_ip)}"
  CORE_HOST="${CORE_HOST:-$SERVER_IP}"
  DASHBOARD_HOST="${DASHBOARD_HOST:-$SERVER_IP}"
  TELEGRAM_HOST="${TELEGRAM_HOST:-$SERVER_IP}"
  BALE_HOST="${BALE_HOST:-$SERVER_IP}"
  RELAY_HOST="${RELAY_HOST:-$SERVER_IP}"
  SSL_METHOD="${SSL_METHOD:-certbot}"
}
