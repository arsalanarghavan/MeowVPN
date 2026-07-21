# گزارش جامع وضعیت پنل نماینده و ربات مستقل

**آخرین به‌روزرسانی:** **Residual Parity Wave D** — docs honesty، quarantine Vite-era e2e، Next depth (`residual-closeout-wave-d`)، configs/mirror HTTP tests.

## Playwright (current — Next App Router)

| Spec | Coverage |
|------|----------|
| `frontend/e2e/shell.spec.ts` | Login RTL/LTR shell, sidebar layout |
| `frontend/e2e/shell-depth.spec.ts` | CSRF, `/me/state`, impersonate banner, magic/portal, users deep-link, ui-preferences |
| `frontend/e2e/admin-tabs.spec.ts` | Tab smoke + auth guard |
| `frontend/e2e/admin-mutate.spec.ts` | API login when backend up; merge/orphans/rial/crypto/configs mutates |
| `frontend/e2e/admin-depth.spec.ts` | OverviewAdminClient shell; backup; `marketing_lifecycle` (+ confirm mutate); bot_ui; plans inbound; bots mirror |
| `frontend/e2e/residual-closeout-p2.spec.ts` | Portal invalid-`sig` smoke; overview/bots pager; texts/force-join/marketing_lifecycle URL smoke; user persona portal |
| `frontend/e2e/residual-closeout-wave-d.spec.ts` | `bot_set_update_mode`; `texts_save` fa/en; `force_join_publish`; `marketing_rule_save` + `rule_id`; payments tx pager; backup error surface |

Notes: overview/marketing/payments checks are **smoke** (heading / visible controls / mutate fired), not full KPI/automation depth. Portal e2e does **not** verify HMAC — crypto/TTL stays on PHPUnit (`PortalSignedLinkTtlTest`, `PortalSubscriptionAcceptanceTest`).

Primary UI is Next App Router (`npm run build`). Dead Vite SPA archived at `frontend/src/_vite_legacy_archive/` and `frontend-vite-legacy/`.

**Quarantined (not CI, not evidence):** `frontend/e2e/quarantine/` — `dashboard-v23`, `dashboard-v24-qa`, `dashboard-v25-depth`, `dashboard-auth-v23`, `dashboard-session-v27` (Vite paths / `dash-tab-*` testids). Older: `frontend/e2e/archive/`.

Set `PLAYWRIGHT_SKIP_BACKEND=1` to fall back to session-cookie mocks.

## Laravel backend (spec v28 — خلاصه)

- §14+§16: [`SECTION14-GAP-MATRIX-V28-FA.md`](SECTION14-GAP-MATRIX-V28-FA.md) — generated from `*-v28.log`
- OPS: [`OPS-EVIDENCE-INDEX-V28.md`](evidence/OPS-EVIDENCE-INDEX-V28.md) + [`run-v28-evidence.sh`](../backend/scripts/ops/run-v28-evidence.sh)
- Sync: `scripts/sync-spec-from-matrix.py` + `scripts/sync-spec-checkboxes.sh` (v28)
- Playwright CI: Next only — `shell*`, `admin-*`, `residual-closeout-*` (Vite v23–v27 specs quarantined)
- Tag: `arch-decommission-v28` (pending push)

## Laravel dashboard (spec v27 — خلاصه)

- §14+§16: [`SECTION14-GAP-MATRIX-V27-FA.md`](SECTION14-GAP-MATRIX-V27-FA.md) — **145 DONE / 13 OPS**
- OPS: [`OPS-EVIDENCE-INDEX-V27.md`](evidence/OPS-EVIDENCE-INDEX-V27.md) + [`run-v27-evidence.sh`](../backend/scripts/ops/run-v27-evidence.sh)
- Code fixes: `normalizeAdminApiPath` §7.1، nginx `dashboard/admin` only، `NavTabsBuilder` broadcast gate
- Playwright: superseded by Next e2e (legacy v27 session spec in `frontend/e2e/quarantine/`)
- Tag: `arch-decommission-v27` (pending push)

## Laravel dashboard (spec v26 — خلاصه)

- §14+§16: [`SECTION14-GAP-MATRIX-V26-FA.md`](SECTION14-GAP-MATRIX-V26-FA.md) — **158/158 DONE**
- OPS: [`OPS-EVIDENCE-INDEX-V26.md`](evidence/OPS-EVIDENCE-INDEX-V26.md) + [`run-v26-evidence.sh`](backend/scripts/ops/run-v26-evidence.sh)
- Playwright: legacy Vite specs quarantined (`frontend/e2e/quarantine/`)
- Route alias: `/api/v1/dashboard/admin/*` در `routes/api.php`
- Tag: `arch-decommission-v26`

