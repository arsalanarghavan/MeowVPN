#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

apply_install_settings() {
  ensure_secrets
  local core_url="$1"
  local dashboard_url="${2:-}"
  local telegram_url="${3:-}"
  local bale_url="${4:-}"
  local relay_url="${5:-}"
  local enable_relay="${6:-0}"

  local -a args=(--core-url="$core_url")
  [[ -n "$dashboard_url" ]] && args+=(--dashboard-url="$dashboard_url")
  [[ -n "$telegram_url" ]] && args+=(--telegram-url="$telegram_url")
  [[ -n "$bale_url" ]] && args+=(--bale-url="$bale_url")
  [[ -n "$relay_url" ]] && args+=(--relay-url="$relay_url")
  args+=(--telegram-webhook-secret="${MEOWVPN_TELEGRAM_WEBHOOK_SECRET}")
  args+=(--bale-webhook-secret="${MEOWVPN_BALE_WEBHOOK_SECRET}")
  args+=(--relay-shared-secret="${MEOWVPN_RELAY_MASTER_SECRET}")
  [[ "$enable_relay" == "1" ]] && args+=(--enable-relay)

  log "Applying install settings to database..."
  compose_cmd exec -T app php artisan svp:install-apply-settings "${args[@]}"
}

register_webhooks() {
  local platform="${1:-both}"
  log "Registering webhooks ($platform)..."
  compose_cmd exec -T app php artisan svp:register-webhooks --platform="$platform" || warn "Webhook registration failed (configure bot tokens in dashboard)"
}
