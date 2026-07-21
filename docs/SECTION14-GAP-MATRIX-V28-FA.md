# §14 + §16 — ماتریس شکاف v28 (145/158 DONE)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

| وضعیت | تعداد |
|--------|-------|
| DONE | 145 |
| OPS | 13 |
| PARTIAL | 0 |
| OPEN | 0 |

> **v28:** OPS rows flip to DONE when `docs/evidence/*-v28.log` passes strict `log_ok()` (no FAIL/SKIP).

> **Wave D honesty (Next App Router):**
> - **Line drift:** ستون Line نسبت به checkboxهای فعلی `LARAVEL-BACKEND-SPEC-FA.md` حدود **+۲۴** خط جلوتر است — `sync-spec-from-matrix.py` با **متن معیار** match می‌کند، نه Line به‌تنهایی.
> - **PHPUnit:** `GroupAcceptanceV23Test` فقط smoke JSON برای گروه‌های A–H است؛ **نه** پوشش UI/Playwright.
> - **Playwright-Next:** specهای فعال در `frontend/e2e/` (نه `quarantine/`). Vite-era `dashboard-v23`/`v24`/`v25` **قرنطینه** — evidence حذف/جایگزین شد.
> - Coverage mapping (smoke): `admin-depth`, `admin-mutate`, `residual-closeout-p2`, `residual-closeout-wave-d`, `shell-depth` — بقیه سطرها `—`.

