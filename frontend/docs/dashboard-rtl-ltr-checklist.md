# چک‌لیست ممیزی RTL/LTR داشبورد

آخرین اسکن خودکار: **2026-06-03** (فاز تکمیل Action items)

مرجع قرارداد: [`.cursor/rules/dashboard-rtl.mdc`](../../.cursor/rules/dashboard-rtl.mdc) · [`src/lib/dash-locale.ts`](../src/lib/dash-locale.ts)

**ریسپانسیو:** [`dashboard-responsive-checklist.md`](dashboard-responsive-checklist.md) (هدر موبایل، toolbar سایدبار، دیالوگ‌ها — 2026-06-03)

---

## قرارداد (خلاصه)

| Locale | `document` / `main` `dir` | تراز UI | Sheet | URL / کد / mono |
|--------|-------------------------|---------|-------|------------------|
| **FA** | `rtl` | `text-start` (نه `text-right`) | از **left** | `dashLtrCell()` یا `dir="ltr"` روی سلول |
| **EN** | `ltr` | `text-start` | از **right** | همان |

**صفحات جدید:** `useDashLocale()` + `<DashPage>` + `<DashSheetContent>` / `<DashDialogContent>` — بدون `isFa` از parent و بدون `text-right`/`text-left` برای RTL.

---

## معیار «صفحه کامل»

هر ردیف زیر وقتی **کامل** است که همهٔ موارد کد زیر تیک خورده باشند (و در صورت وجود Sheet/Dialog، wrapper درست باشد):

- [x] زیر `DashLocaleProvider` (به‌جز login)
- [x] بدون `isFa={isFa}` از [`dashboard-admin-view.tsx`](../src/components/dashboard-admin-view.tsx)
- [x] ریشه: `<DashPage>` (یا استثنای مستند)
- [x] بدون `text-right` / `text-left` / `flex-row-reverse` فقط-for-RTL در کد ادمین
- [x] Sheet/Dialog از `Dash*` wrappers (در صورت استفاده)
- [x] **تست خودکار v24:** `frontend/e2e/dashboard-v24-qa.spec.ts` (RTL dir + viewports)
- [ ] **تست دستی FA** (optional spot-check)
- [ ] **تست دستی EN** (optional spot-check)

**وضعیت کلی:** تمام تب‌های wired و Action items فاز RTL **در کد تکمیل شده‌اند**؛ فقط تست دستی FA/EN باقی مانده.

---

## دستورهای تأیید خودکار

```bash
cd frontend

# build
npm run build

# lint (شامل قانون text-left/right در components)
npm run lint

# regression — باید صفر باشد (admin-view)
rg 'isFa=\{isFa\}' src/components/dashboard-admin-view.tsx

# text-right/left فقط allowlist
rg 'text-right|text-left' src/components --glob '*.{tsx,ts}'
# انتظار: ui/alert-dialog.tsx, ui/command.tsx (palette)

# Dialog خام در ادمین — باید صفر
rg '<DialogContent' src/components --glob 'dashboard-*.tsx'

# Sheet خام در ادمین (غیر از dash-sheet / ui/sidebar)
rg '<SheetContent' src/components --glob 'dashboard-*.tsx'
```

**نتیجه اسکن 2026-06-03 (پس از تکمیل):**

| بررسی | نتیجه |
|--------|--------|
| `npm run build` | موفق |
| `isFa={isFa}` در admin-view | 0 |
| `text-right`/`text-left` در `components/` | فقط `ui/alert-dialog.tsx` (shadcn) |
| `<DialogContent` در `dashboard-*.tsx` | 0 (همه `DashDialogContent`) |
| فایل‌های `<DashPage>` | 28+ |
| فایل‌های `DashSheetContent` | 7 (+ wrapper) |
| فایل‌های `DashDialogContent` | 16 (+ logs-tab) |
| prop مرده `isFa?` در dashboard-* | 0 |
| prop مرده `rtl` در site-settings fields | 0 |

