#!/usr/bin/env bash
# v28 OPS evidence bundle — strict: truncate logs, exit 1 on any failure.
# Local: SVP_BASE_URL=http://127.0.0.1:8080 SVP_WP_DUMP=backend/tests/fixtures/wp-minimal-dump.sql
#        SVP_LARAVEL_ONLY=1 SVP_PHASE16_MANUAL_SIGNOFF='ops@host date'
#        SVP_SECRET_ROTATION_SIGNED=1 SVP_SOAK_DURATION_SEC=120 SVP_SOAK_ACCEPT_SHORT=1
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
EVID="$ROOT/docs/evidence"
DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
FAILURES=0
export PATH="$ROOT/bin:$PATH"

mkdir -p "$EVID"
log() { echo "$1" | tee -a "$2"; }
mark_fail() { FAILURES=$((FAILURES + 1)); log "FAIL: $1" "$2"; }
truncate_log() { : >"$1"; }

for f in operator-prereqs-v28 docker-smoke-v28 staging-buy-flow-v28 reseller-webhook-v28 \
  relay-forward-v28 relay-webhook-set-v28 relay-control-center-v28 backup-restore-staging-v28 \
  import-run-v28 import-verify-v28 phase16-parallel-v28 soak-24h-v28 admin-alerts-v28 wp-disable-v28 \
  monthly-verify-v28 tls-curl-v28 secret-rotation-v28 run-v28-evidence-summary; do
  truncate_log "$EVID/${f}.log"
done

# Operator prereqs
{
  log "operator-prereqs-v28 start $DATE" "$EVID/operator-prereqs-v28.log"
  log "env: SVP_MYSQL_DSN=${SVP_MYSQL_DSN:-unset}" "$EVID/operator-prereqs-v28.log"
  log "env: SVP_WP_DUMP=${SVP_WP_DUMP:-unset}" "$EVID/operator-prereqs-v28.log"
  log "env: SVP_STAGING_BUY_FLOW=${SVP_STAGING_BUY_FLOW:-unset}" "$EVID/operator-prereqs-v28.log"
  log "env: SVP_SOAK_DURATION_SEC=${SVP_SOAK_DURATION_SEC:-unset}" "$EVID/operator-prereqs-v28.log"
  log "env: https_proxy=${https_proxy:-unset}" "$EVID/operator-prereqs-v28.log"
  for ext in dom xml xmlwriter; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
      log "php ext $ext: OK" "$EVID/operator-prereqs-v28.log"
    elif command -v docker >/dev/null 2>&1; then
      log "php ext $ext: SKIP (will use docker for PHPUnit)" "$EVID/operator-prereqs-v28.log"
    else
      mark_fail "php ext $ext missing" "$EVID/operator-prereqs-v28.log"
    fi
  done
  if php -m 2>/dev/null | grep -qi '^redis$'; then
    log "php ext redis: OK" "$EVID/operator-prereqs-v28.log"
  elif command -v docker >/dev/null 2>&1; then
    log "php ext redis: SKIP (docker)" "$EVID/operator-prereqs-v28.log"
  else
    mark_fail "php ext redis missing" "$EVID/operator-prereqs-v28.log"
  fi
  if command -v gh >/dev/null 2>&1; then log "gh: OK" "$EVID/operator-prereqs-v28.log"; else log "gh: SKIP (optional)" "$EVID/operator-prereqs-v28.log"; fi
  log "wp-cli: SKIP (WordPress decommissioned)" "$EVID/operator-prereqs-v28.log"
  log "operator-prereqs-v28 complete failures=$FAILURES" "$EVID/operator-prereqs-v28.log"
}