| # | Line† | Status | Spec criterion | PHPUnit | Playwright-Next | OPS log |
|---|------|--------|----------------|---------|-----------------|---------|
| 1 | L932 | DONE | کارت‌های آمار (users، receipts، panels) با داده واقعی | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-depth / residual-closeout-p2 | — |
| 2 | L933 | DONE | reseller فقط متریک‌های زیرمجموعه خود را ببیند | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 3 | L934 | DONE | panel health badge قابل refresh | — | admin-depth / residual-closeout-p2 | — |
| 4 | L935 | DONE | لینک سریع به tabهای مجاز reseller کار کند | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-depth / residual-closeout-p2 | — |
| 5 | L936 | DONE | economics overview card به `unit_economics` لینک دهد (admin) | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-depth / residual-closeout-p2 | — |
| 6 | L961 | DONE | نمودار وضعیت پنل‌ها refresh (SPA polling 60s + manual refresh — v24 amendment; not WebSocket) | — | — | — |
| 7 | L962 | DONE | monitor hosts ping status | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 8 | L963 | DONE | reseller فقط پنل‌های مجاز | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 9 | L964 | DONE | دکمه refresh live metrics کار کند | — | — | — |
| 10 | L992 | DONE | login موفق → redirect به dashboard | BearerTokenTest | shell-depth | — |
| 11 | L993 | DONE | session Sanctum برقرار شود | BearerTokenTest | shell-depth | — |
| 12 | L994 | DONE | خطای credential → پیام `{ok:false}` | — | — | — |
| 13 | L995 | DONE | CSRF cookie قبل از login | BearerTokenTest | shell-depth | — |
| 14 | L1019 | DONE | ذخیره branding و اعمال CSS vars در SPA | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 15 | L1020 | DONE | preview logo/favicon | — | — | — |
| 16 | L1021 | DONE | portal page selector از pages list | — | — | — |
| 17 | L1034 | DONE | overrideها در bot و dashboard نمایش داده شوند | — | — | — |
| 18 | L1035 | DONE | reset به default ممکن باشد | — | — | — |
| 19 | L1048 | DONE | proxy test به Telegram API موفق/ناموفض | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 20 | L1049 | DONE | bot requests از proxy عبور کنند | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 21 | L1063 | DONE | Sync config → tenant روی relay | RelaySetupOrderTest | — (OPS/staging) | — |
| 22 | L1064 | DONE | Set webhook via relay | RelaySetupOrderTest | — (OPS/staging) | — |
| 23 | L1065 | DONE | Control center: doctor، logs، nginx، SSL | — | — | — |
| 24 | L1066 | DONE | مطابق `relay-server/SETUP-GUIDE-FA.md` ترتیب راه‌اندازی | RelaySetupOrderTest | — | — |
| 25 | L1079 | DONE | تنظیمات notify در cronها اعمال شود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 26 | L1080 | DONE | cooldown fields respected | — | — | — |
| 27 | L1096 | DONE | لیست سرویس‌های آماده purge | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 28 | L1097 | DONE | manual purge one/all | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 29 | L1098 | DONE | cron scan اجرا شود | — | — | — |
| 30 | L1111 | DONE | crypto settings فقط با `SVP_MODULE_CRYPTO=true` | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 31 | L1112 | DONE | NOWPayments keys encrypted | — | — | — |
| 32 | L1128 | DONE | pagination و filter | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 33 | L1129 | DONE | clear با confirm | — | — | — |
| 34 | L1141 | DONE | defaults روی reseller جدید اعمال شود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 35 | L1142 | DONE | map permissions در admin/state | — | — | — |
| 36 | L1166 | DONE | pagination users + pending | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 37 | L1167 | DONE | reseller فقط subtree | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 38 | L1168 | DONE | click → user detail | — | — | — |
| 39 | L1169 | DONE | manual create user | — | — | — |
| 40 | L1192 | DONE | تمام service ops کار کنند | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 41 | L1193 | DONE | panel sync/regen/transfer | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 42 | L1194 | DONE | reseller permission gates | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 43 | L1195 | DONE | activity log نمایش داده شود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 44 | L1216 | DONE | ایجاد job و پیشرفت itemها | — | — | — |
| 45 | L1217 | DONE | cancel/resume | — | — | — |
| 46 | L1218 | DONE | worker cron هر دقیقه | — | — | — |
| 47 | L1237 | DONE | preview تفاوت‌ها را نشان دهد | — | — | — |
| 48 | L1238 | DONE | merge اتمی — یک user باقی بماند | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-mutate | — |
| 49 | L1239 | DONE | audit log ثبت شود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 50 | L1257 | DONE | webhook register/delete | GroupAcceptanceV23Test (HTTP JSON smoke) | wave-d | — |
| 51 | L1258 | DONE | test connection هر platform | — | wave-d | — |
| 52 | L1259 | DONE | diagnostics dialog اطلاعات مفید | — | wave-d | — |
| 53 | L1273 | DONE | publish announcement به channel | — | wave-d | — |
| 54 | L1274 | DONE | gate در bot handler فعال شود | — | — | — |
| 55 | L1289 | DONE | edit fa/en per key | — | wave-d | — |
| 56 | L1290 | DONE | reset one/all به defaults | — | — | — |
| 57 | L1303 | DONE | drag-drop layout ذخیره شود | — | — | — |
| 58 | L1304 | DONE | reseller نتواند layout را تغییر دهد | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 59 | L1319 | DONE | admin: لیست همه reseller bots | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 60 | L1320 | DONE | reseller: فقط bot خود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 61 | L1321 | DONE | webhook + relay per reseller domain | RelaySetupOrderTest | — | — |
| 62 | L1344 | DONE | CRUD panel | — | — | — |
| 63 | L1345 | DONE | test connection 3x-ui | — | wave-d | — |
| 64 | L1346 | DONE | economics per panel | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 65 | L1347 | DONE | pagination | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 66 | L1363 | DONE | snapshot sync از پنل | — | — | — |
| 67 | L1364 | DONE | batch ops روی clients | — | — | — |
| 68 | L1365 | DONE | assign plan به orphan clients | — | — | — |
| 69 | L1366 | DONE | stale cache indicator | — | — | — |
| 70 | L1378 | DONE | خطوط هزینه ماهانه | — | — | — |
| 71 | L1379 | DONE | mark paid → extend due date | — | — | — |
| 72 | L1394 | DONE | قیمت per GB per panel per reseller | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 73 | L1395 | DONE | panel access toggle | — | — | — |
| 74 | L1396 | DONE | inbound display labels | — | — | — |
| 75 | L1412 | DONE | CRUD plan با panel/category binding | — | — | — |
| 76 | L1413 | DONE | reseller floors نمایش (reseller mode) | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 77 | L1414 | DONE | wholesale line binding | — | — | — |
| 78 | L1428 | DONE | CRUD plan category با panel binding | — | — | — |
| 79 | L1429 | DONE | active toggle و pagination | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 80 | L1430 | DONE | delete با guard foreign plans | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 81 | L1444 | DONE | add/edit/delete card از UI | — | — | — |
| 82 | L1445 | DONE | drag reorder ذخیره شود | — | — | — |
| 83 | L1459 | DONE | approve/reject با delivery | — | admin-mutate payments | — |
| 84 | L1460 | DONE | filters و aggregates | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 85 | L1461 | DONE | reseller scope | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 86 | L1475 | DONE | discount save از admin UI | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 87 | L1476 | DONE | discount delete با confirm | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 88 | L1477 | DONE | redemptions list نمایش داده شود | — | — | — |
| 89 | L1492 | DONE | save panel economics از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 90 | L1493 | DONE | save global config (usd rate) | — | — | — |
| 91 | L1494 | DONE | KPI grid پس از save refresh شود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 92 | L1507 | DONE | customer charges list | — | — | — |
| 93 | L1508 | DONE | wallet topup checkout flow | — | — | — |
| 94 | L1526 | DONE | broadcast send از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 95 | L1527 | DONE | queue progress نمایش داده شود | — | — | — |
| 96 | L1528 | DONE | broadcast cancel از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 97 | L1542 | DONE | marketing rule save از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-depth / wave-d | — |
| 98 | L1543 | DONE | manual send از UI | — | admin-depth / wave-d | — |
| 99 | L1544 | DONE | segment preview نمایش داده شود | — | admin-depth / wave-d | — |
| 100 | L1556 | DONE | referral settings save از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 101 | L1568 | DONE | referral chart داده واقعی | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 102 | L1569 | DONE | referral table pagination | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 103 | L1583 | DONE | reseller provision از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 104 | L1584 | DONE | permissions save از UI | — | — | — |
| 105 | L1585 | DONE | bind users از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 106 | L1595 | DONE | stats + daily chart | — | — | — |
| 107 | L1596 | DONE | impersonate از admin | ImpersonationTest / dashboard-v25-depth | shell-depth | — |
| 108 | L1609 | DONE | inbound labels save از UI | — | — | — |
| 109 | L1610 | DONE | payment methods save از UI | — | — | — |
| 110 | L1627 | DONE | l2tp add از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-tabs smoke | — |
| 111 | L1628 | DONE | l2tp update از UI | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-tabs smoke | — |
| 112 | L1629 | DONE | l2tp delete با confirm | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-tabs smoke | — |
| 113 | L1630 | DONE | tab مخفی وقتی `SVP_MODULE_L2TP=false` | — | admin-tabs smoke | — |
| 114 | L1644 | DONE | backup download از UI | BackupRestoreStagingTest | admin-depth / wave-d backup surface | — |
| 115 | L1645 | DONE | backup upload restore از UI | BackupRestoreStagingTest | admin-depth / wave-d backup surface | — |
| 116 | L1646 | DONE | manual backup run از UI | BackupRestoreStagingTest | admin-depth / wave-d backup surface | — |
| 117 | L1660 | DONE | filter domain/event_type/q | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 118 | L1661 | DONE | pagination | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 119 | L1662 | DONE | impersonation events visible | ImpersonationTest / dashboard-v25-depth | shell-depth | — |
| 120 | L1846 | OPS | `docker compose up` → nginx + mysql + redis + app healthy | ParityMigrationMysqlTest + docker-smoke-v28 | — | evidence/docker-smoke-v28.log |
| 121 | L1847 | DONE | `php artisan test` green (smoke) | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 122 | L1848 | DONE | `frontend` build به `frontend/dist/` و mount در nginx | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 123 | L1857 | DONE | `php artisan migrate` بدون خطا | ParityMigrationMysqlTest | — | — |
| 124 | L1858 | DONE | Model factories برای users/services | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 125 | L1859 | DONE | settings CRUD unit test | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 126 | L1868 | DONE | login از React SPA کار کند | BearerTokenTest | — | — |
| 127 | L1869 | DONE | bootstrap `features`، `branding`، `navTabs` برگردد | BootstrapControllerTest | — | — |
| 128 | L1870 | DONE | role admin/reseller تشخیص داده شود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 129 | L1879 | DONE | تب users، plans، panels داده واقعی نشان دهند | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 130 | L1880 | DONE | pagination keys سازگار با SPA | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 131 | L1881 | DONE | reseller scoping verified | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 132 | L1890 | DONE | smoke test هر op → `{ok:true}` یا خطای معنادار | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 133 | L1891 | DONE | reseller policy matrix enforce شود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 134 | L1892 | DONE | audit log برای ops حساس | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 135 | L1901 | OPS | buy flow end-to-end در staging | GroupAcceptanceV23Test (HTTP JSON smoke) | — | evidence/staging-buy-flow-v28.log |
| 136 | L1902 | DONE | service delivery بعد از receipt approve | — | admin-mutate payments | — |
| 137 | L1903 | DONE | rate limit webhook تست شود | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 138 | L1912 | DONE | create service روی 3x-ui | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 139 | L1913 | DONE | configs snapshot + batch ops | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-mutate configs | — |
| 140 | L1914 | DONE | panel_online cron data | — | — | — |
| 141 | L1923 | DONE | reseller login + scoped data | BearerTokenTest | — | — |
| 142 | L1924 | DONE | sub-reseller hierarchy | GroupAcceptanceV23Test (HTTP JSON smoke) | — | — |
| 143 | L1925 | OPS | reseller bot webhook | GroupAcceptanceV23Test (HTTP JSON smoke) | — | evidence/reseller-webhook-v28.log |
| 144 | L1934 | OPS | sync config/domains با VPS relay | RelaySetupOrderTest | — (OPS/staging) | evidence/relay-forward-v28.log |
| 145 | L1935 | OPS | set webhook via relay | RelaySetupOrderTest | — (OPS/staging) | evidence/relay-webhook-set-v28.log |
| 146 | L1936 | OPS | control center ops از dashboard | — | — | evidence/relay-control-center-v28.log |
| 147 | L1945 | DONE | broadcast 1000+ users بدون timeout | BroadcastLoadEnqueueTest | — | — |
| 148 | L1946 | DONE | bulk wallet job complete | GroupAcceptanceV23Test (HTTP JSON smoke) | admin-mutate configs | — |
| 149 | L1947 | DONE | marketing cron sends offers | MarketingCronOffersTest | — | — |
| 150 | L1956 | OPS | backup دانلود و restore در staging | BackupRestoreStagingTest | — | evidence/backup-restore-staging-v28.log |
| 151 | L1957 | DONE | crypto IPN → transaction confirmed | CryptoIpnConfirmedTest | — | — |
| 152 | L1958 | DONE | L2TP tab با feature flag | L2tpModuleGateTest | admin-tabs smoke | — |
| 153 | L1967 | OPS | import از DB وردپرس بدون از دست رفتن داده | WpImportRowCountTest | — | evidence/import-run-v28.log |
| 154 | L1968 | OPS | row counts match | WpImportRowCountTest | — | evidence/import-verify-v28.log |
| 155 | L1969 | OPS | parallel run WP+Laravel در staging | GroupAcceptanceV23Test (HTTP JSON smoke) | — | evidence/phase16-parallel-v28.log |
| 156 | L1978 | OPS | ۲۴h soak test بدون error spike | soak-24h-v26.log | — | evidence/soak-24h-v28.log |
| 157 | L1979 | OPS | alerting روی panel down | admin-alerts-fire-smoke-v28 | — | evidence/admin-alerts-v28.log |
| 158 | L1980 | OPS | WP خاموش — فقط Laravel | wp-disable-v26.log | — | evidence/wp-disable-v28.log |

Operator / date: 2026-07-20 (v28 — Wave D docs honesty; 13 OPS pending live verify)
