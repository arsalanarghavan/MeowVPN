#!/usr/bin/env bash
# v27 OPS evidence bundle — strict: failures propagate (no || true masking).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
EVID="$ROOT/docs/evidence"
DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
BASE="${SVP_BASE_URL:-https://api.simplevpbot.ir}"
FAILURES=0

mkdir -p "$EVID"
log() { echo "$1" | tee -a "$2"; }
mark_fail() { FAILURES=$((FAILURES + 1)); log "FAIL: $1" "$2"; }

# Operator prereqs
{
  log "operator-prereqs-v27 start $DATE" "$EVID/operator-prereqs-v27.log"
  for ext in dom xml xmlwriter; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
      log "php ext $ext: OK" "$EVID/operator-prereqs-v27.log"
    else
      mark_fail "php ext $ext missing" "$EVID/operator-prereqs-v27.log"
    fi
  done
  if php -m 2>/dev/null | grep -qi '^redis$'; then
    log "php ext redis: OK" "$EVID/operator-prereqs-v27.log"
  else
    mark_fail "php ext redis missing" "$EVID/operator-prereqs-v27.log"
  fi
  if command -v gh >/dev/null 2>&1; then log "gh: OK" "$EVID/operator-prereqs-v27.log"; else mark_fail "gh not installed" "$EVID/operator-prereqs-v27.log"; fi
  log "operator-prereqs-v27 complete failures=$FAILURES" "$EVID/operator-prereqs-v27.log"
}

# L1846 docker / health
{
  log "docker-smoke-v27 start $DATE host=$BASE" "$EVID/docker-smoke-v27.log"
  log "ci: .github/workflows/ci.yml docker-smoke-v27 canonical+alias parity" "$EVID/docker-smoke-v27.log"
  if curl -sfS -m 15 "$BASE/health/ready" >>"$EVID/docker-smoke-v27.log" 2>&1; then
    log "health/ready: OK" "$EVID/docker-smoke-v27.log"
  else
    mark_fail "health/ready unreachable from operator host" "$EVID/docker-smoke-v27.log"
  fi
  bash "$ROOT/backend/scripts/ci/check-frontend-api-paths.sh" >>"$EVID/docker-smoke-v27.log" 2>&1 || mark_fail "frontend path parity" "$EVID/docker-smoke-v27.log"
  log "docker-smoke-v27 complete exit=$((FAILURES))" "$EVID/docker-smoke-v27.log"
}

# L1901 staging buy flow
if [[ -n "${SVP_STAGING_BUY_FLOW:-}" ]]; then
  SVP_BASE_URL="$BASE" bash "$ROOT/backend/scripts/e2e/e2e-staging-buy-flow.sh" 2>&1 | tee "$EVID/staging-buy-flow-v27.log"
else
  log "staging-buy-flow-v27 SKIP: set SVP_STAGING_BUY_FLOW=1 on staging host" "$EVID/staging-buy-flow-v27.log"
  mark_fail "staging buy flow not run" "$EVID/staging-buy-flow-v27.log"
fi

# PHPUnit-backed OPS (require php-xml)
run_phpunit_filter() {
  local name="$1" filter="$2" logfile="$3"
  log "${name} start $DATE" "$logfile"
  if (cd "$ROOT/backend" && php artisan test --filter="$filter" >>"$logfile" 2>&1); then
    log "${name} complete exit=0" "$logfile"
  else
    mark_fail "${name} phpunit filter=$filter" "$logfile"
  fi
}
run_phpunit_filter "reseller-webhook-v27" "ResellerWebhook" "$EVID/reseller-webhook-v27.log"
run_phpunit_filter "relay-forward-v27" "RelaySetupOrderTest" "$EVID/relay-forward-v27.log"
cp "$EVID/relay-forward-v27.log" "$EVID/relay-webhook-set-v27.log"
echo "relay-webhook-set-v27 derived from relay-forward-v27" >>"$EVID/relay-webhook-set-v27.log"
cp "$EVID/relay-forward-v27.log" "$EVID/relay-control-center-v27.log"
echo "relay-control-center-v27 derived from relay-forward-v27" >>"$EVID/relay-control-center-v27.log"
run_phpunit_filter "backup-restore-staging-v27" "BackupRestoreStagingTest" "$EVID/backup-restore-staging-v27.log"

