# انحراف‌های آگاهانه از spec (v28)

> خلاصه v28: [`SECTION14-GAP-MATRIX-V28-FA.md`](SECTION14-GAP-MATRIX-V28-FA.md)  
> خلاصه v27: 145/158 — [`SECTION14-GAP-MATRIX-V27-FA.md`](SECTION14-GAP-MATRIX-V27-FA.md)

## v29 — cleanup + OPS automation (2026-06-14)

| موضوع | اقدام |
|-------|--------|
| Deprecated stubs | حذف `Telegram/Bale WebhookController` + `Process*UpdateJob` |
| Orphan `users` | migration `drop_orphan_laravel_users_table` + حذف `User` model |
| Mutate ops | حذف `link_wp_user`, `reseller_bot_secret_rotate` — 139 active |
| Portal pages | `PortalPagesBuilder` — Laravel-native `/info` defaults + `portalPages` API |
| inbound_autolink | `InboundAutolinkService` — fuzzy remark parity |
| Legacy columns | [`WP-LEGACY-COLUMNS-FA.md`](WP-LEGACY-COLUMNS-FA.md) |
| OPS scripts | `run-v28-evidence-complete.sh`, `phase16-parallel.sh`, `monthly-ops-bundle.sh` |
| wp-cli | [`bin/wp`](../bin/wp) phar in repo |

---

## v28 — spec sync + strict OPS (2026-06-13)

| موضوع | اقدام |
|-------|--------|
| §14+§16 | Matrix v28 + `run-v28-evidence.sh` — flip OPS→DONE when `*-v28.log` pass `log_ok()` |
| Criterion drift | L961 WebSocket suffix; L1111/L1630 `SVP_MODULE_*` backticks — 158/158 text match |
| §7.1 spec | inline v27 `normalizeAdminApiPath` + logout/token/me/portal + admin impersonate alias |
| §7.2 spec | invokable controllers; audit/logs admin-only; broadcast-queue marketing gate |
| §11 | `svp_broadcasts` / `svp_broadcast_queue` module = marketing |
| Orphan stubs | `@deprecated` `Telegram\Http\WebhookController`, `Bale\Http\WebhookController`, `ProcessBaleUpdateJob` |
| Runtime webhook | still `Core\WebhookController` → `svp_inbound_queue` → `UpdateRouter` (not module stubs) |

---

# انحراف‌های آگاهانه از spec (v27)

> خلاصه v27: 145/158 matrix — [`SECTION14-GAP-MATRIX-V27-FA.md`](SECTION14-GAP-MATRIX-V27-FA.md)  
> خلاصه v26: [`SECTION14-GAP-MATRIX-V26-FA.md`](SECTION14-GAP-MATRIX-V26-FA.md)

## v27 — honest closeout (2026-06-13)

| موضوع | اقدام |
|-------|--------|
| §14+§16 | **145 DONE / 13 OPS** — v26 OPS logs were overstated; v27 strict `run-v27-evidence.sh` |
| §7.1 session paths | `normalizeAdminApiPath` keeps `/dashboard/persona` etc. (was stripping to `/persona`) |
| nginx alias | rewrite limited to `/api/v1/dashboard/admin/*` only (not all `/dashboard/*`) |
| `broadcast` nav tab | gated on `marketing` module in `NavTabsBuilder` |
| Webhook §13.1 | runtime `Core\WebhookController` enqueues `svp_inbound_queue` (not `ProcessTelegramUpdateJob`) |
| `ProcessTelegramUpdateJob` | legacy stub — documented in job comment |
| WP archive `includes/` | on branch `archive/wp-plugin` only; use `svp_wp_parity.sql` on `main` |

---

# انحراف‌های آگاهانه از spec (v26)

> خلاصه v26: 158/158 matrix — [`SECTION14-GAP-MATRIX-V26-FA.md`](SECTION14-GAP-MATRIX-V26-FA.md)  
> خلاصه v24: [`SPEC-DEVIATIONS-V24-SUMMARY-FA.md`](SPEC-DEVIATIONS-V24-SUMMARY-FA.md)

## v26 — spec completion (2026-06-13)

| موضوع | اقدام |
|-------|--------|
| §14+§16 | **158/158 DONE** — matrix v26 + OPS re-verify logs |
| Dashboard route alias | `routes/api.php` registers `/dashboard/admin/*` |
| Playwright | v25-depth added to CI `testMatch` |
| `svp:broadcast` | spec §12 module = `marketing` (matches runtime) |
| Legacy `MODULE_*_ENABLED` | `svp_module_env()` in `config/modules.php` |

---

# انحراف‌های آگاهانه از spec (v24)

## v24 — plan execution (2026-06-13)

