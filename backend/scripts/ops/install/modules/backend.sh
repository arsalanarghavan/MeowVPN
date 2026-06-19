#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/../lib/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/prompts.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/docker.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/env.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/nginx.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/backend.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/webhooks.sh"

module_backend() {
  collect_single_domain CORE_HOST "Enter Dashboard Core Domain:"
  progress "Configuring backend environment"
  init_backend_env
  export_compose_env

  local core_url
  core_url="$(public_url_for_host "$CORE_HOST" "$(host_use_ssl "$CORE_HOST" && echo 1 || echo 0)")"

  progress "Configuring nginx (core)"
  install_nginx_core
  progress "Starting Docker services"
  compose_up "" workers
  progress "Waiting for API health"
  wait_for_health "http://127.0.0.1:8080/health/ready" || true
  progress "Running migrations and seed"
  backend_post_install 1
  progress "Applying install settings"
  apply_install_settings "$core_url"
  progress "Running smoke tests"
  run_smoke_tests "$core_url"
  progress "Initializing setup wizard"
  init_install_wizard "$core_url"
  print_admin_credentials
  print_ssl_renew_hint
  progress "Verifying installation"
  verify_install
  log "Install Dashboard Backend complete."
}

module_backend "$@"
