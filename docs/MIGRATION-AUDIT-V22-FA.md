# ممیزی مهاجرت v22 — صداقت نهایی پس از v21

تاریخ: 2026-06-15

## خلاصه

| لایه | v21 ادعا | v22 واقعیت |
|------|----------|------------|
| Spec checkboxes | 126/126 `[x]` | **98 `[x]` + 28 `[ ]`** (هم‌خوان با matrix) |
| Gap matrix | 98/18/10 بدون ID | **126 سطر** با DONE/PARTIAL/OPEN + 10 OPEN شناسه‌دار |
| OPS evidence | `prod.example` synthetic | **`api.simplevpbot.ir`** — لاگ خام کامل |
| Playwright | ~36 marker-only | **v22 strict** — UI confirm/CRUD shells |
| Mutate depth | 44 ops | **140 ops** (v21 44 + v22 96) |
| `$resellerMap` | تست 68 | **61** (هم‌خوان کد/تست) |

## فایل‌های مرجع

- Matrix: [`SECTION14-GAP-MATRIX-V22-FA.md`](SECTION14-GAP-MATRIX-V22-FA.md)
- Spec: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)
- OPS: [`evidence/OPS-EVIDENCE-INDEX-V22.md`](evidence/OPS-EVIDENCE-INDEX-V22.md)
- انحراف‌ها: [`SPEC-DEVIATIONS-FA.md`](SPEC-DEVIATIONS-FA.md) § v22
- nginx alias: [`NGINX-DASHBOARD-API-ALIAS-FA.md`](NGINX-DASHBOARD-API-ALIAS-FA.md)
- Cutover: [`evidence/CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md) § v22

## ۱۰ OPEN (نیاز UI عمیق‌تر)

1. F.2 Plan Categories — CRUD UI
2. F.3 Cards — CRUD + reorder
3. F.5 Discounts — save/delete
4. F.6 Unit Economics — save per panel
5. G.1 Broadcast — send UI
6. G.2 Marketing Lifecycle — rule save UI
7. G.3 Referral Settings — save
8. G.5 Resellers — CRUD + permissions
9. H.1 L2TP — add/update/delete UI
10. H.2 Backup — upload restore UI

v22 Playwright و `data-testid` پایه برای این تب‌ها اضافه شده؛ checkbox spec تا interaction کامل باز می‌ماند.

## Spec amendments (v22)

- Horizon queue worker — [`SPEC-DEVIATIONS-FA.md`](SPEC-DEVIATIONS-FA.md)
- Commerce module gate
- nginx `/api/` alias
- Portal `{success,data}` envelope
- `dashboard_users` جدول جدا
- Webhook secret rotation

## ARCH-12

تغییرات git commit بزرگ (حذف WP legacy) فقط با درخواست صریح operator — خارج از scope خودکار v22.

## CI

- `sync-spec-checkboxes.sh` — exit 1 اگر `spec_done > matrix_DONE`
- Playwright: فقط `dashboard-v22*.spec.ts`
- PHPUnit: `GroupAcceptanceV22Test`, `MutateDepthBatchV22*`, `BroadcastWorkerTimeoutTest`
