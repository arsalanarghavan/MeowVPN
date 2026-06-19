#!/usr/bin/env bash
# MeowVPN — bootstrap installer: select target first, then download only required components.
#
# Interactive:
#   bash <(curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/install.sh)
#
# Non-interactive:
#   curl -fsSL .../install.sh | sudo bash -s -- --mode backend
#
# Update only:
#   curl -fsSL .../install.sh | sudo bash -s -- --update-only
#
set -euo pipefail

MEOWVPN_INSTALLER_API=3
MEOWVPN_GITHUB_REPO="${MEOWVPN_GITHUB_REPO:-arsalanarghavan/MeowVPN}"
MEOWVPN_REPO="${MEOWVPN_REPO:-https://github.com/${MEOWVPN_GITHUB_REPO}.git}"
MEOWVPN_BRANCH="${MEOWVPN_BRANCH:-main}"
MEOWVPN_DIR="${MEOWVPN_DIR:-/opt/meowvpn}"
MEOWVPN_RELEASE_TAG="${MEOWVPN_RELEASE_TAG:-components-latest}"
MEOWVPN_MANIFEST_URL="${MEOWVPN_MANIFEST_URL:-https://github.com/${MEOWVPN_GITHUB_REPO}/releases/download/${MEOWVPN_RELEASE_TAG}/manifest.json}"
MEOWVPN_TARBALL_URL="${MEOWVPN_TARBALL_URL:-https://github.com/${MEOWVPN_GITHUB_REPO}/archive/refs/heads/${MEOWVPN_BRANCH}.tar.gz}"
MEOWVPN_USE_GIT="${MEOWVPN_USE_GIT:-0}"
MEOWVPN_FULL_SYNC="${MEOWVPN_FULL_SYNC:-0}"
MEOWVPN_REUSE_TREE="${MEOWVPN_REUSE_TREE:-0}"

_BOOTSTRAP_C_PURPLE='\033[38;5;141m'
_BOOTSTRAP_C_RESET='\033[0m'
if [[ -n "${NO_COLOR:-}" ]] || [[ ! -t 1 ]]; then
  _BOOTSTRAP_C_PURPLE=''
  _BOOTSTRAP_C_RESET=''
fi

apply_bootstrap_purple_theme() {
  export NEWT_COLORS='
root=,magenta
window=magenta,white
border=magenta,white
title=magenta,white
button=black,magenta
actbutton=white,magenta
compactbutton=black,magenta
actselbutton=white,magenta
disbutton=gray,magenta
textbox=white,magenta
acttextbox=black,magenta
entry=white,magenta
actentry=black,magenta
listbox=white,magenta
actlistbox=black,magenta
actsellistbox=black,magenta
checkbutton=magenta,white
actcheckbutton=magenta,white
searchbox=white,magenta
actsearchbox=black,magenta
shadow=magenta,magenta
'
}

bootstrap_progress() {
  local current="$1"
  local total="$2"
  local label="$3"
  local pct=0
  if (( total > 0 )); then
    pct=$((current * 100 / total))
  fi
  if (( pct > 100 )); then pct=100; fi
  printf '%b[ %3d%% ]%b %s\n' "$_BOOTSTRAP_C_PURPLE" "$pct" "$_BOOTSTRAP_C_RESET" "$label"
}

UPDATE_ONLY=0
SKIP_CLONE=0
SELECTED_MODE=""
INSTALLER_ARGS=()
MANIFEST_FILE=""
INSTALL_STATE_BACKUP=""

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

Flow: choose install target (or pass --mode), then download only required components.

Bootstrap options:
  --update-only    refresh installed components, rebuild containers, migrate
  --skip-clone     skip download (tree must exist at MEOWVPN_DIR)
  -h, --help       this help

Other flags are passed to backend/scripts/ops/install/install.sh
(e.g. --mode dashboard --non-interactive --core-domain ...)

Env:
  MEOWVPN_DIR            default: /opt/meowvpn
  MEOWVPN_RELEASE_TAG    default: components-latest
  MEOWVPN_MANIFEST_URL   override manifest.json URL
  MEOWVPN_USE_GIT=1      use git clone/pull instead of component releases
  MEOWVPN_FULL_SYNC=1    on component failure, always download full archive
  MEOWVPN_REUSE_TREE=1   skip download (like --skip-clone)
  MEOWVPN_TARBALL_URL    full archive fallback URL
  MEOWVPN_DOCKER_MIRROR  force Docker registry mirror (auto if Hub unreachable)
  MEOWVPN_DOCKER_MIRRORS space-separated default mirrors
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
  command -v "$1" >/dev/null 2>&1
}

