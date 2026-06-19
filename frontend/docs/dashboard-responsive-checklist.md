# چک‌لیست ریسپانسیو داشبورد

آخرین به‌روزرسانی: **2026-06-03**

مرجع پیاده‌سازی: [`dashboard-header-toolbar.tsx`](../src/components/dashboard-header-toolbar.tsx) · [`dash-locale.ts`](../src/lib/dash-locale.ts) (`dashDialogShellClass`) · breakpoint سایدبار **`md` (768px)**

---

## قرارداد shell

| عرض | هدر | آیکن‌های هدر (fullscreen، bot، زبان، accent، تم) |
|-----|-----|--------------------------------------------------|
| **&lt; 768px** | اپراتور: منو + جستجو؛ کاربر: فقط منو | فقط در سایدبار، زیر نام سایت (یا ابتدای `SidebarContent` برای پنل کاربر بدون هدر برند) |
| **≥ 768px** | منو + breadcrumb + جستجو (مرکز) + toolbar | در هدر (سمت راست/چپ منطقی) |

---

## تست خودکار v24

Playwright: `frontend/e2e/dashboard-v24-qa.spec.ts` — viewports 320/375/768/1280 + site_settings tablist.

## تست دستی — عرض viewport

- [ ] **320px** — هدر بدون overflow؛ سایدبار باز؛ toolbar در سایدبار
- [ ] **375px / 390px** — جستجو تمام‌عرض بین trigger و لبه
- [ ] **768px** — مرز Sheet موبایل ↔ سایدبار ثابت
- [ ] **1024px / 1280px+** — breadcrumb + toolbar در هدر

## تست دستی — locale

- [ ] **FA** — سایدبار از سمت درست؛ دیالوگ/شیت scroll
- [ ] **EN** — همان

## تست دستی — پاپ‌آپ‌ها

- [ ] **users** — دیالوگ merge (`sm:max-w-3xl`)
- [ ] **configs** — دیالوگ‌های CRUD
- [ ] **receipts** — پیش‌نمایش تصویر (`sm:max-w-5xl`)
- [ ] **backup** — AlertDialog حذف
- [ ] **CommandDialog** — پالت جستجو (`max-h` ~90dvh)

## تست دستی — صفحات

- [ ] **user detail** — `w-full min-w-0 max-w-7xl`
- [ ] **site_settings** — TabsList scroll افقی
- [ ] **impersonation** — دکمه پایان تمام‌عرض در xs

---

## تأیید خودکار

```bash
cd frontend
npm run build
npm run lint
```
