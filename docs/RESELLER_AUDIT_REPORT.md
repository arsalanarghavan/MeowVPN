# گزارش ممیزی جامع بخش نمایندگی (Reseller)

**تاریخ:** ۱۳ ژوئن ۲۰۲۶ (v27 honest audit)  
**دامنه:** داشبورد SPA، REST API، پورتال legacy، بات وایت‌لیبل، wholesale، impersonation، backup  
**روش:** بررسی استاتیک کد + تست‌های قراردادی + smoke test (PHPUnit به‌خاطر نبود `dom/xml/xmlwriter` اجرا نشد)

> **v27 (Laravel):** [`SECTION14-GAP-MATRIX-V27-FA.md`](docs/SECTION14-GAP-MATRIX-V27-FA.md) — **145 DONE / 13 OPS honest**.  
> تست‌های reseller: [`backend/tests/Feature/Reseller/`](backend/tests/Feature/Reseller/) (نه `tests/Reseller*.php` ریشه WP).

---

## خلاصه اجرایی

سیستم نمایندگی **چندلایه و در کل محکم** است: scope engine، mutate policy، owner injection، redaction در REST. یافته‌های ممیزی اول که به فایل‌های WP (`includes/`, `class-rest-dashboard.php`) اشاره داشتند در **v25 به معادل Laravel منتقل یا resolved** شدند — جدول «منابع حقیقت» بالا مرجع جاری است.

| شدت | تعداد باز | وضعیت |
|-----|-----------|--------|
| Critical | 0 | — |
| High | 1 | H-1 by design (پورتال تخفیف) |
| Medium | 3 | S-5 redact backup، F-4 margin docs، portal HMAC risk |
| Low | 10+ | بهبود تدریجی |
| By design | 5 | آگاهی محصول |
| **رفع‌شده** | 18+ | فاز ۱–۳ + ممیزی اول |

---

## وضعیت رفع یافته‌های ممیزی اول

| ID | موضوع | وضعیت |
|----|-------|--------|
| M-1 | `reseller_reports` مخفی در sidebar | **رفع** — حذف از `ADMIN_ONLY_TAB_KEYS` + inject در `admin-nav.ts` |
| M-2 | fallback کلاینت ناقص | **رفع** — `reseller_reports: "users.manage"` در `App.tsx` |
| M-6 | توکن‌های legacy plaintext | **رفع** — migration `2.4.4` + `migrate_plaintext_tokens_to_encrypted()` |
| B-1 | دو op توکن تکراری | **رفع** — `patch_reseller_bot_profile_tokens()` |
| B-3 | perms در backup | **رفع** — `wordpress/reseller-permissions.json` export/restore |
| H-1 | REST vs پورتال تخفیف | **By design** — مستند در mutate-policy و admin-ajax |
| M-4 | webhook secret در URL | **مستند** — یادداشت امنیتی در `class-webhook.php` |

---

## ۱. ممیزی تب‌ها و دسترسی‌ها

### منابع حقیقت (Laravel v25)

| لایه | فایل | نقش |
|------|------|-----|
| سرور | [`NavTabsBuilder.php`](backend/app/Services/Dashboard/NavTabsBuilder.php) | `resellerAllowedTabs` در bootstrap/admin state |
| سرور | [`MutatePolicyService.php`](backend/app/Services/Mutations/MutatePolicyService.php) | `$resellerMap` (61 entry) + module gates |
| سرور | [`ResellerScopeMiddleware.php`](backend/app/Http/Middleware/ResellerScopeMiddleware.php) | owner injection + subtree scope |
| REST | [`AdminStateController.php`](backend/app/Http/Controllers/Api/AdminStateController.php) | aggregate state per `activeTab` |
| کلاینت | [`App.tsx`](frontend/src/App.tsx) → `RESELLER_ALLOWED_BY_PERMISSION` | fallback اگر bootstrap ناقص باشد |
| ناوبری | [`admin-nav.ts`](frontend/src/config/admin-nav.ts) → `ADMIN_ONLY_TAB_KEYS` | sidebar filter |
| URL | [`dash-tab.ts`](frontend/src/lib/dash-tab.ts) + `safeResellerTab()` | ریدایرکت تب غیرمجاز |

> **WP legacy:** `class-rest-dashboard.php` / `includes/` — **resolved v25**؛ منبع حقیقت فقط Laravel paths بالا.

### ماتریس تطبیق (پس از رفع M-1/M-2)

