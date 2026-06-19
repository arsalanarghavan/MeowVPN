#!/usr/bin/env bash
# Run WordPress SQL import via Laravel (post-cutover path).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

DUMP="${1:-${SVP_WP_DUMP:-}}"
if [[ -z "$DUMP" || ! -f "$DUMP" ]]; then
  echo "Usage: SVP_WP_DUMP=/path/to/dump.sql bash backend/scripts/ops/import-run.sh" >&2
  echo "   or: bash backend/scripts/ops/import-run.sh /path/to/dump.sql" >&2
  exit 1
fi

DEFAULT_PW="${SVP_WP_DEFAULT_PASSWORD:-$(openssl rand -hex 12)}"
PREFIX="${SVP_WP_PREFIX:-wp_}"
FORCE="${SVP_WP_FORCE:-}"
BACKUPS="${SVP_WP_BACKUPS_FROM:-}"

ARGS=(--default-password="$DEFAULT_PW" --prefix="$PREFIX")
[[ -n "$FORCE" ]] && ARGS+=(--force)
[[ -n "$BACKUPS" ]] && ARGS+=(--backups-from="$BACKUPS")

if command -v docker >/dev/null 2>&1 && [[ -f docker-compose.yml ]]; then
  docker compose exec -T app php artisan wp:import "$DUMP" "${ARGS[@]}"
else
  php artisan wp:import "$DUMP" "${ARGS[@]}"
fi

echo "import-run: done (default dashboard password: $DEFAULT_PW — change in setup wizard)"
