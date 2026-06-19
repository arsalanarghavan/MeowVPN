#!/usr/bin/env bash
# Post-deploy 48h monitor — RUNBOOK-PRODUCTION-FA.md
set -euo pipefail

BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
DURATION="${SVP_POST_CUTOVER_SEC:-172800}"
INTERVAL="${SVP_POST_CUTOVER_INTERVAL_SEC:-300}"
LOG="${SVP_POST_CUTOVER_LOG:-docs/evidence/post-cutover-monitor-$(date +%F).log}"

end=$((SECONDS + DURATION))
fail=0
echo "post-cutover-monitor start base=$BASE duration=${DURATION}s" | tee -a "$LOG"

while (( SECONDS < end )); do
  ts="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  for path in /health /health/ready /metrics; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "${BASE}${path}" || echo 000)"
    if [[ "$code" != "200" ]]; then
      echo "$ts FAIL $path http=$code" | tee -a "$LOG"
      fail=$((fail + 1))
    fi
  done
  sleep "$INTERVAL"
done

echo "post-cutover-monitor complete fail_count=$fail" | tee -a "$LOG"
[[ "$fail" -eq 0 ]]