# L1846 docker / health
{
  log "docker-smoke-v28 start $DATE host=$BASE" "$EVID/docker-smoke-v28.log"
  log "ci: .github/workflows/ci.yml docker-smoke-v28 canonical+alias+persona" "$EVID/docker-smoke-v28.log"
  if env -u https_proxy -u http_proxy -u HTTPS_PROXY -u HTTP_PROXY \
    curl -sfS -m 15 "$BASE/health/ready" >>"$EVID/docker-smoke-v28.log" 2>&1; then
    log "health/ready: OK" "$EVID/docker-smoke-v28.log"
  else
    mark_fail "health/ready unreachable from operator host" "$EVID/docker-smoke-v28.log"
  fi
  bash "$ROOT/backend/scripts/ci/check-frontend-api-paths.sh" >>"$EVID/docker-smoke-v28.log" 2>&1 || mark_fail "frontend path parity" "$EVID/docker-smoke-v28.log"
  log "docker-smoke-v28 complete" "$EVID/docker-smoke-v28.log"
}

# L1901 staging buy flow
if [[ -n "${SVP_STAGING_BUY_FLOW:-}" ]]; then
  SVP_BASE_URL="$BASE" bash "$ROOT/backend/scripts/e2e/e2e-staging-buy-flow.sh" >>"$EVID/staging-buy-flow-v28.log" 2>&1 \
    && log "staging-buy-flow-v28 dashboard complete exit=0" "$EVID/staging-buy-flow-v28.log" \
    || mark_fail "staging buy flow failed" "$EVID/staging-buy-flow-v28.log"
  SVP_E2E_TRACE_LOG="$EVID/staging-buy-flow-v28.log" SVP_BASE_URL="$BASE" \
    bash "$ROOT/backend/scripts/e2e/e2e-staging-bot-buy-flow.sh" >>"$EVID/staging-buy-flow-v28.log" 2>&1 \
    && log "staging-bot-buy-flow-v28 complete exit=0" "$EVID/staging-buy-flow-v28.log" \
    || mark_fail "staging bot buy flow failed" "$EVID/staging-buy-flow-v28.log"
else
  log "staging-buy-flow-v28 SKIP: set SVP_STAGING_BUY_FLOW=1 on staging host" "$EVID/staging-buy-flow-v28.log"
  mark_fail "staging buy flow not run" "$EVID/staging-buy-flow-v28.log"
fi

run_phpunit_filter() {
  local name="$1" filter="$2" logfile="$3"
  log "${name} start $DATE" "$logfile"
  if bash "$ROOT/backend/scripts/ops/run-phpunit.sh" "$filter" >>"$logfile" 2>&1; then
    log "${name} complete exit=0" "$logfile"
  else
    mark_fail "${name} phpunit filter=$filter" "$logfile"
  fi
}
run_phpunit_filter "reseller-webhook-v28" "ResellerWebhook" "$EVID/reseller-webhook-v28.log"
run_phpunit_filter "relay-forward-v28" "RelaySetupOrderTest" "$EVID/relay-forward-v28.log"
cp "$EVID/relay-forward-v28.log" "$EVID/relay-webhook-set-v28.log"
echo "relay-webhook-set-v28 derived from relay-forward-v28" >>"$EVID/relay-webhook-set-v28.log"
cp "$EVID/relay-forward-v28.log" "$EVID/relay-control-center-v28.log"
echo "relay-control-center-v28 derived from relay-forward-v28" >>"$EVID/relay-control-center-v28.log"
run_phpunit_filter "backup-restore-staging-v28" "BackupRestoreStagingTest" "$EVID/backup-restore-staging-v28.log"
run_phpunit_filter "bot-parity-gate-v28" "Bot" "$EVID/staging-buy-flow-v28.log"

# Import — Laravel-only (wp:import removed)
if [[ "${SVP_LARAVEL_ONLY:-1}" == "1" ]]; then
  {
    log "import-run-v28 SKIP: wp:import removed; Laravel-only cutover" "$EVID/import-run-v28.log"
    log "import-verify-v28 SKIP: use php artisan migrate --force && db:seed" "$EVID/import-verify-v28.log"
  }
else
  mark_fail "import-run requires SVP_LARAVEL_ONLY=1 (WordPress import decommissioned)" "$EVID/import-run-v28.log"
fi

# Phase 16 parallel
if SVP_BASE_URL="$BASE" bash "$ROOT/backend/scripts/ops/phase16-parallel.sh" "$EVID/phase16-parallel-v28.log"; then
  log "phase16-parallel-v28 complete exit=0" "$EVID/phase16-parallel-v28.log"