| تب | perm سرور | fallback کلاینت | nav نماینده | URL (`safeResellerTab`) |
|----|-----------|-----------------|-------------|-------------------------|
| `dashboard` | همیشه | ✓ | ✓ | ✓ |
| `reseller_settings` | همیشه | ✓ | inject | ✓ |
| `reseller_workspace` | همیشه | ✓ | — | ✓ |
| `reseller_reports` | `users.manage` | ✓ (رفع) | ✓ (رفع) | ✓ |
| `reseller_charge` | `plans.manage` | ✓ | inject | ✓ |
| `reseller_bots` | `services.manage` | ✓ | inject | ✓ |
| `users` / `resellers` | `users.manage` | ✓ | ✓ | ✓ |
| `plans` / `discounts` / `cards` | `plans.manage` | ✓ | ✓ | ✓ |
| `marketing_lifecycle` | `marketing.lifecycle` | ✓ | ✓ | ✓ |
| `monitoring` | `services.manage` | ✓ | ✓ | ✓ |
| `audit` | خارج از map | — | مخفی | ✗ |
| `site_settings` / `backup` / `xui_panels` / `configs` / `unit_economics` | `admin_only` | — | مخفی | ✗ |
| `notifications` / `logs` | `admin_only` سرور | — | در nav نیست | ✗ |
| `reseller_xui_panels` | فقط ادمین (خارج `all_tabs` map) | — | مخفی | ✗ (مگر bypass UI) |

### یافته‌های تب / UX

#### L-1 — `notifications` و `logs` فقط در سرور block (Low)

در `admin_only` سرور هستند؛ در `ADMIN_ONLY_TAB_KEYS` کلاینت نیستند. در عمل nav و `safeResellerTab` دسترسی را می‌بندند.

#### L-2 — سرور `activeTab` را validate نمی‌کند (Low)

`navTabs` فیلتر می‌شود ولی payload بر اساس `activeTab` بدون چک مجوز تب ساخته می‌شود. داده حساس (panels کامل، unit economics، audit) برای reseller لود نمی‌شوند — **IDOR مستقیم یافت نشد**.

#### N-1 — `reseller_xui_panels` بدون guard صریح در SPA (Medium)

`dashboard-admin-view.tsx` تب `reseller_xui_panels` را بدون `!isReseller` رندر می‌کند. `safeResellerTab` معمولاً جلوگیری می‌کند؛ defense-in-depth ضعیف است.

**پیشنهاد:** `if (activeTab === "reseller_xui_panels" && isReseller) return null` یا redirect.

#### N-2 — `reseller_workspace` props ناقص (Low)

مسیر workspace جزئیات کاربر بدون `canReviewReceipts` / `enabledPlatforms` نسبت به مسیر عادی users — ناسازگاری UX.

---

## ۲. ممیزی SPA (کامپوننت‌ها)

### الگوی کلی

UI بیشتر **read-only/disabled** برای reseller است؛ **امنیت واقعی روی REST** متکی است. این درست است اما چند نقطه defense-in-depth ضعیف دارند.

| فایل | وضعیت | یافته |
|------|--------|-------|
| `dashboard-marketing-lifecycle-admin.tsx` | قوی | `canMutate = !readOnlySettings && !isReseller` |
| `dashboard-resellers-admin.tsx` | قوی | granular: `canManageResellerControls`, panel price scope |
| `dashboard-user-detail-admin.tsx` | ضعیف UI | wallet/ban/marketing/service mutate بدون `isReseller` gate |
| `dashboard-reseller-panels-admin.tsx` | فقط نمایش | بدون role guard (ادمین-only) |
| `dashboard-plans-admin.tsx` | قابل قبول | reseller mutations با `plans.manage` سرور |
| `dashboard-bots-admin.tsx` | قابل قبول | variant `reseller_self` محدود |

#### N-3 — User detail: mutation entry points بدون UI gate (Medium / امنیت سرور)

`dashboard-user-detail-admin.tsx`: wallet delta، ban/unban، `marketing_send_manual`، service ops برای reseller UI محدود نشده‌اند. REST با scope + policy محافظت می‌کند؛ **اگر سرور درست باشد OK** — ولی UX گمراه‌کننده و ریسک رگرسیون.

**پیشنهاد:** mirror permission flags از parent یا `readOnlyAdminActions`.

#### N-4 — Marketing rule sheet: دکمه save بدون `canMutate` (Low)

ورودی‌ها gated هستند؛ sheet save defense-in-depth ندارد.

---

## ۳. ممیزی REST / IDOR

### Endpointها

| Endpoint | Guard | نتیجه |
|----------|-------|-------|
| `GET /dashboard/admin/state` | `perm_admin_or_reseller` + scope | ✓ |
| `GET /dashboard/admin/user/{id}` | `dashboard_actor_may_read_user` | ✓ |
| `GET /dashboard/admin/user-search` | `effective_moderatable_user_ids` | ✓ |
| `POST /dashboard/admin/mutate` | policy → perm → scope → injection | ✓ |
| `GET /dashboard/admin/audit` | `perm_manage` | ✓ |

### mutate ops (۱۱۸ dispatch / ۵۹ policy)

- **۵۹ op** خارج policy → `forbidden_op` برای reseller (قبل از handler)
- **۵۹ op** داخل policy → perm + scope + handler guards
- `gate_service_moderation_for_op` برای service ops

### یافته‌های REST

