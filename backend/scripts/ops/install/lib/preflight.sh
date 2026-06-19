#!/usr/bin/env bash
# MeowVPN install — preflight checks.
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/system.sh"

check_os() {
  [[ -f /etc/os-release ]] || die "Cannot detect OS (/etc/os-release missing)"
  # shellcheck disable=SC1091
  source /etc/os-release
  local id="${ID:-unknown}"
  local ver="${VERSION_ID:-0}"
  case "$id" in
    ubuntu)
      if awk "BEGIN { exit !($ver >= 22.04) }" 2>/dev/null; then
        log "OS OK: Ubuntu ${ver}"
      else
        warn "Ubuntu ${ver} detected — Ubuntu 22.04+ recommended"
      fi
      ;;
    debian)
      if awk "BEGIN { exit !($ver >= 12) }" 2>/dev/null; then
        log "OS OK: Debian ${ver}"
      else
        warn "Debian ${ver} detected — Debian 12+ recommended"
      fi
      ;;
    *)
      warn "Untested OS: ${PRETTY_NAME:-$id} — Ubuntu 22.04+ or Debian 12+ recommended"
      ;;
  esac
}

check_arch() {
  local arch
  arch="$(dpkg --print-architecture 2>/dev/null || uname -m)"
  case "$arch" in
    amd64|arm64|aarch64) log "Architecture OK: $arch" ;;
    *) die "Unsupported architecture: $arch (need amd64 or arm64)" ;;
  esac
}

check_internet() {
  log "Checking network connectivity..."
  retry 5 10 -- curl -fsS --max-time 15 https://download.docker.com >/dev/null \
    || die "Cannot reach download.docker.com — check network/DNS/firewall"
  retry 5 10 -- curl -fsS --max-time 15 https://github.com >/dev/null \
    || die "Cannot reach github.com — check network/DNS/firewall"
  log "Network OK"
}

check_disk() {
  local free_kb
  free_kb="$(df -Pk /var 2>/dev/null | awk 'NR==2 {print $4}')"
  local free_gb=$((free_kb / 1024 / 1024))
  if (( free_gb < 5 )); then
    die "Need at least 5 GB free on /var (found ~${free_gb} GB). Expand disk or clean /var."
  fi
  log "Disk OK: ~${free_gb} GB free on /var"
}

ensure_swap() {
  local mem_kb swap_kb
  mem_kb="$(grep -E '^MemTotal:' /proc/meminfo | awk '{print $2}')"
  swap_kb="$(grep -E '^SwapTotal:' /proc/meminfo | awk '{print $2}')"
  if (( mem_kb >= 2097152 || swap_kb > 0 )); then
    log "Memory/swap OK (RAM=$((mem_kb/1024))MB swap=$((swap_kb/1024))MB)"
    return 0
  fi
  log "Low RAM (${mem_kb}KB) and no swap — creating 2G /swapfile..."
  if [[ -f /swapfile ]]; then
    swapon /swapfile 2>/dev/null || true
    return 0
  fi
  fallocate -l 2G /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=progress
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  if ! grep -q '^/swapfile ' /etc/fstab 2>/dev/null; then
    echo '/swapfile none swap sw 0 0' >>/etc/fstab
  fi
  log "Swap enabled: 2G /swapfile"
}

ensure_time_sync() {
  if systemctl is-active systemd-timesyncd >/dev/null 2>&1; then
    systemctl enable systemd-timesyncd 2>/dev/null || true
    systemctl start systemd-timesyncd 2>/dev/null || true
    log "Time sync: systemd-timesyncd"
    return 0
  fi
  if command -v chronyc >/dev/null 2>&1; then
    systemctl enable chrony 2>/dev/null || systemctl enable chronyd 2>/dev/null || true
    systemctl start chrony 2>/dev/null || systemctl start chronyd 2>/dev/null || true
    log "Time sync: chrony"
    return 0
  fi
  apt_install systemd-timesyncd 2>/dev/null || true
  systemctl enable systemd-timesyncd 2>/dev/null || true
  systemctl start systemd-timesyncd 2>/dev/null || true
  log "Time sync: installed systemd-timesyncd"
}

check_ports() {
  local port proc
  for port in 80 443; do
    if ss -tlnp 2>/dev/null | grep -q ":${port} "; then
      proc="$(ss -tlnp 2>/dev/null | grep ":${port} " | head -1 || true)"
      if [[ "$proc" != *nginx* ]]; then
        warn "Port ${port} already in use (not nginx): ${proc}"
      fi
    fi
  done
}

preflight_all() {
  require_root
  check_os
  check_arch
  check_internet
  check_disk
  ensure_swap
  ensure_time_sync
  check_ports
  log "Preflight checks passed"
}
