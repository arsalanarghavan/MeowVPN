#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/system.sh"
source "$(dirname "${BASH_SOURCE[0]}")/preflight.sh"

MEOWVPN_DOCKER_MIRROR="${MEOWVPN_DOCKER_MIRROR:-}"
MEOWVPN_DOCKER_MIRRORS="${MEOWVPN_DOCKER_MIRRORS:-https://docker.m.daocloud.io https://docker.1ms.run https://hub.rat.dev}"

docker_daemon_ready() {
  docker info >/dev/null 2>&1 && docker compose version >/dev/null 2>&1
}

docker_hub_reachable() {
  curl -fsS --max-time 10 "https://registry-1.docker.io/v2/" >/dev/null 2>&1
}

merge_docker_daemon_mirrors() {
  local -a mirrors=("$@")
  local daemon_file="/etc/docker/daemon.json"
  local mirrors_json
  mirrors_json="$(printf '%s\n' "${mirrors[@]}" | jq -R . | jq -s .)"
  mkdir -p /etc/docker
  if [[ -f "$daemon_file" ]]; then
    jq --argjson new "$mirrors_json" '
      .["registry-mirrors"] = ((.["registry-mirrors"] // []) + $new | unique)
      | .["max-concurrent-downloads"] = (.["max-concurrent-downloads"] // 2)
    ' "$daemon_file" >"${daemon_file}.tmp" && mv "${daemon_file}.tmp" "$daemon_file"
  else
    jq -n --argjson mirrors "$mirrors_json" \
      '{ "registry-mirrors": $mirrors, "max-concurrent-downloads": 2 }' >"$daemon_file"
  fi
}

restart_docker_daemon() {
  systemctl restart docker 2>/dev/null || service docker restart 2>/dev/null || true
  retry 10 3 -- docker_daemon_ready || die "Docker daemon not ready after registry configuration"
}

configure_docker_registry() {
  local -a mirrors=()
  local m

  if [[ -n "$MEOWVPN_DOCKER_MIRROR" ]]; then
    mirrors+=("$MEOWVPN_DOCKER_MIRROR")
    log "Using Docker registry mirror from MEOWVPN_DOCKER_MIRROR: $MEOWVPN_DOCKER_MIRROR"
  elif docker_hub_reachable; then
    log "Docker Hub reachable — no registry mirror needed"
    return 0
  else
    warn "Docker Hub unreachable — configuring registry mirrors automatically"
    for m in $MEOWVPN_DOCKER_MIRRORS; do
      [[ -n "$m" ]] && mirrors+=("$m")
    done
    if [[ ${#mirrors[@]} -eq 0 ]]; then
      warn "No default mirrors available — set MEOWVPN_DOCKER_MIRROR and re-run"
      return 0
    fi
    log "Registry mirrors: ${mirrors[*]}"
  fi

  merge_docker_daemon_mirrors "${mirrors[@]}"
  restart_docker_daemon
  log "Docker registry configuration applied"
}

docker_pull_retry() {
  local image="$1"
  log "Pulling image: $image"
  if retry 5 20 -- docker pull "$image"; then
    return 0
  fi
  die "Failed to pull $image — Docker Hub may be unreachable.
  Set MEOWVPN_DOCKER_MIRROR=https://your-mirror.example and re-run the installer."
}

images_for_mode() {
  local mode="${1:-${INSTALL_MODE:-all}}"
  local -a base=(
    nginx:1.27-alpine
    mysql:8.4
    redis:7-alpine
    php:8.3-fpm-bookworm
    composer:2
  )
  case "$mode" in
    relay) ;;
    backend)
      printf '%s\n' "${base[@]}"
      ;;
    frontend|dashboard)
      printf '%s\n' node:22-alpine "${base[@]}"
      ;;
    telegram|bale)
      printf '%s\n' php:8.3-cli-alpine "${base[@]}"
      ;;
    all)
      printf '%s\n' \
        node:22-alpine \
        node:22-bookworm-slim \
        php:8.3-cli-alpine \
        "${base[@]}"
      ;;
    *)
      printf '%s\n' "${base[@]}"
      ;;
  esac
}

prepull_images_for_mode() {
  local mode="${1:-${INSTALL_MODE:-all}}"
  local image
  if [[ "$mode" == "relay" ]]; then
    log "Relay mode — skipping Docker image pre-pull (host Node install)"
    return 0
  fi
  while IFS= read -r image; do
    [[ -n "$image" ]] || continue
    docker_pull_retry "$image"
  done < <(images_for_mode "$mode")
}

install_docker_official_repo() {
  log "Installing Docker (official apt repository)..."
  apt_install ca-certificates curl gnupg
  install -m 0755 -d /etc/apt/keyrings
  local distro codename
  distro="$(. /etc/os-release && echo "${ID}")"
  codename="$(. /etc/os-release && echo "${VERSION_CODENAME}")"
  download "https://download.docker.com/linux/${distro}/gpg" /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${distro} ${codename} stable" \
    > /etc/apt/sources.list.d/docker.list
  apt_update
  apt_install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
}