#### N-5 — `receipt_reject_reasons_save` تنظیمات سراسری (Medium)

در policy با `receipts.review` مجاز است؛ handler `apply_settings_tab('receipts', …)` — **تغییر preset رد رسید برای کل سایت**، نه per-reseller.

**پیشنهاد:** حذف از policy reseller یا storage جداگانه.

#### N-6 — `discount_redemptions` ممکن است PII خارج scope نشان دهد (Low)

کد مالکیت discount چک می‌شود؛ redeemerها فیلتر `actor_may_moderate_user` ندارند.

#### N-7 — `bot_delete_webhook` در policy ولی handler مسدود (Info)

reseller باید `reseller_bot_webhook_delete` استفاده کند — mismatch policy/handler.

#### ✓ تأیید‌شده

- `user_manual_create`: `invited_by` اجباری
- Impersonation read با scope target
- User rows: `dashboard_password`, `state_data` حذف
- Panel credentials unset در API

---

## ۴. ممیزی پورتال Legacy

### ops پورتال (۲۲ op)

اکثر ops با `portal_admin_can_access_user` / scope IDs محافظت شده‌اند.  
`portal_deny_reseller_global`: crypto، referral، bulk site-wide.

### یافته‌های پورتال

#### H-1 — تخفیف: REST مسدود، پورتال مجاز (High / By design)

| مسیر | `discount_save` | `discount_delete` |
|------|-----------------|-------------------|
| REST | `forbidden_op` | `forbidden_op` |
| پورتال امضاشده | مجاز + owner injection | مجاز + owner match |
| SPA | read-only + `portalAdminUrl` | read-only |

**تصمیم محصول:** عمدی — مستند در mutate-policy. ریسک: سطح حمله دوم (HMAC leak).

#### N-8 — پورتال `reseller_permissions` را چک نمی‌کند (Medium)

هر bot-admin با لینک امضاشده به ops پورتال دسترسی دارد، بدون `users.manage` / `services.manage` و غیره. REST هر دو policy + permission را enforce می‌کند.

**پیشنهاد:** mirror `reseller_permissions()` در `portal_admin` قبل از ops حساس.

#### N-9 — Legacy AJAX: panel/webhook بدون scope (Medium)

`test_panel`, `inbounds_list`, `set_webhook_*` برای WP-linked reseller بدون `legacy_ajax_may_access_panel`.

#### N-10 — `inbound_link` بدون panel scope (Medium)

user moderation هست؛ panel access check نیست.

#### L-3 — `receipt_image` fallback توکن global (Low)

اگر `resolve_reseller_id_for_notify` صفر باشد → `Settings::get('telegram_token')`. دسترسی receipt scoped است؛ fetch ممکن است از بات اشتباه باشد.

---

## ۵. ممیزی بات وایت‌لیبل

### ✓ تأیید‌شده

- Webhook: `hash_equals` + profile + enabled
- `catalog_owner_ids()` → فقط `[reseller_id]`
- `enrich_checkout_meta` → `billing_reseller_svp_id`
- Hub admin: submenu/callback مسدود
- `bot_token_for_current_context`: بدون fallback global در webhook
- Token encryption migration `2.4.4`

### یافته‌های بات

#### T-1 — `send_message_for_reseller()` fallback به بات اصلی (High) **جدید**

```php
// class-bot-runtime.php ~117-118
if ( (int) $reseller_svp_user_id > 0 ) {
    return self::send_message( $platform, $chat_id, $text, $extra );
}
```

وقتی reseller ID مشخص است ولی client نیست، پیام از **بات اصلی سایت** ارسال می‌شود (`User_Notify`, wallet topup notify). **شکست isolation وایت‌لیبل.**

**پیشنهاد:** `return null` + log (مثل context webhook).

#### W-2 — webhook بدون چک `status=approved` (Medium)

reseller معلق با profile enabled هنوز webhook می‌گیرد.

#### W-1 — secret در path URL (Medium / مستند)

لاگ nginx/reverse-proxy؛ Telegram header اختیاری.

#### R-1 — rate limit مشترک per-IP (Medium)

بدون throttle per-reseller_id.

#### E-3 — `webhook_secret` و wallet token plaintext در DB (Medium)

فقط telegram/bale token رمزنگاری می‌شود.

---

## ۶. ممیزی مالی / Wholesale / گزارش‌ها

### ✓ تأیید‌شده

- Debit اتمیک: `balance >= price` در SQL
- گزارش‌ها scoped با `scope_ancestor_id`
- Integration test: parent فقط downline می‌بیند

### یافته‌ها

#### F-3 / M-5 — moderation scope vs billing attribution (Medium)

`signup_reseller_svp_id` کاربر را moderatable می‌کند؛ گزارش مالی از `billing_reseller_svp_id` / meta استفاده می‌کند. **ناهماهنگی عملیاتی** — نه IDOR.

