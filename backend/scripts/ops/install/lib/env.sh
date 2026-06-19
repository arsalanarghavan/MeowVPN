#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

set_env_var() {
  local file="$1"
  local key="$2"
  local val="$3"
  touch "$file"
  if grep -q "^${key}=" "$file" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$file"
  else
    echo "${key}=${val}" >>"$file"
  fi
}

ensure_secrets() {
  load_state
  [[ -n "${MEOWVPN_APP_KEY:-}" ]] || MEOWVPN_APP_KEY="base64:$(openssl rand -base64 32)"
  [[ -n "${MEOWVPN_DB_PASSWORD:-}" ]] || MEOWVPN_DB_PASSWORD="$(rand_secret)"
  [[ -n "${MEOWVPN_DB_ROOT_PASSWORD:-}" ]] || MEOWVPN_DB_ROOT_PASSWORD="$(rand_secret)"
  [[ -n "${MEOWVPN_BOT_SERVICE_SECRET:-}" ]] || MEOWVPN_BOT_SERVICE_SECRET="$(rand_secret)"
  [[ -n "${MEOWVPN_TELEGRAM_WEBHOOK_SECRET:-}" ]] || MEOWVPN_TELEGRAM_WEBHOOK_SECRET="$(rand_secret)"
  [[ -n "${MEOWVPN_BALE_WEBHOOK_SECRET:-}" ]] || MEOWVPN_BALE_WEBHOOK_SECRET="$(rand_secret)"
  [[ -n "${MEOWVPN_RELAY_MASTER_SECRET:-}" ]] || MEOWVPN_RELAY_MASTER_SECRET="$(rand_secret)"
  [[ -n "${MEOWVPN_ADMIN_PASSWORD:-}" ]] || MEOWVPN_ADMIN_PASSWORD="$(rand_hex 8)"
  save_state_kv "MEOWVPN_APP_KEY" "$MEOWVPN_APP_KEY"
  save_state_kv "MEOWVPN_DB_PASSWORD" "$MEOWVPN_DB_PASSWORD"
  save_state_kv "MEOWVPN_DB_ROOT_PASSWORD" "$MEOWVPN_DB_ROOT_PASSWORD"
  save_state_kv "MEOWVPN_BOT_SERVICE_SECRET" "$MEOWVPN_BOT_SERVICE_SECRET"
  save_state_kv "MEOWVPN_TELEGRAM_WEBHOOK_SECRET" "$MEOWVPN_TELEGRAM_WEBHOOK_SECRET"
  save_state_kv "MEOWVPN_BALE_WEBHOOK_SECRET" "$MEOWVPN_BALE_WEBHOOK_SECRET"
  save_state_kv "MEOWVPN_RELAY_MASTER_SECRET" "$MEOWVPN_RELAY_MASTER_SECRET"
  save_state_kv "MEOWVPN_ADMIN_PASSWORD" "$MEOWVPN_ADMIN_PASSWORD"
}

