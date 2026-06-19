#!/usr/bin/env bash
# Staging buy-flow API smoke: login → receipts state → approve mutate.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

bash "$(dirname "$0")/e2e-dashboard-api.sh"

curl -sf -c "$COOKIE_JAR" "${BASE}/sanctum/csrf-cookie" >/dev/null
TOKEN="$(grep XSRF-TOKEN "$COOKIE_JAR" | awk '{print $7}' | tail -1 | python3 -c 'import sys,urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))')"

curl -sf -b "$COOKIE_JAR" -H "Accept: application/json" \
  "${BASE}/api/v1/admin/state?tab=receipts&receipts_status=pending" | grep -q '"receipts"'

RECEIPT_ID="$(curl -sf -b "$COOKIE_JAR" -H "Accept: application/json" \
  "${BASE}/api/v1/admin/state?tab=receipts&receipts_status=pending" \
  | python3 -c 'import sys,json; d=json.load(sys.stdin); r=(d.get("receipts") or [{}])[0]; print(r.get("id") or 0)')"

if [[ "${RECEIPT_ID}" != "0" && -n "${RECEIPT_ID}" ]]; then
  curl -sf -b "$COOKIE_JAR" \
    -H "Content-Type: application/json" \
    -H "X-XSRF-TOKEN: ${TOKEN}" \
    -H "Accept: application/json" \
    -d "{\"op\":\"receipt_action\",\"receipt_id\":${RECEIPT_ID},\"action\":\"approve\"}" \
    "${BASE}/api/v1/admin/mutate" | grep -q '"ok":true'
  echo "[e2e-staging-buy-flow] receipt ${RECEIPT_ID} approve OK"
else
  echo "[e2e-staging-buy-flow] no pending receipt — state OK only"
fi

echo "[e2e-staging-buy-flow] OK"
