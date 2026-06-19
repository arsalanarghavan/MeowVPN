# §14 Dashboard — ماتریس شکاف v18 (~126 checkbox)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md) §14

| وضعیت | تعداد | توضیح v18 |
|--------|-------|-----------|
| DONE | 118 | checkbox با کد/تست/e2e/evidence |
| PARTIAL | 0 code | B.3.2 proxy + B.4.4 relay — **production logs** v18 |
| OPEN (OPS only) | 8 | live operator re-run on next rotation (secrets/TLS) |

## گروه A — Auth / Overview

| ID | Checkbox | v18 | Test/Evidence |
|----|----------|-----|---------------|
| A.1 | Admin login CSRF | DONE | `AuthSanctumFlowTest` |
| A.2 | Overview stats | DONE | `GroupAcceptanceV18Test::test_group_a_*` |
| A.3 | Reseller isolation | DONE | Playwright v18 Group A |
| A.4 | Economics link | DONE | Playwright `unit_economics` |

## گروه B — Site settings (10 subtabs)

| Subtab | v18 | Test |
|--------|-----|------|
| general | DONE | Playwright v18 loop |
| whitelabel | DONE | Playwright v18 |
| service_naming | DONE | Playwright v18 |
| proxy | DONE | Playwright + `TelegramProxyEgressTest` |
| relay | DONE | Playwright + prod relay-forward log |
| notifications | DONE | Playwright v18 (nav subtab) |
| purge_expired | DONE | Playwright v18 |
| finance | DONE | Playwright v18 |
| backup | DONE | Playwright v18 |
| logs | DONE | Playwright v18 |

## گروه C — Users

| ID | v18 | Test |
|----|-----|------|
| C.1–C.3 | user CRUD/state | DONE | `GroupAcceptanceV15Test` |
| C.4 merge preview/flow | DONE | Playwright v18 + `GroupAcceptanceV18Test` |
| C.5 bulk jobs | DONE | `GroupAcceptanceV18Test::test_group_c_users_bulk_state` |

## گروه D — Bot settings

| ID | v18 | Test |
|----|-----|------|
| D.1–D.3 | texts/diagnostics | DONE | `GroupDBotSettingsAcceptanceTest` |
| D.4 bot_ui reseller RO | DONE | Playwright v18 Group D |

## گروه E — Panels / Configs

| ID | v18 | Test |
|----|-----|------|
| E.1 xui panels | DONE | tab smoke v18 |
| E.2 configs stale/sync | DONE | Playwright v18 Group E |

## گروه F — Commerce

| ID | v18 | Test |
|----|-----|------|
| F.1 plans/cards | DONE | tab smoke |
| F.4 receipts approve/reject | DONE | Playwright v18 + mutate |
| F.5 cards reorder | DONE | Playwright v18 + `card_reorder` mutate |

## گروه G — Reseller

| ID | v18 | Test |
|----|-----|------|
| G.1–G.5 | reseller tabs | DONE | v18 tabs + matrix 72 |
| G.6 reports + impersonate | DONE | Playwright v18 strict banner |

## گروه H — System

| ID | v18 | Test |
|----|-----|------|
| H.1 L2TP | DONE | `L2tpCrudTest` + SSH mock v18 |
| H.2 backup restore upload | DONE | Playwright v18 upload smoke |
| H.3 audit filter/pagination | DONE | `AuditControllerFilterTest` + Playwright |

## §15 Mutate (cross-ref)

| Metric | v18 |
|--------|-----|
| 141 op smoke | `MutateSmokeTest` ok:true |
| 72 reseller ok | `MutateResellerPositiveMatrixTest` |
| 69 admin-only ok | `MutateAdminPositiveMatrixTest` |
| depth batches | `MutateDepthBatchV18Part1/2` |

## انحرافات آگاهانه (doc)

| موضوع | Doc |
|-------|-----|
| notifications/logs nav | `NAV-TABS-NOTIFICATIONS-FA.md` |
| reseller_charge/settings not in ADMIN_TAB_KEYS | Playwright v18 explicit |
| polling 60s not websocket | `dashboard-monitoring.tsx` |

Operator / date: 2026-06-12 (v18 automated sign-off)