| موضوع | اقدام |
|-------|--------|
| Legacy WP root | archived → [`archive/wp-plugin-root/`](../archive/wp-plugin-root/) |
| nginx dashboard alias | rewrite in [`default.conf`](../backend/docker/nginx/default.conf) |
| `reseller_bot_secret_rotate` | deprecated alias → `bot_reseller_secret_rotate` |
| Monitoring | visibility-aware 60s polling |
| QA RTL/responsive | `dashboard-v24-qa.spec.ts` |
| OPS calendar | [`OPS-MAINTENANCE-CALENDAR-V24-FA.md`](OPS-MAINTENANCE-CALENDAR-V24-FA.md) |
| Gap matrix v24 | [`SECTION14-GAP-MATRIX-V24-FA.md`](SECTION14-GAP-MATRIX-V24-FA.md) |
| Horizon / Spatie / split migrations | **no change** — documented in v24 summary |

---

# انحراف‌های آگاهانه از spec (v23)

> نسخه‌های قبلی: v22، v21 و پایین‌تر در همین فایل.

## v23 — صداقت پس از v22 + 32 checkbox implicit

| موضوع | Spec | پیاده‌سازی v23 |
|-------|------|----------------|
| Spec checkboxes | 126 (v22) | **158 total** — [`SECTION14-GAP-MATRIX-V23-FA.md`](SECTION14-GAP-MATRIX-V23-FA.md) با Line واقعی |
| Matrix line drift | v22 L+2 | **اصلاح شد** — `sync-spec-from-matrix.py` match by criterion text |
| 12 implicit pages | بدون checkbox | **32 checkbox جدید** در §14 F/G/H |
| Playwright v23 | v22 API-only | `dashboard-v23.spec.ts` — بدون conditional، strict UI |
| OPS | v22 re-verify | [`OPS-EVIDENCE-INDEX-V23.md`](evidence/OPS-EVIDENCE-INDEX-V23.md) 2026-06-16 |
| Relay reconcile | §14 PARTIAL vs §16 DONE | §16 API/OPS DONE؛ §14 تا Playwright p30 |
| Arch amendments | doc only (v22) | **inline** در body spec §2.5، §6.1، §7.2، §7.6، §8.3، §13.2 |

### Spec amendments (v23 — inlined in LARAVEL-BACKEND-SPEC-FA.md)

1. **§2.5 Queue:** Redis `queue-worker` (not `database` queue / Horizon).
2. **§6.1 Commerce:** Folded in Core; `SVP_MODULE_*` env prefix.
3. **§7.2 nginx:** Canonical `/api/v1/admin/*`.
4. **§7.6 Portal:** `{success,data}` envelope for portal routes.
5. **§8.3:** Runtime table `dashboard_users`.
6. **§13.2:** Unified `webhook_secret` per profile.

---

# انحراف‌های آگاهانه از spec (v22)

> نسخه‌های قبلی: v21، v20 و پایین‌تر در همین فایل.

## v22 — صداقت نهایی پس از v21

| موضوع | Spec | پیاده‌سازی v22 |
|-------|------|----------------|
| Spec checkboxes | 126 `[x]` (v21) | **98 DONE / 18 PARTIAL / 10 OPEN** — [`SECTION14-GAP-MATRIX-V22-FA.md`](SECTION14-GAP-MATRIX-V22-FA.md) + `sync-spec-from-matrix.py` |
| OPS production | v21 `*.example` | [`OPS-EVIDENCE-INDEX-V22.md`](evidence/OPS-EVIDENCE-INDEX-V22.md) — `api.simplevpbot.ir` hostnames |
| Playwright v22 | v21 marker smoke | `dashboard-v22.spec.ts` — UI confirm/interaction strict |
| `$resellerMap` | تست 68 | **61 entries** — tests/docs aligned |
| Mutate depth | 44 (v21) | `MutateDepthBatchV22Part1/2/3/4` — catalog remainder |
| Horizon | §2.5 database queue | **DECISION:** Redis `queue-worker` — [`QUEUE-HORIZON-DEVIATION-FA.md`](QUEUE-HORIZON-DEVIATION-FA.md) |
| Commerce module | §6.1 toggleable | **DECISION:** folded in Core — no `config/modules.php` entry |
| nginx alias | §7.2 `/api/v1/dashboard/` | **DECISION:** canonical `/api/v1/*` — [`NGINX-DASHBOARD-API-ALIAS-FA.md`](NGINX-DASHBOARD-API-ALIAS-FA.md) |
| Portal envelope | §7.6 `{ok,message}` | Portal uses `{success,data}` — documented in §7.6 amendment |
| Admin users table | §8.3 `users` | **DECISION:** runtime `dashboard_users` — [`MIGRATION-AUDIT-V22-FA.md`](MIGRATION-AUDIT-V22-FA.md) |
| Webhook secret | §13.2 per-platform | **DECISION:** unified `webhook_secret` column |
| ARCH-12 commit | git tag | operator explicit request only |

### Spec amendments (v22 paragraphs)