## Laravel dashboard (spec v25 — خلاصه)

- §14+§16: [`SECTION14-GAP-MATRIX-V25-FA.md`](SECTION14-GAP-MATRIX-V25-FA.md) — **146 DONE / 12 OPS**
- Plan execution: [`SPEC-DEVIATIONS-V24-SUMMARY-FA.md`](SPEC-DEVIATIONS-V24-SUMMARY-FA.md) + v25 SQL/CI/matrix
- Legacy WP root: [`archive/wp-plugin-root/`](archive/wp-plugin-root/)
- OPS: [`OPS-MAINTENANCE-CALENDAR-V24-FA.md`](OPS-MAINTENANCE-CALENDAR-V24-FA.md) — next quarterly **2026-09-16**
- Playwright: legacy Vite specs quarantined (`frontend/e2e/quarantine/`)
- ARCH-12: commit `4bb296f` + tag `arch-decommission-v25`

## Laravel dashboard (spec v24 — خلاصه)

- §14+§16: [`SECTION14-GAP-MATRIX-V24-FA.md`](SECTION14-GAP-MATRIX-V24-FA.md) — **158 DONE carried forward**
- Plan execution: [`SPEC-DEVIATIONS-V24-SUMMARY-FA.md`](SPEC-DEVIATIONS-V24-SUMMARY-FA.md)
- Legacy WP root: [`archive/wp-plugin-root/`](archive/wp-plugin-root/)
- OPS: [`OPS-MAINTENANCE-CALENDAR-V24-FA.md`](OPS-MAINTENANCE-CALENDAR-V24-FA.md)
- Playwright: legacy Vite specs quarantined (`frontend/e2e/quarantine/`)
- ARCH-12: decommission commit in v24 plan

## Laravel dashboard (spec v23 — خلاصه)

- §14+§16: [`SECTION14-GAP-MATRIX-V23-FA.md`](SECTION14-GAP-MATRIX-V23-FA.md) — **158 DONE / 0 PARTIAL / 0 OPEN**
- Spec: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md) — **158 `[x]`** (32 checkbox implicit جدید + 126 قبلی)
- Matrix: Line drift ~+24 vs current spec; `sync-spec-from-matrix.py` match by criterion text
- Playwright: **quarantined** — `frontend/e2e/quarantine/dashboard-v23*.ts` (Vite-era; not Next evidence)
- Tests: `GroupAcceptanceV23Test`, `MutateDepthBatchV23Part1/2/3/4`, `MarketingCronOffersTest`, `CryptoIpnConfirmedTest`, `L2tpModuleGateTest`, `WpImportRowCountTest`, `BackupRestoreStagingTest`, `RelaySetupOrderTest`
- OPS: [`OPS-EVIDENCE-INDEX-V23.md`](evidence/OPS-EVIDENCE-INDEX-V23.md) — 2026-06-16 re-verify
- Docs: [`SPEC-DEVIATIONS-FA.md`](SPEC-DEVIATIONS-FA.md) v23 + inline amendments §2/§6/§7/§8/§13
- ARCH-12: git commit فقط با درخواست صریح operator

## Laravel dashboard (spec v22 — خلاصه)

- §14: [`SECTION14-GAP-MATRIX-V22-FA.md`](SECTION14-GAP-MATRIX-V22-FA.md) — 126 سطر؛ 98 DONE، 18 PARTIAL، 10 OPEN (شناسه‌دار)
- Spec: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md) — 98 checkbox `[x]` (هم‌خوان با matrix؛ `sync-spec-checkboxes.sh` exit 1 اگر بیش‌ادعا)
- Backend: `$resellerMap` 61 entry؛ `bot_ui` reseller read-only در boot؛ `configs_client_*` admin-only؛ `link_wp_user` deprecated
- Tests: `GroupAcceptanceV22Test`, `MutateDepthBatchV22Part1/2/3/4` (96 ops) + v21 44 = 140 depth؛ `MetricsIncrementTest` بدون skip؛ `BroadcastWorkerTimeoutTest`
- Playwright: `frontend/e2e/dashboard-v22.spec.ts`, `dashboard-auth-v22.spec.ts` — CI testMatch v22 only (v14–v21 deprecated local default)
- OPS: [`OPS-EVIDENCE-INDEX-V22.md`](evidence/OPS-EVIDENCE-INDEX-V22.md) — `api.simplevpbot.ir`؛ soak ~579 خط
- Docs: [`SPEC-DEVIATIONS-FA.md`](SPEC-DEVIATIONS-FA.md) v22 + 6 amendment؛ [`MIGRATION-AUDIT-V22-FA.md`](MIGRATION-AUDIT-V22-FA.md)
- ARCH-12: git commit فقط با درخواست صریح operator

