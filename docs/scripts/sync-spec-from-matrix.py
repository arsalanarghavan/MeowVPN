#!/usr/bin/env python3
"""Sync LARAVEL-BACKEND-SPEC-FA.md checkboxes from SECTION14-GAP-MATRIX-V28-FA.md (v28)."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = ROOT / "docs/LARAVEL-BACKEND-SPEC-FA.md"
MATRIX = ROOT / "docs/SECTION14-GAP-MATRIX-V28-FA.md"

status_by_text: dict[str, str] = {}
for line in MATRIX.read_text().splitlines():
    m = re.match(r"\| \d+ \| L\d+ \| (DONE|OPS|PARTIAL|OPEN) \| (.+?) \|", line)
    if m:
        status_by_text[m.group(2).strip()] = m.group(1)

lines = SPEC.read_text().splitlines()
out: list[str] = []
synced = 0
for line in lines:
    m = re.match(r"^- \[([ x])\] (.+)$", line)
    if m:
        crit = m.group(2).strip()
        st = status_by_text.get(crit)
        if st:
            tick = "x" if st == "DONE" else " "
            out.append(f"- [{tick}] {crit}")
            synced += 1
            continue
    out.append(line)

SPEC.write_text("\n".join(out) + "\n")
done = sum(1 for s in status_by_text.values() if s == "DONE")
print(f"Synced {synced} checkboxes: {done} DONE")
