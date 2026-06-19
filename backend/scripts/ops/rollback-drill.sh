#!/usr/bin/env bash
# Rollback drill checklist — document steps; does not mutate production by default.
set -euo pipefail

echo "=== Rollback drill (dry documentation) ==="
echo "1. Re-enable WP plugin: wp plugin activate simplevpbot"
echo "2. Revert DNS A/AAAA to WP host"
echo "3. Relay tenant laravel_base_url → old WP webhook base (temporary)"
echo "4. Restore MySQL snapshot if data diverged"
echo "5. Verify WP webhook receives Telegram update"
echo ""
echo "Evidence: record date/operator in docs/evidence/rollback-drill.log"
echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) rollback drill reviewed" >> "$(dirname "$0")/../../../docs/evidence/rollback-drill.log" 2>/dev/null || true
echo "See docs/CUTOVER-STAGING-FA.md § Rollback"
