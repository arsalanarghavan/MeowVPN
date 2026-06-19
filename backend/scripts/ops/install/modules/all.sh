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

  local core_url dash_url tg_url bale_url relay_url vite_api
  if defer_domains_enabled; then
    core_url="$(public_url_for_service "$SERVER_IP" "$SVP_HTTP_PORT")"
    dash_url="$core_url"
    tg_url=""
    bale_url=""
    relay_url=""
    vite_api="${core_url}/api/v1"
  else
    core_url="$(public_url_for_host "$CORE_HOST" "$(host_use_ssl "$CORE_HOST" && echo 1 || echo 0)")"
    dash_url="$(public_url_for_host "$DASHBOARD_HOST" "$(host_use_ssl "$DASHBOARD_HOST" && echo 1 || echo 0)")"
    tg_url="$(public_url_for_host "$TELEGRAM_HOST" "$(host_use_ssl "$TELEGRAM_HOST" && echo 1 || echo 0)")"
    bale_url="$(public_url_for_host "$BALE_HOST" "$(host_use_ssl "$BALE_HOST" && echo 1 || echo 0)")"
    relay_url="$(public_url_for_host "$RELAY_HOST" "$(host_use_ssl "$RELAY_HOST" && echo 1 || echo 0)")"
    vite_api="${core_url}/api/v1"
  fi

  progress "Initializing environment"
  init_backend_env
  init_telegram_bot_env
  init_bale_bot_env
  export_compose_env

  progress "Building frontend"
  build_frontend "$vite_api"

  if defer_domains_enabled; then
    log "Skipping host nginx (bootstrap install — configure domains in setup wizard)"
  else
    progress "Configuring nginx"
    install_nginx_core
    install_nginx_dashboard
    install_nginx_telegram
    install_nginx_bale
    install_nginx_relay
  fi

  progress "Starting all Docker services"
  if defer_domains_enabled; then
    compose_up "" workers telegram bale relay
    progress "Waiting for API health"
    wait_for_health "http://127.0.0.1:${SVP_HTTP_PORT}/health/ready" || true
  else
    compose_up "" workers frontend telegram bale relay
    progress "Waiting for API health"
    wait_for_health "http://127.0.0.1:8080/health/ready" || true
  fi

  progress "Running migrations and seed"
  backend_post_install 1
  progress "Applying install settings"
  apply_install_settings "$core_url" "$dash_url" "$tg_url" "$bale_url" "$relay_url" 1
  progress "Registering webhooks"
  register_webhooks both || true

  progress "Running smoke tests"
  if defer_domains_enabled; then
    run_smoke_tests "$core_url" "$dash_url"
    smoke_curl "local API" "http://127.0.0.1:${SVP_HTTP_PORT}/health/ready" || true
  else
    run_smoke_tests "$core_url" "$dash_url"
    smoke_curl "telegram bot" "${tg_url}/health"
    smoke_curl "bale bot" "${bale_url}/health"
  fi

  progress "Initializing setup wizard"
  init_install_wizard "$core_url" "$dash_url" "$tg_url" "$bale_url" "$relay_url"
  print_admin_credentials
  if ! defer_domains_enabled; then
    print_ssl_renew_hint
  fi
  progress "Verifying installation"
  verify_install
  if defer_domains_enabled; then
    log "Install All complete. Open ${dash_url}/setup/ (token printed above) to configure domains."
  else
    log "Install All complete."
  fi
}

module_all "$@"
