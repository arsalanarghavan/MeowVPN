#!/usr/bin/env bash
# Phase 16 — parallel WP+Laravel staging signoff (automated checks + manual attestation).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
LOG="${1:-$ROOT/docs/evidence/phase16-parallel-v28.log}"
FAIL=0

log() { echo "$1" | tee -a "$LOG"; }

: >"$LOG"
log "phase16-parallel start $(date -u +%Y-%m-%dT%H:%M:%SZ) base=$BASE"

# Automated Laravel-only checks (WP may still run read-only during parallel window).
if bash "$ROOT/backend/scripts/ops/staging-cutover-checklist.sh" >>"$LOG" 2>&1; then
  log "staging-cutover-checklist: OK"
else
  log "FAIL: staging-cutover-checklist"
  FAIL=1
fi

# Laravel-only idempotency (wp:import removed).
log "migrate:fresh smoke: SKIP (use staging-cutover-checklist only)"

if [[ -n "${SVP_PHASE16_MANUAL_SIGNOFF:-}" ]]; then
  log "manual signoff: $SVP_PHASE16_MANUAL_SIGNOFF"
  log "phase16-parallel complete exit=0"
  exit 0
fi

if [[ "${SVP_LARAVEL_ONLY:-}" == "1" ]]; then
  log "SVP_LARAVEL_ONLY=1 — WP parallel window closed; Laravel authoritative"
  log "phase16-parallel complete exit=0"
  exit 0
fi

log "INFO: set SVP_PHASE16_MANUAL_SIGNOFF='operator@host YYYY-MM-DD' after staging parallel window"
log "INFO: or SVP_LARAVEL_ONLY=1 when WP decommissioned (see WP-DECOMMISSION-FA.md)"
log "FAIL: phase16 manual attestation pending"
exit 1