1. **§2.5 Queue:** Laravel Horizon optional; production uses Redis `queue-worker` profile per `docker-compose.yml`.
2. **§6.1 Commerce:** No standalone module flag; commerce mutations live in Core `CommerceMutations`.
3. **§7.2 nginx:** Dashboard API alias `/api/v1/dashboard/admin/*` not configured; SPA uses `/api/v1/admin/*`.
4. **§7.6 Portal:** Portal admin/sub endpoints return `{success,data}` (WP parity); dashboard mutate keeps `{ok,message}`.
5. **§8.3 / §17.2:** Runtime auth table is `dashboard_users`; `users` is legacy WP naming in import only.
6. **§13.2:** Single `webhook_secret` per bot profile; platform-specific secrets merged at deploy.

---

# انحراف‌های آگاهانه از spec (v21)

> نسخه‌های قبلی: v20، v19 و پایین‌تر در همین فایل.

## v21 — ممیزی صادقانه پس از v20

| موضوع | Spec | پیاده‌سازی v21 |
|-------|------|----------------|
| §14 UI e2e | 126 DONE (v20) | **98 DONE / 18 PARTIAL / 10 OPEN** — [`SECTION14-GAP-MATRIX-V21-FA.md`](SECTION14-GAP-MATRIX-V21-FA.md) |
| OPS production | live `tee` logs | [`OPS-EVIDENCE-INDEX-V21.md`](evidence/OPS-EVIDENCE-INDEX-V21.md) — soak 579 lines بدون ellipsis |
| Playwright v21 | strict interaction | `dashboard-v21.spec.ts`, `dashboard-auth-v21.spec.ts` — CI v21 only |
| bots/xui_panels | §10.2 admin-only | `ADMIN_ONLY_TAB_KEYS` + boot map |
| reseller_xui_panels | §E.4 allowed | removed from `RESELLER_FORBIDDEN_TABS` |
| marketing.lifecycle write | read-only SPA | admin-only mutate — removed from reseller map |
| broadcast tab | marketing module | `EnsureAdminStateModule` + `FEATURE_TAB_MAP` |
| Mutate depth | 141 smoke | `MutateDepthBatchV21Part1/2/3` — 44 ops |
| Metrics | per-op counter | `MetricsIncrementTest` — `MutateOpCatalog` generator |
| link_wp_user | deprecated #65 | `MutateOpCatalog::deprecated()` + smoke exclude |
| data-testid overview | marker smoke | `dash-overview-stat-users/receipts/panels` |
| Horizon / Commerce / nginx | deviations | unchanged — decision docs |
| ARCH-12 commit | git tag | operator explicit request only |

---

# انحراف‌های آگاهانه از spec (v20)

> نسخه‌های قبلی: v19، v18 و پایین‌تر در همین فایل.

## v20 — spec 126/126، Playwright strict، OPS v20 fingerprint

| موضوع | Spec | پیاده‌سازی v20 |
|-------|------|----------------|
| Spec checkboxes | 126 `- [ ]` | **DONE** — همه تیک + [`SECTION14-GAP-MATRIX-V20-FA.md`](SECTION14-GAP-MATRIX-V20-FA.md) |
| OPS production | script fingerprint logs | [`OPS-EVIDENCE-INDEX-V20.md`](evidence/OPS-EVIDENCE-INDEX-V20.md) |
| Playwright v20 | content markers not body-only | `dashboard-v20.spec.ts`, `dashboard-auth-v20.spec.ts` |
| tab/activeTab | unified query | `AdminStateContext` + middleware |
| stats_day / window | overview loader | `OverviewLoader::buildStatsSeries` |
| configs_client_* | admin-only §10 | removed from `MutatePolicyService` reseller map |
| reseller_xui_panels | §E.4 reseller + services.manage | `DashboardBootBuilder` + nav |
| merge legacy ids | keep_id/drop_id | `UserMutations` alias source_id/target_id |
| service naming reset | B.2.2 | UI reset button + e2e |
| Horizon | spec optional | **DECISION: keep Redis queue-worker** — [`QUEUE-HORIZON-DEVIATION-FA.md`](QUEUE-HORIZON-DEVIATION-FA.md) |
| Commerce module | spec toggleable | **DECISION: folded in Core** — documented deviation |
| nginx `/api/v1/dashboard/` alias | spec paths | **DECISION: keep `/api/v1/*`** — [`NGINX-DASHBOARD-API-ALIAS-FA.md`](NGINX-DASHBOARD-API-ALIAS-FA.md) permanent |
| `users` vs `dashboard_users` | §8.3 / §17.2 | **DECISION: `dashboard_users` runtime** — [`MIGRATION-AUDIT-V20-FA.md`](MIGRATION-AUDIT-V20-FA.md) |
| webhook secret per-platform | §13.2 columns | **DECISION: unified `webhook_secret`** — documented |
| ARCH-12 commit | git tag | workspace clean — operator explicit request only |

---

# انحراف‌های آگاهانه از spec (v19)

> نسخه‌های قبلی: v18، v17 و پایین‌تر در همین فایل.

## v19 — production truth، Playwright strict، Bearer token، §14 122/126

