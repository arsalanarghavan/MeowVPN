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
# Env: MEOWVPN_REPO MEOWVPN_BRANCH MEOWVPN_DIR MEOWVPN_TARBALL_URL MEOWVPN_SKIP_GIT
#
set -euo pipefail

MEOWVPN_REPO="${MEOWVPN_REPO:-https://github.com/arsalanarghavan/MeowVPN.git}"
MEOWVPN_BRANCH="${MEOWVPN_BRANCH:-main}"
MEOWVPN_DIR="${MEOWVPN_DIR:-/opt/meowvpn}"
MEOWVPN_TARBALL_URL="${MEOWVPN_TARBALL_URL:-https://github.com/arsalanarghavan/MeowVPN/archive/refs/heads/${MEOWVPN_BRANCH}.tar.gz}"
MEOWVPN_SKIP_GIT="${MEOWVPN_SKIP_GIT:-0}"

UPDATE_ONLY=0
SKIP_CLONE=0
INSTALLER_ARGS=()

# Per-invocation git HTTP tuning (no permanent git config changes).
GIT_HTTP_OPTS=(
  -c http.version=HTTP/1.1
  -c http.lowSpeedLimit=1000
  -c http.lowSpeedTime=600
)

INNER_INSTALL="$MEOWVPN_DIR/backend/scripts/ops/install/install.sh"
COMPOSE_OVERRIDE="$MEOWVPN_DIR/backend/scripts/ops/install/docker-compose.install.override.yml"

usage() {
  cat <<'EOF'
MeowVPN bootstrap installer

  bash <(curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/install.sh)

Options (bootstrap):
  --update-only    git pull, docker compose rebuild, migrate (no install menu)
  --skip-clone     skip git/tarball sync (tree must already exist at MEOWVPN_DIR)
  -h, --help       this help

All other flags are passed to backend/scripts/ops/install/install.sh
(e.g. --mode all --non-interactive --core-domain ...)

Env:
  MEOWVPN_REPO         default: https://github.com/arsalanarghavan/MeowVPN.git
  MEOWVPN_BRANCH       default: main
  MEOWVPN_DIR          default: /opt/meowvpn
  MEOWVPN_TARBALL_URL  GitHub archive tarball fallback URL
  MEOWVPN_SKIP_GIT     set to 1 to download tarball only (no git clone)
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --update-only) UPDATE_ONLY=1; shift ;;
    --skip-clone) SKIP_CLONE=1; shift ;;
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

die_clone_help() {
  cat >&2 <<EOF
[meowvpn] ERROR: Could not download MeowVPN from GitHub (git and tarball both failed).

GitHub may be unreachable from this server (SSL timeout, firewall, or DNS).

Try one of these:

  1) SSH clone (deploy key on this VPS):
     MEOWVPN_REPO=git@github.com:arsalanarghavan/MeowVPN.git \\
       bash <(curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/install.sh)

  2) Copy from your PC (where GitHub works):
     git clone --depth 1 https://github.com/arsalanarghavan/MeowVPN.git
     scp -r MeowVPN root@YOUR_VPS:/opt/meowvpn
     ssh root@YOUR_VPS 'cd /opt/meowvpn && bash backend/scripts/ops/install/install.sh'

  3) If the tree is already at $MEOWVPN_DIR:
     bash <(curl -fsSL .../install.sh) --skip-clone

  4) Diagnostics on the VPS:
     curl -I --max-time 15 https://github.com
     dig +short github.com
EOF
  exit 1
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

ensure_tar() {
  if need_cmd tar; then
    return 0
  fi
  echo "[meowvpn] Installing tar..."
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y --no-install-recommends tar
}

if ! need_cmd curl; then
  echo "curl is required. Install curl or use a base image that includes it." >&2
  exit 1
fi

ensure_git
ensure_tar

# Run a command up to 3 times with backoff (5s, 15s, 30s).
retry_with_backoff() {
  local attempt=1
  local delay=5
  local max=3
  while [[ $attempt -le $max ]]; do
    if "$@"; then
      return 0
    fi
    if [[ $attempt -eq $max ]]; then
      return 1
    fi
    echo "[meowvpn] Retry $attempt/$max failed — waiting ${delay}s..."
    sleep "$delay"
    attempt=$((attempt + 1))
    delay=$((delay * 3))
  done
  return 1
}

