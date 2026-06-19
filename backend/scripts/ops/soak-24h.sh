#!/usr/bin/env bash
# 24h soak monitor — polls health endpoints and logs failures.
set -euo pipefail

BASE_URL="${SVP_BASE_URL:-http://localhost}"
INTERVAL="${SVP_SOAK_INTERVAL_SEC:-300}"
DURATION="${SVP_SOAK_DURATION_SEC:-86400}"
LOG_FILE="${SVP_SOAK_LOG:-/tmp/svp-soak.log}"

end=$((SECONDS + DURATION))
fail_count=0
echo "soak start base=$BASE_URL interval=${INTERVAL}s duration=${DURATION}s" | tee -a "$LOG_FILE"

while (( SECONDS < end )); do
  ts="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  for path in /health /health/ready; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}${path}" || echo 000)"
    if [[ "$code" != "200" ]]; then
      echo "$ts FAIL $path http=$code" | tee -a "$LOG_FILE"
      fail_count=$((fail_count + 1))
    else
      echo "$ts OK $path" >> "$LOG_FILE"
    fi
  done
  sleep "$INTERVAL"
done

echo "soak complete FAIL count: $fail_count" | tee -a "$LOG_FILE"
if [[ "$fail_count" -gt 0 ]]; then
  exit 1
fi
