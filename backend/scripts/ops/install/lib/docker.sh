#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

ensure_docker() {
  if ! command -v docker >/dev/null 2>&1; then
    log "Installing Docker..."
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y ca-certificates curl
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc 2>/dev/null \
      || curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    local distro
    distro="$(. /etc/os-release && echo "${ID}")"
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${distro} $(. /etc/os-release && echo "${VERSION_CODENAME}") stable" \
      > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
  fi
  systemctl enable docker
  systemctl start docker
}

ensure_prereqs() {
  require_root
  ensure_docker
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y openssl curl whiptail 2>/dev/null || true
}

compose_up() {
  local -a profiles=("$@")
  local -a args=(up -d --build)
  local p
  for p in "${profiles[@]}"; do
    [[ -n "$p" ]] && args+=(--profile "$p")
  done
  log "docker compose ${args[*]}"
  (cd "$BACKEND_DIR" && compose_cmd "${args[@]}")
}

wait_for_health() {
  local url="$1"
  local tries="${2:-60}"
  local i
  for ((i = 1; i <= tries; i++)); do
    if curl -fsS "$url" >/dev/null 2>&1; then
      log "Health OK: $url"
      return 0
    fi
    sleep 2
  done
  warn "Health check timed out: $url"
  return 1
}

build_frontend() {
  local api_base="${1:-/api/v1}"
  log "Building frontend (VITE_API_BASE=$api_base)..."
  (cd "$REPO_ROOT/frontend" && npm ci && VITE_API_BASE="$api_base" npm run build)
}

build_frontend_docker() {
  local api_base="${1:-/api/v1}"
  log "Building frontend image (VITE_API_BASE=$api_base)..."
  (cd "$BACKEND_DIR" && VITE_API_BASE="$api_base" compose_cmd build frontend)
}
