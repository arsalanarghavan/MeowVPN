#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/../lib/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/prompts.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/docker.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/env.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/nginx.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/backend.sh"

module_telegram() {
  load_domain_state
  CORE_URL="${CORE_URL:-}"
  if [[ -z "$CORE_URL" ]]; then
    CORE_URL="$(prompt_core_url "")"
    [[ -n "$CORE_URL" ]] || die "Dashboard Core URL required (BACKEND_URL)"
    save_state_kv "CORE_URL" "$CORE_URL"
  fi
  CORE_HOST="${CORE_HOST:-$(echo "$CORE_URL" | sed -E 's#^https?://##')}"

  collect_single_domain TELEGRAM_HOST "Enter Telegram Bot Domain:"
  SVP_INSTALL_BOTS_STANDALONE=1
  export SVP_INSTALL_BOTS_STANDALONE
  init_telegram_bot_env
  export_compose_env

  install_nginx_telegram
  compose_up telegram
  wait_for_health "http://127.0.0.1:8091/health" || true

  local tg_url
  tg_url="$(public_url_for_host "$TELEGRAM_HOST" "$(host_use_ssl "$TELEGRAM_HOST" && echo 1 || echo 0)")"
  smoke_curl "telegram bot" "${tg_url}/health"
  print_ssl_renew_hint
  verify_install
  log "Install Telegram Bot complete. Point relay forward URL to: ${tg_url}"
}

module_telegram "$@"
