#!/usr/bin/env bash
# Remove WP plugin sources from main after archive branch exists.
# Requires: CONFIRM=1 and prior run of archive-wp-plugin.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT"

if [[ "${CONFIRM:-}" != "1" ]]; then
  echo "Set CONFIRM=1 to delete includes/ from main (after archive/wp-plugin branch exists)."
  exit 1
fi

if ! git show-ref --verify --quiet refs/heads/archive/wp-plugin; then
  echo "ERROR: branch archive/wp-plugin not found. Run archive-wp-plugin.sh first."
  exit 1
fi

git rm -r includes/
echo "includes/ staged for removal. Commit and push when ops sign-off is complete."