_BOOTSTRAP_APT_PAUSED=0

bootstrap_pause_apt() {
  if [[ "$_BOOTSTRAP_APT_PAUSED" == "1" ]]; then
    return 0
  fi
  _BOOTSTRAP_APT_PAUSED=1
  if ! command -v systemctl >/dev/null 2>&1; then
    return 0
  fi
  echo "[meowvpn] Pausing automatic apt services for bootstrap..."
  systemctl stop unattended-upgrades.service 2>/dev/null || true
  systemctl disable --now unattended-upgrades.service 2>/dev/null || true
  systemctl stop apt-daily.service apt-daily-upgrade.service 2>/dev/null || true
  systemctl stop apt-daily.timer apt-daily-upgrade.timer 2>/dev/null || true
  systemctl mask apt-daily.timer apt-daily-upgrade.timer 2>/dev/null || true
}

bootstrap_apt_lock_wait() {
  local max_wait=120 waited=0
  bootstrap_pause_apt
  while (( waited < max_wait )); do
    if ! fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 \
      && ! fuser /var/lib/dpkg/lock >/dev/null 2>&1 \
      && ! fuser /var/lib/apt/lists/lock >/dev/null 2>&1 \
      && ! pgrep -x unattended-upgr >/dev/null 2>&1; then
      return 0
    fi
    sleep 5
    waited=$((waited + 5))
  done
  echo "[meowvpn] WARN: apt lock still held after ${max_wait}s — continuing anyway" >&2
}

bootstrap_apt_install() {
  bootstrap_apt_lock_wait
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y --no-install-recommends "$@"
}

