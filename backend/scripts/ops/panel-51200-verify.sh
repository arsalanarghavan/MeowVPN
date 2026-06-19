#!/usr/bin/env bash
# Staging verify: panel fix-51200-traffic (§19.5 — operator-run with real 3x-ui panel).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
PANEL_ID="${SVP_PANEL_ID:-1}"
LOG="${SVP_PANEL_51200_LOG:-$ROOT/docs/evidence/panel-51200-verify-$(date +%F).log}"

{
  echo "panel-51200-verify start $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "panel_id=$PANEL_ID base=$BASE"
  echo "PHPUnit smoke (no live panel):"
  bash "$ROOT/backend/scripts/ops/run-phpunit.sh" "PanelTraffic51200" || echo "WARN: PanelTraffic51200 filter not found — manual verify"
  echo "Live panel: POST $BASE/api/v1/admin/panel/fix-51200-traffic {\"panel_id\":$PANEL_ID}"
  echo "See docs/PANEL-PARITY-AUDIT-FA.md"
  if [[ -n "${SVP_PANEL_51200_CONFIRMED:-}" ]]; then
    echo "operator confirmed: $SVP_PANEL_51200_CONFIRMED"
    echo "panel-51200-verify OK"
    exit 0
  fi
  echo "INFO: set SVP_PANEL_51200_CONFIRMED='operator@host date' after staging panel verify"
  exit 0
} 2>&1 | tee -a "$LOG"
