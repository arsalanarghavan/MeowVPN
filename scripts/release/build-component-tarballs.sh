#!/usr/bin/env bash
# Build MeowVPN component tarballs + manifest.json for GitHub Releases.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OUT="${OUT_DIR:-$ROOT/dist/release}"
VERSION="${VERSION:-$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo unknown)}"
GITHUB_REPO="${GITHUB_REPO:-arsalanarghavan/MeowVPN}"
RELEASE_TAG="${RELEASE_TAG:-components-latest}"
BASE_URL="https://github.com/${GITHUB_REPO}/releases/download/${RELEASE_TAG}"

mkdir -p "$OUT"

TAR_EXCLUDES=(
  --exclude='node_modules'
  --exclude='vendor'
  --exclude='.git'
  --exclude='backend/.install'
  --exclude='relay-server/node_modules'
  --exclude='dist'
  --exclude='.cursor'
)

sha_of() {
  sha256sum "$1" | awk '{print $1}'
}

pack_paths() {
  local outfile="$1"
  shift
  tar -czf "$outfile" "${TAR_EXCLUDES[@]}" -C "$ROOT" "$@"
  sha_of "$outfile"
}

echo "[release] Building component tarballs (version=${VERSION}) → ${OUT}"

SHA_INSTALL_KIT="$(pack_paths "$OUT/meowvpn-install-kit.tar.gz" backend/scripts/ops/install)"
SHA_BACKEND="$(pack_paths "$OUT/meowvpn-backend.tar.gz" backend)"
SHA_FRONTEND="$(pack_paths "$OUT/meowvpn-frontend.tar.gz" frontend)"
SHA_RELAY="$(pack_paths "$OUT/meowvpn-relay-server.tar.gz" relay-server)"
SHA_TELEGRAM="$(pack_paths "$OUT/meowvpn-telegram-bot.tar.gz" telegram_bot)"
SHA_BALE="$(pack_paths "$OUT/meowvpn-bale-bot.tar.gz" bale_bot)"
SHA_FULL="$(pack_paths "$OUT/meowvpn-full.tar.gz" \
  backend frontend relay-server telegram_bot bale_bot docs scripts install.sh README.md)"

cat >"$OUT/manifest.json" <<EOF
{
  "version": "${VERSION}",
  "base_url": "${BASE_URL}",
  "components": {
    "install-kit": {
      "file": "meowvpn-install-kit.tar.gz",
      "sha256": "${SHA_INSTALL_KIT}"
    },
    "backend": {
      "file": "meowvpn-backend.tar.gz",
      "sha256": "${SHA_BACKEND}"
    },
    "frontend": {
      "file": "meowvpn-frontend.tar.gz",
      "sha256": "${SHA_FRONTEND}"
    },
    "relay-server": {
      "file": "meowvpn-relay-server.tar.gz",
      "sha256": "${SHA_RELAY}"
    },
    "telegram_bot": {
      "file": "meowvpn-telegram-bot.tar.gz",
      "sha256": "${SHA_TELEGRAM}"
    },
    "bale_bot": {
      "file": "meowvpn-bale-bot.tar.gz",
      "sha256": "${SHA_BALE}"
    },
    "full": {
      "file": "meowvpn-full.tar.gz",
      "sha256": "${SHA_FULL}"
    }
  },
  "modes": {
    "backend": ["install-kit", "backend"],
    "frontend": ["install-kit", "backend", "frontend"],
    "dashboard": ["install-kit", "backend", "frontend"],
    "telegram": ["install-kit", "backend", "telegram_bot"],
    "bale": ["install-kit", "backend", "bale_bot"],
    "relay": ["install-kit", "relay-server"],
    "all": ["full"]
  }
}
EOF

echo "[release] Wrote ${OUT}/manifest.json"
ls -lh "$OUT"/*.tar.gz "$OUT/manifest.json"
