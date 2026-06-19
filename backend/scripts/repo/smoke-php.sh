#!/usr/bin/env bash
# DEPRECATED (v13 ARCH-11): redirects to Laravel backend PHPUnit.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
echo "DEPRECATED: scripts/smoke-php.sh → cd backend && php artisan test" >&2
cd "$ROOT/backend"
if [[ -f artisan ]]; then
  php artisan test
else
  echo "backend/ not found" >&2
  exit 1
fi