init_backend_env() {
  ensure_secrets
  load_domain_state
  local env_file="$BACKEND_DIR/.env"
  if [[ ! -f "$env_file" ]]; then
    cp "$BACKEND_DIR/.env.example" "$env_file"
  fi
  local core_url
  if defer_domains_enabled && [[ -n "${SVP_HTTP_PORT:-}" ]]; then
    core_url="$(public_url_for_service "${CORE_HOST:-$(detect_server_ip)}" "$SVP_HTTP_PORT")"
  else
    core_url="$(public_url_for_host "${CORE_HOST:-$(detect_server_ip)}" "$(host_use_ssl "${CORE_HOST:-}" && echo 1 || echo 0)")"
  fi
  local apex
  apex="$(apex_domain "${CORE_HOST:-}")"
  local sanctum="${DASHBOARD_HOST:-}"
  if defer_domains_enabled && [[ -n "${SVP_HTTP_PORT:-}" ]]; then
    sanctum="${SERVER_IP:-$(detect_server_ip)}:${SVP_HTTP_PORT}"
  elif [[ -n "$sanctum" && "$sanctum" != "${CORE_HOST:-}" ]]; then
    sanctum="${sanctum},${CORE_HOST}"
  fi
  sanctum="${sanctum:-${CORE_HOST:-localhost}}"

  set_env_var "$env_file" "APP_ENV" "production"
  set_env_var "$env_file" "APP_KEY" "$MEOWVPN_APP_KEY"
  set_env_var "$env_file" "APP_URL" "$core_url"
  set_env_var "$env_file" "DB_PASSWORD" "$MEOWVPN_DB_PASSWORD"
  set_env_var "$env_file" "DB_ROOT_PASSWORD" "$MEOWVPN_DB_ROOT_PASSWORD"
  set_env_var "$env_file" "SVP_ADMIN_USERNAME" "admin"
  set_env_var "$env_file" "SVP_ADMIN_PASSWORD" "$MEOWVPN_ADMIN_PASSWORD"
  set_env_var "$env_file" "SVP_BOT_SERVICE_SECRET" "$MEOWVPN_BOT_SERVICE_SECRET"
  set_env_var "$env_file" "SVP_LEGACY_WEBHOOK_ON_BACKEND" "false"
  set_env_var "$env_file" "SVP_RATE_LIMIT_TRUST_FORWARDED_FOR" "true"
  set_env_var "$env_file" "SVP_TELEGRAM_WEBHOOK_SECRET" "$MEOWVPN_TELEGRAM_WEBHOOK_SECRET"
  set_env_var "$env_file" "SVP_BALE_WEBHOOK_SECRET" "$MEOWVPN_BALE_WEBHOOK_SECRET"
  if [[ -n "${RELAY_HOST:-}" ]] && ! defer_domains_enabled; then
    local relay_url
    relay_url="$(public_url_for_host "$RELAY_HOST" "$(host_use_ssl "$RELAY_HOST" && echo 1 || echo 0)")"
    set_env_var "$env_file" "SVP_MODULE_RELAY" "true"
    set_env_var "$env_file" "SVP_RELAY_SHARED_SECRET" "$MEOWVPN_RELAY_MASTER_SECRET"
    set_env_var "$env_file" "SVP_RELAY_PUBLIC_URL" "$relay_url"
    set_env_var "$env_file" "SVP_RELAY_ADMIN_URL" "$relay_url"
  fi
  if [[ -n "$apex" ]]; then
    set_env_var "$env_file" "SESSION_DOMAIN" "$apex"
    set_env_var "$env_file" "SESSION_SECURE_COOKIE" "true"
  else
    set_env_var "$env_file" "SESSION_DOMAIN" "null"
    set_env_var "$env_file" "SESSION_SECURE_COOKIE" "false"
  fi
  set_env_var "$env_file" "SANCTUM_STATEFUL_DOMAINS" "$sanctum"
  chmod 600 "$env_file"
  log "Wrote $env_file"
}

init_telegram_bot_env() {
  ensure_secrets
  local env_file="$REPO_ROOT/telegram_bot/.env"
  local backend_url="${CORE_URL:-}"
  if [[ -z "$backend_url" ]]; then
    backend_url="$(public_url_for_host "${CORE_HOST:-$(detect_server_ip)}" "$(host_use_ssl "${CORE_HOST:-}" && echo 1 || echo 0)")"
  fi
  cat >"$env_file" <<EOF
BACKEND_URL=${backend_url}
SVP_BOT_SERVICE_SECRET=${MEOWVPN_BOT_SERVICE_SECRET}
TELEGRAM_WEBHOOK_SECRET=${MEOWVPN_TELEGRAM_WEBHOOK_SECRET}
TELEGRAM_SECRET_HEADER=
EOF
  chmod 600 "$env_file"
}

init_bale_bot_env() {
  ensure_secrets
  local env_file="$REPO_ROOT/bale_bot/.env"
  local backend_url="${CORE_URL:-}"
  if [[ -z "$backend_url" ]]; then
    backend_url="$(public_url_for_host "${CORE_HOST:-$(detect_server_ip)}" "$(host_use_ssl "${CORE_HOST:-}" && echo 1 || echo 0)")"
  fi
  cat >"$env_file" <<EOF
BACKEND_URL=${backend_url}
SVP_BOT_SERVICE_SECRET=${MEOWVPN_BOT_SERVICE_SECRET}
BALE_WEBHOOK_SECRET=${MEOWVPN_BALE_WEBHOOK_SECRET}
EOF
  chmod 600 "$env_file"
}

export_compose_env() {
  # shellcheck disable=SC1091
  set -a
  [[ -f "$BACKEND_DIR/.env" ]] && source "$BACKEND_DIR/.env"
  set +a
  export DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-${MEOWVPN_DB_ROOT_PASSWORD:-secret}}"
  export SVP_BOT_SERVICE_SECRET="${SVP_BOT_SERVICE_SECRET:-${MEOWVPN_BOT_SERVICE_SECRET:-}}"
  export TELEGRAM_WEBHOOK_SECRET="${TELEGRAM_WEBHOOK_SECRET:-${MEOWVPN_TELEGRAM_WEBHOOK_SECRET:-}}"
  export BALE_WEBHOOK_SECRET="${BALE_WEBHOOK_SECRET:-${MEOWVPN_BALE_WEBHOOK_SECRET:-}}"
  export RELAY_MASTER_SECRET="${RELAY_MASTER_SECRET:-${MEOWVPN_RELAY_MASTER_SECRET:-changeme}}"
  export BACKEND_URL="${BACKEND_URL:-http://web}"
}