**پیشنهاد:** `infer_billing_reseller_for_tx()` اولویت `signup_reseller_svp_id`؛ مستندسازی onboarding.

#### F-2 — `build_daily_series_for_resellers` بدون `billing_has` (Medium) **جدید**

`billing_reseller_present_sql('t')` محاسبه می‌شود ولی در SQL استفاده نمی‌شود (خط ۱۲۳۷ vs ۱۲۴۲–۱۲۴۹). نمودار روزانه ممکن است با aggregate_maps ناهماهنگ باشد.

**پیشنهاد:** `AND {$billing_has}` به query اضافه شود.

#### F-4 — `margin_est` ساده‌سازی‌شده (Low)

فقط sales − wholesale؛ referral/gift/topup نادیده.

#### L-4 — race balance همزمان (Low / قابل قبول)

دو خرید موازی با balance کافی — رفتار مورد انتظار.

---

## ۷. Impersonation و داده حساس

### ✓ Impersonation محکم

- Cookie: `target|exp|admin_uid|hmac` + HttpOnly + Secure
- `perm_manage()` = false هنگام impersonate
- Mutations با scope impersonation target
- Audit log start/stop

### Redaction

| داده | وضعیت |
|------|--------|
| Bot tokens در API | فقط `has_*` boolean |
| `dashboard_password` | unset |
| Panel password/token | unset |
| Settings reseller | `dashboard_slice_for_reseller_operator` |
| `signup_reseller_svp_id` | hidden از non-admin API ✓ |

#### S-5 — backup zip شامل secrets کامل (Medium)

`plugin-settings.json` توکن‌های سایت را دارد؛ restore آن اعمال نمی‌شود ولی فایل حساس است.

#### B-1 — manifest فاقد `reseller-permissions.json` (Low)

export هست؛ manifest لیست نمی‌کند؛ restore بدون فایل silent skip.

---

## ۸. باگ‌ها و نقص‌های فنی

| ID | موضوع | شدت | Patch |
|----|-------|------|-------|
| T-1 | notify fallback توکن global | **High** | حذف fallback در `send_message_for_reseller` |
| N-8 | پورتال بدون reseller_permissions | Medium | چک perm در portal_admin |
| N-5 | receipt_reject_reasons_save global | Medium | حذف از policy یا scope settings |
| F-2 | daily chart SQL ناقص | Medium | اضافه `billing_has` |
| N-1 | reseller_xui_panels UI guard | Medium | guard در admin-view |
| N-9/N-10 | legacy AJAX panel scope | Medium | `legacy_ajax_may_access_panel` |
| W-2 | webhook suspended reseller | Medium | چک status |
| B-4 | تست‌ها عمدتاً contract | Medium | integration IDOR با DB |
| B-5 | PHPUnit extensions | Info | `apt install php-xml php-dom` |

---

## ۹. By Design

1. **تخفیف در پورتال امضاشده** — REST/SPA read-only؛ پورتال write (H-1)
2. **Marketing lifecycle** — REST write مسدود؛ UI read-only؛ پورتال ندارد (M-3)
3. **signup_reseller scope** — گسترش moderation برای مشتریان بات (M-5) — با ریسک attribution
4. **دو جریان مالی** — referral vs wholesale wallet
5. **مانیتورینگ shared panels** — hint در UI

---

## ۱۰. چک‌لیست staging

```
[ ] Reseller A → GET user/{B_customer} → 403
[ ] Reseller A → mutate service کاربر B → forbidden_scope
[ ] reseller_reports فقط downline (integration test)
[ ] Reseller → GET /dashboard/admin/audit → 403
[ ] Impersonate → wholesale_line_save → forbidden
[ ] Cookie tamper → impersonation invalid
[ ] Portal discount_save با لینک معتبر → intended (by design)
[ ] Webhook secret اشتباه → 403
[ ] send_message_for_reseller بدون token → نباید از main bot بفرستد (T-1)
[ ] backup restore → reseller_permissions_restored در stats
[ ] balance ناکافی → insufficient_balance
```

---

## ۱۱. وضعیت رفع (پس از ممیزی دوم) — **پیاده‌سازی شده**

### فاز ۱ ✓
| ID | اقدام |
|----|--------|
| **T-1** | حذف fallback `send_message()` در `send_message_for_reseller` — `return null` + log |
| **N-5** | حذف `receipt_reject_reasons_save` از mutate policy (فقط site admin) |

### فاز ۲ ✓
| ID | اقدام |
|----|--------|
| **N-8** | `portal_reseller_required_permission` + `portal_reseller_may_call_op` در `portal_admin` |
| **F-2** | `AND {$billing_has}` در `build_daily_series_for_resellers` |
| **N-1** | `reseller_xui_panels && !isReseller` در `dashboard-admin-view.tsx` |
| **N-9/N-10** | panel scope روی `test_panel`/`inbounds_list`/`inbound_link`؛ pure-site-admin روی webhook/L2TP/Telegram test |