git_clone_with_retry() {
  local dest="$1"
  echo "[meowvpn] Cloning $MEOWVPN_REPO (branch $MEOWVPN_BRANCH) → $dest"
  retry_with_backoff git "${GIT_HTTP_OPTS[@]}" clone --depth 1 --branch "$MEOWVPN_BRANCH" "$MEOWVPN_REPO" "$dest"
}

git_update_with_retry() {
  local dir="$1"
  echo "[meowvpn] Updating $dir (branch $MEOWVPN_BRANCH)..."
  retry_with_backoff git "${GIT_HTTP_OPTS[@]}" -C "$dir" fetch origin "$MEOWVPN_BRANCH"
  retry_with_backoff git -C "$dir" checkout "$MEOWVPN_BRANCH"
  retry_with_backoff git -C "$dir" pull --ff-only origin "$MEOWVPN_BRANCH"
}

sync_repo_tarball() {
  local tmpdir archive extracted inner state_backup=""
  tmpdir="$(mktemp -d /tmp/meowvpn-bootstrap.XXXXXX)"
  archive="$tmpdir/meowvpn.tar.gz"
  extracted="$tmpdir/extract"

  echo "[meowvpn] Downloading tarball: $MEOWVPN_TARBALL_URL"
  if ! curl -fsSL --retry 3 --retry-delay 5 --connect-timeout 30 --max-time 600 \
    -o "$archive" "$MEOWVPN_TARBALL_URL"; then
    rm -rf "$tmpdir"
    return 1
  fi

  mkdir -p "$extracted"
  if ! tar -xzf "$archive" -C "$extracted"; then
    rm -rf "$tmpdir"
    return 1
  fi

  inner="$(find "$extracted" -mindepth 1 -maxdepth 1 -type d | head -1)"
  if [[ -z "$inner" || ! -d "$inner/backend" ]]; then
    echo "[meowvpn] Tarball layout unexpected (no backend/ in archive root)" >&2
    rm -rf "$tmpdir"
    return 1
  fi

  if [[ -d "$MEOWVPN_DIR" ]]; then
    if [[ -d "$MEOWVPN_DIR/backend/.install" ]]; then
      state_backup="$tmpdir/install-state-backup"
      echo "[meowvpn] Preserving install state from $MEOWVPN_DIR/backend/.install"
      cp -a "$MEOWVPN_DIR/backend/.install" "$state_backup"
    fi
    echo "[meowvpn] Replacing existing $MEOWVPN_DIR with tarball contents..."
    rm -rf "$MEOWVPN_DIR"
  fi

  mkdir -p "$(dirname "$MEOWVPN_DIR")"
  mv "$inner" "$MEOWVPN_DIR"
  if [[ -n "$state_backup" && -d "$state_backup" ]]; then
    mkdir -p "$MEOWVPN_DIR/backend"
    cp -a "$state_backup" "$MEOWVPN_DIR/backend/.install"
    echo "[meowvpn] Restored install state to $MEOWVPN_DIR/backend/.install"
  fi
  rm -rf "$tmpdir"
  echo "[meowvpn] Tarball extracted to $MEOWVPN_DIR"
  return 0
}

sync_repo_git() {
  if [[ -d "$MEOWVPN_DIR/.git" ]]; then
    git_update_with_retry "$MEOWVPN_DIR"
    return 0
  fi

  if [[ -d "$MEOWVPN_DIR" ]]; then
    echo "[meowvpn] $MEOWVPN_DIR exists but is not a git repo" >&2
    return 1
  fi

  git_clone_with_retry "$MEOWVPN_DIR"
}

sync_repo() {
  if [[ -d "$MEOWVPN_DIR" && -f "$INNER_INSTALL" ]]; then
    echo "[meowvpn] Reusing existing tree at $MEOWVPN_DIR (rm -rf it to force re-download)"
    return 0
  fi

  if [[ "$MEOWVPN_SKIP_GIT" == "1" ]]; then
    echo "[meowvpn] MEOWVPN_SKIP_GIT=1 — using tarball only"
    sync_repo_tarball || die_clone_help
    return 0
  fi

  if sync_repo_git; then
    return 0
  fi

  echo "[meowvpn] git clone/update failed — trying tarball fallback..."
  sync_repo_tarball || die_clone_help
}

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

if [[ "$SKIP_CLONE" -eq 1 ]]; then
  if [[ ! -f "$INNER_INSTALL" ]]; then
    echo "[meowvpn] --skip-clone set but installer not found: $INNER_INSTALL" >&2
    exit 1
  fi
  echo "[meowvpn] Skipping repo sync (--skip-clone); using $MEOWVPN_DIR"
else
  sync_repo
fi

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
