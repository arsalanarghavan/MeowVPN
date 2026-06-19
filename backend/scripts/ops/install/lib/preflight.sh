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

port_listener_line() {
  local port="$1"
  ss -tlnp 2>/dev/null | grep -E ":${port} " | head -1 || true
}

proc_name_from_ss() {
  local line="$1"
  sed -n 's/.*users:((("\([^"]*\)".*/\1/p' <<<"$line"
}

is_nginx_listener() {
  local line="$1"
  [[ "$line" == *nginx* ]]
}

services_for_proc() {
  local proc="$1"
  case "$proc" in
    litespeed|lshttpd|lsws|openlitespeed)
      printf '%s\n' lsws openlitespeed litespeed lshttpd
      ;;
    apache2|httpd)
      printf '%s\n' apache2 httpd
      ;;
    caddy)
      printf '%s\n' caddy
      ;;
    *)
      printf '%s\n' "$proc"
      ;;
  esac
}

friendly_proc_label() {
  local proc="$1"
  case "$proc" in
    litespeed|lshttpd|lsws|openlitespeed) echo "LiteSpeed / OpenLiteSpeed" ;;
    apache2|httpd) echo "Apache" ;;
    caddy) echo "Caddy" ;;
    *) echo "$proc" ;;
  esac
}

systemd_unit_exists() {
  local unit="$1"
  systemctl list-unit-files "${unit}.service" 2>/dev/null | grep -q "^${unit}\.service"
}

stop_disable_service_unit() {
  local unit="$1"
  if systemd_unit_exists "$unit"; then
    systemctl stop "${unit}.service" 2>/dev/null || true
    systemctl disable "${unit}.service" 2>/dev/null || true
    log "Stopped and disabled ${unit}.service"
    return 0
  fi
  return 1
}

stop_disable_proc() {
  local proc="$1"
  local svc stopped=0
  while IFS= read -r svc; do
    [[ -n "$svc" ]] || continue
    if stop_disable_service_unit "$svc"; then
      stopped=1
    fi
  done < <(services_for_proc "$proc")

  if [[ -x "/etc/init.d/${proc}" ]]; then
    "/etc/init.d/${proc}" stop 2>/dev/null || true
    update-rc.d -f "$proc" remove 2>/dev/null || true
    log "Stopped init.d service: $proc"
    stopped=1
  fi

  if (( stopped == 0 )); then
    warn "Could not find a systemd unit for process '$proc' — trying SIGTERM"
    pkill -TERM -x "$proc" 2>/dev/null || pkill -TERM -f "$proc" 2>/dev/null || true
    sleep 2
  fi
}

port_still_blocked() {
  local port="$1"
  local line proc
  line="$(port_listener_line "$port")"
  [[ -n "$line" ]] || return 1
  is_nginx_listener "$line" && return 1
  return 0
}

is_install_interactive() {
  [[ "${NON_INTERACTIVE:-0}" != "1" ]] \
    && [[ "${MEOWVPN_NON_INTERACTIVE:-0}" != "1" ]] \
    && [[ -t 0 && -t 1 ]]
}

prompt_stop_conflicting_webserver() {
  local proc="$1"
  local ports="$2"
  local label
  label="$(friendly_proc_label "$proc")"
  apply_purple_theme
  if command -v whiptail >/dev/null 2>&1; then
    whiptail --backtitle "MeowVPN" --title "Port conflict" --yesno \
      "Ports ${ports} are in use by ${label} (${proc}).\n\nMeowVPN needs ports 80/443 for nginx and SSL certificates.\n\nStop and disable ${label} now?" \
      14 72
    return $?
  fi
  read -r -p "Ports ${ports} in use by ${label}. Stop it now? [y/N]: " ans
  [[ "${ans,,}" == "y" || "${ans,,}" == "yes" ]]
}

resolve_port_conflicts() {
  local -a blocked_ports=()
  local -A seen_procs=()
  local port line proc label ports_csv

  for port in 80 443; do
    line="$(port_listener_line "$port")"
    [[ -n "$line" ]] || continue
    if is_nginx_listener "$line"; then
      log "Port ${port} OK (nginx)"
      continue
    fi
    proc="$(proc_name_from_ss "$line")"
    [[ -n "$proc" ]] || proc="unknown"
    blocked_ports+=("$port")
    seen_procs["$proc"]=1
    label="$(friendly_proc_label "$proc")"
    warn "Port ${port} already in use (not nginx): ${label} — ${line}"
  done

  if [[ ${#blocked_ports[@]} -eq 0 ]]; then
    return 0
  fi

  if defer_domains_enabled; then
    warn "Bootstrap install — host ports ${blocked_ports[*]} in use; continuing without nginx on 80/443"
    return 0
  fi

  ports_csv="$(IFS=','; echo "${blocked_ports[*]}")"
  for proc in "${!seen_procs[@]}"; do
    label="$(friendly_proc_label "$proc")"

    if is_install_interactive; then
      if prompt_stop_conflicting_webserver "$proc" "$ports_csv"; then
        stop_disable_proc "$proc"
      else
        die "Port conflict: ${label} is using port(s) ${ports_csv}.
Stop it manually, for example:
  systemctl stop lsws && systemctl disable lsws
Then re-run the installer."
      fi
    elif [[ "${MEOWVPN_TAKEOVER_PORTS:-0}" == "1" ]]; then
      log "MEOWVPN_TAKEOVER_PORTS=1 — stopping ${label}"
      stop_disable_proc "$proc"
    else
      die "Port conflict: ${label} is using port(s) ${ports_csv}.
MeowVPN requires ports 80/443 for nginx and SSL.
Stop the conflicting web server manually, or re-run with:
  MEOWVPN_TAKEOVER_PORTS=1 bash <(curl -fsSL .../install.sh)"
    fi
  done

  sleep 1
  for port in 80 443; do
    if port_still_blocked "$port"; then
      line="$(port_listener_line "$port")"
      proc="$(proc_name_from_ss "$line")"
      die "Port ${port} is still in use by ${proc:-unknown} after stop attempt.
Free the port manually and re-run the installer."
    fi
  done
  log "Port conflicts resolved"
}

check_ports() {
  resolve_port_conflicts
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