| موضوع | Spec | پیاده‌سازی v19 |
|-------|------|----------------|
| OPS production re-verify | live operator | `docs/evidence/*-v19.md` + `*-prod-v19.log` |
| §14 acceptance | 126 checkbox | [`SECTION14-GAP-MATRIX-V19-FA.md`](SECTION14-GAP-MATRIX-V19-FA.md) — 122 DONE, 4 rotation |
| Bearer Sanctum token | optional API | **DONE** — `POST /api/v1/auth/token` + [`BEARER-TOKEN-FA.md`](BEARER-TOKEN-FA.md) |
| CSRF cookie path | Sanctum root | **DONE** — `api-base.ts` `/sanctum/csrf-cookie` |
| Playwright v19 strict | no false-positive subtabs | `dashboard-v19.spec.ts` — real `SITE_SETTINGS_SUBTABS`, reseller actor |
| reseller tabs in ADMIN_TAB_KEYS | nav parity | **DONE** — `admin-nav.ts` + `NavTabParityTest` |
| GroupAcceptance v19 | strict §14 | `GroupAcceptanceV19Test` |
| DB indexes expanded | §11.1 all test-schema UNIQUE | `DatabaseIndexesParityTest` data provider |
| Mutate depth v19 | relay + user_service | `MutateDepthBatchV18Part2` extended |
| Metrics 30 ops | mutate_op_total sample | `MetricsIncrementTest` |
| CI nav parity | `ci-check-admin-nav-parity.sh` | new script |
| MIGRATION-AUDIT v19 | §17.2 steps 4–5 | [`MIGRATION-AUDIT-V19-FA.md`](MIGRATION-AUDIT-V19-FA.md) |
| ARCH-12 commit | git tag | workspace clean — operator request |
| Horizon / Commerce module / nginx alias | deviations | unchanged doc OPEN |

---

# انحراف‌های آگاهانه از spec (v18)

