#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/../lib/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/prompts.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/docker.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/env.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/nginx.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/backend.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/webhooks.sh"

module_dashboard() {
  collect_dashboard_domains
  init_backend_env
  export_compose_env

  local core_url dash_url
  core_url="$(public_url_for_host "$CORE_HOST" "$(host_use_ssl "$CORE_HOST" && echo 1 || echo 0)")"
  dash_url="$(public_url_for_host "$DASHBOARD_HOST" "$(host_use_ssl "$DASHBOARD_HOST" && echo 1 || echo 0)")"

  build_frontend "${core_url}/api/v1"
  install_nginx_core
  install_nginx_dashboard

  compose_up "" workers frontend
  wait_for_health "http://127.0.0.1:8080/health/ready" || true
  backend_post_install 1
  apply_install_settings "$core_url" "$dash_url"
  run_smoke_tests "$core_url" "$dash_url"
  init_install_wizard "$core_url" "$dash_url"
  print_admin_credentials
  print_ssl_renew_hint
  verify_install
  log "Install Dashboard complete."
}

module_dashboard "$@"
