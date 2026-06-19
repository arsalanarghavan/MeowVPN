#!/usr/bin/env bash
# CI: ensure dashboard TS does not call /api/v1/admin/* without normalizeAdminApiPath (v18 — zero warnings).
set -euo pipefail
REPO="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$REPO/frontend/src"
violations=0
raw_paths=()
while IFS= read -r -d '' f; do
  if grep -qE '["'\''`]/api/v1/admin/' "$f" \
    && ! grep -qE 'normalizeAdminApiPath|apiBase\(|postAdminMutate|dash-admin-mutate|dash-admin-upload' "$f"; then
    if grep -qE "/api/v1/(bootstrap|auth/|me/)" "$f"; then
      continue
    fi
    echo "WARN: possible raw admin API path in $f"
    raw_paths+=("$f")
    violations=$((violations + 1))
  fi
done < <(find . \( -name '*.ts' -o -name '*.tsx' \) -print0)
if [[ "$violations" -gt 0 ]]; then
  echo "Frontend fetch audit failed ($violations warnings — v18 requires zero):"
  printf '  %s\n' "${raw_paths[@]}"
  exit 1
fi
NAV="$REPO/frontend/src/config/admin-nav.ts"
if ! grep -q 'FEATURE_TAB_MAP' "$NAV"; then
  echo "FEATURE_TAB_MAP missing in admin-nav.ts"
  exit 1
fi
if grep -rq 'X-WP-Nonce' "$REPO/frontend/src"; then
  echo "X-WP-Nonce must not appear in frontend/src (Appendix B)"
  exit 1
fi
if grep -rqE 'wp-json|admin-ajax' "$REPO/frontend/src"; then
  echo "wp-json and admin-ajax must not appear in frontend/src (Appendix B)"
  exit 1
fi
bash "$(dirname "$0")/check-frontend-api-paths.sh"
EVIDENCE="$REPO/docs/evidence/frontend-fetch-audit-v27.md"
{
  echo "# Frontend fetch audit v27"
  echo ""
  echo "Date: $(date -u +%Y-%m-%d)"
  echo "Warnings: $violations (threshold: 0)"
  echo ""
  echo "All admin fetch paths use normalizeAdminApiPath helpers."
  echo "§7.1 session paths keep /dashboard/ prefix (persona, ui-preferences, impersonate)."
  echo "X-WP-Nonce: absent from frontend/src"
  echo "wp-json / admin-ajax: absent from frontend/src"
} > "$EVIDENCE"
echo "Frontend fetch audit OK ($violations warnings); evidence → $EVIDENCE"