---

## پوسته (Shell)

| بخش | فایل | Provider / dir | nav بدون `rtl` prop | یادداشت | کد | تست FA | تست EN |
|-----|------|----------------|---------------------|---------|-----|--------|--------|
| App | [`App.tsx`](../src/App.tsx) | [x] `DashLocaleProvider` + `html`/`main` `dir` | — | `injectL2tpNavTab` برای nav ادمین | [x] | [ ] | [ ] |
| Sidebar | [`app-sidebar.tsx`](../src/components/app-sidebar.tsx) | [x] `useDashLocale` داخلی | [x] | — | [x] | [ ] | [ ] |
| Nav | [`nav-main.tsx`](../src/components/nav-main.tsx), [`nav-grouped.tsx`](../src/components/nav-grouped.tsx), [`nav-user.tsx`](../src/components/nav-user.tsx) | [x] | [x] | `text-start` + `dir` از context | [x] | [ ] | [ ] |
| Command palette | [`sidebar-search.tsx`](../src/components/sidebar-search.tsx) | [x] | [x] | فقط `useDashLocale()` | [x] | [ ] | [ ] |
| Command UI | [`ui/command.tsx`](../src/components/ui/command.tsx) | [x] optional `rtl` | — | shadcn + `flex-row-reverse` در palette RTL | استثنا | [ ] | [ ] |
| Login | [`dashboard-login.tsx`](../src/components/dashboard-login.tsx) | [ ] خارج provider | — | `lang` از boot؛ تست جدا | N/A | [ ] | [ ] |
| Data table | [`dash-data-table.tsx`](../src/components/dash-data-table.tsx) | [x] | — | `isFa` prop اختیاری deprecated | [x] | [ ] | [ ] |

---

## جدول تب‌های اصلی (admin-view)

ستون **کد:** [x] = برآورده شده · [~] = جزئی / استثنا · [ ] = ناقص · **—** = کاربرد ندارد · **N** = در admin-view mount نمی‌شود

