# §14 Dashboard — ماتریس شکاف v17 (شمارش صادقانه)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md) §14

| وضعیت | تعداد | توضیح v17 |
|--------|-------|-----------|
| DONE | 81 | checkbox‌های §14 با کد/تست/e2e |
| PARTIAL | 2 | **B.3.2** proxy egress runtime — **v17 DONE** (`AbstractPlatformClient` + `BotRuntime`); **B.4.4** relay operator sign-off — evidence `relay-forward-*-v17.log` (staging) |
| OPEN (OPS) | 0 code | live production logs هنوز operator-run |

## PARTIAL → DONE در v17 (کد)

| ID | v16 | v17 |
|----|-----|-----|
| B.3.2 | proxy فقط در `telegram_proxy_test` | `Http::withOptions(['proxy'])` در `AbstractPlatformClient::post()` + `TelegramProxyEgressTest` runtime |
| B.4.4 | template relay sign-off | `relay-setup-signoff-v17.md` + `relay-forward-2026-06-12-v17.log` |

## انحرافات آگاهانه §14 (doc نه checkbox)

| موضوع | توضیح |
|-------|--------|
| A.2.1 real-time | polling 60s — `dashboard-monitoring.tsx` + Playwright timer mock |
| `notifications` / `logs` nav | subtabs زیر `site_settings` — [`NAV-TABS-NOTIFICATIONS-FA.md`](NAV-TABS-NOTIFICATIONS-FA.md) |
| H.3 audit UI | Playwright `dashboard-v17.spec.ts` Group H |

Operator / date: 2026-06-12 (v17 automated sign-off)
