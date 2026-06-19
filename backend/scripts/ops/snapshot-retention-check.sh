#!/usr/bin/env bash
# WP snapshot retention policy check — docs/WP-SNAPSHOT-RETENTION-POLICY-FA.md
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
RETENTION_DAYS="${SVP_WP_SNAPSHOT_RETENTION_DAYS:-30}"
LOG="${SVP_SNAPSHOT_LOG:-$ROOT/docs/evidence/snapshot-retention-$(date +%F).log}"
FAIL=0

log() { echo "$1" | tee -a "$LOG"; }
: >"$LOG"
log "snapshot-retention check $(date -u +%Y-%m-%dT%H:%M:%SZ) retention_days=$RETENTION_DAYS"

shopt -s nullglob
for f in "$ROOT"/wp-final-*.sql "$ROOT"/docs/evidence/mysql-dump-prod-*.log; do
  if [[ -f "$f" ]]; then
    age_days=$(( ( $(date +%s) - $(stat -c %Y "$f") ) / 86400 ))
    log "file=$f age_days=$age_days"
    if [[ "$age_days" -gt "$RETENTION_DAYS" && -z "${SVP_SNAPSHOT_DELETE_APPROVED:-}" ]]; then
      log "WARN: past retention — set SVP_SNAPSHOT_DELETE_APPROVED=1 after ops ticket"
      FAIL=1
    fi
  fi
done

if [[ "$FAIL" -eq 0 ]]; then
  log "snapshot-retention OK"
  exit 0
fi
log "snapshot-retention pending operator approval for deletion"
exit 1
