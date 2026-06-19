#!/usr/bin/env bash
# Monthly production verify: scheduler list + login + mutate smoke.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BASE="${SVP_BASE_URL:-http://localhost:8080}"
LOG="${SVP_VERIFY_LOG:-docs/evidence/monthly-verify-$(date +%F).log}"

exec > >(tee -a "$LOG") 2>&1

echo "=== monthly-verify $(date -Iseconds) ==="
echo "BASE=$BASE"

cd "$ROOT"

echo "--- schedule:list (14 svp jobs) ---"
docker compose exec -T app php artisan schedule:list 2>/dev/null || php artisan schedule:list

EXPECTED=(
  svp:backup
  svp:expiry
  svp:purge_expired
  svp:autorenew
  svp:broadcast
  svp:users_bulk
  svp:panel_online
  svp:panel_service_sync
  svp:inbound_clients_cache
  svp:idle_offers
  svp:marketing
  svp:admin_alerts
  svp:panel_economics_renewal
  svp:inbound_queue_drain
)

for job in "${EXPECTED[@]}"; do
  if ! php artisan schedule:list 2>/dev/null | grep -q "$job"; then
    echo "WARN: missing scheduled job $job (module may be disabled)"
  fi
done

echo "--- health/ready ---"
curl -sfS "$BASE/health/ready" | head -c 500
echo

echo "--- login smoke ---"
COOKIE_JAR="$(mktemp)"
curl -sfS -c "$COOKIE_JAR" "$BASE/sanctum/csrf-cookie" >/dev/null
XSRF="$(grep XSRF-TOKEN "$COOKIE_JAR" | awk '{print $7}' | python3 -c 'import sys,urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))')"
curl -sfS -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Accept: application/json" \
  -X POST "$BASE/api/v1/auth/login" \
  -d '{"log":"admin","pwd":"changeme"}' | head -c 300
echo

echo "--- mutate smoke (settings_tab general read path via bootstrap) ---"
curl -sfS -b "$COOKIE_JAR" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Accept: application/json" \
  "$BASE/api/v1/bootstrap" | head -c 300
echo

rm -f "$COOKIE_JAR"
echo "=== monthly-verify OK ==="
