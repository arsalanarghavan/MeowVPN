#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/../lib/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/prompts.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/docker.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/env.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/nginx.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/backend.sh"

module_bale() {
  load_domain_state
  CORE_URL="${CORE_URL:-}"
  if [[ -z "$CORE_URL" ]]; then
    CORE_URL="$(prompt_core_url "")"
    [[ -n "$CORE_URL" ]] || die "Dashboard Core URL required (BACKEND_URL)"
    save_state_kv "CORE_URL" "$CORE_URL"
  fi

  collect_single_domain BALE_HOST "Enter Bale Bot Domain:"
  SVP_INSTALL_BOTS_STANDALONE=1
  export SVP_INSTALL_BOTS_STANDALONE
  init_bale_bot_env
  export_compose_env

  install_nginx_bale
  compose_up bale
  wait_for_health "http://127.0.0.1:8092/health" || true

  local bale_url
  bale_url="$(public_url_for_host "$BALE_HOST" "$(host_use_ssl "$BALE_HOST" && echo 1 || echo 0)")"
  smoke_curl "bale bot" "${bale_url}/health"
  print_ssl_renew_hint
  log "Install Bale Bot complete. Bale webhook base: ${bale_url}"
}

module_bale "$@"