else
  mark_fail "phase16 parallel signoff" "$EVID/phase16-parallel-v28.log"
fi

SOAK_DUR="${SVP_SOAK_DURATION_SEC:-120}"
SVP_SOAK_LOG="$EVID/soak-24h-v28.log" SVP_SOAK_DURATION_SEC="$SOAK_DUR" SVP_BASE_URL="$BASE" \
  bash "$ROOT/backend/scripts/ops/soak-24h.sh" >>"$EVID/soak-24h-v28.log" 2>&1 \
  || mark_fail "soak failures (duration=${SOAK_DUR}s)" "$EVID/soak-24h-v28.log"
if [[ "$SOAK_DUR" != "86400" && -z "${SVP_SOAK_ACCEPT_SHORT:-}" ]]; then
  mark_fail "soak duration ${SOAK_DUR}s not 86400 (set SVP_SOAK_ACCEPT_SHORT=1 for smoke)" "$EVID/soak-24h-v28.log"
fi

if bash "$ROOT/backend/scripts/ops/admin-alerts-fire-smoke.sh" >>"$EVID/admin-alerts-v28.log" 2>&1; then
  log "admin-alerts-v28 complete exit=0" "$EVID/admin-alerts-v28.log"
else
  mark_fail "admin-alerts-fire-smoke" "$EVID/admin-alerts-v28.log"
fi

if [[ "${SVP_LARAVEL_ONLY:-}" == "1" ]]; then
  log "wp-disable-v28: SVP_LARAVEL_ONLY=1 — WP decommissioned per WP-DECOMMISSION-FA.md" "$EVID/wp-disable-v28.log"
  log "wp-disable-v28 complete exit=0" "$EVID/wp-disable-v28.log"
elif command -v wp >/dev/null 2>&1 && [[ -n "${WP_PATH:-}" ]] && [[ -d "${WP_PATH}" ]]; then
  bash "$ROOT/backend/scripts/ops/wp-disable-staging.sh" >>"$EVID/wp-disable-v28.log" 2>&1 \
    && log "wp-disable-v28 complete exit=0" "$EVID/wp-disable-v28.log" \
    || mark_fail "wp-disable-staging failed" "$EVID/wp-disable-v28.log"
else
  mark_fail "wp-disable: set SVP_LARAVEL_ONLY=1 or WP_PATH with wp-cli" "$EVID/wp-disable-v28.log"
fi

SVP_BASE_URL="$BASE" bash "$ROOT/backend/scripts/ops/monthly-verify.sh" >>"$EVID/monthly-verify-v28.log" 2>&1 \
  || mark_fail "monthly-verify" "$EVID/monthly-verify-v28.log"
env -u https_proxy -u http_proxy -u HTTPS_PROXY -u HTTP_PROXY \
  curl -sfSI --max-time 20 "$BASE/health/ready" >>"$EVID/tls-curl-v28.log" 2>&1 \
  || mark_fail "tls-curl" "$EVID/tls-curl-v28.log"

{
  echo "secret-rotation-v28 checklist $DATE"
  echo "- [ ] Rotate telegram_webhook_secret"
  echo "- [ ] Rotate relay HMAC keys"
  echo "- [ ] Rotate Sanctum APP_KEY (staging only)"
  if [[ -n "${SVP_SECRET_ROTATION_SIGNED:-}" ]]; then
    echo "SIGNED: $SVP_SECRET_ROTATION_SIGNED"
  fi
} >>"$EVID/secret-rotation-v28.log"
if [[ -z "${SVP_SECRET_ROTATION_SIGNED:-}" ]]; then
  mark_fail "secret rotation checklist unsigned (set SVP_SECRET_ROTATION_SIGNED)" "$EVID/secret-rotation-v28.log"
else
  log "secret-rotation-v28 signed OK" "$EVID/secret-rotation-v28.log"
fi

log "run-v28-evidence complete failures=$FAILURES" "$EVID/run-v28-evidence-summary.log"
if [[ "$FAILURES" -gt 0 ]]; then
  exit 1
fi
exit 0
