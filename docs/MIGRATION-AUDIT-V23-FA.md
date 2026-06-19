# ممیزی مهاجرت v23 — بستن کامل پس از v22

تاریخ: 2026-06-16

## خلاصه

| لایه | v22 | v23 |
|------|-----|-----|
| Spec checkboxes | 98 `[x]` + 28 `[ ]` | **158 `[x]`** |
| Gap matrix | 98/18/10 | **158/0/0 DONE** |
| Implicit pages | 12 بدون checkbox | **32 checkbox** اضافه شد |
| Matrix line drift | L+2 خطا | **اصلاح** — match by criterion text |
| Playwright | v22 + API-only | **v23 strict** — بدون conditional |
| OPS | v22 index | **v23 re-verify** 2026-06-16 |

## فایل‌های مرجع

- Matrix: [`SECTION14-GAP-MATRIX-V23-FA.md`](SECTION14-GAP-MATRIX-V23-FA.md)
- Spec: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)
- OPS: [`evidence/OPS-EVIDENCE-INDEX-V23.md`](evidence/OPS-EVIDENCE-INDEX-V23.md)
- انحراف‌ها: [`SPEC-DEVIATIONS-FA.md`](SPEC-DEVIATIONS-FA.md) § v23
- Cutover: [`evidence/CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md) § v23

## §16 OPEN → DONE (v23)

| Criterion | Evidence |
|-----------|----------|
| marketing cron sends offers | `MarketingCronOffersTest` |
| backup download + restore | `BackupRestoreStagingTest` + OPS p02–p06 |
| crypto IPN confirmed | `CryptoIpnConfirmedTest` + portal-parity-v23 |
| L2TP feature flag | `L2tpModuleGateTest` + Playwright p47 |
| import / row counts / parallel | OPS import logs + `WpImportRowCountTest` |
| soak / alerting / WP off | OPS soak-24h-v23 + admin-alerts + wp-disable |

## CI

- `sync-spec-checkboxes.sh` → OPS-INDEX-V23
- Playwright: فقط `dashboard-v23*.spec.ts`
- PHPUnit: `GroupAcceptanceV23Test` + §16 suite

## ARCH-12

Git commit بزرگ فقط با درخواست صریح operator.
