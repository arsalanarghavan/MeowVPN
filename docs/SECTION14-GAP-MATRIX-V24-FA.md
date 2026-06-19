# §14 + §16 — ماتریس شکاف v24 (158 checkbox (carried forward))

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

| وضعیت | تعداد |
|--------|-------|
| DONE | 158 |
| PARTIAL | 0 |
| OPEN | 0 |

> **v24:** Carried forward from v23 (158/158 DONE). Additional evidence: `dashboard-v24-qa.spec.ts`, [`OPS-MAINTENANCE-CALENDAR-V24-FA.md`](OPS-MAINTENANCE-CALENDAR-V24-FA.md). OPS logs: [`OPS-EVIDENCE-INDEX-V23.md`](evidence/OPS-EVIDENCE-INDEX-V23.md).

| # | Line | Status | Spec criterion | Evidence |
|---|------|--------|----------------|----------|
| 1 | L932 | DONE | کارت‌های آمار (users، receipts، panels) با داده واقعی | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 2 | L933 | DONE | reseller فقط متریک‌های زیرمجموعه خود را ببیند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 3 | L934 | DONE | panel health badge قابل refresh | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 4 | L935 | DONE | لینک سریع به tabهای مجاز reseller کار کند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 5 | L936 | DONE | economics overview card به `unit_economics` لینک دهد (admin) | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 6 | L961 | DONE | نمودار وضعیت پنل‌ها real-time refresh | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 7 | L962 | DONE | monitor hosts ping status | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 8 | L963 | DONE | reseller فقط پنل‌های مجاز | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 9 | L964 | DONE | دکمه refresh live metrics کار کند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 10 | L992 | DONE | login موفق → redirect به dashboard | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 11 | L993 | DONE | session Sanctum برقرار شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 12 | L994 | DONE | خطای credential → پیام `{ok:false}` | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 13 | L995 | DONE | CSRF cookie قبل از login | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 14 | L1019 | DONE | ذخیره branding و اعمال CSS vars در SPA | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 15 | L1020 | DONE | preview logo/favicon | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 16 | L1021 | DONE | portal page selector از pages list | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 17 | L1034 | DONE | overrideها در bot و dashboard نمایش داده شوند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 18 | L1035 | DONE | reset به default ممکن باشد | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 19 | L1048 | DONE | proxy test به Telegram API موفق/ناموفض | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 20 | L1049 | DONE | bot requests از proxy عبور کنند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 21 | L1063 | DONE | Sync config → tenant روی relay | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 22 | L1064 | DONE | Set webhook via relay | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 23 | L1065 | DONE | Control center: doctor، logs، nginx، SSL | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 24 | L1066 | DONE | مطابق `relay-server/SETUP-GUIDE-FA.md` ترتیب راه‌اندازی | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 25 | L1079 | DONE | تنظیمات notify در cronها اعمال شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 26 | L1080 | DONE | cooldown fields respected | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 27 | L1096 | DONE | لیست سرویس‌های آماده purge | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 28 | L1097 | DONE | manual purge one/all | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 29 | L1098 | DONE | cron scan اجرا شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 30 | L1111 | DONE | crypto settings فقط با MODULE_CRYPTO_ENABLED | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 31 | L1112 | DONE | NOWPayments keys encrypted | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 32 | L1128 | DONE | pagination و filter | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 33 | L1129 | DONE | clear با confirm | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 34 | L1141 | DONE | defaults روی reseller جدید اعمال شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 35 | L1142 | DONE | map permissions در admin/state | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 36 | L1166 | DONE | pagination users + pending | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 37 | L1167 | DONE | reseller فقط subtree | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 38 | L1168 | DONE | click → user detail | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 39 | L1169 | DONE | manual create user | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 40 | L1192 | DONE | تمام service ops کار کنند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 41 | L1193 | DONE | panel sync/regen/transfer | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 42 | L1194 | DONE | reseller permission gates | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 43 | L1195 | DONE | activity log نمایش داده شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 44 | L1216 | DONE | ایجاد job و پیشرفت itemها | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 45 | L1217 | DONE | cancel/resume | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 46 | L1218 | DONE | worker cron هر دقیقه | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 47 | L1237 | DONE | preview تفاوت‌ها را نشان دهد | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 48 | L1238 | DONE | merge اتمی — یک user باقی بماند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 49 | L1239 | DONE | audit log ثبت شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 50 | L1257 | DONE | webhook register/delete | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 51 | L1258 | DONE | test connection هر platform | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 52 | L1259 | DONE | diagnostics dialog اطلاعات مفید | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 53 | L1273 | DONE | publish announcement به channel | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 54 | L1274 | DONE | gate در bot handler فعال شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 55 | L1289 | DONE | edit fa/en per key | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 56 | L1290 | DONE | reset one/all به defaults | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 57 | L1303 | DONE | drag-drop layout ذخیره شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 58 | L1304 | DONE | reseller نتواند layout را تغییر دهد | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 59 | L1319 | DONE | admin: لیست همه reseller bots | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 60 | L1320 | DONE | reseller: فقط bot خود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 61 | L1321 | DONE | webhook + relay per reseller domain | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 62 | L1344 | DONE | CRUD panel | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 63 | L1345 | DONE | test connection 3x-ui | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 64 | L1346 | DONE | economics per panel | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 65 | L1347 | DONE | pagination | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 66 | L1363 | DONE | snapshot sync از پنل | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 67 | L1364 | DONE | batch ops روی clients | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 68 | L1365 | DONE | assign plan به orphan clients | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 69 | L1366 | DONE | stale cache indicator | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 70 | L1378 | DONE | خطوط هزینه ماهانه | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 71 | L1379 | DONE | mark paid → extend due date | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 72 | L1394 | DONE | قیمت per GB per panel per reseller | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 73 | L1395 | DONE | panel access toggle | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 74 | L1396 | DONE | inbound display labels | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 75 | L1412 | DONE | CRUD plan با panel/category binding | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 76 | L1413 | DONE | reseller floors نمایش (reseller mode) | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 77 | L1414 | DONE | wholesale line binding | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 78 | L1428 | DONE | CRUD plan category با panel binding | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 79 | L1429 | DONE | active toggle و pagination | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 80 | L1430 | DONE | delete با guard foreign plans | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 81 | L1444 | DONE | add/edit/delete card از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 82 | L1445 | DONE | drag reorder ذخیره شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 83 | L1459 | DONE | approve/reject با delivery | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 84 | L1460 | DONE | filters و aggregates | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 85 | L1461 | DONE | reseller scope | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 86 | L1475 | DONE | discount save از admin UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 87 | L1476 | DONE | discount delete با confirm | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 88 | L1477 | DONE | redemptions list نمایش داده شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 89 | L1492 | DONE | save panel economics از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 90 | L1493 | DONE | save global config (usd rate) | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 91 | L1494 | DONE | KPI grid پس از save refresh شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 92 | L1507 | DONE | customer charges list | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 93 | L1508 | DONE | wallet topup checkout flow | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 94 | L1526 | DONE | broadcast send از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 95 | L1527 | DONE | queue progress نمایش داده شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 96 | L1528 | DONE | broadcast cancel از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 97 | L1542 | DONE | marketing rule save از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 98 | L1543 | DONE | manual send از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 99 | L1544 | DONE | segment preview نمایش داده شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 100 | L1556 | DONE | referral settings save از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 101 | L1568 | DONE | referral chart داده واقعی | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 102 | L1569 | DONE | referral table pagination | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 103 | L1583 | DONE | reseller provision از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 104 | L1584 | DONE | permissions save از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 105 | L1585 | DONE | bind users از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 106 | L1595 | DONE | stats + daily chart | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 107 | L1596 | DONE | impersonate از admin | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 108 | L1609 | DONE | inbound labels save از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 109 | L1610 | DONE | payment methods save از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 110 | L1627 | DONE | l2tp add از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 111 | L1628 | DONE | l2tp update از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 112 | L1629 | DONE | l2tp delete با confirm | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 113 | L1630 | DONE | tab مخفی وقتی `MODULE_L2TP_ENABLED=false` | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 114 | L1644 | DONE | backup download از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 115 | L1645 | DONE | backup upload restore از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 116 | L1646 | DONE | manual backup run از UI | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 117 | L1660 | DONE | filter domain/event_type/q | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 118 | L1661 | DONE | pagination | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 119 | L1662 | DONE | impersonation events visible | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 120 | L1846 | DONE | `docker compose up` → nginx + mysql + redis + app healthy | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 121 | L1847 | DONE | `php artisan test` green (smoke) | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 122 | L1848 | DONE | `frontend` build به `frontend/dist/` و mount در nginx | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 123 | L1857 | DONE | `php artisan migrate` بدون خطا | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 124 | L1858 | DONE | Model factories برای users/services | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 125 | L1859 | DONE | settings CRUD unit test | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 126 | L1868 | DONE | login از React SPA کار کند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 127 | L1869 | DONE | bootstrap `features`، `branding`، `navTabs` برگردد | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 128 | L1870 | DONE | role admin/reseller تشخیص داده شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 129 | L1879 | DONE | تب users، plans، panels داده واقعی نشان دهند | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 130 | L1880 | DONE | pagination keys سازگار با SPA | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 131 | L1881 | DONE | reseller scoping verified | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 132 | L1890 | DONE | smoke test هر op → `{ok:true}` یا خطای معنادار | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 133 | L1891 | DONE | reseller policy matrix enforce شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 134 | L1892 | DONE | audit log برای ops حساس | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 135 | L1901 | DONE | buy flow end-to-end در staging | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 136 | L1902 | DONE | service delivery بعد از receipt approve | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 137 | L1903 | DONE | rate limit webhook تست شود | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 138 | L1912 | DONE | create service روی 3x-ui | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 139 | L1913 | DONE | configs snapshot + batch ops | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 140 | L1914 | DONE | panel_online cron data | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 141 | L1923 | DONE | reseller login + scoped data | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 142 | L1924 | DONE | sub-reseller hierarchy | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 143 | L1925 | DONE | reseller bot webhook | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 144 | L1934 | DONE | sync config/domains با VPS relay | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 145 | L1935 | DONE | set webhook via relay | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 146 | L1936 | DONE | control center ops از dashboard | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 147 | L1945 | DONE | broadcast 1000+ users بدون timeout | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 148 | L1946 | DONE | bulk wallet job complete | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 149 | L1947 | DONE | marketing cron sends offers | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 150 | L1956 | DONE | backup دانلود و restore در staging | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 151 | L1957 | DONE | crypto IPN → transaction confirmed | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 152 | L1958 | DONE | L2TP tab با feature flag | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 153 | L1967 | DONE | import از DB وردپرس بدون از دست رفتن داده | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 154 | L1968 | DONE | row counts match | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 155 | L1969 | DONE | parallel run WP+Laravel در staging | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 156 | L1978 | DONE | ۲۴h soak test بدون error spike | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 157 | L1979 | DONE | alerting روی panel down | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |
| 158 | L1980 | DONE | WP خاموش — فقط Laravel | dashboard-v24-qa.spec.ts / PHPUnit v24 / OPS-EVIDENCE-INDEX-V23 |

Operator / date: 2026-06-16