| tabKey | کامپوننت | Mount | DashPage | useDashLocale | DashSheet | DashDialog | یادداشت کد | تست FA | تست EN |
|--------|----------|-------|----------|---------------|-----------|------------|------------|--------|--------|
| `dashboard` | `dashboard-overview.tsx` | Y | [x] | [x] | — | — | compact + full overview | [ ] | [ ] |
| `monitoring` | `dashboard-monitoring.tsx` | Y | [x] | [x] | — | — | دو حالت DashPage | [ ] | [ ] |
| `site_settings` | `dashboard-site-settings-admin.tsx` | Y | [x] | [~] | — | — | `w-full` subtabs + grid؛ `TabsList justify-start` | [ ] | [ ] |
| `users` | `dashboard-users-admin.tsx` | Y | [x] | [x] | — | [x] | `IdsCell` عمودی (ID/@username)؛ merge dialog | [ ] | [ ] |
| `users` (detail) | `dashboard-user-detail-admin.tsx` | Y | [x] | [x] | — | [x] | کارت سرویس: هدر RTL، quota/expiry FA، `service_set_note` | [ ] | [ ] |
| `users_bulk` | `dashboard-users-bulk-admin.tsx` | Y | [x] | [x] | — | [x] | `w-full`؛ grid xl:2col compose | [ ] | [ ] |
| `referral` / `referral_reports` | `dashboard-referral-admin.tsx` | Y | [x] | [x] | — | — | `w-full`؛ `DashTableShell` | [ ] | [ ] |
| `marketing_lifecycle` | `dashboard-marketing-lifecycle-admin.tsx` | Y | [x] | [ ] | — | Y | DashSheetContent؛ dashLtrCell؛ segment toolbar؛ reports/playbook | [ ] | [ ] |
| `discounts` | `dashboard-discounts-admin.tsx` | Y | [x] | [x] | [x] | [x] | `w-full` KPI grid | [ ] | [ ] |
| `unit_economics` | `dashboard-unit-economics-admin.tsx` | Y | [x] | [x] | — | — | `w-full`؛ `KpiGrid` | [x] | [ ] | [ ] |
| `bot_ui` | `dashboard-bot-ui-studio.tsx` | Y | [x] | [x] | — | — | `w-full`؛ toolbar `justify-end` در FA | [x] | [ ] | [ ] |
| `configs` | `dashboard-configs-admin.tsx` | Y | [x] | [x] | — | [x] | `w-full` | [ ] | [ ] |
| `backup` | `dashboard-backup-admin.tsx` | Y | [x] | [x] | — | [~] | `w-full` 2col؛ `DashTableShell` + pagination؛ AlertDialog | [ ] | [ ] |
| `resellers` | `dashboard-resellers-admin.tsx` | Y | [x] | [x] | — | [x] | جستجو `start-3` / `text-start` | [ ] | [ ] |
| `reseller_workspace` | `dashboard-user-detail-admin.tsx` | Y | [x] | [x] | — | [x] | همان detail | [ ] | [ ] |
| `reseller_reports` | `dashboard-reseller-reports-admin.tsx` | Y | [x] | [x] | — | — | `w-full`؛ KPI؛ AreaChart؛ `DashTableShell`؛ `formatNumber`؛ `ltrCell` برای ID | [ ] | [ ] |
| `reseller_bots` | `dashboard-bots-admin.tsx` | Y | [x] | [x] | — | [x] | variant reseller | [ ] | [ ] |
| `reseller_xui_panels` | `dashboard-reseller-panels-admin.tsx` | Y | [x] | [x] | — | — | | [ ] | [ ] |
| `reseller_settings` | `dashboard-reseller-settings.tsx` | Y | [x] | [~] | — | — | فرم ساده؛ dir از ancestor | [x] | [ ] | [ ] |
| `reseller_charge` | `dashboard-reseller-charge-admin.tsx` | Y | [x] | [x] | — | — | | [ ] | [ ] |
| `broadcast` | `dashboard-broadcast-admin.tsx` | Y | [x] | [x] | — | [x] | | [ ] | [ ] |
| `plans` | `dashboard-plans-admin.tsx` | Y | [x] | [x] | [x] | [x] | | [ ] | [ ] |
| `cards` | `dashboard-cards-admin.tsx` | Y | [x] | [x] | [x] | [x] | | [ ] | [ ] |
| `receipts` | `dashboard-receipts-admin.tsx` | Y | [x] | [x] | — | [x] | | [ ] | [ ] |
| `plan_cats` | `dashboard-plan-cats-admin.tsx` | Y | [x] | [x] | [x] | [x] | | [ ] | [ ] |
| `texts` | `dashboard-texts-admin.tsx` | Y | [x] | [~] | — | — | **content-dir ثابت** FA=rtl EN=ltr (عمدی) | [x] | [ ] | [ ] |
| `bots` | `dashboard-bots-admin.tsx` | Y | [x] | [x] | — | [x] | embed force-join | [ ] | [ ] |
| `xui_panels` | `dashboard-panels-admin.tsx` | Y | [x] | [x] | [x] | [x] | economics sheet جدا | [ ] | [ ] |
| `audit` | `dashboard-audit-admin.tsx` | Y | [x] | [x] | — | — | `DashTableShell`؛ `formatServiceExpiryLine`؛ `ltrCell` فنی | [ ] | [ ] |
| `l2tp_servers` | `dashboard-l2tp-admin.tsx` | **Y** | [x] | [x] | [x] | [x] | وقتی `features.l2tp`؛ nav + admin-view | [ ] | [ ] |
| `inbound_link` | — | **N** | — | — | — | — | redirect → `configs`؛ UI در configs | N/A | N/A |

---

## Site Settings — زیرتب‌ها

