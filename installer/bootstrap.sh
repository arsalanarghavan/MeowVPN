#!/bin/bash
# MeowVPN Bootstrap Installer
# Downloads the full repo and runs the installer
# Usage: curl -sSL https://raw.githubusercontent.com/arsalanarghavan/MeowVPN/main/installer/bootstrap.sh | bash

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

GITHUB_REPO="https://github.com/arsalanarghavan/MeowVPN.git"
INSTALL_DIR="/opt/MeowVPN"

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║           MeowVPN Bootstrap Installer                     ║"
echo "║           Downloading & Installing...                      ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# If running as root, no sudo needed
if [ "$EUID" -eq 0 ]; then
    SUDO=""
else
    SUDO="sudo"
fi

# Install git if not available
if ! command -v git &> /dev/null; then
    echo -e "${YELLOW}ℹ Installing git...${NC}"
    $SUDO apt-get update -qq && $SUDO apt-get install -y git > /dev/null 2>&1
fi

# Clone or update repo
if [ -d "$INSTALL_DIR/.git" ]; then
    echo -e "${YELLOW}ℹ Existing installation found. Pulling latest...${NC}"
    cd "$INSTALL_DIR"
    git pull
else
    if [ -d "$INSTALL_DIR" ]; then
        $SUDO rm -rf "$INSTALL_DIR"
    fi
    echo -e "${YELLOW}ℹ Cloning MeowVPN to $INSTALL_DIR...${NC}"
    $SUDO git clone "$GITHUB_REPO" "$INSTALL_DIR"
    if [ "$EUID" -eq 0 ]; then
        chown -R root:root "$INSTALL_DIR"
    else
        $SUDO chown -R "$(whoami):$(id -gn)" "$INSTALL_DIR"
    fi
fi

echo -e "${GREEN}✓ Repository ready${NC}"
echo ""

# Run the actual installer (not piped, so stdin works for interactive prompts)
cd "$INSTALL_DIR"
exec bash installer/install.sh
