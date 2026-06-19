# §14 + §16 — ماتریس شکاف v21 (صداقت پس از v20)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md) — v20 همه ۱۲۶ تیک داشت بدون e2e/OPS واقعی.

| وضعیت | تعداد |
|--------|-------|
| DONE | 98 |
| PARTIAL | 18 |
| OPEN | 10 |

## §14 گروه A (۱۷ checkbox)

| ID | Spec | v21 | Test/Evidence |
|----|------|-----|---------------|
| A.1.1 | users/receipts/panels cards | DONE | `dashboard-v21.spec.ts` + `dash-overview-stat-*` |
| A.1.2 | reseller scope | DONE | `dashboard-v21` + `GroupAcceptanceV21Test` |
| A.1.3–4 | panel health + quick links | DONE | Playwright v21 strict click |
| A.2.1–4 | monitoring 60s poll + refresh | DONE | `dashboard-v21` A.2 |
| A.3.1–4 | login CSRF/session | DONE | `dashboard-auth-v21.spec.ts` real `/me/state` |
| A.4 | economics link | DONE | Playwright strict |

## §14 گروه B (۲۸)

| Subtab | v21 |
|--------|-----|
| whitelabel … resellers (۹) | DONE — `SITE_SETTINGS_SUBTABS` + markers |
| B.1 CSS var save | DONE — `getComputedStyle` v21 |
| B.2 service naming reset | DONE — click + preview |
| B.3 proxy test toast | DONE — `proxy-egress-prod-v21.log` |
| B.4 relay tabs | DONE — `relay-forward-*-prod-v21.log` |
| B.6 purge | PARTIAL — API depth؛ UI confirm e2e |
| B.8 logs filter/clear | PARTIAL — mutate depth؛ confirm dialog e2e |

## §14 گروه C (۱۶)

| Area | v21 |
|------|-----|
| users list/pagination | DONE — v21 C.1 |
| manual create | PARTIAL — resellers tab only |
| service ops + activity | DONE — C.2 API + UI marker |
| bulk job progress | PARTIAL — cancel/resume UI |
| merge preview/atomic | DONE — C.4 |

## §14 گروه D–H

| Group | v21 |
|-------|-----|
| D bots/texts/bot_ui | DONE — `reseller_bots` route؛ webhook register |
| E panels/configs/reseller_xui | DONE — §10.2 bots/xui admin-only؛ reseller_xui allowed |
| F finance/receipts/charge | PARTIAL — approve/deliver depth؛ topup steps |
| G marketing/impersonate | PARTIAL — broadcast gate؛ impersonate strict |
| H L2TP/backup/audit | DONE — audit filter impersonation |

## Reseller tabs

| Tab | v21 |
|-----|-----|
| reseller_charge/settings | DONE |
| reseller_xui_panels | DONE — removed from `RESELLER_FORBIDDEN_TABS` |
| bots/xui_panels | DONE — `ADMIN_ONLY_TAB_KEYS` §10.2 |

## §16 فاز ۰–۱۲ (۳۹ checkbox)

همه **DONE** — [`OPS-EVIDENCE-INDEX-V21.md`](evidence/OPS-EVIDENCE-INDEX-V21.md)

## §15 Mutate

| Metric | v21 |
|--------|-----|
| 141 smoke | `MutateSmokeTest` |
| depth v21 | `MutateDepthBatchV21Part1/2/3` — 40+ ops |
| metrics 141 | `MetricsIncrementTest` catalog generator |
| marketing.lifecycle write | admin-only — removed from reseller map |
| link_wp_user | deprecated — smoke exclude |

## Backend v21 fixes

| Fix | File |
|-----|------|
| reseller_xui e2e parity | `admin-tab-markers.ts` |
| bots/xui admin-only | `ADMIN_ONLY_TAB_KEYS`, `DashboardBootBuilder` |
| broadcast tab gate | `EnsureAdminStateModule` |
| data-testid overview | `dashboard-overview.tsx` |

Operator / date: 2026-06-14
