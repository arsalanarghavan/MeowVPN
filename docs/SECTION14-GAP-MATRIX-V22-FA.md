# §14 + §16 — ماتریس شکاف v22 (۱۲۶ checkbox صادقانه)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

| وضعیت | تعداد |
|--------|-------|
| DONE | 98 |
| PARTIAL | 18 |
| OPEN | 10 |

| # | Line | Status | Spec criterion | Evidence |
|---|------|--------|----------------|----------|
| 1 | L919 | DONE | کارت‌های آمار (users، receipts، panels) با داده واقعی | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 2 | L920 | DONE | reseller فقط متریک‌های زیرمجموعه خود را ببیند | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 3 | L921 | DONE | panel health badge قابل refresh | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 4 | L922 | DONE | لینک سریع به tabهای مجاز reseller کار کند | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 5 | L923 | DONE | economics overview card به `unit_economics` لینک دهد (admin) | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 6 | L948 | DONE | نمودار وضعیت پنل‌ها real-time refresh | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 7 | L949 | DONE | monitor hosts ping status | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 8 | L950 | DONE | reseller فقط پنل‌های مجاز | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 9 | L951 | DONE | دکمه refresh live metrics کار کند | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 10 | L979 | DONE | login موفق → redirect به dashboard | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 11 | L980 | DONE | session Sanctum برقرار شود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 12 | L981 | DONE | خطای credential → پیام `{ok:false}` | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 13 | L982 | DONE | CSRF cookie قبل از login | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 14 | L1006 | DONE | ذخیره branding و اعمال CSS vars در SPA | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 15 | L1007 | DONE | preview logo/favicon | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 16 | L1008 | DONE | portal page selector از pages list | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 17 | L1021 | DONE | overrideها در bot و dashboard نمایش داده شوند | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 18 | L1022 | DONE | reset به default ممکن باشد | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 19 | L1035 | DONE | proxy test به Telegram API موفق/ناموفض | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 20 | L1036 | DONE | bot requests از proxy عبور کنند | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 21 | L1050 | PARTIAL | Sync config → tenant روی relay | API only or marker — v22-p29..p45 |
| 22 | L1051 | PARTIAL | Set webhook via relay | API only or marker — v22-p29..p45 |
| 23 | L1052 | DONE | Control center: doctor، logs، nginx، SSL | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 24 | L1053 | PARTIAL | مطابق `relay-server/SETUP-GUIDE-FA.md` ترتیب راه‌اندازی | API only or marker — v22-p29..p45 |
| 25 | L1066 | DONE | تنظیمات notify در cronها اعمال شود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 26 | L1067 | PARTIAL | cooldown fields respected | API only or marker — v22-p29..p45 |
| 27 | L1083 | DONE | لیست سرویس‌های آماده purge | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 28 | L1084 | PARTIAL | manual purge one/all | API only or marker — v22-p29..p45 |
| 29 | L1085 | DONE | cron scan اجرا شود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 30 | L1098 | DONE | crypto settings فقط با MODULE_CRYPTO_ENABLED | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 31 | L1099 | DONE | NOWPayments keys encrypted | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 32 | L1115 | DONE | pagination و filter | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 33 | L1116 | PARTIAL | clear با confirm | API only or marker — v22-p29..p45 |
| 34 | L1128 | PARTIAL | defaults روی reseller جدید اعمال شود | API only or marker — v22-p29..p45 |
| 35 | L1129 | DONE | map permissions در admin/state | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 36 | L1153 | DONE | pagination users + pending | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 37 | L1154 | DONE | reseller فقط subtree | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 38 | L1155 | DONE | click → user detail | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 39 | L1156 | PARTIAL | manual create user | API only or marker — v22-p29..p45 |
| 40 | L1179 | PARTIAL | تمام service ops کار کنند | API only or marker — v22-p29..p45 |
| 41 | L1180 | DONE | panel sync/regen/transfer | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 42 | L1181 | DONE | reseller permission gates | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 43 | L1182 | PARTIAL | activity log نمایش داده شود | API only or marker — v22-p29..p45 |
| 44 | L1203 | DONE | ایجاد job و پیشرفت itemها | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 45 | L1204 | PARTIAL | cancel/resume | API only or marker — v22-p29..p45 |
| 46 | L1205 | DONE | worker cron هر دقیقه | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 47 | L1224 | DONE | preview تفاوت‌ها را نشان دهد | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 48 | L1225 | PARTIAL | merge اتمی — یک user باقی بماند | API only or marker — v22-p29..p45 |
| 49 | L1226 | DONE | audit log ثبت شود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 50 | L1244 | DONE | webhook register/delete | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 51 | L1245 | DONE | test connection هر platform | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 52 | L1246 | DONE | diagnostics dialog اطلاعات مفید | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 53 | L1260 | DONE | publish announcement به channel | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 54 | L1261 | DONE | gate در bot handler فعال شود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 55 | L1276 | DONE | edit fa/en per key | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 56 | L1277 | PARTIAL | reset one/all به defaults | API only or marker — v22-p29..p45 |
| 57 | L1290 | PARTIAL | drag-drop layout ذخیره شود | API only or marker — v22-p29..p45 |
| 58 | L1291 | DONE | reseller نتواند layout را تغییر دهد | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 59 | L1306 | DONE | admin: لیست همه reseller bots | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 60 | L1307 | DONE | reseller: فقط bot خود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 61 | L1308 | PARTIAL | webhook + relay per reseller domain | API only or marker — v22-p29..p45 |
| 62 | L1331 | DONE | CRUD panel | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 63 | L1332 | DONE | test connection 3x-ui | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 64 | L1333 | DONE | economics per panel | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 65 | L1334 | DONE | pagination | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 66 | L1350 | DONE | snapshot sync از پنل | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 67 | L1351 | DONE | batch ops روی clients | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 68 | L1352 | DONE | assign plan به orphan clients | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 69 | L1353 | PARTIAL | stale cache indicator | API only or marker — v22-p29..p45 |
| 70 | L1365 | DONE | خطوط هزینه ماهانه | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 71 | L1366 | DONE | mark paid → extend due date | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 72 | L1381 | DONE | قیمت per GB per panel per reseller | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 73 | L1382 | DONE | panel access toggle | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 74 | L1383 | DONE | inbound display labels | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 75 | L1399 | DONE | CRUD plan با panel/category binding | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 76 | L1400 | DONE | reseller floors نمایش (reseller mode) | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 77 | L1401 | DONE | wholesale line binding | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 78 | L1437 | PARTIAL | approve/reject با delivery | API only or marker — v22-p29..p45 |
| 79 | L1438 | DONE | filters و aggregates | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 80 | L1439 | DONE | reseller scope | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 81 | L1475 | DONE | customer charges list | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 82 | L1476 | PARTIAL | wallet topup checkout flow | API only or marker — v22-p29..p45 |
| 83 | L1541 | DONE | stats + daily chart | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 84 | L1542 | DONE | impersonate از admin | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 85 | L1591 | DONE | filter domain/event_type/q | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 86 | L1592 | DONE | pagination | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 87 | L1593 | DONE | impersonation events visible | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 88 | L1777 | DONE | `docker compose up` → nginx + mysql + redis + app healthy | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 89 | L1778 | DONE | `php artisan test` green (smoke) | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 90 | L1779 | DONE | `frontend` build به `frontend/dist/` و mount در nginx | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 91 | L1788 | DONE | `php artisan migrate` بدون خطا | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 92 | L1789 | DONE | Model factories برای users/services | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 93 | L1790 | DONE | settings CRUD unit test | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 94 | L1799 | DONE | login از React SPA کار کند | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 95 | L1800 | DONE | bootstrap `features`، `branding`، `navTabs` برگردد | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 96 | L1801 | DONE | role admin/reseller تشخیص داده شود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 97 | L1810 | DONE | تب users، plans، panels داده واقعی نشان دهند | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 98 | L1811 | DONE | pagination keys سازگار با SPA | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 99 | L1812 | DONE | reseller scoping verified | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 100 | L1821 | DONE | smoke test هر op → `{ok:true}` یا خطای معنادار | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 101 | L1822 | DONE | reseller policy matrix enforce شود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 102 | L1823 | DONE | audit log برای ops حساس | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 103 | L1832 | DONE | buy flow end-to-end در staging | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 104 | L1833 | DONE | service delivery بعد از receipt approve | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 105 | L1834 | DONE | rate limit webhook تست شود | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 106 | L1843 | DONE | create service روی 3x-ui | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 107 | L1844 | DONE | configs snapshot + batch ops | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 108 | L1845 | DONE | panel_online cron data | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 109 | L1854 | DONE | reseller login + scoped data | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 110 | L1855 | DONE | sub-reseller hierarchy | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 111 | L1856 | DONE | reseller bot webhook | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 112 | L1865 | DONE | sync config/domains با VPS relay | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 113 | L1866 | DONE | set webhook via relay | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 114 | L1867 | DONE | control center ops از dashboard | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 115 | L1876 | DONE | broadcast 1000+ users بدون timeout | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 116 | L1877 | DONE | bulk wallet job complete | dashboard-v22.spec.ts / PHPUnit v22 / OPS v22 |
| 117 | L1878 | OPEN | marketing cron sends offers | v22-p41..p44 strict UI pending |
| 118 | L1887 | OPEN | backup دانلود و restore در staging | v22-p41..p44 strict UI pending |
| 119 | L1888 | OPEN | crypto IPN → transaction confirmed | v22-p41..p44 strict UI pending |
| 120 | L1889 | OPEN | L2TP tab با feature flag | v22-p41..p44 strict UI pending |
| 121 | L1898 | OPEN | import از DB وردپرس بدون از دست رفتن داده | v22-p41..p44 strict UI pending |
| 122 | L1899 | OPEN | row counts match | v22-p41..p44 strict UI pending |
| 123 | L1900 | OPEN | parallel run WP+Laravel در staging | v22-p41..p44 strict UI pending |
| 124 | L1909 | OPEN | ۲۴h soak test بدون error spike | v22-p41..p44 strict UI pending |
| 125 | L1910 | OPEN | alerting روی panel down | v22-p41..p44 strict UI pending |
| 126 | L1911 | OPEN | WP خاموش — فقط Laravel | v22-p41..p44 strict UI pending |

Operator / date: 2026-06-15
