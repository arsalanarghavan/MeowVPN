#!/usr/bin/env bash
# Bot parity buy-flow evidence: health + webhook HTTP trace + PHPUnit chain + dashboard receipt approve.
set -euo pipefail
REPO="$(cd "$(dirname "$0")/../../.." && pwd)"
BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
TRACE_LOG="${SVP_E2E_TRACE_LOG:-}"

log() { echo "[e2e-staging-bot-buy-flow] $1"; }

if [[ -n "${TRACE_LOG}" ]]; then
  mkdir -p "$(dirname "${TRACE_LOG}")"
  exec > >(tee -a "${TRACE_LOG}") 2>&1
fi

log "start ${DATE} base=${BASE}"

cd "${REPO}/backend"

log "health/ready"
curl -v -sfS -m 15 "${BASE}/health/ready" 2>&1
log "health/ready OK"

SECRET="${SVP_TELEGRAM_WEBHOOK_SECRET:-}"
if [[ -n "${SECRET}" ]]; then
  log "webhook POST telegram callback smoke"
  curl -v -sfS -m 20 -X POST "${BASE}/api/v1/webhook/telegram/${SECRET}" \
    -H "Content-Type: application/json" \
    -d '{"update_id":1,"callback_query":{"id":"cb1","from":{"id":900001},"message":{"chat":{"id":900001}},"data":"buy:g:all"}}' \
    2>&1 | tee /dev/stderr | grep -q '"ok"'
  log "webhook callback OK"
else
  log "webhook SKIP (set SVP_TELEGRAM_WEBHOOK_SECRET to exercise HTTP bot path)"
fi

log "PHPUnit bot buy→deliver chain"
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-/etc/php/8.3/cli/conf.d:${REPO}/backend/.php-ini}"
php artisan test \
  tests/Feature/Bot/BuyFlowCompleteTest.php \
  tests/Feature/Bot/BuyFlowEdgeCasesTest.php \
  tests/Feature/Bot/ConfigDeliveryCompleteTest.php \
  tests/Feature/Commerce/BuyFlowApproveDeliverTest.php \
  2>&1 | tee /dev/stderr

log "dashboard receipt approve chain"
SVP_BASE_URL="${BASE}" bash "$(dirname "$0")/e2e-staging-buy-flow.sh" 2>&1 | tee /dev/stderr

log "OK"
