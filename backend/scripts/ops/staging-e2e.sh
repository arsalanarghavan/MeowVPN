#!/usr/bin/env bash
# Staging E2E smoke — HTTP checks + optional auth bootstrap.
set -euo pipefail

BASE_URL="${SVP_BASE_URL:-http://localhost:8080}"
FAIL=0

check() {
  local name="$1" url="$2" expect="$3"
  local code
  code="$(curl -s -o /dev/null -w '%{http_code}' "$url" || echo 000)"
  if [[ "$code" == "$expect" ]]; then
    echo "OK  $name"
  else
    echo "FAIL $name expected=$expect got=$code"
    FAIL=1
  fi
}

check_json_ok() {
  local name="$1" url="$2"
  if curl -sf "$url" | grep -q '"ok":true\|"success":true\|"isLoggedIn"'; then
    echo "OK  $name (json)"
  else
    echo "FAIL $name json payload"
    FAIL=1
  fi
}

echo "=== Staging E2E base=$BASE_URL ==="
check "health live" "${BASE_URL}/health" "200"
check "health ready" "${BASE_URL}/health/ready" "200"
check "metrics" "${BASE_URL}/metrics" "200"
check "dashboard shell" "${BASE_URL}/dashboard/" "200"
check "portal info" "${BASE_URL}/info" "200"
check_json_ok "bootstrap logged-out" "${BASE_URL}/api/v1/bootstrap"
check "health deep" "${BASE_URL}/health/deep" "200"
check "sanctum csrf" "${BASE_URL}/sanctum/csrf-cookie" "204"

RELAY_URL="${SVP_RELAY_HEALTH_URL:-}"
if [[ -n "$RELAY_URL" ]]; then
  check "relay health" "$RELAY_URL" "200"
else
  echo "SKIP relay (set SVP_RELAY_HEALTH_URL)"
fi

echo ""
echo "Webhook smoke (optional): SVP_WEBHOOK_SECRET + curl POST ${BASE_URL}/api/v1/webhook/telegram/\$SECRET"
echo "Relay config (optional): curl -H X-SVP-RELAY-SECRET:... ${BASE_URL}/api/v1/relay/config"
echo "Manual: login dashboard, bot message, receipt approve, portal ?svp_adm=1 ?svp_p=1"
echo "PHPUnit: cd backend && php artisan test --filter='Portal|Webhook'"

exit "$FAIL"