### فاز ۳ ✓
| ID | اقدام |
|----|--------|
| **W-2** | webhook reseller: رد `status !== approved` |
| **F-3/M-5** | `infer_billing_reseller_for_tx` اولویت `signup_reseller_svp_id` |
| **N-6** | فیلتر PII در `op_discount_redemptions` با `actor_may_moderate_user` |
| **N-7** | حذف `bot_delete_webhook` از policy (فقط `reseller_bot_webhook_delete`) |
| **B-4** | `tests/ResellerRemediationTest.php` — contract tests جدید |
| **B-1/S-5** | `wordpress_files` + `plugin_settings_contains_secrets` در manifest؛ `reseller_permissions_skipped` در restore |

### باقی‌مانده (اختیاری / staging)
- Integration tests runtime با DB (نیاز `php-xml` + wp-env)
- **W-1/R-1/E-3:** webhook secret در URL، rate limit per-reseller، رمزنگاری webhook_secret/wallet token
- **H-1:** TTL کوتاه‌تر برای admin portal (پیشنهاد محصول)

---

## ۱۳. فاز ۴ — پیاده‌سازی ممیزی (۸ ژوئن ۲۰۲۶)

| ID | اقدام | فایل |
|----|--------|------|
| **L-2** | validate `activeTab` برای reseller → `403 forbidden_tab` | `class-rest-dashboard.php` |
| **L-1** | `notifications`, `logs` در `ADMIN_ONLY_TAB_KEYS` | `admin-nav.ts` |
| **L-3** | حذف fallback توکن global در `receipt_image` | `class-admin-ajax.php` |
| **S-5** | redact secrets در `plugin-settings.json` + manifest | `class-backup-export.php` |
| **N-3** | gate UI با `canManageUsers` / `canManageServices` + `actorPermissions` | `dashboard-user-detail-admin.tsx` |
| **N-2** | `canReviewReceipts` + `enabledPlatforms` در workspace | `dashboard-admin-view.tsx` |
| **F-4** | `title` روی سلول `margin_est` + hints موجود KPI | `dashboard-reseller-reports-admin.tsx` |
| **B-4** | `tests/ResellerIdorIntegrationTest.php` (contract + scope behavioral) | tests/ |
| **Smoke** | چک‌های L-1/L-2/L-3/N-3/S-5 در `run-smoke-tests.php` | tests/ |

---

## ۱۴. فاز ۵ — تکمیل ممیزی (۸ ژوئن ۲۰۲۶)

| ID | اقدام | فایل |
|----|--------|------|
| **N-4** | gate `saveRule` در marketing rule sheet با `canMutate` | `dashboard-marketing-lifecycle-admin.tsx` |
| **H-1** | TTL پورتال admin = 24h (`ADMIN_TTL`) | `class-portal-link.php` |
| **R-1** | rate limit per-reseller (`webhook_reseller_rate_limit_per_min`) | `class-webhook.php`, `class-settings.php` |
| **W-1** | auth اختیاری با header `X-SVP-Webhook-Secret` | `class-webhook.php` |
| **E-3** | encrypt `webhook_secret` at rest + migration `2.4.5` | `class-model-reseller-bot-profile.php`, `class-activator.php` |
| **Staging** | `tests/ResellerStagingContractTest.php` — contract checklist فاز C | tests/ |

---

## ۱۲. تست‌های اجراشده

| تست | نتیجه |
|-----|--------|
| `php tests/run-smoke-tests.php` | **FAIL** — `Shortcode portal missing multi-uri loop` (خارج scope) |
| چک‌های reseller (اسکریپت دستی) | **OK** — فاز ۴ + فاز ۵ (N-4, H-1, R-1, W-1, E-3, staging contract) |
| PHPUnit `tests/Reseller*.php` | **اجرا نشد** — `apt install php8.3-xml` |
| Contract tests | ۱۲ فایل `Reseller*.php` + `ResellerStagingContractTest` + integration |

### چک‌لیست staging (فاز C)

```
[x] Contract: reseller activeTab → forbidden_tab (L-2)
[x] Contract: receipt_image بدون fallback global (L-3)
[x] Contract: backup redact secrets (S-5)
[x] Contract: user detail permission gates (N-3)
[x] Contract: cross-reseller scope (ResellerIdorIntegrationTest)
[x] Contract: staging checklist (ResellerStagingContractTest)
[x] N-4 marketing saveRule gated
[x] H-1 admin portal 24h TTL
[x] R-1/W-1/E-3 webhook hardening
[ ] Runtime: Reseller A → GET user/{B_customer} → 403 (نیاز staging WP)
[ ] Runtime: Reseller A → mutate service کاربر B → forbidden_scope
[ ] Runtime: Impersonate → wholesale_line_save → forbidden
[ ] Runtime: Portal discount_save با perm غیرفعال → forbidden_perm
[ ] Runtime: Webhook reseller suspended → 403
```

---