install_docker_getdocker() {
  log "Installing Docker (get.docker.com fallback)..."
  local tmp
  tmp="$(mktemp)"
  download "https://get.docker.com" "$tmp"
  sh "$tmp"
  rm -f "$tmp"
  apt_install docker-compose-plugin 2>/dev/null || true
}

install_docker_distro() {
  log "Installing Docker (distro packages fallback)..."
  apt_install docker.io docker-compose-plugin
}

ensure_docker() {
  if docker_daemon_ready; then
    log "Docker already installed: $(docker --version)"
    return 0
  fi

  if install_docker_official_repo 2>/dev/null && docker_daemon_ready; then
    :
  elif install_docker_getdocker 2>/dev/null && docker_daemon_ready; then
    :
  elif install_docker_distro 2>/dev/null && docker_daemon_ready; then
    :
  else
    die "Failed to install Docker — check ${MEOWVPN_INSTALL_LOG:-install.log}"
  fi

  systemctl enable docker
  systemctl start docker
  retry 10 3 -- docker_daemon_ready || die "Docker daemon not ready after install"

  log "Docker ready: $(docker --version)"
  log "Compose ready: $(docker compose version)"
}

ensure_nginx_base() {
  if command -v nginx >/dev/null 2>&1; then
    log "nginx already installed: $(nginx -v 2>&1 || true)"
    return 0
  fi
  apt_install nginx
  systemctl enable nginx
  log "nginx installed"
}

ensure_prereqs() {
  progress "Preparing apt for install"
  prepare_apt_for_install
  progress "Running preflight checks"
  preflight_all
  progress "Updating apt package lists"
  apt_update
  progress "Installing base packages"
  apt_install ca-certificates curl gnupg git openssl whiptail jq rsync socat
  progress "Installing Docker"
  ensure_docker
  progress "Configuring Docker registry mirrors"
  configure_docker_registry
  progress "Installing nginx"
  ensure_nginx_base
  progress "Pre-pulling Docker images"
  prepull_images_for_mode "${INSTALL_MODE:-all}"
  log "Host prerequisites complete (Docker-only — PHP/Node run in containers)"
}

compose_up_run() {
  local -a profiles=("$@")
  local -a args=(up -d --build)
  local p
  for p in "${profiles[@]}"; do
    [[ -n "$p" ]] && args+=(--profile "$p")
  done
  (cd "$BACKEND_DIR" && compose_cmd "${args[@]}")
}

compose_up() {
  local -a profiles=("$@")
  log "docker compose up ${profiles[*]}"
  if retry 3 20 -- compose_up_run "${profiles[@]}"; then
    return 0
  fi
  warn "docker compose up failed — container status:"
  (cd "$BACKEND_DIR" && compose_cmd ps -a) 2>&1 || true
  (cd "$BACKEND_DIR" && compose_cmd logs --tail=50) 2>&1 || true
  die "docker compose up failed — if image pull timed out, set MEOWVPN_DOCKER_MIRROR and re-run"
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
  (cd "$BACKEND_DIR" && compose_cmd logs --tail=50 web app 2>/dev/null) || \
    (cd "$BACKEND_DIR" && compose_cmd logs --tail=50) 2>&1 || true
  return 1
}

wait_for_db() {
  log "Waiting for MySQL..."
  local user pass
  user="${DB_USERNAME:-svp}"
  pass="${DB_PASSWORD:-${MEOWVPN_DB_PASSWORD:-secret}}"
  retry 30 5 -- compose_cmd exec -T mysql mysqladmin ping -h localhost -u"$user" -p"$pass" --silent \
    || die "MySQL not ready — check: docker compose logs mysql"
  log "Database ready"
}

build_frontend() {
  local api_base="${1:-/api/v1}"
  log "Building frontend in container (VITE_API_BASE=$api_base)..."
  mkdir -p "$REPO_ROOT/frontend/dist"
  docker_pull_retry node:22-alpine
  retry 3 20 -- docker run --rm \
    -v "$REPO_ROOT/frontend:/app" \
    -w /app \
    -e VITE_API_BASE="$api_base" \
    -e npm_config_fund=false \
    -e npm_config_audit=false \
    node:22-alpine \
    sh -lc "npm ci && npm run build"
  log "Frontend built: $REPO_ROOT/frontend/dist"
}

build_frontend_docker() {
  local api_base="${1:-/api/v1}"
  log "Building frontend image (VITE_API_BASE=$api_base)..."
  (cd "$BACKEND_DIR" && VITE_API_BASE="$api_base" compose_cmd build frontend)
}