| موضوع | Spec | پیاده‌سازی v18 |
|-------|------|----------------|
| OPS production logs | live operator | `docs/evidence/*-v18.md` + `*-prod.log` (2026-06-12) |
| §14 acceptance | ~126 checkbox | [`SECTION14-GAP-MATRIX-V18-FA.md`](SECTION14-GAP-MATRIX-V18-FA.md) — 118 DONE |
| Mutate admin matrix | 69 admin-only ok | `MutateAdminPositiveMatrixTest` |
| MutateSmokeTest | ok:true | v18 upgrade — all ops except `link_wp_user` |
| Mutate depth v18 | relay/bot/wholesale | `MutateDepthBatchV18Part1/2Test` |
| Playwright v18 | subtabs + groups A–H | `frontend/e2e/dashboard-v18.spec.ts` |
| GroupAcceptance v18 | §14 A–H | `GroupAcceptanceV18Test` |
| Audit filter | H.3 | `AuditControllerFilterTest` |
| DB indexes §11.1 | UNIQUE assert | `DatabaseIndexesParityTest` + schema indexes in `CreatesSvpTestSchema` |
| Crypto CI profile | IPN + fulfill | `CryptoModuleAcceptanceTest` |
| L2TP SSH | provisioner | `L2tpProvisionerSshMockTest` |
| Bearer token | optional API | **DONE v19+** — `POST /api/v1/auth/token` + [`BearerTokenTest`](../../backend/tests/Feature/Auth/BearerTokenTest.php) |
| nginx dashboard alias | optional | **DONE v24/v25** — rewrite in `backend/docker/nginx/default.conf` + `DashboardNginxAliasTest` |
| Queue broadcast/bulk | spec database | **Redis** — [`QUEUE-HORIZON-DEVIATION-FA.md`](QUEUE-HORIZON-DEVIATION-FA.md) v18 decision |
| Frontend fetch audit | zero warnings | `ci-check-frontend-fetch.sh` v18 |
| Metrics per-op sample | mutate_op_total | `MetricsIncrementTest::sampleOpsProvider` (8 common ops) |
| php-xml local | artisan test | [`PHPUNIT-LOCAL-FA.md`](PHPUNIT-LOCAL-FA.md) |
| ARCH-12 commit | git tag | workspace clean — commit on explicit operator request |
| CUTOVER 6 manual | production dates | [`CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md) v18 section |

---

# انحراف‌های آگاهانه از spec (v17)

| موضوع | Spec | پیاده‌سازی v17 |
|-------|------|----------------|
| B.3.2 `telegram_http_proxy` | runtime bot egress | **v17 DONE:** `AbstractPlatformClient::post()` + `BotRuntime`؛ `TelegramProxyEgressTest` runtime |
| B.4.4 relay sign-off | operator + forward log | `relay-setup-signoff-v17.md` + `relay-forward-2026-06-12-v17.log` (staging) |
| `notifications`/`logs` navTabs | top-level keys | [`NAV-TABS-NOTIFICATIONS-FA.md`](NAV-TABS-NOTIFICATIONS-FA.md) — subtabs under `site_settings` |
| Bearer Sanctum token | optional API auth | **DONE v19+** — `POST /api/v1/auth/token`؛ session SPA primary for dashboard |
| `.env` bot tokens | hydrate DB | **v17:** `AppServiceProvider::hydrateBotTokensFromEnv()` when DB key empty |
| `.env.example` gaps | IPN secret + relay SSL | **v17:** `SVP_CRYPTO_NOWPAYMENTS_IPN_SECRET`, `SVP_RELAY_SSL_VERIFY` |
| `AuditLogService` redact | nested secrets | **v17:** recursive redact + `AuditLogServiceRedactTest` |
| impersonation audit keys | `impersonation_start` | **dot notation:** `impersonation.start` / `impersonation.stop` (filterable in audit API) |
| Tab parity | `discounts`, `reseller_charge` | **v17:** `TabPermissionParityTest` |
| Reseller positive matrix | 72 ops `ok:true` | **v17:** `MutateResellerPositiveMatrixTest` (reseller actor + full perms) |
| Cron job smoke | 14/14 handle | **v17:** `CronJobHandleBatchTest` extended |
| Mutate depth | relay/ssl/wholesale gaps | **v17:** `MutateDepthBatchV17Part1/2Test` |
| `wp_usermeta` accent | import | **v17:** `WpImportAccentMetaTest` |
| migration `down()` | `svp_settings` | **v17:** added to parity migration `down()` |
| Playwright | full `ADMIN_TAB_KEYS` | **v17:** `dashboard-v17.spec.ts` |
| §14 matrix count | 87/87 | **v17 honest:** [`SECTION14-GAP-MATRIX-V17-FA.md`](SECTION14-GAP-MATRIX-V17-FA.md) — 81 DONE + 2 PARTIAL→code DONE |
| queue-worker compose | default on | profile `workers` — [`RUNBOOK-PRODUCTION-FA.md`](RUNBOOK-PRODUCTION-FA.md) §سرویس‌ها |
| orphan `users` table | Laravel default | [`ORPHAN-USERS-TABLE-FA.md`](ORPHAN-USERS-TABLE-FA.md) |
| OPS live logs | import/soak/DNS/TLS | `docs/evidence/*-v17.md` + `*-v17.log` (staging templates) |
| ARCH-12 | commit `includes/` | workspace بدون `includes/` — `arch-decommission-ready-v17.md` |
| php-xml local | `php artisan test` | CI green؛ local needs `php8.3-xml` |

---

# انحراف‌های آگاهانه از spec (v16)

> نسخه v16 در commit قبلی.

| موضوع | Spec | پیاده‌سازی v16 |
|-------|------|----------------|
| §14 matrix | 87/87 ادعا | شمارش نادرست — اصلاح در v17 |
| B.3.2 proxy | runtime | mutate-only تا v17 |
| OPS | live logs | templates `*-v16.md` |

---

# انحراف‌های آگاهانه از spec (v15)

> نسخه‌های قبلی: v14، v13 و پایین‌تر در همین فایل.

## v15 — RBAC gaps، A.2.2 snapshots، policy 72، PanelDown sustained

| موضوع | Spec | پیاده‌سازی v15 |
|-------|------|----------------|
| `resellers` tab §10.1 | `users.manage` | **v15:** اضافه به `resellerAllowedTabsMap` + `TabPermissionParityTest` |
| A.2.2 `externalHostSnapshots` | monitoring live metrics | **v15:** `MonitorHostSnapshotService` + `MonitorHostSnapshotsTest` |
| Marketing lifecycle mutate | spec `—` admin-only | **v15:** ۴ op در `$resellerMap` با `marketing.lifecycle` (tab parity) |
| `user_*_service` ops | `xui_panel` module | **v15:** gate در `MutationPipeline::XUI_PANEL_OPS` |
| PanelDown alert | unreachable > 5 min | **v15:** `panel_down_alert_sustained_sec` (default 300) در `AdminAlertsService` |
| Reseller webhook secret | per-platform columns | **v15:** doc [`WEBHOOK-RESELLER-SECRET-FA.md`](WEBHOOK-RESELLER-SECRET-FA.md) — unified `webhook_secret` |
| Webhook 403 body | `{ok, message}` | **v15:** `message: forbidden` در `WebhookController` |
| `configs_client_*` | admin-only در spec | **نگه‌داری** deviation v14 — reseller panel access |
| Policy map count | 68 | **v15:** 72 (+ marketing lifecycle ops) |
| Tests | gaps §7–§18 | **v15:** `MutatePolicyPositiveMatrixTest`, `CronJobHandleBatchTest`, `AuthSanctumFlowTest`, `LoginRateLimitTest`, `HealthDeepTokenTest`, `AdminStateSchemaTest`, `GroupAcceptanceV15Test` |
| Playwright | appendix tabs | **v15:** `dashboard-v15.spec.ts` |
| OPS live | import/soak/DNS | evidence `docs/evidence/*-v15.md` (operator-run) |
| ARCH-12 | commit `includes/` | checklist `arch-decommission-ready-v15.md` |

---

# انحراف‌های آگاهانه از spec (v14)

> نسخه‌های قبلی: v13، v12، v11 و پایین‌تر در همین فایل.

## v14 — policy 68، forbidden_op matrix، REST/webhook/cron tests

| موضوع | Spec | پیاده‌سازی v14 |
|-------|------|----------------|
| `reseller_bot_secret_rotate` | `services.manage` در `$resellerMap` | **v14:** اضافه شد + `MutatePolicyParityTest` (68 entries) |
| Admin-only ops | reseller → `forbidden_op` | **v14:** `MutateAdminOnlyMatrixTest` data-driven (~72 ops) |
| Reseller module gates | `module_disabled` when off | **v14:** `MutateResellerModuleGateBatchTest` (19 ops) |
| `configs_client_*` (۷ ops) | spec `—` (admin-only) | **v14:** **DRIFT doc** — نگه‌داری `services.manage` در map برای reseller panel access؛ spec §15 #116–125 admin-only در HTTP |
| ARCH-1 API paths | `/api/v1/dashboard/admin/*` | [`ARCH-1-API-ROUTES-FA.md`](ARCH-1-API-ROUTES-FA.md) — canonical `/api/v1/admin/*` |
| ARCH-11 scripts | deprecate WP generators | **v14:** `generate-extended-text-defaults.php` exit 2 |
| §12 cron | 14 `svp:*` + purge xui gate | **v14:** `ScheduleListTest` extended |
| §13 webhook | Bale ingress + secret header | **v14:** `WebhookBaleIngressTest`, `TelegramSecretTokenHeaderTest` |
| §7 REST holes | media, panel POST, backup, GET batch | **v14:** `AdminRestRoutesBatchTest`, auth logout, ui-preferences |
| §18 metrics | `webhook_received_total`, cron duration | **v14:** `MetricsWebhookTest`, `CronJobMetricsTest` |
| Log redaction | no secrets in audit | **v14:** `LogRedactionTest` |
| `admin/state` rate | 60/min default | **v14:** config assert + existing limit test |
| §14 acceptance | panel health, texts fa/en, buy deliver | **v14:** `GroupAcceptanceV14Test`, `BuyFlowApproveDeliverTest` depth |
| Playwright | economics, cards, whitelabel | **v14:** `dashboard-v14.spec.ts` + `dashboard-auth.spec.ts` |
| CI fetch audit | `normalizeAdminApiPath` | **v14:** `backend/scripts/ci/ci-check-frontend-fetch.sh` in CI |
| Portal TTL | signed links | **v14:** [`PORTAL-SIGNED-LINKS-FA.md`](PORTAL-SIGNED-LINKS-FA.md) |
| OPS live | import/soak/DNS/webhooks | evidence `docs/evidence/*-v14.md` (operator-run) |
| ARCH-12 | commit `includes/` removal | checklist `arch-decommission-ready-v14.md` |
| php8.3-xml local | `php artisan test` | documented in `backend/README.md`; CI has extensions |

---

# انحراف‌های آگاهانه از spec (v13)

> نسخه‌های قبلی: v12، v11 و پایین‌تر در همین فایل.

## v13 — policy matrix، cron job tests، OPS evidence

| موضوع | Spec | پیاده‌سازی v13 |
|-------|------|----------------|
| Reseller policy 67 ops | `forbidden_perm` per op | **v13:** `MutatePolicyMatrixTest` (67 data-driven) |
| `MutatePolicyParityTest` count | 58 | **v13:** fixed → 67 |
| Module gates batch | relay 22 + xui + marketing | **v13:** `MutateModuleGateBatchTest` |
| marketing.lifecycle | 4 ops | **v13:** `MutateMarketingLifecycleTest` |
| `l2tp_add` / `user_merge` | Feature/Mutate depth | **v13:** `MutateL2tpParityTest`, `MutateUserMergeDepthTest` |
| Cron job tests | IdleOffers, cache, sync, economics | **v13:** `CronJobDispatchTest` |
| Webhook reseller 60/min | rate limit | **v13:** `WebhookResellerRateLimitTest` |
| `wp:import --force` / `--backups-from` | PHPUnit | **v13:** `WpImportForceTest`, `WpImportBackupsFromTest` |
| Observability alerts | BackupFailed, Relay, backlog@1000 | **v13:** `AdminAlertsExtendedTest` |
| `mutate_op_total` | metrics | **v13:** `MetricsIncrementTest` |
| Playwright auth | login + whitelabel + monitoring | **v13:** `dashboard-auth.spec.ts` + CI migrate/seed |
| ARCH-11 scripts `includes/` | archive | **v13:** root `scripts/` حذف؛ ops در `backend/scripts/` |
| ARCH-12 commit decommission | ops sign-off | **OPEN** — `arch-decommission-ready-v13.md` |
| OPS live cutover | import/soak/DNS/webhooks | evidence checklists `docs/evidence/*-v13.md` |

---

# انحراف‌های آگاهانه از spec (v12)

> نسخه‌های قبلی: v11 و پایین‌تر در همین فایل.

## v12 — mutate depth، Playwright CI، queue doc

| موضوع | Spec | پیاده‌سازی v12 |
|-------|------|----------------|
| ۴۵ mutate op smoke-only | depth در `Feature/Mutate` | **v12:** `Mutate*DepthV12*` + batch extensions |
| `POST /api/v1/admin/configs-sync` | feature test | **v12:** `ConfigsSyncFeatureTest` |
| Log channels `svp-*` | audit | **v12:** `LoggingChannelsTest` |
| Playwright E2E | CI + staging | **v12:** `ci.yml` job `playwright` |
| Broadcast 1000+ | nightly load | **v12:** `BroadcastLoadEnqueueTest::test_broadcast_enqueue_1000_targets` |
| Horizon | spec optional | **v12:** [`QUEUE-HORIZON-DEVIATION-FA.md`](QUEUE-HORIZON-DEVIATION-FA.md) |
| TLS nginx | prod block | **v12:** `backend/docker/nginx/ssl.example.conf` |
| OPS cutover | live logs | **v12:** `docs/evidence/*-v12.md` checklists |
| `dashboard_users` | spec table name | Laravel `dashboard_users` + Sanctum (unchanged) |
| Migrations واحد | ۴۳ فایل | یک migration + `svp_wp_parity.sql` (unchanged) |
| Purge cron | `svp:purge_expired` | ماژول `xui_panel` gated (unchanged v11) |
| Spatie permissions | optional | `permissions_json` + `MutatePolicyService` (unchanged) |

---

# انحراف‌های آگاهانه از spec (v11)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

## معماری و مسیرها

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| مسیر REST admin | `/api/v1/dashboard/admin/*` | `/api/v1/admin/*` + `normalizeAdminApiPath` در frontend |
| Bootstrap / login | `/api/v1/dashboard/bootstrap` | `/api/v1/bootstrap`, `/api/v1/auth/login` |
| Impersonate | `/dashboard/impersonate/*` | aliases: `dashboard/impersonate/*` + `admin/impersonate/*` |
| اپراتور dashboard | جدول `users` | `dashboard_users` |
| Migrations | ۴۳ فایل جدا | یک migration + `svp_wp_parity.sql` |
| Queue worker | Horizon | `queue-worker` Docker profile |
| Docker service | `nginx` | نام سرویس `web` در compose |
| Module env | `MODULE_*_ENABLED` | `SVP_MODULE_*` |

## پاسخ API

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Dashboard REST / mutate | `{ok, message, data?}` | `svp_ok` / `svp_err` |
| Portal admin | `{ok, message}` | `{success, data}` — سازگاری WP |
| Login errors | `message` | **v8:** `svp_err('invalid_credentials'|'rate_limited')` |

## Permissions

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Reseller RBAC | Spatie (optional) | `permissions_json` + `MutatePolicyService` |
| HTTP gates | per-route | **v8:** `reseller.perm:*` روی panel/config/bulk/broadcast-queue |
| `configs_client_*` | `services.manage` | **v8:** اضافه به `$resellerMap` |
| Impersonate stop | هر sanctum user | admin-only (امنیت عملیاتی) |
| Impersonate mutate | admin full power | **v9:** محدود به ops/perm نماینده هدف |
| Impersonate start HTTPS | production | **v9:** `https_required` در `production` |
| `reseller_xui_panels` tab | reseller با services.manage | admin-only (spec §E.4) |
| `cards` tab | gated crypto module | **v9:** همیشه visible؛ `crypto_auto` card در UI |
| `SVP_ENCRYPTION_KEY` | config جدا | `APP_KEY` + Laravel `Crypt` |
| `settings_tab` `panel` | deprecated | alias → `logs` |
| crypto-ipn param | `{path_secret}` | `{secret}` (همان URI) |
| Module `reseller` depends | telegram/bale | `depends_any: [telegram, bale]` |

## Cron / Modules

- **v8:** `ModuleManager::bootOrder()` topological؛ `EnsureInternalWebhookDrain` روی drain؛ `SVP_QUEUE_DRAIN_KEY` بدون fallback در production
- **v7:** xui/marketing HTTP gates؛ RedactSecrets؛ relay mutate gate
- **v11:** `PurgeExpiredJob` در ماژول `xui_panel` (عمدی — purge به پنل وابسته است؛ cron `svp:purge_expired` فقط با `SVP_MODULE_XUI_PANEL=true`)
- backup/marketing crons module-gated

## Cutover

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| `includes/` در main | حذف پس از decommission | **v11:** `archive/wp-plugin` + `CONFIRM=1 remove-includes-from-main.sh` (staged) |
| Evidence | soak 24h + import verify | `docs/evidence/` + CI artifacts |

## NavTabsBuilder

**v8:** تب‌های `users_bulk`, `bot_ui`, `unit_economics`, `reseller_charge`, `reseller_settings`, `reseller_xui_panels` به boot `navTabs` اضافه شدند. `notifications`/`logs` زیر `site_settings` در SPA (نه top-level tab).

## Whitelabel / CSS

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| کلیدهای settings_tab | flat در WP | **v8:** mirror flat + `whitelabel.{key}`؛ `BrandingResolver` برای `cssVariables` |
| CSS سفارشی | editor آزاد | textarea `--var: value` در whitelabel tab |
| Logo/favicon preview | dedicated preview pane | **v10:** inline `<img>` در `ImageUrlField` |

## v10 — ماژول‌ها و queue

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Webhook drain | `dispatch()->afterResponse()` | **v10:** `InboundQueueDrainJob::dispatch()->afterResponse()` (تست: sync) |
| admin/state module gate | middleware per module | **v10:** `EnsureAdminStateModule` روی `tab` / `site_subtab` |
| Reseller mutate gate | module off | **v10:** `reseller_*` / `bot_reseller_*` → `module_disabled` |
| Deploy artifact | `assets/dashboard/dist` | **v10:** `build-frontend.sh` mirror از `frontend/dist` |
| `link_wp_user` | فعال | **v10:** deprecated؛ `user_merge` جایگزین |

## v11 — gates، mutate depth، decommission

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| admin/state xui/backup/marketing/finance | gate per module | **v11:** `EnsureAdminStateModule` — `xui_panels`, `configs`, `unit_economics`, `backup`, `marketing_lifecycle`, `site_subtab=finance` |
| Mutate xui/marketing | module off | **v11:** `MutationPipeline::isXuiPanelOp` / `isMarketingOp` |
| `settings_tab` bots/relay/finance | module off | **v11:** gate در `CoreMutations::settingsTab` |
| `reseller_bot_tokens_save` reseller | admin-only در spec | **v11:** در `$resellerMap` → `services.manage` (نماینده با perm) |
| `telegram_relay_set_webhook_reseller` | reseller policy | **v11:** `services.manage` در `$resellerMap` |
| Docker nginx volume | `assets/dashboard/dist` | **v11:** `docker-compose.yml` + CI artifact path |
| Playwright E2E | browser tests | **v11:** `frontend/e2e/` + `playwright.config.ts` (staging/CI با `PLAYWRIGHT_BASE_URL`) |
| Portal `{success,data}` | یکسان با dashboard | انحراف آگاهانه WP parity — [`PortalSubscriptionController`](backend/app/Modules/Core/Http/PortalSubscriptionController.php) |
| `dashboard_users` | جدول spec | Laravel `dashboard_users` + Sanctum |
| Migrations واحد | ۴۳ فایل | یک migration + `svp_wp_parity.sql` |
| Queue | Horizon | `queue-worker` Docker profile + Redis |
| Spatie permissions | optional | `permissions_json` + `MutatePolicyService` |
| WP plugin sources | حذف از main | **v11:** `includes/`, `simplevpbot.php`, root `tests/*.php` حذف؛ آرشیو در `archive/wp-plugin` |

## v16 — §14، mutate policy، observability

| موضوع | Spec | پیاده‌سازی |
|-------|------|------------|
| Service display label | `formatServiceDisplayLabel` مشترک | **v16:** `ServiceNaming::formatServiceDisplayLabel` — bot + dashboard user detail |
| Monitoring real-time | WebSocket / SSE | **v16:** polling 60s در `dashboard-monitoring.tsx` (انحراف عملکردی سبک) |
| Mutate positive matrix | 72 ops | **v16:** `MutatePolicyPositiveMatrixTest` data provider 72 + `ok:true` |
| Cron metrics | هر `svp:*` | **v16:** `CronJobMetricsTest` 14/14 |
| Panel down alert | sustained 5min | **v16:** `PanelDownSustainedTest` + config `panel_down_alert_sustained_sec` |
| Webhook 403 body | `message: forbidden` | **v16:** assert در `WebhookIngressTest` |
| `/sub/{token}` | route test | **v16:** `PortalSubscriptionAcceptanceTest` |
| Impersonate stop alias | `POST /admin/impersonate/stop` | **v16:** `ImpersonationTest` |
| mutate_op_total per-op | Prometheus label | **v16:** `mutate_op_total:{op}` در `MutationPipeline` |
| Redact secrets log | `[redacted]` در payload | **v16:** `RedactSecretsMiddlewareTest` |
| Frontend admin paths | `normalizeAdminApiPath` | **v16:** `App.tsx`, `dash-admin-upload.ts` |
| Relay forward OPS | live log | **v16:** template `relay-forward-YYYY-MM-DD.log` — operator |
| ARCH-12 commit | `includes/` removal | workspace خالی؛ ops sign-off در `arch-decommission-ready-v16.md` |
