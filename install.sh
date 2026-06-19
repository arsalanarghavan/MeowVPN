#!/usr/bin/env bash
# MeowVPN — one-liner bootstrap (clone/update repo, run full installer).
#
# Interactive:
#   bash <(curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/install.sh)
#
# Pipe form:
#   curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/install.sh | sudo bash
#
# Non-interactive:
#   curl -fsSL .../install.sh | sudo bash -s -- --mode all --non-interactive --core-domain api.example.com ...
#
# Update only (git pull + rebuild + migrate):
#   curl -fsSL .../install.sh | sudo bash -s -- --update-only
#
# Env: MEOWVPN_REPO MEOWVPN_BRANCH MEOWVPN_DIR
#
set -euo pipefail

MEOWVPN_REPO="${MEOWVPN_REPO:-https://github.com/arsalanarghavan/MeowVPN.git}"
MEOWVPN_BRANCH="${MEOWVPN_BRANCH:-main}"
MEOWVPN_DIR="${MEOWVPN_DIR:-/opt/meowvpn}"

UPDATE_ONLY=0
INSTALLER_ARGS=()

usage() {
  cat <<'EOF'
MeowVPN bootstrap installer

  bash <(curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/install.sh)

Options (bootstrap):
  --update-only    git pull, docker compose rebuild, migrate (no install menu)
  -h, --help       this help

All other flags are passed to backend/scripts/ops/install/install.sh
(e.g. --mode all --non-interactive --core-domain ...)

Env:
  MEOWVPN_REPO    default: https://github.com/arsalanarghavan/MeowVPN.git
  MEOWVPN_BRANCH  default: main
  MEOWVPN_DIR     default: /opt/meowvpn
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --update-only) UPDATE_ONLY=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) INSTALLER_ARGS+=("$1"); shift ;;
  esac
done

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: curl -fsSL .../install.sh | sudo bash" >&2
  exit 1
fi

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || return 1
}

ensure_git() {
  if need_cmd git; then
    return 0
  fi
  echo "[meowvpn] Installing git..."
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y --no-install-recommends git ca-certificates
}

if ! need_cmd curl; then
  echo "curl is required. Install curl or use a base image that includes it." >&2
  exit 1
fi

ensure_git

sync_repo() {
  if [[ -d "$MEOWVPN_DIR/.git" ]]; then
    echo "[meowvpn] Updating $MEOWVPN_DIR (branch $MEOWVPN_BRANCH)..."
    git -C "$MEOWVPN_DIR" fetch origin "$MEOWVPN_BRANCH"
    git -C "$MEOWVPN_DIR" checkout "$MEOWVPN_BRANCH"
    git -C "$MEOWVPN_DIR" pull --ff-only origin "$MEOWVPN_BRANCH"
  else
    if [[ -d "$MEOWVPN_DIR" ]]; then
      echo "[meowvpn] $MEOWVPN_DIR exists but is not a git repo — remove it or set MEOWVPN_DIR" >&2
      exit 1
    fi
    echo "[meowvpn] Cloning $MEOWVPN_REPO (branch $MEOWVPN_BRANCH) → $MEOWVPN_DIR"
    git clone --depth 1 --branch "$MEOWVPN_BRANCH" "$MEOWVPN_REPO" "$MEOWVPN_DIR"
  fi
}

INNER_INSTALL="$MEOWVPN_DIR/backend/scripts/ops/install/install.sh"
COMPOSE_OVERRIDE="$MEOWVPN_DIR/backend/scripts/ops/install/docker-compose.install.override.yml"

run_update_only() {
  local backend="$MEOWVPN_DIR/backend"
  echo "[meowvpn] Rebuilding containers..."
  (
    cd "$backend"
    docker compose -f docker-compose.yml -f "$COMPOSE_OVERRIDE" \
      --profile workers --profile full up -d --build
    docker compose exec -T app php artisan migrate --force
  )
  echo "[meowvpn] Update complete. Tree: $MEOWVPN_DIR"
}

sync_repo

if [[ ! -f "$INNER_INSTALL" ]]; then
  echo "Installer not found: $INNER_INSTALL" >&2
  exit 1
fi

if [[ "$UPDATE_ONLY" -eq 1 ]]; then
  run_update_only
  exit 0
fi

chmod +x "$INNER_INSTALL" 2>/dev/null || true
echo "[meowvpn] Running full installer..."
exec bash "$INNER_INSTALL" "${INSTALLER_ARGS[@]}"
