#!/usr/bin/env bash
# CI: ADMIN_TAB_KEYS vs FEATURE_TAB_MAP parity (v22).
set -euo pipefail
REPO="$(cd "$(dirname "$0")/../../.." && pwd)"
NAV="$REPO/frontend/src/config/admin-nav.ts"
MARKERS="$REPO/frontend/src/config/admin-tab-markers.ts"
missing=0
for key in reseller_charge reseller_settings reseller_xui_panels; do
  if ! grep -q "\"$key\"" "$NAV"; then
    echo "Missing routable tab key: $key"
    missing=$((missing + 1))
  fi
done
if grep -q 'reseller_xui_panels' "$NAV" && grep -A25 'ADMIN_ONLY_TAB_KEYS' "$NAV" | grep -q 'reseller_xui_panels'; then
  echo "reseller_xui_panels must not be ADMIN_ONLY (spec E.4)"
  missing=$((missing + 1))
fi
if grep -A25 'ADMIN_ONLY_TAB_KEYS' "$NAV" | grep -q 'bot_ui'; then
  echo "bot_ui must not be ADMIN_ONLY (reseller read-only §D.4)"
  missing=$((missing + 1))
fi
if grep -q 'bot_ui' "$MARKERS" && grep -A30 'RESELLER_FORBIDDEN_TABS' "$MARKERS" | grep -q 'bot_ui'; then
  echo "bot_ui must not be RESELLER_FORBIDDEN"
  missing=$((missing + 1))
fi
for key in bots xui_panels; do
  if ! grep -A25 'ADMIN_ONLY_TAB_KEYS' "$NAV" | grep -q "\"$key\""; then
    echo "$key must be ADMIN_ONLY per spec §10.2"
    missing=$((missing + 1))
  fi
done
if ! grep -q 'broadcast: "marketing"' "$NAV"; then
  echo "broadcast must map to marketing feature (v21 gate)"
  missing=$((missing + 1))
fi
if ! grep -q 'FEATURE_TAB_MAP' "$NAV"; then
  echo "FEATURE_TAB_MAP missing"
  exit 1
fi
if [[ "$missing" -gt 0 ]]; then
  exit 1
fi
echo "Admin nav parity OK"
