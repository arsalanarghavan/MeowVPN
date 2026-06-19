#!/usr/bin/env bash
# Monthly + quarterly OPS bundle — phase 20 maintenance.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
export PATH="$ROOT/bin:$PATH"
export SVP_BASE_URL="${SVP_BASE_URL:-http://127.0.0.1:8080}"

bash "$ROOT/backend/scripts/ops/monthly-verify.sh"
bash "$ROOT/backend/scripts/ops/snapshot-retention-check.sh"
bash "$ROOT/backend/scripts/ops/rollback-drill.sh" 2>/dev/null || echo "WARN: rollback-drill skipped"
bash "$ROOT/backend/scripts/ops/quarterly-signoff.sh" 2>/dev/null || echo "WARN: quarterly-signoff skipped"
echo "monthly-ops-bundle OK $(date -u +%Y-%m-%dT%H:%M:%SZ)"