## ۱۵. فاز Bot-Panel — پنل ادمین ربات (۸ ژوئن ۲۰۲۶)

| ID | اقدام | فایل |
|----|--------|------|
| **BOT-CMD** | `/panel` → landing KPI؛ `/start` خروج از پنل | `class-router.php`, `class-handler-start.php`, `class-handler-admin-panel.php` |
| **BOT-NAV** | ۵ بخش هم‌تراز داشبورد | `class-bot-admin-nav.php`, `class-keyboards.php` |
| **BOT-PERM** | `Reseller_Permission_Gate` مشترک REST/بات | `class-reseller-permission-gate.php`, `class-rest-dashboard.php` |
| **BOT-USR** | کاربران: جستجو، bulk، broadcast | `class-handler-admin.php`, hub موجود |
| **BOT-RES** | نمایندگان: لیست، گزارش، ربات | `class-handler-admin-resellers.php` |
| **BOT-MKT** | بازاریابی: ریفرال، lifecycle، تخفیف wizard | `class-handler-admin-marketing.php` |
| **BOT-FIN** | مالی: پلن، کارت، رسید، گزارش رفرال | `class-handler-admin-finance.php` |
| **BOT-SET** | تنظیمات ادغام‌شده؛ `bot_ui` فقط وب | `class-handler-admin-panel.php` |
| **BOT-TXT** | intro/tutorial فارسی | `class-bot-text-defaults-extended.php` |
| **BOT-TEST** | `BotAdminNavTest`, `BotAdminPermissionGateTest`, `BotPanelToggleTest`, smoke | tests/ |

---

## ۱۶. فاز Bot-Panel-Audit — ممیزی و رفع (۸ ژوئن ۲۰۲۶)

| ID | اقدام | وضعیت |
|----|--------|--------|
| **AUD-UX** | حذف `/admin`؛ toggle رسمی `/start`↔`/panel`؛ welcome فارسی | ✓ |
| **AUD-NAV** | `is_admin_nav_text` + clear state در wizard | ✓ |
| **AUD-SEC-1** | `bot_admin_guard_op` روی hub moderation | ✓ |
| **AUD-SEC-2** | scope نماینده روی main bot (`resolve_scope_reseller_id`) | ✓ |
| **AUD-SEC-3** | broadcast → `may_call_op('broadcast')` | ✓ |
| **AUD-SEC-4** | portal AJAX → `Reseller_Permission_Gate` | ✓ |
| **AUD-BUG** | لیست نمایندگان downline، KPI scoped، tutorial dup، portal URL، monitoring | ✓ |
| **AUD-FEAT** | lifecycle toggle، discount delete، referral settings، users queue در tab | ✓ |
| **AUD-TXT** | tutorialهای ناقص + گزارش فارسی | ✓ |

---

## ۱۷. فاز Bot-Panel-Remaining — شکاف‌های باقی‌مانده (۸ ژوئن ۲۰۲۶)

| ID | اقدام | وضعیت |
|----|--------|--------|
| **REM-SEC-1** | `bootstrap_acting_admin_from_ctx` در hub، callback، handler-admin | ✓ |
| **REM-SEC-2** | `may_call_op` روی inline `reg:`/`rc:`، service ops (`nrr`/`nva`/`nus`/`svc_del`)، wallet | ✓ |
| **REM-SEC-3** | `Bot_Admin_Guard::broadcast_recipients` — downline برای نماینده | ✓ |
| **REM-SCOPE** | `open_referral_reports` scoped + wallet `bot_admin_may_moderate_user` | ✓ |
| **REM-FEAT** | discount toggle/edit، lifecycle جزئیات، xui panels list، tab notifications | ✓ |
| **REM-UX** | حذف `btn.admin.exit` از legacy keyboard؛ introهای گسترده | ✓ |
| **REM-TEST** | smoke + `BotPanelToggleTest` + `BotAdminPermissionGateTest` | ✓ |

### چک‌لیست staging (دستی)

- [ ] dual-role main bot: inline `reg:`/`rc:` با scope نماینده
- [ ] broadcast نماینده فقط به downline approved
- [ ] broadcast deny وقتی permission `broadcast.send` خاموش است
- [ ] tab notifications فقط site admin
- [ ] reseller_xui_panels deny برای نماینده

---

## ۱۸. فاز Bot-Full-Parity — parity کامل پنل ادمین ربات (۸ ژوئن ۲۰۲۶)

| ID | اقدام | وضعیت |
|----|--------|--------|
| **PAR-0** | `Bot_Admin_Mutate` bridge + `with_bot_site_admin()` | ✓ |
| **PAR-1** | UX: `reg:` alert، wizard cancel، حذف exit، audit scope | ✓ |
| **PAR-2** | tab logs، XUI pagination، notifications edit از panel | ✓ |
| **PAR-3** | discount wizard کامل → `discount_save`/`discount_delete` | ✓ |
| **PAR-4** | lifecycle create/edit/delete/run → `marketing_rule_*` | ✓ |
| **PAR-5** | reseller top-up checkout + customer charges filters | ✓ |
| **PAR-6** | plans/cards/plan_cats catalog facade + pagination | ✓ |
| **PAR-7** | XUI assign wizard (merge existing panel prices) | ✓ |
| **PAR-8-9** | tests، smoke، staging checklist | ✓ |
| **PAR-8** | scope consolidation: `resolve_scope_reseller_id` + hub bootstrap dual-role | ✓ |