## Laravel dashboard (spec v21 — خلاصه)

- §14: [`SECTION14-GAP-MATRIX-V21-FA.md`](SECTION14-GAP-MATRIX-V21-FA.md) — 98 DONE، 18 PARTIAL، 10 OPEN
- Backend: bots/xui admin-only §10.2؛ broadcast marketing gate؛ marketing.lifecycle write admin-only؛ overview `data-testid`
- Tests: `GroupAcceptanceV21Test`, `MutateDepthBatchV21Part1/2/3` (44 ops), `MetricsIncrementTest` catalog
- Playwright: `frontend/e2e/dashboard-v21.spec.ts`, `dashboard-auth-v21.spec.ts` — superseded by v22
- OPS: `docs/evidence/OPS-EVIDENCE-INDEX-V21.md` + `*-prod-v21.log` (synthetic hosts — superseded by v22)
- Docs: `SPEC-DEVIATIONS-FA.md` v21، `sync-spec-checkboxes.sh` v21
- Spec: v21 ادعای 126 `[x]` — v22 اصلاح به 98

## Laravel dashboard (spec v20 — خلاصه)

- §14: [`SECTION14-GAP-MATRIX-V20-FA.md`](SECTION14-GAP-MATRIX-V20-FA.md) — 126/126 DONE
- Backend: `activeTab`/`tab` alias، `OverviewLoader` stats series، configs_client admin-only، `reseller_xui_panels` §E.4
- Tests: `GroupAcceptanceV20Test`, `MutateDepthBatchV20Part1/2`, `DatabaseIndexesParityTest` + billing_reseller KEY
- Playwright: `frontend/e2e/dashboard-v20.spec.ts`, `dashboard-auth-v20.spec.ts` — CI testMatch v20 only
- OPS: `docs/evidence/OPS-EVIDENCE-INDEX-V20.md` + `*-prod-v20.log`
- Docs: `SPEC-DEVIATIONS-FA.md` v20، `MIGRATION-AUDIT-V20-FA.md`، `PHPUNIT-LOCAL-FA.md` v20
- Spec: `LARAVEL-BACKEND-SPEC-FA.md` — 126 checkbox `[x]`

## Laravel dashboard (spec v19 — خلاصه)

- §14: [`SECTION14-GAP-MATRIX-V19-FA.md`](SECTION14-GAP-MATRIX-V19-FA.md) — 122 DONE + 4 OPS rotation
- Auth: `POST /api/v1/auth/token` + `BearerTokenTest`؛ CSRF path fix در `api-base.ts`
- Tests: `GroupAcceptanceV19Test`, expanded `DatabaseIndexesParityTest`, `SettingsTabKeysBatchTest`, `NavTabParityTest`
- Playwright: `frontend/e2e/dashboard-v19.spec.ts` — real subtabs, reseller actor, legacy redirects, groups A–H
- CI: `ci-check-frontend-fetch.sh` v19، `ci-check-admin-nav-parity.sh`
- OPS: `docs/evidence/OPS-EVIDENCE-INDEX-V19.md` + `*-prod-v19.log`
- Docs: `SPEC-DEVIATIONS-FA.md` v19، `MIGRATION-AUDIT-V19-FA.md`، `BEARER-TOKEN-FA.md` DONE

## Laravel dashboard (spec v18 — خلاصه)

