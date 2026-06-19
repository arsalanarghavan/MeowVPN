#!/usr/bin/env bash
# MeowVPN install — system helpers (logging, apt, retry).
# shellcheck disable=SC2034
MEOWVPN_INSTALL_LOG="${MEOWVPN_INSTALL_LOG:-}"

setup_logging() {
  local log_dir="${STATE_DIR:-/tmp}"
  mkdir -p "$log_dir"
  MEOWVPN_INSTALL_LOG="${log_dir}/install.log"
  exec > >(tee -a "$MEOWVPN_INSTALL_LOG") 2>&1
  echo "=== MeowVPN install $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
  log "Logging to $MEOWVPN_INSTALL_LOG"
}

install_err_trap() {
  set -Eeuo pipefail
  trap 'install_on_error $LINENO "$BASH_COMMAND"' ERR
}

install_on_error() {
  local line="$1"
  local cmd="$2"
  echo "[meowvpn-install] ERROR: command failed at line ${line}: ${cmd}" >&2
  [[ -n "${MEOWVPN_INSTALL_LOG:-}" ]] && echo "[meowvpn-install] See log: ${MEOWVPN_INSTALL_LOG}" >&2
  exit 1
}

# retry <tries> <sleep_sec> -- <command...>
retry() {
  local tries="$1"
  local sleep_sec="$2"
  shift 2
  [[ "$1" == "--" ]] && shift
  local attempt=1
  while (( attempt <= tries )); do
    if "$@"; then
      return 0
    fi
    if (( attempt < tries )); then
      warn "Retry ${attempt}/${tries} failed; sleeping ${sleep_sec}s..."
      sleep "$sleep_sec"
    fi
    attempt=$((attempt + 1))
  done
  return 1
}

apt_lock_wait() {
  local max_wait="${1:-300}"
  local waited=0
  while (( waited < max_wait )); do
    if ! fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 \
      && ! fuser /var/lib/apt/lists/lock >/dev/null 2>&1 \
      && ! pgrep -x unattended-upgr >/dev/null 2>&1; then
      return 0
    fi
    log "Waiting for apt lock (${waited}s/${max_wait}s)..."
    sleep 5
    waited=$((waited + 5))
  done
  die "apt lock held for ${max_wait}s — try again after unattended-upgrades finishes"
}

apt_update() {
  apt_lock_wait
  export DEBIAN_FRONTEND=noninteractive
  export NEEDRESTART_MODE=a
  retry 5 10 -- apt-get update -qq
}

apt_install() {
  [[ $# -gt 0 ]] || return 0
  apt_lock_wait
  export DEBIAN_FRONTEND=noninteractive
  export NEEDRESTART_MODE=a
  retry 3 10 -- apt-get install -y --no-install-recommends "$@"
}

download() {
  local url="$1"
  local dest="$2"
  retry 5 10 -- curl -fsSL --retry 3 --retry-delay 5 --max-time 120 "$url" -o "$dest"
}
