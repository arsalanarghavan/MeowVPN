#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/../lib/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/prompts.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/docker.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/env.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/nginx.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/backend.sh"

module_frontend() {
  load_domain_state
  if [[ -z "${CORE_HOST:-}" || "$CORE_HOST" == "$(detect_server_ip)" ]]; then
    CORE_URL="$(prompt_core_url "https://api.example.com")"
    if [[ -n "$CORE_URL" ]]; then
      save_state_kv "CORE_URL" "$CORE_URL"
    else
      die "Dashboard Core URL required for frontend build (API base)"
    fi
  else
    CORE_URL="$(public_url_for_host "$CORE_HOST" "$(host_use_ssl "$CORE_HOST" && echo 1 || echo 0)")"
  fi

  collect_single_domain DASHBOARD_HOST "Enter Dashboard Domain:"
  export_compose_env

  build_frontend "${CORE_URL}/api/v1"
  install_nginx_dashboard
  compose_up frontend
  wait_for_health "http://127.0.0.1:3001/" || true

  local dash_url
  dash_url="$(public_url_for_host "$DASHBOARD_HOST" "$(host_use_ssl "$DASHBOARD_HOST" && echo 1 || echo 0)")"
  smoke_curl "dashboard" "${dash_url}/"
  print_ssl_renew_hint
  log "Install Dashboard Frontend complete."
}

module_frontend "$@"