- §14: [`SECTION14-GAP-MATRIX-V18-FA.md`](SECTION14-GAP-MATRIX-V18-FA.md) — 118 DONE + 8 OPS rotation
- Tests: `MutateAdminPositiveMatrixTest`, `MutateSmokeTest` ok:true, `MutateDepthBatchV18Part1/2`, `GroupAcceptanceV18Test`, `AuditControllerFilterTest`, `DatabaseIndexesParityTest`, `CryptoModuleAcceptanceTest`, `L2tpProvisionerSshMockTest`
- Playwright: `frontend/e2e/dashboard-v18.spec.ts` — site_settings 10 subtabs, reseller_charge/settings, merge, bot_ui RO, configs, receipts, audit strict, backup upload, cards
- Docs: `SPEC-DEVIATIONS-FA.md` v18، `BEARER-TOKEN-FA.md` OPEN، `NGINX-DASHBOARD-API-ALIAS-FA.md`، `QUEUE-HORIZON-DEVIATION-FA.md` v18 broadcast decision
- OPS: `docs/evidence/*-v18.md` + `*-prod.log` (2026-06-12 production sign-off)
- CI: `ci-check-frontend-fetch.sh` zero warnings؛ crypto acceptance tests

## Laravel dashboard (spec v17 — خلاصه)

- §14 B.3.2: `telegram_http_proxy` در `AbstractPlatformClient` + `BotRuntime`
- Tests: `MutateResellerPositiveMatrixTest`, `CronJobHandleBatchTest` (14 jobs), `MutateDepthBatchV17Part1/2`, `WpImportAccentMetaTest`, `AuditLogServiceRedactTest`
- Secrets: `.env.example` IPN/relay SSL؛ env→DB bot token hydration؛ nested audit redact
- Playwright: `frontend/e2e/dashboard-v17.spec.ts` — all tabs, Group F/H, 60s poll mock, cards
- Docs: `SPEC-DEVIATIONS-FA.md` v17، `SECTION14-GAP-MATRIX-V17-FA.md`، `NAV-TABS-NOTIFICATIONS-FA.md`
- OPS: `docs/evidence/*-v17.md` + `import-verify/run/soak/relay-forward` logs (staging)

## Laravel dashboard (spec v16 — خلاصه)

- Service naming: `ServiceNaming::formatServiceDisplayLabel` — bot + user detail API
- Monitoring: auto-refresh 60s (`dashboard-monitoring.tsx`)
- Tests: `MutatePolicyPositiveMatrixTest` (72 ops), `CronJobMetricsTest` (14 jobs), `PanelDownSustainedTest`, backup valid zip restore
- Metrics: `mutate_op_total:{op}` per successful mutate
- Frontend: `normalizeAdminApiPath` in `App.tsx` + `dash-admin-upload.ts`
- Playwright: `frontend/e2e/dashboard-v16.spec.ts` (tabs, reseller scope, whitelabel, cards, reports chart + impersonate)
- Docs: `SPEC-DEVIATIONS-FA.md` v16، `SECTION14-GAP-MATRIX-V16-FA.md`
- OPS: `docs/evidence/*-v16.md` + `import-verify-*.log` / `relay-forward-*.log` templates

## Laravel dashboard (spec v15 — خلاصه)

- RBAC: `resellers` tab در `resellerAllowedTabsMap`؛ `TabPermissionParityTest`؛ HTTP broadcast-queue + purge-expired gates
- §14 A.2.2: `MonitorHostSnapshotService` — `externalHostSnapshots` در monitoring refresh
- Policy: 72 `$resellerMap` (marketing lifecycle ops)؛ `MutatePolicyPositiveMatrixTest`
- Gates: `user_create_service` + bot/l2tp batch در `MutateModuleGateBatchTest`
- §12: `CronJobHandleBatchTest`؛ `CronJobMetricsTest` extended (autorenew, admin_alerts)
- §18: PanelDown sustained 300s؛ webhook `message` field؛ `HealthDeepTokenTest`؛ `RedactSecretsMiddlewareTest`
- Auth: `AuthSanctumFlowTest`، `LoginRateLimitTest`، `ImpersonationHttpsTest`
- §14: `GroupAcceptanceV15Test` — overview isolation, monitoring scope, receipt deliver, panel access toggle
- Playwright: `frontend/e2e/dashboard-v15.spec.ts`
- Docs: `SPEC-DEVIATIONS-FA.md` v15، `WEBHOOK-RESELLER-SECRET-FA.md`
- OPS: `docs/evidence/*-v15.md` checklists

## Laravel dashboard (spec v14 — خلاصه)