die_download_help() {
  cat >&2 <<EOF
[meowvpn] ERROR: Could not download MeowVPN from GitHub.

Try:
  MEOWVPN_FULL_SYNC=1 bash <(curl -fsSL https://raw.githubusercontent.com/${MEOWVPN_GITHUB_REPO}/main/install.sh)
  MEOWVPN_USE_GIT=1 bash <(curl -fsSL .../install.sh)
  bash <(curl -fsSL .../install.sh) --skip-clone   # if tree already at $MEOWVPN_DIR

Diagnostics:
  curl -I --max-time 15 https://github.com
  curl -fsSL --max-time 30 "$MEOWVPN_MANIFEST_URL"
EOF
  exit 1
}

ensure_tar() {
  if need_cmd tar; then return 0; fi
  echo "[meowvpn] Installing tar..."
  bootstrap_apt_install tar
}

ensure_whiptail() {
  if need_cmd whiptail; then return 0; fi
  echo "[meowvpn] Installing whiptail for install menu..."
  bootstrap_apt_install whiptail
}

ensure_git() {
  if need_cmd git; then return 0; fi
  echo "[meowvpn] Installing git..."
  bootstrap_apt_install git ca-certificates
}

ensure_python3() {
  if need_cmd python3; then return 0; fi
  echo "[meowvpn] Installing python3 (manifest parsing)..."
  bootstrap_apt_install python3-minimal
}

if ! need_cmd curl; then
  echo "curl is required." >&2
  exit 1
fi

ensure_tar
ensure_python3

retry_with_backoff() {
  local attempt=1 delay=5 max=3
  while [[ $attempt -le $max ]]; do
    if "$@"; then return 0; fi
    if [[ $attempt -eq $max ]]; then return 1; fi
    echo "[meowvpn] Retry $attempt/$max failed — waiting ${delay}s..."
    sleep "$delay"
    attempt=$((attempt + 1))
    delay=$((delay * 3))
  done
  return 1
}

extract_mode_from_args() {
  local -a kept=()
  local i=1
  SELECTED_MODE=""
  while [[ $i -le $# ]]; do
    if [[ "${!i}" == "--mode" ]]; then
      SELECTED_MODE="${!((i + 1))}"
      i=$((i + 2))
    else
      kept+=("${!i}")
      i=$((i + 1))
    fi
  done
  INSTALLER_ARGS=("${kept[@]}")
}

manifest_py() {
  python3 - "$MANIFEST_FILE" "$@" <<'PY'
import json, sys
manifest = json.load(open(sys.argv[1]))
cmd = sys.argv[2]
if cmd == "mode_components":
    for c in manifest["modes"][sys.argv[3]]:
        print(c)
elif cmd == "component_file":
    print(manifest["components"][sys.argv[3]]["file"])
elif cmd == "component_sha256":
    print(manifest["components"][sys.argv[3]]["sha256"])
elif cmd == "component_url":
    base = manifest.get("base_url", "").rstrip("/")
    comp = manifest["components"][sys.argv[3]]
    print(f"{base}/{comp['file']}")
PY
}

fetch_manifest() {
  local tmp
  tmp="$(mktemp /tmp/meowvpn-manifest.XXXXXX.json)"
  bootstrap_progress 1 2 "Fetching component manifest"
  echo "[meowvpn] Fetching manifest: $MEOWVPN_MANIFEST_URL"
  if ! retry_with_backoff curl -fsSL --retry 3 --retry-delay 5 --connect-timeout 30 --max-time 120 \
    -o "$tmp" "$MEOWVPN_MANIFEST_URL"; then
    rm -f "$tmp"
    return 1
  fi
  if ! python3 -c "import json; json.load(open('$tmp'))" 2>/dev/null; then
    rm -f "$tmp"
    return 1
  fi
  MANIFEST_FILE="$tmp"
  local ver
  ver="$(python3 -c "import json; print(json.load(open('$MANIFEST_FILE'))['version'])")"
  bootstrap_progress 2 2 "Manifest ready (version $ver)"
  echo "[meowvpn] Manifest version: $ver"
  return 0
}

backup_install_state() {
  INSTALL_STATE_BACKUP=""
  if [[ -d "$MEOWVPN_DIR/backend/.install" ]]; then
    INSTALL_STATE_BACKUP="$(mktemp -d /tmp/meowvpn-state.XXXXXX)"
    cp -a "$MEOWVPN_DIR/backend/.install" "$INSTALL_STATE_BACKUP/"
    echo "[meowvpn] Preserving install state from $MEOWVPN_DIR/backend/.install"
  fi
}

restore_install_state() {
  if [[ -n "$INSTALL_STATE_BACKUP" && -d "$INSTALL_STATE_BACKUP/.install" ]]; then
    mkdir -p "$MEOWVPN_DIR/backend"
    cp -a "$INSTALL_STATE_BACKUP/.install" "$MEOWVPN_DIR/backend/.install"
    echo "[meowvpn] Restored install state to $MEOWVPN_DIR/backend/.install"
  fi
}

download_and_extract_component() {
  local comp="$1"
  local dl_index="${2:-1}"
  local dl_total="${3:-1}"
  local file url expected actual tmpdir archive
  file="$(manifest_py component_file "$comp")"
  url="$(manifest_py component_url "$comp")"
  expected="$(manifest_py component_sha256 "$comp")"
  tmpdir="$(mktemp -d /tmp/meowvpn-dl.XXXXXX)"
  archive="$tmpdir/$file"

  bootstrap_progress "$dl_index" "$dl_total" "Downloading component: $comp ($file)"
  if ! retry_with_backoff curl -fSL --progress-bar --retry 3 --retry-delay 5 --connect-timeout 30 --max-time 600 \
    -o "$archive" "$url"; then
    rm -rf "$tmpdir"
    return 1
  fi

  actual="$(sha256sum "$archive" | awk '{print $1}')"
  if [[ "$actual" != "$expected" ]]; then
    echo "[meowvpn] ERROR: sha256 mismatch for $comp (expected $expected, got $actual)" >&2
    rm -rf "$tmpdir"
    return 1
  fi

  bootstrap_progress "$dl_index" "$dl_total" "Extracting component: $comp"

  if [[ "$comp" == "backend" || "$comp" == "full" ]]; then
    backup_install_state
  fi

  mkdir -p "$MEOWVPN_DIR"
  if ! tar -xzf "$archive" -C "$MEOWVPN_DIR"; then
    rm -rf "$tmpdir"
    return 1
  fi

  if [[ "$comp" == "backend" || "$comp" == "full" ]]; then
    restore_install_state
  fi

  rm -rf "$tmpdir"
  echo "[meowvpn] Installed component: $comp"
  return 0
}

sync_components_list() {
  local -a comps=("$@")
  local total="${#comps[@]}"
  local i=0
  local comp
  for comp in "${comps[@]}"; do
    i=$((i + 1))
    download_and_extract_component "$comp" "$i" "$total" || return 1
  done
  return 0
}

sync_components_for_mode() {
  local mode="$1"
  local -a components=()
  fetch_manifest || return 1
  mapfile -t components < <(manifest_py mode_components "$mode")
  echo "[meowvpn] Mode '$mode' requires: ${components[*]}"
  sync_components_list "${components[@]}"
}

detect_installed_components_for_update() {
  local -a found=()
  local has_backend=0 has_frontend=0 has_relay=0 has_tg=0 has_bale=0

  [[ -f "$MEOWVPN_DIR/backend/docker-compose.yml" ]] && has_backend=1
  [[ -f "$MEOWVPN_DIR/frontend/package.json" ]] && has_frontend=1
  [[ -f "$MEOWVPN_DIR/relay-server/package.json" ]] && has_relay=1
  [[ -f "$MEOWVPN_DIR/telegram_bot/Dockerfile" ]] && has_tg=1
  [[ -f "$MEOWVPN_DIR/bale_bot/Dockerfile" ]] && has_bale=1

  if [[ $has_backend -eq 1 && $has_frontend -eq 1 && $has_relay -eq 1 && $has_tg -eq 1 && $has_bale -eq 1 ]]; then
    echo "full"
    return 0
  fi

  found+=("install-kit")
  [[ $has_backend -eq 1 ]] && found+=("backend")
  [[ $has_frontend -eq 1 ]] && found+=("frontend")
  [[ $has_relay -eq 1 ]] && found+=("relay-server")
  [[ $has_tg -eq 1 ]] && found+=("telegram_bot")
  [[ $has_bale -eq 1 ]] && found+=("bale_bot")

  if [[ ${#found[@]} -eq 1 ]]; then
    echo "full"
    return 0
  fi

  printf '%s\n' "${found[@]}"
}

sync_update_components() {
  local -a components=()
  mapfile -t components < <(detect_installed_components_for_update)
  echo "[meowvpn] Update will refresh: ${components[*]}"
  fetch_manifest || return 1
  if [[ ${#components[@]} -eq 1 && "${components[0]}" == "full" ]]; then
    sync_components_list "full"
  else
    sync_components_list "${components[@]}"
  fi
}

bootstrap_select_mode() {
  ensure_whiptail
  apply_bootstrap_purple_theme
  local choice
  choice="$(whiptail --backtitle "MeowVPN" --title "MeowVPN Install" --menu "Select install target\n(only required files will be downloaded)" 20 72 8 \
    1 "Install All" \
    2 "Install Dashboard (Backend + Frontend)" \
    3 "Install Telegram Bot" \
    4 "Install Bale Bot" \
    5 "Install Dashboard Backend" \
    6 "Install Dashboard Frontend" \
    7 "Install Relay" \
    3>&1 1>&2 2>&3)" || exit 0

  case "$choice" in
    1) SELECTED_MODE=all ;;
    2) SELECTED_MODE=dashboard ;;
    3) SELECTED_MODE=telegram ;;
    4) SELECTED_MODE=bale ;;
    5) SELECTED_MODE=backend ;;
    6) SELECTED_MODE=frontend ;;
    7) SELECTED_MODE=relay ;;
    *) echo "Cancelled."; exit 0 ;;
  esac
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
  local tmpdir archive extracted inner
  tmpdir="$(mktemp -d /tmp/meowvpn-bootstrap.XXXXXX)"
  archive="$tmpdir/meowvpn.tar.gz"
  extracted="$tmpdir/extract"

  echo "[meowvpn] Downloading full archive: $MEOWVPN_TARBALL_URL"
  bootstrap_progress 0 1 "Downloading full archive"
  if ! curl -fSL --progress-bar --retry 3 --retry-delay 5 --connect-timeout 30 --max-time 600 \
    -o "$archive" "$MEOWVPN_TARBALL_URL"; then
    rm -rf "$tmpdir"
    return 1
  fi

  bootstrap_progress 1 1 "Extracting full archive"
  mkdir -p "$extracted"
  if ! tar -xzf "$archive" -C "$extracted"; then
    rm -rf "$tmpdir"
    return 1
  fi

  inner="$(find "$extracted" -mindepth 1 -maxdepth 1 -type d | head -1)"
  if [[ -z "$inner" || ! -d "$inner/backend" ]]; then
    echo "[meowvpn] Archive layout unexpected (no backend/)" >&2
    rm -rf "$tmpdir"
    return 1
  fi

  backup_install_state
  if [[ -d "$MEOWVPN_DIR" ]]; then
    echo "[meowvpn] Replacing existing $MEOWVPN_DIR with archive contents..."
    rm -rf "$MEOWVPN_DIR"
  fi
  mkdir -p "$(dirname "$MEOWVPN_DIR")"
  mv "$inner" "$MEOWVPN_DIR"
  restore_install_state
  rm -rf "$tmpdir"
  echo "[meowvpn] Archive extracted to $MEOWVPN_DIR"
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

sync_repo_git_full() {
  ensure_git
  if [[ -d "$MEOWVPN_DIR/.git" ]]; then
    git_update_with_retry "$MEOWVPN_DIR"
    return 0
  fi
  if [[ ! -d "$MEOWVPN_DIR" ]]; then
    if sync_repo_git; then return 0; fi
    echo "[meowvpn] git failed — trying full archive..."
    sync_repo_tarball || return 1
    return 0
  fi
  sync_repo_tarball || return 1
}

sync_repo_full_fallback() {
  echo "[meowvpn] Component release unavailable — falling back to full download"
  if [[ "$MEOWVPN_USE_GIT" == "1" ]]; then
    sync_repo_git_full || die_download_help
  else
    sync_repo_tarball || die_download_help
  fi
}

try_sync_for_mode() {
  local mode="$1"
  if sync_components_for_mode "$mode"; then
    return 0
  fi
  if [[ "$mode" == "all" || "$MEOWVPN_FULL_SYNC" == "1" ]]; then
    sync_repo_full_fallback
    return 0
  fi
  sync_repo_full_fallback
}

repair_nested_backend_layout() {
  local base="$MEOWVPN_DIR/backend"
  if [[ -f "$base/docker-compose.yml" ]]; then return 0; fi
  if [[ ! -d "$base/backend" ]]; then return 0; fi
  if [[ ! -f "$base/backend/docker-compose.yml" ]]; then return 0; fi
  echo "[meowvpn] Repairing nested backend/backend layout..."
  shopt -s dotglob nullglob
  local item name
  for item in "$base/backend"/*; do
    [[ -e "$item" ]] || continue
    name="$(basename "$item")"
    [[ -e "$base/$name" ]] && continue
    mv "$item" "$base/$name"
  done
  shopt -u dotglob nullglob
  rmdir "$base/backend" 2>/dev/null || true
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

extract_mode_from_args "${INSTALLER_ARGS[@]}"

if [[ "$SKIP_CLONE" -eq 1 || "$MEOWVPN_REUSE_TREE" == "1" ]]; then
  if [[ ! -f "$INNER_INSTALL" ]]; then
    echo "[meowvpn] --skip-clone set but installer not found: $INNER_INSTALL" >&2
    exit 1
  fi
  echo "[meowvpn] Skipping download (--skip-clone / MEOWVPN_REUSE_TREE=1)"
elif [[ "$UPDATE_ONLY" -eq 1 ]]; then
  if ! sync_update_components; then
    sync_repo_full_fallback || die_download_help
  fi
  echo "[meowvpn] Bootstrap update sync complete (API v${MEOWVPN_INSTALLER_API})"
  repair_nested_backend_layout
  run_update_only
  exit 0
else
  if [[ -z "$SELECTED_MODE" ]]; then
    bootstrap_select_mode
  fi
  try_sync_for_mode "$SELECTED_MODE" || die_download_help
  echo "[meowvpn] Bootstrap sync complete for mode=$SELECTED_MODE (API v${MEOWVPN_INSTALLER_API})"
fi

repair_nested_backend_layout

if [[ ! -f "$INNER_INSTALL" ]]; then
  echo "Installer not found: $INNER_INSTALL" >&2
  exit 1
fi

if [[ "$UPDATE_ONLY" -eq 1 ]]; then
  run_update_only
  exit 0
fi

chmod +x "$INNER_INSTALL" 2>/dev/null || true
if [[ -n "$SELECTED_MODE" ]]; then
  FINAL_ARGS=(--mode "$SELECTED_MODE" "${INSTALLER_ARGS[@]}")
  if [[ "$SELECTED_MODE" == "all" ]]; then
    FINAL_ARGS+=(--defer-domains)
  fi
  echo "[meowvpn] Running installer (mode=$SELECTED_MODE)..."
else
  FINAL_ARGS=("${INSTALLER_ARGS[@]}")
  echo "[meowvpn] Running installer..."
fi
exec bash "$INNER_INSTALL" "${FINAL_ARGS[@]}"