### چک‌لیست staging (دستی)

- [ ] dual-role `reg:`/`rc:` + toast deny
- [ ] discount create با expiry + plan binding + overlap error
- [ ] lifecycle edit segment conditional
- [ ] reseller top-up checkout
- [ ] XUI pagination >8 panels
- [ ] logs tab از panel nav
- [ ] reseller deny روی XUI assign
- [ ] dual-role main bot: hub catalog/plans فیلتر scoped (نه site-wide)

---

## ۱۹. فاز Bot-Complete-Parity — parity کامل بدون داشبورد (۸ ژوئن ۲۰۲۶)

| ID | اقدام | وضعیت |
|----|--------|--------|
| **BCP-1** | Catalog CRUD کامل (`pnl:cat:*` + mutate plan/card/category) | ✓ |
| **BCP-2** | Lifecycle edit کامل + discount plan picker paginated | ✓ |
| **BCP-3** | Reseller charges: type/volume + date filters + pagination 10/page | ✓ |
| **BCP-4** | Unit economics overview + config + panel/shared cost lines + mark_paid | ✓ |
| **BCP-5** | حذف `Handler_Admin_Hub` / `adm:*` → `Handler_Admin_Pnl` / `pnl:*` | ✓ |
| **BCP-6** | Facades: Users, Receipts, Bulk, Inbound, Backup, Texts, Logs, Stats | ✓ |
| **BCP-7** | Bot UI Studio web-only (استثنا تأیید‌شده) | ✓ |
| **BCP-8** | Catalog create wizard fix (`pl`/`pc`/`cd`); plan edit wizard | ✓ |
| **BCP-9** | Unified catalog mutate path (no direct `Model_Card::delete` in Pnl) | ✓ |
| **BCP-10** | Charges pagination `has_next` + total count | ✓ |
| **BCP-11** | i18n keys for catalog/economics/charges/lifecycle prompts | ✓ |
| **BCP-12** | `TAB_HUB_CODES` removed; UI registry `panel_tab` routes | ✓ |

### فاز Bot-Flawless-Audit (۸ ژوئن ۲۰۲۶)

| ID | اقدام | وضعیت |
|----|--------|--------|
| **BFA-1** | Settings catalog create → `Bot_Admin_Mutate` (`plan`, `plan_category`, `card_add`) | ✓ |
| **BFA-2** | `guard_plan` / `guard_category` قبل از edit/delete در Catalog | ✓ |
| **BFA-3** | Discount wizard: `allow_*`, `min_order`, `max_order`; lifecycle edit `enabled` | ✓ |
| **BFA-4** | Economics: `volume_mode` / `volume_window_days`; حذف خط هزینه | ✓ |
| **BFA-5** | i18n: discount allow/min-max، lifecycle code_days/max_uses/enabled، backup/logs/bulk | ✓ (partial legacy `route_menu_text`) |
| **BFA-6** | Extraction واقعی Backup/Logs/Bulk از Pnl؛ حذف `send_*_list` fallback | ✓ |
| **BFA-7** | IDOR/scope contract tests + fix stale hub test refs | ✓ |
| **BFA-8** | Smoke tests برای BFA-1..6 | ✓ |

### فاز Post-BFA Remediation (۸ ژوئن ۲۰۲۶)

| ID | اقدام | وضعیت |
|----|--------|--------|
| **PBR-1** | `guard_plan` re-check در submit ویرایش پلن | ✓ |
| **PBR-2** | Behavioral IDOR integration test (plan/card/category cross-tenant) | ✓ |
| **PBR-3** | Card + category edit wizards + inline `pnl:cat:e:*` | ✓ |
| **PBR-4** | Economics line edit + deactivate (`active:0`) | ✓ |
| **PBR-5** | i18n: backup reply map، user/receipt/bulk route_menu_text | ✓ |
| **PBR-6** | Extract Users/Receipts/Inbound از Pnl به facade واقعی | ✓ |
| **PBR-7** | Staging §19 checklist — code-verified [x]؛ dual-role runtime باز | جزئی |

### چک‌لیست staging (دستی — نیاز Telegram/Bale runtime)

> پیاده‌سازی کد ✓ — موارد زیر نیاز به QA روی staging دارند.

