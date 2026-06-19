#!/usr/bin/env bash
# Dry-run: build tarballs, extract per mode, assert required paths exist.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BUILD="$ROOT/scripts/release/build-component-tarballs.sh"
OUT="$ROOT/dist/release-verify"
MANIFEST="$OUT/manifest.json"

rm -rf "$OUT"
OUT_DIR="$OUT" bash "$BUILD"

assert_path() {
  local base="$1"
  local rel="$2"
  [[ -e "$base/$rel" ]] || {
    echo "MISSING: $base/$rel" >&2
    return 1
  }
}

extract_mode() {
  local mode="$1"
  local dest="$OUT/tree-$mode"
  rm -rf "$dest"
  mkdir -p "$dest"

  local components
  components="$(python3 -c "
import json
m = json.load(open('$MANIFEST'))
for c in m['modes']['$mode']:
    print(c)
")"

  local comp file
  while IFS= read -r comp; do
    [[ -n "$comp" ]] || continue
    file="$(python3 -c "import json; m=json.load(open('$MANIFEST')); print(m['components']['$comp']['file'])")"
    echo "[$mode] extract $file" >&2
    tar -xzf "$OUT/$file" -C "$dest"
  done <<<"$components"
  echo "$dest"
}

check_mode() {
  local mode="$1"
  shift
  local dest
  dest="$(extract_mode "$mode")"
  local p
  for p in "$@"; do
    assert_path "$dest" "$p"
  done
  echo "OK mode=$mode"
}

check_mode backend backend/docker-compose.yml backend/scripts/ops/install/install.sh
check_mode frontend backend/docker-compose.yml frontend/Dockerfile
check_mode dashboard backend/docker-compose.yml frontend/Dockerfile
check_mode telegram backend/docker-compose.yml telegram_bot/Dockerfile
check_mode bale backend/docker-compose.yml bale_bot/Dockerfile
check_mode relay backend/scripts/ops/install/install.sh relay-server/scripts/install.sh
check_mode all backend/docker-compose.yml frontend/Dockerfile relay-server/scripts/install.sh

echo "All component layout checks passed."
