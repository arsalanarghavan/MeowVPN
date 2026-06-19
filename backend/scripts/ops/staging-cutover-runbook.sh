#!/usr/bin/env bash
# Staging cutover execution — import, post-import, checklist, soak smoke.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT/backend"

DSN="${SVP_MYSQL_DSN:-}"
DUMP="${SVP_WP_DUMP:-}"
PREFIX="${SVP_WP_PREFIX:-wp_}"
BASE_URL="${SVP_BASE_URL:-http://localhost:8080}"

echo "=== Staging cutover runbook base=$BASE_URL ==="

echo "1. migrate + seed (wp:import removed)"
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\SvpTestDataSeeder --force || true

echo "2. rebuild reseller closure"
php artisan svp:rebuild-reseller-closure

echo "3. register webhooks"
php artisan svp:register-webhooks --platform=both || true

echo "4. schedule list"
php artisan schedule:list

echo "5. automated checklist"
SVP_BASE_URL="$BASE_URL" bash scripts/ops/staging-cutover-checklist.sh

echo "6. staging E2E"
SVP_BASE_URL="$BASE_URL" bash scripts/ops/staging-e2e.sh

echo "7. short soak (5m — full 24h: SVP_SOAK_DURATION_SEC=86400)"
SVP_BASE_URL="$BASE_URL" SVP_SOAK_DURATION_SEC="${SVP_SOAK_DURATION_SEC:-300}" \
  SVP_SOAK_LOG="${ROOT}/docs/evidence/soak-staging-$(date +%F).log" \
  bash scripts/ops/soak-24h.sh

echo "Done — follow docs/WP-DECOMMISSION-FA.md and docs/evidence/CUTOVER-SIGNOFF-FA.md"
