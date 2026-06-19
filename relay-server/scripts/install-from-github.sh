#!/usr/bin/env bash
# Clone SimpleVPBot relay-server from GitHub and run full install (Node, build, systemd, optional nginx/SSL).
#
# One-liner (replace domain/email):
#   curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/install-from-github.sh | sudo bash -s -- --domain tg.example.com --email you@example.com --ssl certbot
#
set -euo pipefail

REPO_URL="${SVP_RELAY_REPO:-https://github.com/arsalanarghavan/simplevpbot.git}"
BRANCH="${SVP_RELAY_BRANCH:-main}"
CLONE_DIR="${SVP_RELAY_CLONE_DIR:-/tmp/simplevpbot-relay-install}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo bash install-from-github.sh [install.sh flags]"
  exit 1
fi

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1"; exit 1; }
}

need_cmd curl
need_cmd git

rm -rf "$CLONE_DIR"
echo "Cloning $REPO_URL (branch $BRANCH)..."
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$CLONE_DIR"

INSTALL_SCRIPT="$CLONE_DIR/relay-server/scripts/install.sh"
if [[ ! -f "$INSTALL_SCRIPT" ]]; then
  echo "relay-server/scripts/install.sh not found in clone."
  exit 1
fi

chmod +x "$INSTALL_SCRIPT"
echo "Running installer..."
bash "$INSTALL_SCRIPT" "$@"

rm -rf "$CLONE_DIR"
echo "Clone directory removed."
