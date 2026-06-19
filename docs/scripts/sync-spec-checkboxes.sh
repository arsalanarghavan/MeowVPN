#!/usr/bin/env bash
# v28: spec checkbox coverage vs SECTION14-GAP-MATRIX-V28 — honest DONE/OPS sync.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SPEC="$ROOT/docs/LARAVEL-BACKEND-SPEC-FA.md"
MATRIX="$ROOT/docs/SECTION14-GAP-MATRIX-V28-FA.md"
OPS="$ROOT/docs/evidence/OPS-EVIDENCE-INDEX-V28.md"
open_count=$(grep -c '^- \[ \]' "$SPEC" || true)
done_count=$(grep -c '^- \[x\]' "$SPEC" || true)
row_done=$(grep -cE '\| L[0-9]+ \| DONE \|' "$MATRIX" || true)
row_ops=$(grep -cE '\| L[0-9]+ \| OPS \|' "$MATRIX" || true)
total=$((row_done + row_ops))
echo "Spec open checkboxes: $open_count"
echo "Spec done checkboxes: $done_count"
echo "Matrix DONE/OPS: $row_done / $row_ops (total $total)"
if [[ ! -f "$OPS" ]]; then
  echo "MISSING: OPS-EVIDENCE-INDEX-V28.md"
  exit 1
fi
if [[ "$total" -ne 158 ]]; then
  echo "FAIL: expected 158 matrix rows, got $total"
  exit 1
fi
if [[ "$done_count" -ne "$row_done" ]] || [[ "$open_count" -ne "$row_ops" ]]; then
  echo "FAIL: spec/matrix mismatch (done=$done_count vs matrix=$row_done, open=$open_count vs ops=$row_ops)"
  exit 1
fi
echo "SYNC OK — v28 matrix ${row_done}/${total} DONE, ${row_ops} OPS honest"