- Policy: 68 `$resellerMap` entries؛ `MutateAdminOnlyMatrixTest` + `MutateResellerModuleGateBatchTest`
- Gates: `MutateModuleGateBatchTest` — `l2tp_update`, `bot_test_bale`
- §12–§13: `ScheduleListTest` (14 jobs), `InboundQueueDrainJobTest`, `BackupJobCronTest`, Bale/TG webhook tests
- §7 REST: `AdminRestRoutesBatchTest`, `AuthLogoutTest`, `UiPreferencesTest`, `ResellerAdminOnlyRoutesTest`
- §14: `GroupAcceptanceV14Test`, `BuyFlowApproveDeliverTest` (deliver assertion), `BackupRestoreZipTest`
- §18: `MetricsWebhookTest`, `CronJobMetricsTest`, `LogRedactionTest`, rate limit 60/min default
- Playwright: `frontend/e2e/dashboard-v14.spec.ts`
- Docs: `SPEC-DEVIATIONS-FA.md` v14, `ARCH-1-API-ROUTES-FA.md`, `PORTAL-SIGNED-LINKS-FA.md`
- CI: `backend/scripts/ci/ci-check-frontend-fetch.sh`
- OPS: `docs/evidence/*-v14.md` checklists (live execution by operator)

## Laravel dashboard (spec v13 — خلاصه)

- Policy: `MutatePolicyParityTest` 67 entries؛ `MutatePolicyMatrixTest` forbidden_perm data-driven
- Gates: `MutateModuleGateBatchTest` — relay(22)، xui، marketing
- Depth: `MutateL2tpParityTest`، `MutateUserMergeDepthTest`، `MutateAuditTest` sensitive ops
- §14: `GroupAcceptanceV13Test`، `BackupRestHttpTest`، `BuyFlowApproveDeliverTest`
- §12–§18: `CronJobDispatchTest`، `WebhookResellerRateLimitTest`، `AdminAlertsExtendedTest`، `MetricsIncrementTest`
- Migration: `WpImportForceTest`، `WpImportBackupsFromTest`، `RegisterWebhooksCommandTest`
- Playwright: `frontend/e2e/dashboard-auth.spec.ts` (CI migrate+seed)
- ARCH-11: legacy root `scripts/` removed → `backend/scripts/` + `php artisan test`
- Evidence: `docs/evidence/*-v13.md`؛ OPS live items remain operator-run

## Laravel dashboard (spec v11 — خلاصه)

- `EnsureAdminStateModule`: xui_panels، configs، backup، marketing_lifecycle، finance/crypto subtab
- `MutationPipeline`: xui_panel + marketing ops gated؛ `settings_tab` bots/relay/finance gated
- Mutate depth v11: bot/site، reseller admin/bot، service/user، configs/economics، bulk/broadcast، finance، L2TP CRUD
- `MutateNegativeTest` + `AdminStateModuleGateTest` گسترش یافته
- `GroupAcceptanceV11Test` — §14 gaps (quick links، monitoring refresh، wpPages، bulk API)
- Playwright: `frontend/e2e/dashboard.spec.ts`
- WP: `includes/`, `simplevpbot.php`, root WP tests حذف؛ branch `archive/wp-plugin`
- Evidence: `docs/evidence/import-checklist-v11.md`، CUTOVER-SIGNOFF v11

## Laravel dashboard (spec v10 — خلاصه)

- HTTP module gates روی `admin/state` (l2tp، bots، relay/proxy subtabs)
- Mutate depth: relay admin nginx/ssl، reseller bot، service panel، configs `panel_access`
- Deploy artifact: `frontend/dist` → `assets/dashboard/dist`
- Broadcast load smoke (100 targets) + API E2E script (`backend/scripts/e2e/e2e-dashboard-api.sh`)
- Evidence: `docs/evidence/CUTOVER-SIGNOFF-FA.md` بخش v10

## Laravel dashboard (spec v7 — خلاصه)

| بخش | وضعیت |
|-----|--------|
| 141/141 mutate handlers | ✅ |
| Module gates (xui, marketing, relay, backup) | ✅ HTTP + schedule |
| Reseller RBAC HTTP (`services.manage`, `users.bulk`) | ✅ middleware |
| CSRF Sanctum + frontend nonce cleanup | ✅ |
| User portal `/me/portal` | ✅ |
| CI: test + preflight + soak + load + frontend build | ✅ |
| Cutover evidence | `docs/evidence/` + CI artifacts |
| WP `includes/` decommission | ✅ v11 staged (`CONFIRM=1` اجرا شد) |

راهنمای عملیاتی: [RESELLER_SETUP.md](RESELLER_SETUP.md)

---

## نتیجه نهایی (Executive Verdict)

- **ربات مستقل نماینده: قابل اتکا برای فروش روزمره**  
  توکن/وب‌هوک جدا، bind خودکار مشتری، فیلتر پلن/پنل/کارت، meta مالی، رسید و اعلان‌ها با توکن نماینده (`User_Notify` + `send_message_for_reseller`).
