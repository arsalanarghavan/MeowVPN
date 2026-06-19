# §14 + §16 — ماتریس شکاف v28 (145/158 DONE)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

| وضعیت | تعداد |
|--------|-------|
| DONE | 145 |
| OPS | 13 |
| PARTIAL | 0 |
| OPEN | 0 |

> **v28:** OPS rows flip to DONE when `docs/evidence/*-v28.log` passes strict `log_ok()` (no FAIL/SKIP).

| # | Line | Status | Spec criterion | PHPUnit | Playwright-v23 | Playwright-v24-qa | Playwright-v25 | OPS log |
|---|------|--------|----------------|---------|----------------|-------------------|----------------|---------|
| 1 | L932 | DONE | کارت‌های آمار (users، receipts، panels) با داده واقعی | GroupAcceptanceV23Test | dashboard-v23 F.4 | — | dashboard-v25-depth receipts | — |
| 2 | L933 | DONE | reseller فقط متریک‌های زیرمجموعه خود را ببیند | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 3 | L934 | DONE | panel health badge قابل refresh | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 4 | L935 | DONE | لینک سریع به tabهای مجاز reseller کار کند | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 5 | L936 | DONE | economics overview card به `unit_economics` لینک دهد (admin) | GroupAcceptanceV23Test | dashboard-v23 A.1 | — | — | — |
| 6 | L961 | DONE | نمودار وضعیت پنل‌ها refresh (SPA polling 60s + manual refresh — v24 amendment; not WebSocket) | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 7 | L962 | DONE | monitor hosts ping status | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 8 | L963 | DONE | reseller فقط پنل‌های مجاز | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 9 | L964 | DONE | دکمه refresh live metrics کار کند | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 10 | L992 | DONE | login موفق → redirect به dashboard | BearerTokenTest | dashboard-v23 B.1 | dashboard-v24-qa /auth/login | — | — |
| 11 | L993 | DONE | session Sanctum برقرار شود | BearerTokenTest | dashboard-v23.spec.ts | — | — | — |
| 12 | L994 | DONE | خطای credential → پیام `{ok:false}` | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 13 | L995 | DONE | CSRF cookie قبل از login | BearerTokenTest | dashboard-v23 B.1 | dashboard-v24-qa /auth/login | — | — |
| 14 | L1019 | DONE | ذخیره branding و اعمال CSS vars در SPA | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 15 | L1020 | DONE | preview logo/favicon | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 16 | L1021 | DONE | portal page selector از pages list | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 17 | L1034 | DONE | overrideها در bot و dashboard نمایش داده شوند | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 18 | L1035 | DONE | reset به default ممکن باشد | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 19 | L1048 | DONE | proxy test به Telegram API موفق/ناموفض | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 20 | L1049 | DONE | bot requests از proxy عبور کنند | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 21 | L1063 | DONE | Sync config → tenant روی relay | RelaySetupOrderTest | dashboard-v23 B.4 | — | dashboard-v25-depth relay | — |
| 22 | L1064 | DONE | Set webhook via relay | RelaySetupOrderTest | dashboard-v23 B.4 | — | dashboard-v25-depth relay | — |
| 23 | L1065 | DONE | Control center: doctor، logs، nginx، SSL | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | dashboard-v25-depth relay | — |
| 24 | L1066 | DONE | مطابق `relay-server/SETUP-GUIDE-FA.md` ترتیب راه‌اندازی | RelaySetupOrderTest | dashboard-v23 B.4 | — | dashboard-v25-depth relay | — |
| 25 | L1079 | DONE | تنظیمات notify در cronها اعمال شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 26 | L1080 | DONE | cooldown fields respected | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 27 | L1096 | DONE | لیست سرویس‌های آماده purge | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 28 | L1097 | DONE | manual purge one/all | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 29 | L1098 | DONE | cron scan اجرا شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 30 | L1111 | DONE | crypto settings فقط با `SVP_MODULE_CRYPTO=true` | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | dashboard-v25-depth crypto | — |
| 31 | L1112 | DONE | NOWPayments keys encrypted | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 32 | L1128 | DONE | pagination و filter | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 33 | L1129 | DONE | clear با confirm | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 34 | L1141 | DONE | defaults روی reseller جدید اعمال شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 35 | L1142 | DONE | map permissions در admin/state | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 36 | L1166 | DONE | pagination users + pending | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 37 | L1167 | DONE | reseller فقط subtree | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 38 | L1168 | DONE | click → user detail | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 39 | L1169 | DONE | manual create user | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 40 | L1192 | DONE | تمام service ops کار کنند | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 41 | L1193 | DONE | panel sync/regen/transfer | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 42 | L1194 | DONE | reseller permission gates | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 43 | L1195 | DONE | activity log نمایش داده شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 44 | L1216 | DONE | ایجاد job و پیشرفت itemها | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 45 | L1217 | DONE | cancel/resume | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 46 | L1218 | DONE | worker cron هر دقیقه | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 47 | L1237 | DONE | preview تفاوت‌ها را نشان دهد | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 48 | L1238 | DONE | merge اتمی — یک user باقی بماند | GroupAcceptanceV23Test | dashboard-v23 C.4 | — | dashboard-v25-depth user merge | — |
| 49 | L1239 | DONE | audit log ثبت شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 50 | L1257 | DONE | webhook register/delete | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 51 | L1258 | DONE | test connection هر platform | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 52 | L1259 | DONE | diagnostics dialog اطلاعات مفید | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 53 | L1273 | DONE | publish announcement به channel | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 54 | L1274 | DONE | gate در bot handler فعال شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 55 | L1289 | DONE | edit fa/en per key | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 56 | L1290 | DONE | reset one/all به defaults | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 57 | L1303 | DONE | drag-drop layout ذخیره شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 58 | L1304 | DONE | reseller نتواند layout را تغییر دهد | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 59 | L1319 | DONE | admin: لیست همه reseller bots | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 60 | L1320 | DONE | reseller: فقط bot خود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 61 | L1321 | DONE | webhook + relay per reseller domain | RelaySetupOrderTest | dashboard-v23 B.4 | — | dashboard-v25-depth relay | — |
| 62 | L1344 | DONE | CRUD panel | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 63 | L1345 | DONE | test connection 3x-ui | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 64 | L1346 | DONE | economics per panel | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 65 | L1347 | DONE | pagination | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 66 | L1363 | DONE | snapshot sync از پنل | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 67 | L1364 | DONE | batch ops روی clients | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 68 | L1365 | DONE | assign plan به orphan clients | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 69 | L1366 | DONE | stale cache indicator | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 70 | L1378 | DONE | خطوط هزینه ماهانه | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 71 | L1379 | DONE | mark paid → extend due date | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 72 | L1394 | DONE | قیمت per GB per panel per reseller | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 73 | L1395 | DONE | panel access toggle | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 74 | L1396 | DONE | inbound display labels | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 75 | L1412 | DONE | CRUD plan با panel/category binding | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 76 | L1413 | DONE | reseller floors نمایش (reseller mode) | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 77 | L1414 | DONE | wholesale line binding | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 78 | L1428 | DONE | CRUD plan category با panel binding | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 79 | L1429 | DONE | active toggle و pagination | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 80 | L1430 | DONE | delete با guard foreign plans | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 81 | L1444 | DONE | add/edit/delete card از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 82 | L1445 | DONE | drag reorder ذخیره شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 83 | L1459 | DONE | approve/reject با delivery | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 84 | L1460 | DONE | filters و aggregates | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 85 | L1461 | DONE | reseller scope | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 86 | L1475 | DONE | discount save از admin UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 87 | L1476 | DONE | discount delete با confirm | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 88 | L1477 | DONE | redemptions list نمایش داده شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 89 | L1492 | DONE | save panel economics از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 90 | L1493 | DONE | save global config (usd rate) | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 91 | L1494 | DONE | KPI grid پس از save refresh شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 92 | L1507 | DONE | customer charges list | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 93 | L1508 | DONE | wallet topup checkout flow | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 94 | L1526 | DONE | broadcast send از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 95 | L1527 | DONE | queue progress نمایش داده شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 96 | L1528 | DONE | broadcast cancel از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 97 | L1542 | DONE | marketing rule save از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 98 | L1543 | DONE | manual send از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 99 | L1544 | DONE | segment preview نمایش داده شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 100 | L1556 | DONE | referral settings save از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 101 | L1568 | DONE | referral chart داده واقعی | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 102 | L1569 | DONE | referral table pagination | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 103 | L1583 | DONE | reseller provision از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 104 | L1584 | DONE | permissions save از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 105 | L1585 | DONE | bind users از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 106 | L1595 | DONE | stats + daily chart | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 107 | L1596 | DONE | impersonate از admin | ImpersonationTest / dashboard-v25-depth | dashboard-v23 G.6 | — | dashboard-v25-depth impersonate xs | — |
| 108 | L1609 | DONE | inbound labels save از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 109 | L1610 | DONE | payment methods save از UI | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 110 | L1627 | DONE | l2tp add از UI | GroupAcceptanceV23Test | dashboard-v23 H.1 | — | dashboard-v25-depth L2TP | — |
| 111 | L1628 | DONE | l2tp update از UI | GroupAcceptanceV23Test | dashboard-v23 H.1 | — | dashboard-v25-depth L2TP | — |
| 112 | L1629 | DONE | l2tp delete با confirm | GroupAcceptanceV23Test | dashboard-v23 H.1 | — | dashboard-v25-depth L2TP | — |
| 113 | L1630 | DONE | tab مخفی وقتی `SVP_MODULE_L2TP=false` | GroupAcceptanceV23Test | dashboard-v23 H.1 | — | dashboard-v25-depth L2TP | — |
| 114 | L1644 | DONE | backup download از UI | BackupRestoreStagingTest | dashboard-v23 H.2 | — | dashboard-v25-depth backup | — |
| 115 | L1645 | DONE | backup upload restore از UI | BackupRestoreStagingTest | dashboard-v23 H.2 | — | dashboard-v25-depth backup | — |
| 116 | L1646 | DONE | manual backup run از UI | BackupRestoreStagingTest | dashboard-v23 H.2 | — | dashboard-v25-depth backup | — |
| 117 | L1660 | DONE | filter domain/event_type/q | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 118 | L1661 | DONE | pagination | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 119 | L1662 | DONE | impersonation events visible | ImpersonationTest / dashboard-v25-depth | dashboard-v23 G.6 | — | dashboard-v25-depth impersonate xs | — |
| 120 | L1846 | OPS | `docker compose up` → nginx + mysql + redis + app healthy | ParityMigrationMysqlTest + docker-smoke-v28 | dashboard-v23.spec.ts | — | — | evidence/docker-smoke-v28.log |
| 121 | L1847 | DONE | `php artisan test` green (smoke) | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 122 | L1848 | DONE | `frontend` build به `frontend/dist/` و mount در nginx | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 123 | L1857 | DONE | `php artisan migrate` بدون خطا | ParityMigrationMysqlTest | dashboard-v23.spec.ts | — | — | — |
| 124 | L1858 | DONE | Model factories برای users/services | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 125 | L1859 | DONE | settings CRUD unit test | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 126 | L1868 | DONE | login از React SPA کار کند | BearerTokenTest | dashboard-v23 B.1 | dashboard-v24-qa /auth/login | — | — |
| 127 | L1869 | DONE | bootstrap `features`، `branding`، `navTabs` برگردد | BootstrapControllerTest | dashboard-v23.spec.ts | — | — | — |
| 128 | L1870 | DONE | role admin/reseller تشخیص داده شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 129 | L1879 | DONE | تب users، plans، panels داده واقعی نشان دهند | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 130 | L1880 | DONE | pagination keys سازگار با SPA | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 131 | L1881 | DONE | reseller scoping verified | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 132 | L1890 | DONE | smoke test هر op → `{ok:true}` یا خطای معنادار | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 133 | L1891 | DONE | reseller policy matrix enforce شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 134 | L1892 | DONE | audit log برای ops حساس | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 135 | L1901 | OPS | buy flow end-to-end در staging | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | evidence/staging-buy-flow-v28.log |
| 136 | L1902 | DONE | service delivery بعد از receipt approve | GroupAcceptanceV23Test | dashboard-v23 F.4 | — | dashboard-v25-depth receipts | — |
| 137 | L1903 | DONE | rate limit webhook تست شود | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 138 | L1912 | DONE | create service روی 3x-ui | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 139 | L1913 | DONE | configs snapshot + batch ops | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 140 | L1914 | DONE | panel_online cron data | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 141 | L1923 | DONE | reseller login + scoped data | BearerTokenTest | dashboard-v23 B.1 | dashboard-v24-qa /auth/login | — | — |
| 142 | L1924 | DONE | sub-reseller hierarchy | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 143 | L1925 | OPS | reseller bot webhook | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | evidence/reseller-webhook-v28.log |
| 144 | L1934 | OPS | sync config/domains با VPS relay | RelaySetupOrderTest | dashboard-v23 B.4 | — | dashboard-v25-depth relay | evidence/relay-forward-v28.log |
| 145 | L1935 | OPS | set webhook via relay | RelaySetupOrderTest | dashboard-v23 B.4 | — | dashboard-v25-depth relay | evidence/relay-webhook-set-v28.log |
| 146 | L1936 | OPS | control center ops از dashboard | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | dashboard-v25-depth relay | evidence/relay-control-center-v28.log |
| 147 | L1945 | DONE | broadcast 1000+ users بدون timeout | BroadcastLoadEnqueueTest | dashboard-v23.spec.ts | — | — | — |
| 148 | L1946 | DONE | bulk wallet job complete | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | — |
| 149 | L1947 | DONE | marketing cron sends offers | MarketingCronOffersTest | dashboard-v23.spec.ts | — | — | — |
| 150 | L1956 | OPS | backup دانلود و restore در staging | BackupRestoreStagingTest | dashboard-v23 H.2 | — | dashboard-v25-depth backup | evidence/backup-restore-staging-v28.log |
| 151 | L1957 | DONE | crypto IPN → transaction confirmed | CryptoIpnConfirmedTest | dashboard-v23.spec.ts | — | dashboard-v25-depth crypto | — |
| 152 | L1958 | DONE | L2TP tab با feature flag | L2tpModuleGateTest | dashboard-v23 H.1 | — | dashboard-v25-depth L2TP | — |
| 153 | L1967 | OPS | import از DB وردپرس بدون از دست رفتن داده | WpImportRowCountTest | dashboard-v23.spec.ts | — | — | evidence/import-run-v28.log |
| 154 | L1968 | OPS | row counts match | WpImportRowCountTest | dashboard-v23.spec.ts | — | — | evidence/import-verify-v28.log |
| 155 | L1969 | OPS | parallel run WP+Laravel در staging | GroupAcceptanceV23Test | dashboard-v23.spec.ts | — | — | evidence/phase16-parallel-v28.log |
| 156 | L1978 | OPS | ۲۴h soak test بدون error spike | soak-24h-v26.log | dashboard-v23.spec.ts | — | — | evidence/soak-24h-v28.log |
| 157 | L1979 | OPS | alerting روی panel down | admin-alerts-fire-smoke-v28 | dashboard-v23.spec.ts | — | — | evidence/admin-alerts-v28.log |
| 158 | L1980 | OPS | WP خاموش — فقط Laravel | wp-disable-v26.log | dashboard-v23.spec.ts | — | — | evidence/wp-disable-v28.log |

Operator / date: 2026-06-13 (v28 — 13 OPS pending live verify)
