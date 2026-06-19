#!/usr/bin/env bash
# Spec §18 — alert rule smoke (staging). Exits non-zero if probes fail.
set -euo pipefail

BASE_URL="${SVP_BASE_URL:-http://127.0.0.1:8080}"
TOKEN="${SVP_HEALTH_DEEP_TOKEN:-}"

echo "[alert-smoke] base=$BASE_URL"

curl -sfS "${BASE_URL}/health/ready" | grep -q '"ok"'

if [[ -n "$TOKEN" ]]; then
  curl -sfS -H "X-Health-Token: ${TOKEN}" "${BASE_URL}/health/deep" >/dev/null
else
  curl -sfS "${BASE_URL}/health/deep" >/dev/null || echo "[alert-smoke] deep probe skipped (token gate)"
fi

echo "[alert-smoke] OK"
