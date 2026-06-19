#!/usr/bin/env bash
# Lightweight load smoke for health + bootstrap (not full k6).
set -euo pipefail

BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
REQS="${SVP_LOAD_REQUESTS:-50}"

ok=0
fail=0
for i in $(seq 1 "$REQS"); do
  if curl -sf "${BASE}/health/ready" >/dev/null; then
    ok=$((ok + 1))
  else
    fail=$((fail + 1))
  fi
done
echo "load-smoke ready: ok=$ok fail=$fail total=$REQS"
[[ "$fail" -eq 0 ]]