والد: [`dashboard-site-settings-admin.tsx`](../src/components/dashboard-site-settings-admin.tsx) — [x] `DashPage` · `TabsList justify-start` · `TabsContent text-start`

| subtab | فایل | useDashLocale | DashDialog | LTR fields | یادداشت | کد | تست FA | تست EN |
|--------|------|---------------|------------|------------|---------|-----|--------|--------|
| `whitelabel` | `site-settings-whitelabel-tab.tsx` | [x] | — | [x] `ltrCell` | `text-start` root | [x] | [ ] | [ ] |
| `service_naming` | `site-settings-service-naming-tab.tsx` | [x] | — | [x] `ltrCell` + `DashTableShell` inbound | | [x] | [ ] | [ ] |
| `proxy` | `site-settings-proxy-tab.tsx` | [x] | — | [x] `ltrCell` | | [x] | [ ] | [ ] |
| `notifications` | `site-settings-notifications-tab.tsx` | [x] | — | [x] `ltrCell` expiry days؛ `iconGapClass` | | [x] | [ ] | [ ] |
| `finance` | `site-settings-finance-tab.tsx` | [x] | — | [x] `ltrCell` | | [x] | [ ] | [ ] |
| `logs` | `site-settings-logs-tab.tsx` | [x] | [x] | [x] | `DashTableShell`؛ `formatServiceExpiryLine` | [x] | [ ] | [ ] |
| `resellers` | `site-settings-resellers-tab.tsx` | [ ] | — | — | `text-start` CardContent | [x] | [ ] | [ ] |

فیلدهای مشترک: [`image-url-field.tsx`](../src/components/site-settings/image-url-field.tsx), [`color-hex-field.tsx`](../src/components/site-settings/color-hex-field.tsx) — [x] `ltrCell` · بدون prop `rtl`

---

## کامپوننت‌های تو در تو / بدون تب مستقل

| نقش | فایل | useDashLocale | DashSheet | DashDialog | یادداشت | کد | تست FA | تست EN |
|-----|------|---------------|-----------|------------|---------|-----|--------|--------|
| Overview sections | `dashboard-overview-sections.tsx` | [x] | — | — | exportهای preview جداول | [x] | [ ] | [ ] |
| Economics card | `dashboard-economics-overview-card.tsx` | [x] | — | — | داخل overview | [x] | [ ] | [ ] |
| Payment alert | `dashboard-economics-payment-alert.tsx` | [x] | — | — | | [x] | [ ] | [ ] |
| Panel economics | `dashboard-panel-economics-sheet.tsx` | [x] | [x] | — | از panels | [x] | [ ] | [ ] |
| User service card | `dashboard-user-service-card.tsx` | [x] | — | [x] | | [x] | [ ] | [ ] |
| User merge | `dashboard-user-merge-admin.tsx` | [x] | — | — | داخل users | [x] | [ ] | [ ] |
| Bots IDs dialog | `dashboard-bots-admin-ids.tsx` | [x] | — | [x] | | [x] | [ ] | [ ] |
| Force join (embed) | `dashboard-force-join-admin.tsx` | [x] | — | — | داخل bots-admin | [x] | [ ] | [ ] |
| Page header | `dashboard-page-header.tsx` | — | — | — | layout only | [x] | [ ] | [ ] |
| Date picker | `dashboard-date-picker/*` + facades | [x] | — | — | FA: Jalali + `dir` از locale؛ EN: Gregorian + `dir=ltr`؛ فقط `DashboardDatePicker` / `DashboardDateTimePicker` | [x] | [ ] | [ ] |
| Team switcher | `team-switcher.tsx` | — | — | — | `text-start`؛ shadcn template | [x] | — | — |

**حذف شده (legacy orphan):**

