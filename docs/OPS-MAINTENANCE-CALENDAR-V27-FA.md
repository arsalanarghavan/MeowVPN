# OPS Maintenance Calendar v27 (FA)

Base: [`OPS-MAINTENANCE-CALENDAR-V26-FA.md`](OPS-MAINTENANCE-CALENDAR-V26-FA.md)

## v27 re-verify (2026-06-13)

| Task | Due | Evidence |
|------|-----|----------|
| Strict OPS bundle | 2026-06-13 | [`run-v27-evidence.sh`](../backend/scripts/ops/run-v27-evidence.sh) |
| Matrix honest sync | 2026-06-13 | [`SECTION14-GAP-MATRIX-V27-FA.md`](SECTION14-GAP-MATRIX-V27-FA.md) — 145/158 |
| Close 13 OPS rows | TBD operator host | [`OPS-EVIDENCE-INDEX-V27.md`](evidence/OPS-EVIDENCE-INDEX-V27.md) |
| Soak 86400s prod | before ARCH signoff | `SVP_SOAK_DURATION_SEC=86400` |
| Secret rotation | 2026-09-16 | quarterly calendar |
| Monthly verify | 2026-07-13 | `monthly-verify-v27.log` template |

Next quarterly: **2026-09-16** (unchanged from v24 calendar).
