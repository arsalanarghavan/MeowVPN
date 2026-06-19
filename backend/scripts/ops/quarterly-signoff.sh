#!/usr/bin/env bash
# Quarterly production sign-off checklist (portal, webhook, crypto — operator-run).
set -euo pipefail

BASE="${SVP_BASE_URL:-https://api.simplevpbot.ir}"
LOG="${SVP_SIGNOFF_LOG:-docs/evidence/quarterly-signoff-$(date +%F).log}"

exec > >(tee "$LOG") 2>&1

echo "=== quarterly-signoff $(date -Iseconds) ==="
echo "BASE=$BASE"
echo "Next rotation due: see docs/OPS-MAINTENANCE-CALENDAR-V24-FA.md"

echo "--- health ---"
curl -sfS "$BASE/health/ready"
echo

echo "--- portal parity script (if configured) ---"
if [[ -x backend/scripts/ops/portal-parity.sh ]]; then
  SVP_BASE_URL="$BASE" backend/scripts/ops/portal-parity.sh || echo "portal-parity skipped"
fi

echo "Manual items (operator):"
echo "  [ ] Portal admin ?svp_adm=1"
echo "  [ ] Portal sub plain + HTML"
echo "  [ ] Bot webhook direct + relay"
echo "  [ ] Crypto IPN test transaction"
echo "=== quarterly-signoff template complete ==="
