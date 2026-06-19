#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/../lib/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/prompts.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/ssl.sh"
source "$(dirname "${BASH_SOURCE[0]}")/../lib/backend.sh"

module_relay() {
  load_domain_state
  if [[ -z "${RELAY_HOST:-}" || "$RELAY_HOST" == "${SERVER_IP:-}" ]]; then
    collect_single_domain RELAY_HOST "Enter Relay Domain:"
    save_state_kv "RELAY_HOST" "$RELAY_HOST"
  fi

  local relay_install="$REPO_ROOT/relay-server/scripts/install.sh"
  [[ -f "$relay_install" ]] || die "Missing relay install script: $relay_install"

  local -a args=()
  if is_valid_hostname "$RELAY_HOST"; then
    args+=(--domain "$RELAY_HOST" --ssl "${SSL_METHOD:-certbot}")
    [[ -n "${SSL_EMAIL:-}" ]] && args+=(--email "$SSL_EMAIL")
  else
    warn "Relay domain is IP — relay install uses self-signed admin SSL"
  fi

  progress "Installing relay server"
  bash "$relay_install" "${args[@]}"
  progress "Verifying installation"
  verify_install
  log "Install Relay complete."
}

module_relay "$@"