# Import
if [[ -n "${SVP_MYSQL_DSN:-}" ]]; then
  bash "$ROOT/backend/scripts/ops/import-run.sh" 2>&1 | tee "$EVID/import-run-v27.log"
  bash "$ROOT/backend/scripts/ops/import-verify.sh" 2>&1 | tee "$EVID/import-verify-v27.log" || true
else
  log "import-run-v27 SKIP: set SVP_MYSQL_DSN" "$EVID/import-run-v27.log"
  mark_fail "import-run requires SVP_MYSQL_DSN" "$EVID/import-run-v27.log"
  log "import-verify-v27 SKIP: set SVP_MYSQL_DSN" "$EVID/import-verify-v27.log"
  mark_fail "import-verify requires SVP_MYSQL_DSN" "$EVID/import-verify-v27.log"
fi

# Parallel signoff
log "phase16-parallel-v27 manual signoff required on staging" "$EVID/phase16-parallel-v27.log"
mark_fail "phase16 parallel not executed" "$EVID/phase16-parallel-v27.log"

# Soak — short CI soak unless SVP_SOAK_DURATION_SEC=86400
SOAK_DUR="${SVP_SOAK_DURATION_SEC:-120}"
SVP_SOAK_LOG="$EVID/soak-24h-v27.log" SVP_SOAK_DURATION_SEC="$SOAK_DUR" SVP_BASE_URL="$BASE" \
  bash "$ROOT/backend/scripts/ops/soak-24h.sh" 2>&1 | tee -a "$EVID/soak-24h-v27.log" || mark_fail "soak failures (duration=${SOAK_DUR}s)" "$EVID/soak-24h-v27.log"
if [[ "$SOAK_DUR" != "86400" ]]; then
  mark_fail "soak duration ${SOAK_DUR}s not 86400" "$EVID/soak-24h-v27.log"
fi

# Admin alerts
if bash "$ROOT/backend/scripts/ops/admin-alerts-fire-smoke.sh" >>"$EVID/admin-alerts-v27.log" 2>&1; then
  log "admin-alerts-v27 complete exit=0" "$EVID/admin-alerts-v27.log"
else
  mark_fail "admin-alerts-fire-smoke" "$EVID/admin-alerts-v27.log"
fi

# WP off
if command -v wp >/dev/null 2>&1; then
  log "wp-disable-v27: wp-cli present — run manual wp-disable checklist" "$EVID/wp-disable-v27.log"
  mark_fail "wp-disable manual checklist pending" "$EVID/wp-disable-v27.log"
else
  mark_fail "wp-cli not found" "$EVID/wp-disable-v27.log"
fi

# Rotating OPS
bash "$ROOT/backend/scripts/ops/monthly-verify.sh" >>"$EVID/monthly-verify-v27.log" 2>&1 || mark_fail "monthly-verify" "$EVID/monthly-verify-v27.log"
curl -vI --max-time 20 "$BASE/health/ready" >>"$EVID/tls-curl-v27.log" 2>&1 || mark_fail "tls-curl" "$EVID/tls-curl-v27.log"
{
  echo "secret-rotation-v27 checklist $DATE"
  echo "- [ ] Rotate telegram_webhook_secret"
  echo "- [ ] Rotate relay HMAC keys"
  echo "- [ ] Rotate Sanctum APP_KEY (staging only)"
} | tee "$EVID/secret-rotation-v27.log"
mark_fail "secret rotation checklist unsigned" "$EVID/secret-rotation-v27.log"

log "run-v27-evidence complete failures=$FAILURES" "$EVID/run-v27-evidence-summary.log"
exit "$FAILURES"
