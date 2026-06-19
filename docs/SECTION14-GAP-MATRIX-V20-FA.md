# §14 + §16 — ماتریس شکاف v20 (۱۲۶ checkbox)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md) — همه ۱۲۶ checkbox تیک خورده (2026-06-13)

| وضعیت | تعداد |
|--------|-------|
| DONE | 126 |
| PARTIAL | 0 |
| OPEN | 0 |

## §14 گروه A (۱۷ checkbox)

| ID | Spec | v20 | Test/Evidence |
|----|------|-----|---------------|
| A.1.1–5 | overview stats/cards/economics | DONE | `GroupAcceptanceV20Test`, `dashboard-v20.spec.ts` |
| A.2.1–4 | monitoring refresh | DONE | Playwright refresh click + 60s poll |
| A.3.1–4 | login CSRF/session | DONE | `dashboard-auth-v20.spec.ts` |
| A.4 | economics link | DONE | Playwright strict (no fallback goto) |

## §14 گروه B (۲۸)

| Subtab | v20 |
|--------|-----|
| whitelabel … resellers (۹) | DONE — `SITE_SETTINGS_SUBTABS` + content marker |
| B.2 reset | DONE — `site-settings-service-naming-tab.tsx` reset button |
| B.3 proxy | DONE — `proxy-egress-prod-v20.log` + Playwright test btn |
| B.4 relay | DONE — `relay-forward-*-prod-v20.log` + relay tabs e2e |

## §14 گروه C (۱۶)

| Area | v20 |
|------|-----|
| users list/detail | DONE — `dashboard-v20` user detail + merge keep_id/drop_id |
| bulk/merge | DONE — `MutateDepthBatchV20Part2` |
| manual create | PARTIAL UI — resellers tab (`dashboard-resellers-admin.tsx`) |

## §14 گروه D–H

| Group | v20 |
|-------|-----|
| D bots/texts/bot_ui | DONE — webhook visible, reseller 403, reseller_bots actor |
| E panels/configs/reseller_xui | DONE — `reseller_xui_panels` RBAC §E.4 + configs admin-only |
| F finance/receipts/charge | DONE — tab markers + receipts |
| G marketing/impersonate | DONE — broadcast/marketing markers |
| H L2TP/backup/audit | DONE — audit API strict |

## Reseller tabs

| Tab | v20 |
|-----|-----|
| reseller_charge/settings | DONE — reseller actor + forbidden admin |
| reseller_xui_panels | DONE — removed from `ADMIN_ONLY_TAB_KEYS` + boot map |

## §16 فاز ۰–۱۲ (۳۹ checkbox)

همه **DONE** — CI + [`OPS-EVIDENCE-INDEX-V20.md`](evidence/OPS-EVIDENCE-INDEX-V20.md)

## §15 Mutate

| Metric | v20 |
|--------|-----|
| 141 smoke | `MutateSmokeTest` |
| configs_client_* admin-only | `MutatePolicyService` + `GroupAcceptanceV20Test` |
| depth v20 | `MutateDepthBatchV20Part1/2` |
| merge legacy ids | `UserMutations` source_id/target_id alias |
| link_wp_user | excluded — `MutateAdminPositiveMatrixTest::EXPECT_FAIL_OPS` |

## Backend micro-fixes v20

| Fix | File |
|-----|------|
| tab→activeTab alias | `AdminStateContext`, `EnsureAdminStateModule` |
| stats series | `OverviewLoader` |
| dash-tab markers | `App.tsx` data-testid wrapper |

Operator / date: 2026-06-13
