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

_APT_AUTO_PAUSED=0
_APT_KILL_TRIED=0
_APT_DPKG_CONFIGURE_TRIED=0

pause_apt_auto_services() {
  if [[ "$_APT_AUTO_PAUSED" == "1" ]]; then
    return 0
  fi
  _APT_AUTO_PAUSED=1
  log "Pausing automatic apt services (unattended-upgrades, apt-daily)..."
  systemctl stop unattended-upgrades.service 2>/dev/null || true
  systemctl disable --now unattended-upgrades.service 2>/dev/null || true
  systemctl stop apt-daily.service apt-daily-upgrade.service 2>/dev/null || true
  systemctl stop apt-daily.timer apt-daily-upgrade.timer 2>/dev/null || true
  systemctl mask apt-daily.timer apt-daily-upgrade.timer 2>/dev/null || true
}

apt_lock_busy() {
  local lock
  for lock in \
    /var/lib/dpkg/lock \
    /var/lib/dpkg/lock-frontend \
    /var/lib/apt/lists/lock \
    /var/cache/apt/archives/lock; do
    if [[ -e "$lock" ]] && fuser "$lock" >/dev/null 2>&1; then
      return 0
    fi
  done
  if pgrep -x unattended-upgr >/dev/null 2>&1; then
    return 0
  fi
  if pgrep -x apt-get >/dev/null 2>&1 || pgrep -x apt >/dev/null 2>&1 || pgrep -x dpkg >/dev/null 2>&1; then
    return 0
  fi
  return 1
}

log_apt_lock_holders() {
  local lock
  for lock in /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock; do
    if [[ -e "$lock" ]]; then
      local holders
      holders="$(fuser -v "$lock" 2>&1 | tail -n +2 || true)"
      if [[ -n "$holders" ]]; then
        warn "Lock holder on $lock: $(echo "$holders" | tr '\n' ' ' | head -c 200)"
      fi
    fi
  done
  if pgrep -xa unattended-upgr >/dev/null 2>&1; then
    warn "unattended-upgrades still running: $(pgrep -xa unattended-upgr | head -1)"
  fi
}

prepare_apt_for_install() {
  pause_apt_auto_services
}

apt_lock_wait() {
  local max_wait="${1:-600}"
  local waited=0
  pause_apt_auto_services
  while (( waited < max_wait )); do
    if ! apt_lock_busy; then
      return 0
    fi
    if (( waited > 0 && waited % 60 == 0 )); then
      pause_apt_auto_services
      log_apt_lock_holders
    fi
    if (( waited >= 120 && _APT_KILL_TRIED == 0 )); then
      _APT_KILL_TRIED=1
      warn "Sending SIGTERM to unattended-upgrades after ${waited}s..."
      systemctl kill unattended-upgrades.service 2>/dev/null || true
      sleep 5
    fi
    if (( waited >= 180 && _APT_DPKG_CONFIGURE_TRIED == 0 )); then
      _APT_DPKG_CONFIGURE_TRIED=1
      warn "Running dpkg --configure -a after ${waited}s..."
      DEBIAN_FRONTEND=noninteractive dpkg --configure -a 2>/dev/null || true
      sleep 5
    fi
    log "Waiting for apt lock (${waited}s/${max_wait}s)..."
    sleep 5
    waited=$((waited + 5))
  done
  log_apt_lock_holders
  die "apt lock still held after ${max_wait}s. A background update may be running.
  Check:  sudo lsof /var/lib/dpkg/lock-frontend
  Then re-run the installer (install state in backend/.install is preserved)."
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
