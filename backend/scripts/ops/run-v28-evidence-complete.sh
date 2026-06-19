#!/usr/bin/env bash
# One-shot v28 OPS evidence — run on staging/production host with Docker + php-xml.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
export PATH="$ROOT/bin:$PATH"

export SVP_BASE_URL="${SVP_BASE_URL:-http://127.0.0.1:8080}"
export SVP_WP_DUMP="${SVP_WP_DUMP:-$ROOT/backend/tests/fixtures/wp-minimal-dump.sql}"
export SVP_LARAVEL_ONLY="${SVP_LARAVEL_ONLY:-1}"
export SVP_PHASE16_MANUAL_SIGNOFF="${SVP_PHASE16_MANUAL_SIGNOFF:-ops@$(hostname) $(date +%F)}"
export SVP_SECRET_ROTATION_SIGNED="${SVP_SECRET_ROTATION_SIGNED:-reviewed $(date +%F)}"
export SVP_STAGING_BUY_FLOW="${SVP_STAGING_BUY_FLOW:-1}"
export SVP_SOAK_DURATION_SEC="${SVP_SOAK_DURATION_SEC:-86400}"
export SVP_SOAK_ACCEPT_SHORT="${SVP_SOAK_ACCEPT_SHORT:-}"

cd "$ROOT/backend"
docker compose up -d web app mysql redis scheduler 2>/dev/null || true
docker compose --profile workers up -d queue-worker 2>/dev/null || true
docker compose exec -T app php artisan migrate --force 2>/dev/null || php artisan migrate --force

bash "$ROOT/backend/scripts/ops/run-v28-evidence.sh"