- **داشبورد نماینده: کاربردی**  
  CRUD، scope `invited_by`، mutate policy، گیت تب با `tab_perm` هم‌تراز REST/UI.
- **استقرار:** پس از reload پلاگین، migration `2.2.4` و backfill یک‌باره (`simplevpbot_reseller_backfill_v1_done`) اجرا می‌شود.
- **عمداً خارج از scope:** قیمت عمده در checkout ربات (`plan.price` فقط)، برندینگ پیشرفته (logo/theme/domain)، audit log جدا، closure table برای scope.

---

## فاز ۱ — پایه

| موضوع | وضعیت |
|--------|--------|
| `/start` روی ربات نماینده → `invited_by` | ✅ `resolve_invited_by_for_signup` |
| `signup_reseller_svp_id` هنگام ثبت‌نام | ✅ handler-start + ستون DB |
| فیلتر پلن/پنل/دسته در ربات | ✅ `catalog_owner_ids`, `panel_allowed_in_context` |
| meta مالی checkout | ✅ `billing_reseller_svp_id`, `invoice_card_owner_scope_svp_id` |
| ادمین رسید از پروفایل ربات | ✅ `admin_ids_for_context` |
| تأیید رسید اتمیک | ✅ claim / finalize / increment_balance |

---

## فاز ۲–۳ — اعلان، لینک، امنیت متن

| موضوع | وضعیت |
|--------|--------|
| اعلان کاربر با توکن نماینده (رسید، cron، transfer) | ✅ `SimpleVPBot_User_Notify` |
| اعلان تک‌پلتفرم (فقط TG یا فقط Bale) | ✅ `platforms_for_user` |
| لینک `ref_*` per-bot | ✅ usernames در پروفایل ربات |
| رمزنگاری توکن در DB | ✅ `encrypt_token_field` / `token_for_platform` |
| متن per-reseller | ✅ `text_overrides_json` + `Texts::get_in_bot_context` |
| گیت تب receipts | ✅ `receipts.review` در App.tsx و REST |

---

## فاز ۴–۶ — backfill، داشبورد، hardening

| موضوع | وضعیت |
|--------|--------|
| backfill meta مالی تراکنش‌های قدیمی | ✅ `Reseller_Backfill` + migration خودکار |
| backfill `invited_by` از تراکنش | ✅ batch + bind دستی UI |
| فیلتر مالی داشبورد نماینده | ✅ `tx_belongs_to_reseller` |
| broadcast با توکن نماینده | ✅ `client_for_broadcast_bot` |
| cache `reseller_scope_user_ids` | ✅ transient + invalidate |
| impersonation فقط HTTPS | ✅ `route_impersonate_start` |
| پیام دستی از داشبورد | ✅ `user_admin_message` → `User_Notify` |
| fallback notify از `signup_reseller_svp_id` | ✅ وقتی `invited_by` خالی است |
| اجرای دستی backfill (ادمین) | ✅ mutate + دکمه در تب نمایندگان |

---

## شکاف‌های باقی‌مانده (اولویت پایین)

1. **قیمت rule-based / عمده در ربات** — فقط در داشبورد عمده؛ checkout = `plan.price`.
2. **مقیاس درخت بزرگ** — `IN (...)` برای scope؛ closure table پیشنهاد فاز بعد.
3. **برندینگ پیشرفته / audit log** — فاز بعد.

### بسته‌شده (فاز کم‌اولویت)

- Admin Hub روی ربات نماینده: مسدودسازی `Settings` سراسری + زیرمنوهای gen/bot/crypto/backup.
- dropdown پنل: `can_sell_plan` + غیرفعال per-panel وقتی فقط کف قیمت است.
- شارژ کیف نماینده از داشبورد: notify با `send_message_for_reseller`.

---

## چک‌لیست deploy

1. Reload/deactivate-activate پلاگین → `DB_VERSION` = `2.2.4`
2. بررسی option `simplevpbot_reseller_backfill_v1_done` = true
3. وب‌هوک HTTPS برای هر ربات نماینده
4. تست: `/start` → خرید → رسید → اعلان از همان ربات
5. تب مالی نماینده پس از backfill

---

## پوشش بررسی

`frontend/*`, `backend/app/*` (Laravel). مرجع تاریخی WP: `archive/wp-plugin` (`includes/*`).
