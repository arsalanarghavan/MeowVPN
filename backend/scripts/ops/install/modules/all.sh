#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/../lib/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/prompts.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/docker.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/env.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/nginx.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/backend.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/webhooks.sh"

module_all() {
  collect_all_domains
  init_backend_env
  init_telegram_bot_env
  init_bale_bot_env
  export_compose_env

  local core_url dash_url tg_url bale_url relay_url
  core_url="$(public_url_for_host "$CORE_HOST" "$(host_use_ssl "$CORE_HOST" && echo 1 || echo 0)")"
  dash_url="$(public_url_for_host "$DASHBOARD_HOST" "$(host_use_ssl "$DASHBOARD_HOST" && echo 1 || echo 0)")"
  tg_url="$(public_url_for_host "$TELEGRAM_HOST" "$(host_use_ssl "$TELEGRAM_HOST" && echo 1 || echo 0)")"
  bale_url="$(public_url_for_host "$BALE_HOST" "$(host_use_ssl "$BALE_HOST" && echo 1 || echo 0)")"
  relay_url="$(public_url_for_host "$RELAY_HOST" "$(host_use_ssl "$RELAY_HOST" && echo 1 || echo 0)")"

  local vite_api="${core_url}/api/v1"
  build_frontend "$vite_api"

  install_nginx_core
  install_nginx_dashboard
  install_nginx_telegram
  install_nginx_bale
  install_nginx_relay

  compose_up "" workers frontend telegram bale relay
  wait_for_health "http://127.0.0.1:8080/health/ready" || true

  backend_post_install 1
  apply_install_settings "$core_url" "$dash_url" "$tg_url" "$bale_url" "$relay_url" 1
  register_webhooks both

  run_smoke_tests "$core_url" "$dash_url"
  smoke_curl "telegram bot" "${tg_url}/health"
  smoke_curl "bale bot" "${bale_url}/health"
  print_admin_credentials
  print_ssl_renew_hint
  verify_install
  log "Install All complete."
}

module_all "$@"
