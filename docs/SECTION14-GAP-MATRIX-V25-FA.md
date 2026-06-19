# §14 + §16 — ماتریس شکاف v25 (158 checkbox — evidence صادقانه)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

| وضعیت | تعداد |
|--------|-------|
| DONE | 146 |
| OPS | 12 |
| PARTIAL | 0 |
| OPEN | 0 |

> **v25:** Evidence per row split into PHPUnit / Playwright-v23 / Playwright-v24-qa / OPS.
> **OPS** = operator-only (import/soak/WP-off/live relay) — checkbox unchecked until fresh log.
> v24 blanket `dashboard-v24-qa` on all rows superseded.

| # | Line | Status | Spec criterion | PHPUnit | Playwright-v23 | Playwright-v24-qa | OPS |
|---|------|--------|----------------|---------|----------------|-------------------|-----|
| 1 | L932 | DONE | کارت‌های آمار (users، receipts، panels) با داده واقعی | GroupAcceptanceV23Test (smoke) | dashboard-v23 C.1 | — | — |
| 2 | L933 | DONE | reseller فقط متریک‌های زیرمجموعه خود را ببیند | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 3 | L934 | DONE | panel health badge قابل refresh | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 4 | L935 | DONE | لینک سریع به tabهای مجاز reseller کار کند | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 5 | L936 | DONE | economics overview card به `unit_economics` لینک دهد (admin) | GroupAcceptanceV23Test (smoke) | dashboard-v23 A.1 | — | — |
| 6 | L961 | DONE | نمودار وضعیت پنل‌ها real-time refresh | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 7 | L962 | DONE | monitor hosts ping status | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 8 | L963 | DONE | reseller فقط پنل‌های مجاز | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 9 | L964 | DONE | دکمه refresh live metrics کار کند | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 10 | L992 | DONE | login موفق → redirect به dashboard | BearerTokenTest / AuthControllerTest | dashboard-v23 B.1 | dashboard-v24-qa /auth/login | — |
| 11 | L993 | DONE | session Sanctum برقرار شود | BearerTokenTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 12 | L994 | DONE | خطای credential → پیام `{ok:false}` | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 13 | L995 | DONE | CSRF cookie قبل از login | BearerTokenTest / AuthControllerTest | dashboard-v23 B.1 | dashboard-v24-qa /auth/login | — |
| 14 | L1019 | DONE | ذخیره branding و اعمال CSS vars در SPA | GroupAcceptanceV23Test (smoke) | dashboard-v23 B.2 | — | — |
| 15 | L1020 | DONE | preview logo/favicon | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 16 | L1021 | DONE | portal page selector از pages list | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 17 | L1034 | DONE | overrideها در bot و dashboard نمایش داده شوند | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 18 | L1035 | DONE | reset به default ممکن باشد | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 19 | L1048 | DONE | proxy test به Telegram API موفق/ناموفض | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 20 | L1049 | DONE | bot requests از proxy عبور کنند | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 21 | L1063 | DONE | Sync config → tenant روی relay | RelaySetupOrderTest | dashboard-v23 B.4 | — | — |
| 22 | L1064 | DONE | Set webhook via relay | RelaySetupOrderTest | dashboard-v23 B.4 | — | — |
| 23 | L1065 | DONE | Control center: doctor، logs، nginx، SSL | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 24 | L1066 | DONE | مطابق `relay-server/SETUP-GUIDE-FA.md` ترتیب راه‌اندازی | RelaySetupOrderTest | dashboard-v23 B.4 | — | — |
| 25 | L1079 | DONE | تنظیمات notify در cronها اعمال شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 26 | L1080 | DONE | cooldown fields respected | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 27 | L1096 | DONE | لیست سرویس‌های آماده purge | PurgeExpiredTest | dashboard-v23 B.6 | — | — |
| 28 | L1097 | DONE | manual purge one/all | PurgeExpiredTest | dashboard-v23 B.6 | — | — |
| 29 | L1098 | DONE | cron scan اجرا شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 30 | L1111 | DONE | crypto settings فقط با MODULE_CRYPTO_ENABLED | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 31 | L1112 | DONE | NOWPayments keys encrypted | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 32 | L1128 | DONE | pagination و filter | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 33 | L1129 | DONE | clear با confirm | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 34 | L1141 | DONE | defaults روی reseller جدید اعمال شود | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 35 | L1142 | DONE | map permissions در admin/state | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 36 | L1166 | DONE | pagination users + pending | GroupAcceptanceV23Test (smoke) | dashboard-v23 C.1 | — | — |
| 37 | L1167 | DONE | reseller فقط subtree | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 38 | L1168 | DONE | click → user detail | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 39 | L1169 | DONE | manual create user | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 40 | L1192 | DONE | تمام service ops کار کنند | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 41 | L1193 | DONE | panel sync/regen/transfer | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 42 | L1194 | DONE | reseller permission gates | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 43 | L1195 | DONE | activity log نمایش داده شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 44 | L1216 | DONE | ایجاد job و پیشرفت itemها | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 45 | L1217 | DONE | cancel/resume | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 46 | L1218 | DONE | worker cron هر دقیقه | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 47 | L1237 | DONE | preview تفاوت‌ها را نشان دهد | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 48 | L1238 | DONE | merge اتمی — یک user باقی بماند | GroupAcceptanceV23Test (smoke) | dashboard-v23 C.4 | — | — |
| 49 | L1239 | DONE | audit log ثبت شود | AuditLogTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 50 | L1257 | DONE | webhook register/delete | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 51 | L1258 | DONE | test connection هر platform | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 52 | L1259 | DONE | diagnostics dialog اطلاعات مفید | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 53 | L1273 | DONE | publish announcement به channel | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 54 | L1274 | DONE | gate در bot handler فعال شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 55 | L1289 | DONE | edit fa/en per key | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 56 | L1290 | DONE | reset one/all به defaults | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 57 | L1303 | DONE | drag-drop layout ذخیره شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 58 | L1304 | DONE | reseller نتواند layout را تغییر دهد | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 59 | L1319 | DONE | admin: لیست همه reseller bots | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 60 | L1320 | DONE | reseller: فقط bot خود | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 61 | L1321 | DONE | webhook + relay per reseller domain | RelaySetupOrderTest | dashboard-v23 B.4 | — | — |
| 62 | L1344 | DONE | CRUD panel | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 63 | L1345 | DONE | test connection 3x-ui | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 64 | L1346 | DONE | economics per panel | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 65 | L1347 | DONE | pagination | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 66 | L1363 | DONE | snapshot sync از پنل | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 67 | L1364 | DONE | batch ops روی clients | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 68 | L1365 | DONE | assign plan به orphan clients | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 69 | L1366 | DONE | stale cache indicator | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 70 | L1378 | DONE | خطوط هزینه ماهانه | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 71 | L1379 | DONE | mark paid → extend due date | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 72 | L1394 | DONE | قیمت per GB per panel per reseller | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 73 | L1395 | DONE | panel access toggle | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 74 | L1396 | DONE | inbound display labels | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 75 | L1412 | DONE | CRUD plan با panel/category binding | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 76 | L1413 | DONE | reseller floors نمایش (reseller mode) | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 77 | L1414 | DONE | wholesale line binding | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 78 | L1428 | DONE | CRUD plan category با panel binding | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 79 | L1429 | DONE | active toggle و pagination | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 80 | L1430 | DONE | delete با guard foreign plans | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 81 | L1444 | DONE | add/edit/delete card از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 82 | L1445 | DONE | drag reorder ذخیره شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 83 | L1459 | DONE | approve/reject با delivery | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 84 | L1460 | DONE | filters و aggregates | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 85 | L1461 | DONE | reseller scope | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 86 | L1475 | DONE | discount save از admin UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 87 | L1476 | DONE | discount delete با confirm | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 88 | L1477 | DONE | redemptions list نمایش داده شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 89 | L1492 | DONE | save panel economics از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 90 | L1493 | DONE | save global config (usd rate) | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 91 | L1494 | DONE | KPI grid پس از save refresh شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 92 | L1507 | DONE | customer charges list | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 93 | L1508 | DONE | wallet topup checkout flow | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 94 | L1526 | DONE | broadcast send از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23 F.3 | — | — |
| 95 | L1527 | DONE | queue progress نمایش داده شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 96 | L1528 | DONE | broadcast cancel از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23 F.3 | — | — |
| 97 | L1542 | DONE | marketing rule save از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 98 | L1543 | DONE | manual send از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 99 | L1544 | DONE | segment preview نمایش داده شود | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 100 | L1556 | DONE | referral settings save از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 101 | L1568 | DONE | referral chart داده واقعی | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 102 | L1569 | DONE | referral table pagination | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 103 | L1583 | DONE | reseller provision از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 104 | L1584 | DONE | permissions save از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 105 | L1585 | DONE | bind users از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23 C.1 | — | — |
| 106 | L1595 | DONE | stats + daily chart | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 107 | L1596 | DONE | impersonate از admin | ImpersonationTest | dashboard-v23 G.6 | — | — |
| 108 | L1609 | DONE | inbound labels save از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 109 | L1610 | DONE | payment methods save از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 110 | L1627 | DONE | l2tp add از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23 H.1 | — | — |
| 111 | L1628 | DONE | l2tp update از UI | GroupAcceptanceV23Test (smoke) | dashboard-v23 H.1 | — | — |
| 112 | L1629 | DONE | l2tp delete با confirm | GroupAcceptanceV23Test (smoke) | dashboard-v23 H.1 | — | — |
| 113 | L1630 | DONE | tab مخفی وقتی `MODULE_L2TP_ENABLED=false` | GroupAcceptanceV23Test (smoke) | dashboard-v23 H.1 | — | — |
| 114 | L1644 | DONE | backup download از UI | BackupRestoreStagingTest | dashboard-v23 H.2 | — | — |
| 115 | L1645 | DONE | backup upload restore از UI | BackupRestoreStagingTest | dashboard-v23 H.2 | — | — |
| 116 | L1646 | DONE | manual backup run از UI | BackupRestoreStagingTest | dashboard-v23 H.2 | — | — |
| 117 | L1660 | DONE | filter domain/event_type/q | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 118 | L1661 | DONE | pagination | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 119 | L1662 | DONE | impersonation events visible | ImpersonationTest | dashboard-v23 G.6 | — | — |
| 120 | L1846 | OPS | `docker compose up` → nginx + mysql + redis + app healthy | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | OPS-EVIDENCE-INDEX-V23 |
| 121 | L1847 | DONE | `php artisan test` green (smoke) | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 122 | L1848 | DONE | `frontend` build به `frontend/dist/` و mount در nginx | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 123 | L1857 | DONE | `php artisan migrate` بدون خطا | ParityMigrationMysqlTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 124 | L1858 | DONE | Model factories برای users/services | GroupAcceptanceV23Test (smoke) | dashboard-v23 C.1 | — | — |
| 125 | L1859 | DONE | settings CRUD unit test | SettingsServiceTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 126 | L1868 | DONE | login از React SPA کار کند | BearerTokenTest / AuthControllerTest | dashboard-v23 B.1 | dashboard-v24-qa /auth/login | — |
| 127 | L1869 | DONE | bootstrap `features`، `branding`، `navTabs` برگردد | BootstrapControllerTest | dashboard-v23 B.2 | — | — |
| 128 | L1870 | DONE | role admin/reseller تشخیص داده شود | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 129 | L1879 | DONE | تب users، plans، panels داده واقعی نشان دهند | GroupAcceptanceV23Test (smoke) | dashboard-v23 C.1 | — | — |
| 130 | L1880 | DONE | pagination keys سازگار با SPA | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 131 | L1881 | DONE | reseller scoping verified | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 132 | L1890 | DONE | smoke test هر op → `{ok:true}` یا خطای معنادار | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 133 | L1891 | DONE | reseller policy matrix enforce شود | ResellerScopeTest / GroupAcceptanceV23Test | dashboard-v23 G.x | — | — |
| 134 | L1892 | DONE | audit log برای ops حساس | AuditLogTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 135 | L1901 | OPS | buy flow end-to-end در staging | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | OPS-EVIDENCE-INDEX-V23 |
| 136 | L1902 | DONE | service delivery بعد از receipt approve | GroupAcceptanceV23Test (smoke) | dashboard-v23 F.4 | — | — |
| 137 | L1903 | DONE | rate limit webhook تست شود | WebhookRateLimitTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 138 | L1912 | DONE | create service روی 3x-ui | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 139 | L1913 | DONE | configs snapshot + batch ops | ConfigsSnapshotTest | dashboard-v23 E.2 | — | — |
| 140 | L1914 | DONE | panel_online cron data | PanelOnlineJobTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 141 | L1923 | DONE | reseller login + scoped data | BearerTokenTest / AuthControllerTest | dashboard-v23 B.1 | dashboard-v24-qa /auth/login | — |
| 142 | L1924 | DONE | sub-reseller hierarchy | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | — |
| 143 | L1925 | OPS | reseller bot webhook | GroupAcceptanceV23Test (smoke) | dashboard-v23 G.x | — | OPS-EVIDENCE-INDEX-V23 |
| 144 | L1934 | OPS | sync config/domains با VPS relay | RelaySetupOrderTest | dashboard-v23 B.4 | — | OPS-EVIDENCE-INDEX-V23 |
| 145 | L1935 | OPS | set webhook via relay | RelaySetupOrderTest | dashboard-v23 B.4 | — | OPS-EVIDENCE-INDEX-V23 |
| 146 | L1936 | OPS | control center ops از dashboard | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | OPS-EVIDENCE-INDEX-V23 |
| 147 | L1945 | DONE | broadcast 1000+ users بدون timeout | BroadcastLoadEnqueueTest | dashboard-v23 C.1 | — | — |
| 148 | L1946 | DONE | bulk wallet job complete | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | — |
| 149 | L1947 | DONE | marketing cron sends offers | MarketingCronOffersTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 150 | L1956 | OPS | backup دانلود و restore در staging | BackupRestoreStagingTest | dashboard-v23 H.2 | — | OPS-EVIDENCE-INDEX-V23 |
| 151 | L1957 | DONE | crypto IPN → transaction confirmed | CryptoIpnConfirmedTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 152 | L1958 | DONE | L2TP tab با feature flag | L2tpModuleGateTest | dashboard-v23 H.1 | — | — |
| 153 | L1967 | OPS | import از DB وردپرس بدون از دست رفتن داده | WpImportRowCountTest / WpImportForceTest | dashboard-v23.spec.ts (tab shell) | — | OPS-EVIDENCE-INDEX-V23 |
| 154 | L1968 | DONE | row counts match | WpImportRowCountTest | dashboard-v23.spec.ts (tab shell) | — | — |
| 155 | L1969 | OPS | parallel run WP+Laravel در staging | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | OPS-EVIDENCE-INDEX-V23 |
| 156 | L1978 | OPS | ۲۴h soak test بدون error spike | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | OPS-EVIDENCE-INDEX-V23 |
| 157 | L1979 | OPS | alerting روی panel down | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | OPS-EVIDENCE-INDEX-V23 |
| 158 | L1980 | OPS | WP خاموش — فقط Laravel | GroupAcceptanceV23Test (smoke) | dashboard-v23.spec.ts (tab shell) | — | OPS-EVIDENCE-INDEX-V23 |

Operator / date: 2026-06-13 (v25 post-audit)