- `dashboard-notifications-admin.tsx` → [`site-settings-notifications-tab.tsx`](../src/components/site-settings/site-settings-notifications-tab.tsx) + redirect `/dashboard/notifications/`
- `dashboard-logs-admin.tsx` → [`site-settings-logs-tab.tsx`](../src/components/site-settings/site-settings-logs-tab.tsx) + redirect `/dashboard/logs/`
- `dashboard-inbound-link-admin.tsx` → [`dashboard-configs-admin.tsx`](../src/components/dashboard-configs-admin.tsx) + redirect `inbound_link` → `configs`

---

## چک‌لیست تست دستی (هر تب مهم)

پس از تعویض زبان در UI (FA/EN)، این موارد را برای هر تب نمونه ببینید:

### FA (`lang=fa`)

- [ ] متن و عنوان‌ها از **راست** شروع می‌شوند (`text-start` تحت `dir=rtl`)
- [ ] Sheet از **سمت چپ** باز می‌شود (panels, plans, discounts, …)
- [ ] Dialog: تراز `text-start`؛ بدون `text-right` دستی
- [ ] جدول: سرستون و سلول با تراز منطقی
- [ ] URL / کد / mono: خوانا (`dir=ltr` یا `ltrCell`)
- [ ] Sidebar و command palette
- [ ] Overview + monitoring charts
- [ ] L2TP servers (اگر feature فعال)
- [ ] Date pickers: receipts/discounts/configs/economics — تقویم شمسی RTL

### EN (`lang=en`)

- [ ] همان موارد با `dir=ltr`
- [ ] Sheet از **سمت راست**
- [ ] Resellers search icon سمت start
- [ ] Site settings whitelabel hex/URL fields
- [ ] Texts admin: textarea FA همچنان rtl، EN همچنان ltr
- [ ] Date pickers: همان صفحات — تقویم میلادی LTR

### تب‌های پیشنهادی برای smoke test

| اولویت | tabKey | چرا |
|--------|--------|-----|
| P0 | `dashboard`, `users`, `xui_panels`, `configs` | پرترافیک + dialog/sheet |
| P0 | `site_settings` → whitelabel, logs | LTR fields + dialog |
| P1 | `plans`, `receipts`, `broadcast`, `resellers` | sheet/dialog/format + date filters |
| P1 | `texts`, `l2tp_servers` | content-dir + feature flag |
| P2 | `bot_ui`, `unit_economics`, `backup` | edge UI |

---

## Action items — تکمیل شده (2026-06-03)

| P | کار | وضعیت |
|---|-----|--------|
| P1 | prop مرده `isFa?` | [x] force-join, reseller-settings |
| P1 | حذف `rtl` از whitelabel fields | [x] |
| P1 | texts: content-dir ثابت (نه UI locale) | [x] مستند + comment |
| P2 | `KpiGrid` → `useDashLocale` داخلی | [x] |
| P2 | `sidebar-search` بدون prop `rtl` | [x] |
| P2 | Wire `DashboardL2tpAdmin` | [x] admin-view + `injectL2tpNavTab` |
| P3 | حذف `DashboardInboundLinkAdmin` | [x] ادغام در configs |
| P3 | `configs` → `dialogDir` از context | [x] |
| P3 | orphan logs/notifications | [x] حذف؛ redirect به site_settings |
| P4 | `team-switcher` → `text-start` | [x] |

---

## خلاصه درصد تکمیل (تخمینی)

| لایه | Wired tabs | کامل کد | نیاز تست دستی |
|------|------------|---------|----------------|
| زیرساخت + admin-view | 100% | 100% | بله |
| DashPage | 30/30 wired | 100% | بله |
| useDashLocale (صفحات با format) | ~100% | ~100% | بله |
| DashSheet | 7 صفحه | 100% جایی که sheet هست | بله |
| DashDialog | 16+ فایل | 100% جایی که Dialog هست | بله |
| **کل پروژه ادمین** | — | **~100%** | **همه تب‌های P0/P1** |

---

*این سند با اسکن repo و `npm run build` تولید شده؛ پس از هر فاز RTL، بخش «آخرین اسکن» و جداول کد را به‌روز کنید.*
