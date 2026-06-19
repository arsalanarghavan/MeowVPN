#!/usr/bin/env bash
# Update an existing /opt/svp-relay install from GitHub (no git required in install dir).
#
# One-liner:
#   curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/update-from-github.sh | sudo bash
#
set -euo pipefail

REPO_URL="${SVP_RELAY_REPO:-https://github.com/arsalanarghavan/simplevpbot.git}"
BRANCH="${SVP_RELAY_BRANCH:-main}"
CLONE_DIR="${SVP_RELAY_CLONE_DIR:-/tmp/simplevpbot-relay-update}"
INSTALL_DIR="${INSTALL_DIR:-/opt/svp-relay}"
SERVICE_USER="${SERVICE_USER:-svp-relay}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo bash update-from-github.sh"
  exit 1
fi

for cmd in git rsync node npm; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "Missing: $cmd"; exit 1; }
done

if [[ ! -d "$INSTALL_DIR" ]]; then
  echo "Install dir not found: $INSTALL_DIR"
  echo "Use install-from-github.sh for first-time install."
  exit 1
fi

rm -rf "$CLONE_DIR"
echo "Fetching $REPO_URL ($BRANCH)..."
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$CLONE_DIR"

echo "Updating $INSTALL_DIR (keeping .env and data/)..."
rsync -a --delete \
  --exclude node_modules \
  --exclude data \
  --exclude .env \
  "$CLONE_DIR/relay-server/" "$INSTALL_DIR/"

chown -R "$SERVICE_USER:$SERVICE_USER" "$INSTALL_DIR"

echo "Building..."
cd "$INSTALL_DIR"
sudo -u "$SERVICE_USER" npm ci
sudo -u "$SERVICE_USER" npm run build

bash "$CLONE_DIR/relay-server/scripts/install-cli-bin.sh" "$INSTALL_DIR"

if systemctl is-enabled svp-relay &>/dev/null; then
  systemctl restart svp-relay
  echo "Service restarted."
fi

rm -rf "$CLONE_DIR"
echo ""
echo "Done. Control panel: svp-relay"
echo "Or: node $INSTALL_DIR/dist/cli/svp-relay.js panel"
