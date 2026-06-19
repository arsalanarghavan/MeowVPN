# §14 Dashboard — ماتریس شکاف v16

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md) §14 (۸۷ checkbox)

| وضعیت | تعداد | توضیح v16 |
|--------|-------|-----------|
| DONE | 87 | همه checkbox‌های §14 پس از v16 |
| PARTIAL | 0 | — |
| OPEN | 0 | B.4.4 relay — evidence `relay-setup-signoff-v16.md` + CI relay job |

## اقدامات v16 کلیدی

| ID | اقدام | Evidence / تست |
|----|--------|----------------|
| A.2.1 | polling ۶۰s در `dashboard-monitoring.tsx` | Playwright monitoring chart |
| A.2.2 | `externalHostSnapshots` | v15 DONE |
| B.2.1 | `ServiceNaming::formatServiceDisplayLabel` | bot + user detail + `ServiceNamingDisplayTest` |
| B.4.4 | relay sign-off | `relay-forward-*.log` template |
| C.1.3 | users → detail | Playwright `dashboard-v16.spec.ts` |
| C.2.1 | `user_service_transfer` depth | `MutateServiceMatrixTest` |
| G.6.1 | reseller reports chart | Playwright + seeder tx |
| G.6.2 | impersonate از reports | Playwright |

Operator / date: _______________
