#!/usr/bin/env bash
# Pre-cutover checklist — run on staging before WP decommission.
set -euo pipefail

BASE_URL="${SVP_BASE_URL:-http://localhost}"
FAIL=0

check() {
  local name="$1"
  local code
  code="$(curl -s -o /dev/null -w '%{http_code}' "$2" || echo 000)"
  if [[ "$code" == "$3" ]]; then
    echo "OK   $name ($code)"
  else
    echo "FAIL $name expected=$3 got=$code"
    FAIL=1
  fi
}

echo "=== Staging cutover checklist base=$BASE_URL ==="

check "health live" "${BASE_URL}/health" "200"
check "health ready" "${BASE_URL}/health/ready" "200"
check "metrics" "${BASE_URL}/metrics" "200"
check "dashboard shell" "${BASE_URL}/dashboard/" "200"
check "bootstrap (optional auth)" "${BASE_URL}/api/v1/bootstrap" "200"
check "portal info route" "${BASE_URL}/info" "200"

RELAY_URL="${SVP_RELAY_HEALTH_URL:-}"
if [[ -n "$RELAY_URL" ]]; then
  check "relay health" "$RELAY_URL" "200"
else
  echo "SKIP relay health (set SVP_RELAY_HEALTH_URL)"
fi

if [[ -f "${BASH_SOURCE%/*}/soak-24h.sh" ]]; then
  echo "INFO soak: SVP_SOAK_DURATION_SEC=300 ${BASH_SOURCE%/*}/soak-24h.sh (5m smoke)"
  echo "INFO full soak: SVP_SOAK_DURATION_SEC=86400 ${BASH_SOURCE%/*}/soak-24h.sh"
fi

if [[ -f "${BASH_SOURCE%/*}/../../../docs/WP-DECOMMISSION-FA.md" ]]; then
  echo "OK   WP decommission doc present"
else
  echo "FAIL WP-DECOMMISSION-FA.md missing"
  FAIL=1
fi

echo ""
echo "Manual checks:"
echo "  - relay forwards to ${BASE_URL}/api/v1/webhook/telegram/<secret>"
echo "  - portal admin ?svp_adm=1 and subscription ?svp_p=1"
echo "  - wp:import --verify-only clean"
echo "  - scheduler container running (php artisan schedule:list)"

if [[ "$FAIL" -eq 0 ]]; then
  echo "=== All automated checks passed ==="
  exit 0
fi

echo "=== Checklist failed ==="
exit 1
