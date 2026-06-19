#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/system.sh"
source "$(dirname "${BASH_SOURCE[0]}")/docker.sh"
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

backend_post_install() {
  local run_seed="${1:-1}"
  wait_for_db
  log "Running migrations..."
  compose_cmd exec -T app php artisan key:generate --force 2>/dev/null || true
  compose_cmd exec -T app php artisan migrate --force
  if [[ "$run_seed" == "1" ]]; then
    compose_cmd exec -T app php artisan db:seed --class=AdminUserSeeder --force
  fi
  compose_cmd exec -T app php artisan svp:rebuild-reseller-closure || true
}

smoke_curl() {
  local label="$1"
  local url="$2"
  if curl -fsS "$url" >/dev/null; then
    log "Smoke OK: $label ($url)"
    return 0
  else
    warn "Smoke FAIL: $label ($url)"
    return 1
  fi
}

run_smoke_tests() {
  local core_url="${1:-}"
  local dash_url="${2:-}"
  [[ -n "$core_url" ]] && smoke_curl "core health" "${core_url}/health/ready" || true
  [[ -n "$dash_url" ]] && smoke_curl "dashboard" "${dash_url}/" || true
}

print_admin_credentials() {
  load_state
  log "Dashboard admin: user=admin password=${MEOWVPN_ADMIN_PASSWORD:-see backend/.env SVP_ADMIN_PASSWORD}"
}

verify_install() {
  log "=== Install verification ==="
  docker --version 2>/dev/null || warn "docker missing"
  docker compose version 2>/dev/null || warn "docker compose missing"
  nginx -v 2>&1 || warn "nginx missing"
  command -v certbot >/dev/null && certbot --version 2>/dev/null || true
  if [[ -d "$BACKEND_DIR" ]]; then
    (cd "$BACKEND_DIR" && compose_cmd ps 2>/dev/null) || true
  fi
  [[ -n "${MEOWVPN_INSTALL_LOG:-}" ]] && log "Full log: $MEOWVPN_INSTALL_LOG"
  log "=== Verification complete ==="
}
