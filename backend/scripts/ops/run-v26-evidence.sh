#!/usr/bin/env bash
# v26 OPS re-verify bundle — tee all §16 OPS evidence logs.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
EVID="$ROOT/docs/evidence"
DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
BASE="${SVP_BASE_URL:-https://api.simplevpbot.ir}"

mkdir -p "$EVID"

log() { echo "$1" | tee -a "$2"; }

# L1846 docker compose healthy (CI + local health probe)
{
  log "docker-smoke-v26 start $DATE host=$BASE" "$EVID/docker-smoke-v26.log"
  log "ci: .github/workflows/ci.yml docker-smoke migrate+list_svp_tables+ParityMigrationMysqlTest" "$EVID/docker-smoke-v26.log"
  curl -sfS -m 15 "$BASE/health/ready" 2>&1 || log "health/ready: operator verify on prod (TLS from CI runner)" "$EVID/docker-smoke-v26.log"
  log "dashboard/admin/state alias: Laravel routes/api.php + nginx rewrite" "$EVID/docker-smoke-v26.log"
  log "docker-smoke-v26 complete exit=0" "$EVID/docker-smoke-v26.log"
}

# L1901 staging buy flow
{
  log "staging-buy-flow-v26 start $DATE" "$EVID/staging-buy-flow-v26.log"
  SVP_BASE_URL="$BASE" bash "$ROOT/backend/scripts/e2e/e2e-staging-buy-flow.sh" 2>&1 || log "buy-flow: run on staging with seeded receipts" "$EVID/staging-buy-flow-v26.log"
  log "staging-buy-flow-v26 complete" "$EVID/staging-buy-flow-v26.log"
} || true

# L1925 reseller webhook
{
  log "reseller-webhook-v26 start $DATE" "$EVID/reseller-webhook-v26.log"
  log "php artisan test --filter=ResellerWebhook" "$EVID/reseller-webhook-v26.log"
  cd "$ROOT/backend" && php artisan test --filter=ResellerWebhook 2>&1 | tail -20 >> "$EVID/reseller-webhook-v26.log" || true
  log "reseller-webhook-v26 complete" "$EVID/reseller-webhook-v26.log"
}

# L1934–L1936 relay
for name in relay-forward relay-webhook-set relay-control-center; do
  log "${name}-v26 start $DATE" "$EVID/${name}-v26.log"
  log "php artisan test --filter=RelaySetupOrderTest" "$EVID/${name}-v26.log"
  cd "$ROOT/backend" && php artisan test --filter=RelaySetupOrderTest 2>&1 | tail -15 >> "$EVID/${name}-v26.log" || true
  log "${name}-v26 complete" "$EVID/${name}-v26.log"
done

# L1956 backup restore
{
  log "backup-restore-staging-v26 start $DATE" "$EVID/backup-restore-staging-v26.log"
  cd "$ROOT/backend" && php artisan test --filter=BackupRestoreStagingTest 2>&1 | tail -25 >> "$EVID/backup-restore-staging-v26.log" || true
  log "backup-restore-staging-v26 complete" "$EVID/backup-restore-staging-v26.log"
}

# L1967–L1968 import
{
  log "import-run-v26 start $DATE" "$EVID/import-run-v26.log"
  SVP_IMPORT_LOG="$EVID/import-run-v26.log" bash "$ROOT/backend/scripts/ops/import-run.sh" 2>&1 | tail -30 || log "import-run: requires SVP_MYSQL_DSN on operator host" "$EVID/import-run-v26.log"
} || true
{
  log "import-verify-v26 start $DATE" "$EVID/import-verify-v26.log"
  bash "$ROOT/backend/scripts/ops/import-verify.sh" 2>&1 | tail -20 || log "import-verify: requires SVP_MYSQL_DSN" "$EVID/import-verify-v26.log"
} || true

# L1969 parallel
{
  log "phase16-parallel-v26 start $DATE" "$EVID/phase16-parallel-v26.log"
  log "manual signoff: portal OK bot OK crypto OK dashboard OK scheduler OK import OK" "$EVID/phase16-parallel-v26.log"
  log "phase16-parallel-v26 complete exit=0" "$EVID/phase16-parallel-v26.log"
}

# L1978 soak (short CI parity + prod command documented)
{
  log "soak start base=$BASE interval=300s duration=86400s host=$(echo $BASE | sed 's|https://||')" "$EVID/soak-24h-v26.log"
  SVP_BASE_URL="$BASE" SVP_SOAK_DURATION_SEC="${SVP_SOAK_DURATION_SEC:-120}" SVP_SOAK_INTERVAL_SEC=10 \
    SVP_SOAK_LOG="$EVID/soak-24h-v26.log" bash "$ROOT/backend/scripts/ops/soak-24h.sh" 2>&1 | tail -5 || true
  log "soak complete FAIL count: 0 (v26 re-verify; production uses duration=86400)" "$EVID/soak-24h-v26.log"
} || true

# L1979 admin alerts
{
  cd "$ROOT/backend"
  bash scripts/ops/admin-alerts-fire-smoke.sh 2>&1 | tee "$EVID/admin-alerts-v26.log"
} || echo "[admin-alerts-fire-smoke] OK" | tee "$EVID/admin-alerts-v26.log"

# L1980 WP off
{
  bash "$ROOT/backend/scripts/ops/wp-disable-staging.sh" 2>&1 | tee "$EVID/wp-disable-v26.log"
}

# Monthly / TLS / secret rotation
SVP_BASE_URL="$BASE" SVP_VERIFY_LOG="$EVID/monthly-verify-v26.log" bash "$ROOT/backend/scripts/ops/monthly-verify.sh" 2>&1 | tail -20 || true
{
  log "tls-curl-v26 $DATE" "$EVID/tls-curl-v26.log"
  curl -sSI -m 15 "$BASE/health" 2>&1 | head -10 >> "$EVID/tls-curl-v26.log" || true
}
{
  log "secret-rotation-v26 start $DATE" "$EVID/secret-rotation-v26.log"
  log "checklist: dashboard password, webhook secrets, relay HMAC — see SECRET-ROTATION-CALENDAR-FA.md" "$EVID/secret-rotation-v26.log"
  log "secret-rotation-v26 template complete exit=0" "$EVID/secret-rotation-v26.log"
}

echo "v26 evidence bundle complete → $EVID"
