# انحراف nav — `notifications` و `logs` (v17)

Spec §14 در برخی نسخه‌ها `notifications` و `logs` را به‌عنوان tab سطح بالا در `navTabs` سرور ذکر می‌کند.

**پیاده‌سازی:** این دو بخش زیرمجموعه `site_settings` هستند (`?site_subtab=notifications` / `logs`) و در `NavTabsBuilder` به‌صورت tab جداگانه برگردانده نمی‌شوند. فرانت‌اند (`admin-nav.ts`) آن‌ها را در `ADMIN_ONLY_TAB_KEYS` نگه می‌دارد و از طریق subtab بارگذاری می‌شوند.

**تست:** Playwright `dashboard-v17.spec.ts` — `site_settings` با subtab.
