#!/usr/bin/env bash
# MeowVPN standard installer — interactive menu + non-interactive CLI.
set -euo pipefail

MEOWVPN_INSTALLER_API=2

INSTALL_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "$INSTALL_ROOT/lib/common.sh"
source "$INSTALL_ROOT/lib/prompts.sh"

[[ -f "$BACKEND_DIR/docker-compose.yml" ]] || die "Invalid install tree: missing $BACKEND_DIR/docker-compose.yml"
[[ -f "$INSTALL_ROOT/install.sh" ]] || die "Installer layout broken: missing $INSTALL_ROOT/install.sh"
log "Installer API v${MEOWVPN_INSTALLER_API} — REPO_ROOT=$REPO_ROOT BACKEND_DIR=$BACKEND_DIR"

# Parse --help before logging / root / apt (no side effects).
parse_cli_args "$@"

install_err_trap
setup_logging

source "$INSTALL_ROOT/lib/docker.sh"

ensure_prereqs

run_mode() {
  local mode="$1"
  case "$mode" in
    all) bash "$INSTALL_ROOT/modules/all.sh" ;;
    dashboard) bash "$INSTALL_ROOT/modules/dashboard.sh" ;;
    backend) bash "$INSTALL_ROOT/modules/backend.sh" ;;
    frontend) bash "$INSTALL_ROOT/modules/frontend.sh" ;;
    telegram) bash "$INSTALL_ROOT/modules/telegram.sh" ;;
    bale) bash "$INSTALL_ROOT/modules/bale.sh" ;;
    relay) bash "$INSTALL_ROOT/modules/relay.sh" ;;
    *) die "Unknown mode: $mode" ;;
  esac
}

if [[ -n "$INSTALL_MODE" ]]; then
  case "$INSTALL_MODE" in
    all)
      [[ "$NON_INTERACTIVE" == "1" ]] && collect_all_domains
      ;;
    dashboard)
      [[ "$NON_INTERACTIVE" == "1" ]] && collect_dashboard_domains
      ;;
    backend|frontend|telegram|bale|relay)
      ;;
  esac
  run_mode "$INSTALL_MODE"
  exit 0
fi

if ! command -v whiptail >/dev/null 2>&1; then
  cat <<'EOF'
MeowVPN installer — whiptail not found. Use:
  sudo bash backend/scripts/ops/install.sh --mode all --non-interactive \
    --core-domain api.example.com --dashboard-domain panel.example.com \
    --telegram-domain tg.example.com --bale-domain bale.example.com \
    --relay-domain relay.example.com --ssl certbot --email you@example.com
EOF
  exit 1
fi

choice="$(whiptail --title "MeowVPN Install" --menu "Select install target" 18 72 8 \
  1 "Install All" \
  2 "Install Dashboard (Backend + Frontend)" \
  3 "Install Telegram Bot" \
  4 "Install Bale Bot" \
  5 "Install Dashboard Backend" \
  6 "Install Dashboard Frontend" \
  7 "Install Relay" \
  3>&1 1>&2 2>&3)" || exit 0

case "$choice" in
  1) collect_all_domains; run_mode all ;;
  2) collect_dashboard_domains; run_mode dashboard ;;
  3) run_mode telegram ;;
  4) run_mode bale ;;
  5) run_mode backend ;;
  6) run_mode frontend ;;
  7) run_mode relay ;;
  *) die "Cancelled" ;;
esac
