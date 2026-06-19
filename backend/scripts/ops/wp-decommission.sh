#!/usr/bin/env bash
# Pre-flight checks before WP decommission (see docs/WP-DECOMMISSION-FA.md).
set -euo pipefail

BASE_URL="${SVP_BASE_URL:-http://localhost}"
FAIL=0

check() {
  local name="$1"
  local url="$2"
  local code
  code="$(curl -s -o /dev/null -w '%{http_code}' "$url" || echo 000)"
  if [[ "$code" == "200" ]]; then
    echo "OK  $name ($code)"
  else
    echo "FAIL $name ($code) $url"
    FAIL=1
  fi
}

echo "WP decommission pre-flight"
check "health" "${BASE_URL}/health"
check "ready" "${BASE_URL}/health/ready"
check "bootstrap" "${BASE_URL}/api/v1/bootstrap"
check "portal-sub" "${BASE_URL}/info"

if [[ "$FAIL" -ne 0 ]]; then
  echo "Pre-flight failed — do not decommission WP yet."
  exit 1
fi

echo "Pre-flight passed. Follow docs/WP-DECOMMISSION-FA.md for cutover steps."
