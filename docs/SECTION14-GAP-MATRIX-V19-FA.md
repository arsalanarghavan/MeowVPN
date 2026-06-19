# §14 + §16 — ماتریس شکاف v19 (۱۲۶ checkbox)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

| وضعیت | تعداد |
|--------|-------|
| DONE | 122 |
| PARTIAL | 4 (live rotation calendar) |
| OPEN code | 0 |

## §14 گروه A (۱۲ checkbox)

| ID | Spec | v19 | Test/Evidence |
|----|------|-----|---------------|
| A.1.1–5 | overview stats/cards | DONE | `GroupAcceptanceV19Test`, Playwright v19 |
| A.2.1–4 | monitoring refresh | DONE | Playwright 60s poll + refresh |
| A.3.1–4 | login CSRF | DONE | `AuthSanctumFlowTest`, `api-base` CSRF fix |
| A.4 | economics link | DONE | Playwright economics navigate |

## §14 گروه B (~۳۰)

| Subtab | v19 |
|--------|-----|
| whitelabel … resellers (۹ واقعی) | DONE — `SITE_SETTINGS_SUBTABS` Playwright |
| B.3.2 proxy egress | DONE code + `proxy-egress-prod-v19.log` |
| B.4.4 relay | DONE + `relay-forward-*-prod-v19.log` |

## §14 گروه C–H

| Group | v19 |
|-------|-----|
| C users/detail/bulk/merge | DONE — Playwright + `GroupAcceptanceV19Test` |
| D bots/texts/bot_ui | DONE — reseller RO Playwright |
| E panels/configs | DONE |
| F commerce/receipts/cards | DONE |
| G marketing/reseller/impersonate | DONE |
| H L2TP/backup/audit | DONE strict |

## Reseller tabs

| Tab | v19 |
|-----|-----|
| reseller_charge | DONE — `ADMIN_TAB_KEYS` + reseller Playwright |
| reseller_settings | DONE — same |

## §16 فاز ۰–۱۲ (۳۹ checkbox)

همه **DONE** — CI + prod evidence v19.

## §15 Mutate

| Metric | v19 |
|--------|-----|
| 141 smoke ok:true | `MutateSmokeTest` |
| 72 reseller | `MutateResellerPositiveMatrixTest` |
| 69 admin | `MutateAdminPositiveMatrixTest` |
| 17 settings_tab | `SettingsTabKeysBatchTest` |
| depth v19 | `MutateDepthBatchV18Part2` relay + user_service |

## PARTIAL (۴) — OPS rotation calendar

- TLS certbot renew live re-run quarterly
- Proxy egress prod after secret rotation
- Relay B.4.4 chain after relay secret rotation
- Reseller per-domain webhook annual verify

Operator / date: 2026-06-12