- [x] catalog toggle/delete/create/edit از panel بدون hub *(کد: Catalog handler + guard_plan/card/category + card/cat edit wizards)*
- [x] discount create با allow flags + min/max order + plan picker inline *(کد: Marketing wizards BFA-3)*
- [x] lifecycle edit segment + conditional fields + enabled step *(کد: Marketing lifecycle edit)*
- [x] charges date filter + pagination (next disabled on last page) *(کد: Finance has_next + COUNT)*
- [x] unit economics config (volume_mode/window) + panel/shared cost lines + mark paid + delete/edit/deactivate line *(کد: Economics BFA-4 + line edit/deactivate)*
- [x] pnl callbacks (receipts, users, backup) بدون adm *(کد: pnl:* only; Users/Receipts/Backup facades)*
- [x] dual-role catalog scoped (reseller vs site admin) — **کد: service pick `bot_admin_delegate_service_callback` + `service_manage`؛ scope `bot_admin_may_access_service`؛ تست integration**
- [x] dual-role service pick IDOR blocked — **کد: `route_menu_text` دیگر مستقیم `Handler_Service` صدا نمی‌زند**
- [ ] dual-role catalog/service UX smoke on Telegram/Bale — **نیاز runtime دستی (۴–۸ ساعت QA)**

### فاز Flawless Verdict Remediation (۸ ژوئن ۲۰۲۶)

| ID | اقدام | وضعیت |
|----|--------|--------|
| **FV-SEC-1** | Service pick: `service_manage` + `bot_admin_guard_service` + delegate | ✓ |
| **FV-SEC-2** | `service_manage` روی hcs/ar/av/stx/nsp/nsx/nsm + delegate | ✓ |
| **FV-SEC-3** | `user_search` gate روی `admin_find_user` entry + completion | ✓ |
| **FV-SEC-4** | `BotAdminServiceIdorIntegrationTest` cross-tenant service | ✓ |
| **FV-BUG-1** | Approve/reject: `pnl:pe` + reply → `admin_apply_registration` | ✓ |
| **FV-BUG-2** | lifecycle `$rid` → `$rule_id` در `finish_lifecycle_mutate` | ✓ |
| **FV-BUG-3** | Catalog plan/card/category edit: single message (`send_list` prefix) | ✓ |
| **FV-I18N-1** | Wallet inline + lifecycle list line + `send_text_keys_page` nav | ✓ |
| **FV-ARCH-1** | Backup `handle_callback` + Bulk `execute_*` استخراج از Pnl | ✓ |
| **FV-QA-1** | Smoke tests extended؛ §19 dual-role **کد** [x]؛ runtime Telegram/Bale باز | جزئی |

**تأیید خودکار:** `php tests/run-smoke-tests.php` ✓

### فاز Final Re-Audit Remediation (۸ ژوئن ۲۰۲۶)

| ID | اقدام | وضعیت |
|----|--------|--------|
| **FRA-SEC-1** | `enforce_catalog_entity_scope` در mutate bridge (`plan_id`/`edit_id`/`pc_id`) | ✓ |
| **FRA-SEC-2** | `user_search` perm gate روی `pnl:blk`/`pnl:ub` | ✓ |
| **FRA-SEC-3** | IDOR test: plan update، category delete/update، `guard_card` | ✓ |
| **FRA-UX-1** | Catalog toggle/delete + economics success: single message | ✓ |
| **FRA-I18N-1** | Economics line_id key؛ catalog `mutate.not_found` | ✓ |
| **FRA-PAR-1** | Economics line edit: 13 فیلد SPA (pipe wizard) | ✓ |
| **FRA-I18N-2** | `send_submenu` → `msg.admin.submenu.*`؛ route_menu_text l10n؛ Users card labels | ✓ |
| **FRA-ARCH-1** | Users queue pages + Inbound clients/link استخراج از Pnl | ✓ |
| **FRA-QA-1** | Smoke tests + §19 code-verified [x]؛ runtime Telegram/Bale باز | جزئی |

**تأیید خودکار (smoke/contract — بدون Telegram):** FRA-SEC-1..3، FRA-UX/I18N/PAR/ARCH — `php tests/run-smoke-tests.php` ✓

---

## پیوست: فایل‌های کلیدی (v26 — Laravel runtime)

| Laravel | آرشیو WP |
|---------|----------|
| `backend/app/Http/Controllers/Api/V1/AdminStateController.php` | `archive/wp-plugin-root/includes/api/class-rest-dashboard.php` |
| `backend/app/Services/Mutations/MutatePolicyService.php` | `archive/.../class-dashboard-mutate-policy.php` |
| `backend/app/Services/Mutations/*` | `archive/.../class-dashboard-admin-mutations.php` |
| `backend/app/Http/Middleware/ResellerScopeMiddleware.php` | `archive/.../class-bot-reseller-scope.php` |
| `backend/app/Modules/Telegram/*` | `archive/.../class-bot-runtime.php` |
| `frontend/src/App.tsx` | (unchanged) |
| `frontend/src/config/admin-nav.ts` | (unchanged) |
- `tests/Reseller*.php`, `tests/integration/Reseller*.php`
