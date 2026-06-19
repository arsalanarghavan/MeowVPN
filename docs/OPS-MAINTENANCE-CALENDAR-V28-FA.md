# OPS Maintenance Calendar v28 (FA)

Base: [`OPS-MAINTENANCE-CALENDAR-V27-FA.md`](OPS-MAINTENANCE-CALENDAR-V27-FA.md)

## v28 re-verify (2026-06-13)

| Task | Due | Evidence |
|------|-----|----------|
| Strict OPS bundle v28 | 2026-06-13 | [`run-v28-evidence.sh`](../backend/scripts/ops/run-v28-evidence.sh) |
| Matrix v28 sync | 2026-06-13 | [`SECTION14-GAP-MATRIX-V28-FA.md`](SECTION14-GAP-MATRIX-V28-FA.md) |
| Close 13 OPS rows | TBD operator host | [`OPS-EVIDENCE-INDEX-V28.md`](evidence/OPS-EVIDENCE-INDEX-V28.md) |
| Soak 86400s prod | before ARCH signoff | `SVP_SOAK_DURATION_SEC=86400` → `soak-24h-v28.log` |
| Secret rotation | 2026-09-16 | `secret-rotation-v28.log` |
| Monthly verify | 2026-07-13 | `monthly-verify-v28.log` |

Next quarterly: **2026-09-16** (unchanged from v24 calendar).
