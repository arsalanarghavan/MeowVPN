# Cutover Sign-off — Staging / Production

چک‌لیست evidence برای [`WP-DECOMMISSION-FA.md`](WP-DECOMMISSION-FA.md).

## Automated (repo scripts + CI)

| Step | Command | Evidence |
|------|---------|----------|
| CI preflight | `backend/scripts/ops/cutover-preflight.sh` (GitHub Actions `backend` job) | `docs/evidence/cutover-preflight-YYYY-MM-DD.log` |
| CI short soak | `SVP_SOAK_DURATION_SEC=30 soak-24h.sh` | workflow log |
| CI load smoke | `SVP_LOAD_REQUESTS=100 load-smoke.sh` | workflow log |
| Nightly soak | `.github/workflows/nightly-soak.yml` | `docs/evidence/soak-nightly-YYYY-MM-DD.log` artifact |
| Import verify only | `SVP_MYSQL_DSN=... backend/scripts/ops/import-verify.sh` | `docs/evidence/import-verify-YYYY-MM-DD.log` |
| Import + verify | `SVP_MYSQL_DSN=... backend/scripts/ops/staging-cutover-runbook.sh` | log output |
| HTTP smoke | `SVP_BASE_URL=... backend/scripts/ops/staging-e2e.sh` | exit 0 |
| Checklist | `SVP_BASE_URL=... backend/scripts/ops/staging-cutover-checklist.sh` | exit 0 |
| Soak 24h | `SVP_SOAK_DURATION_SEC=86400 SVP_BASE_URL=... backend/scripts/ops/soak-24h.sh` | `docs/evidence/soak-24h-YYYY-MM-DD.log` |
| Rollback drill | `backend/scripts/ops/rollback-drill.sh` | `docs/evidence/rollback-drill.log` |
| WP disable (staging) | `WP_PATH=... backend/scripts/ops/wp-disable-staging.sh` | manual confirm |

## Manual sign-off

- [ ] Portal admin `?svp_adm=1` — stats, membership, create_service
- [ ] Portal sub plain + HTML
- [ ] Bot webhook (direct + relay)
- [ ] Crypto IPN test transaction
- [ ] Dashboard login + mutate smoke
- [ ] Scheduler 14 jobs running

## Production cutover

Runbook: [`CUTOVER-STAGING-FA.md`](CUTOVER-STAGING-FA.md) + DNS change ticket.

Operator / date: _______________

## v9 code readiness (automated)

- [x] CI: PHPUnit, cutover preflight, soak 30s, load 100, alert-smoke
- [x] Impersonation mutate policy + reseller scope during impersonate
- [x] L2TP / bot mutate module gates; cards tab always in nav
- [x] nginx routes: `/info`, `/health`, `/metrics`, `/api/`, `/dashboard`
- [x] `includes/` removal — **v11** `CONFIRM=1 remove-includes-from-main.sh` (staged)
- [ ] Staging: import verify log, soak 24h, 6 manual signoffs *(historical — superseded by v23 prod OK)*

## v10 code readiness (automated)

- [x] `EnsureAdminStateModule` HTTP gate (l2tp/bots/relay/proxy tabs)
- [x] Reseller mutate module gate; `panel_access` در `reseller_panel_prices_save`
- [x] Mutate depth tests: relay nginx/ssl, reseller bot, service panel, configs, marketing, misc
- [x] `frontend/scripts/build.sh` → `frontend/dist` (deploy-artifact parity)
- [x] `InboundQueueDrainJob::afterResponse()`؛ broadcast load smoke (100 targets)
- [x] API E2E script: `backend/scripts/e2e/e2e-dashboard-api.sh`
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, WP off

## v11 code readiness (automated)

- [x] `EnsureAdminStateModule` — xui/backup/marketing/finance subtabs
- [x] `MutationPipeline` xui + marketing module gates
- [x] Mutate behavioral depth tests (35 smoke ops → depth)
- [x] `backend/docker-compose.yml` nginx mount `frontend/dist`
- [x] CI artifact path `frontend/dist/`
- [x] Playwright scaffold `frontend/e2e/`
- [x] WP decommission: `includes/`, `simplevpbot.php`, root WP tests removed
- [ ] Operator: staging import verify log (`docs/evidence/import-verify-YYYY-MM-DD.log`)
- [ ] Operator: soak 86400s log (`docs/evidence/soak-24h-YYYY-MM-DD.log`)
- [ ] Operator: 6 manual signoffs above + date below

Operator / date (v11): _______________

## v12 code readiness (automated)

- [x] Mutate depth v12 — ۴۵ ops در `backend/tests/Feature/Mutate/*Depth*`
- [x] `ConfigsSyncFeatureTest`, `LoggingChannelsTest`, `TelegramProxyEgressTest`
- [x] `MutationPipelineModuleGateTest` regression
- [x] Playwright job در `.github/workflows/ci.yml`
- [x] Broadcast 1000+ — `BroadcastLoadEnqueueTest` + nightly workflow
- [x] Evidence checklists — `docs/evidence/*-v12.md`
- [x] TLS example — `backend/docker/nginx/ssl.example.conf`
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, 6 manual signoffs

Operator / date (v12): _______________

## v13 code readiness (automated)

- [x] `MutatePolicyMatrixTest` — 67 reseller-mapped ops `forbidden_perm`
- [x] `MutateModuleGateBatchTest` — relay + xui + marketing batch gates
- [x] `MutateMarketingLifecycleTest` — `marketing.lifecycle` perm
- [x] Migration tests — `--force`, `--backups-from`, `reseller_perms`, `svp:register-webhooks`
- [x] `GroupAcceptanceV13Test`, `BackupRestHttpTest`, `BuyFlowApproveDeliverTest`
- [x] `WebhookResellerRateLimitTest`, `AdminAlertsExtendedTest`, `MetricsIncrementTest`
- [x] Playwright auth — `dashboard-auth.spec.ts` + CI sqlite seed
- [x] Evidence checklists — `docs/evidence/*-v13.md`
- [x] ARCH-11 — root `scripts/` removed; ops/CI under `backend/scripts/` + spec sync under `docs/scripts/`
- [ ] ARCH-12 — commit/push `includes/` removal (ops sign-off only)
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, 6 manual signoffs

Operator / date (v13): _______________

## v14 code readiness (automated)

- [x] `MutatePolicyParityTest` — 68 reseller-mapped ops (`reseller_bot_secret_rotate`)
- [x] `MutateAdminOnlyMatrixTest` — admin-only ops → `forbidden_op`
- [x] `MutateResellerModuleGateBatchTest` — reseller module off → `module_disabled`
- [x] `ScheduleListTest` — 14 `svp:*` jobs + purge when xui off
- [x] Webhook: Bale ingress, Telegram secret-token header, `MetricsWebhookTest`
- [x] REST batch: media, panel POST×3, backup reset-stuck/restore-upload, GET routes
- [x] `GroupAcceptanceV14Test`, `BuyFlowApproveDeliverTest`, `BackupRestoreZipTest`
- [x] Playwright: `dashboard-v14.spec.ts` + `dashboard-auth.spec.ts`
- [x] CI: `ci-check-frontend-fetch.sh`, ARCH-11 deprecation exit 2
- [x] Evidence templates — `docs/evidence/*-v14.md`
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, 6 manual signoffs
- [ ] ARCH-12 — git commit/push `includes/` removal (ops sign-off)

Operator / date (v14): _______________

## v15 code readiness (automated)

- [x] `resellers` tab RBAC + `TabPermissionParityTest`
- [x] `externalHostSnapshots` — `MonitorHostSnapshotService` (§14 A.2.2)
- [x] Policy 72 entries (+ marketing.lifecycle mutate ops)
- [x] `user_create_service` xui_panel module gate
- [x] PanelDown sustained 300s (`SVP_PANEL_DOWN_ALERT_SUSTAINED_SEC`)
- [x] `CronJobHandleBatchTest`, extended `CronJobMetricsTest`
- [x] `AuthSanctumFlowTest`, `LoginRateLimitTest`, `ImpersonationHttpsTest`
- [x] `GroupAcceptanceV15Test`, `AdminStateSchemaTest`, `MutatePolicyPositiveMatrixTest`
- [x] Playwright: `dashboard-v15.spec.ts`
- [x] Evidence templates — `docs/evidence/*-v15.md`
- [ ] Operator: import verify log, soak 86400s, DNS, live webhooks, 6 manual signoffs
- [ ] ARCH-12 — git commit/push `includes/` removal (ops sign-off)

Operator / date (v15): _______________

## v16 code readiness (automated)

- [x] `formatServiceDisplayLabel` — bot `ServiceHandler`, `AdminUserDetailBuilder`, tests
- [x] Monitoring auto-refresh 60s + Playwright chart smoke
- [x] `MutatePolicyPositiveMatrixTest` — 72 mapped ops `ok:true`
- [x] `InteractsWithMutate` payloads — reseller-mapped ops
- [x] `CronJobMetricsTest` — 14/14 `svp:*` labels
- [x] `PanelDownSustainedTest`, webhook 403 `message`, `/sub/{token}` route
- [x] `POST /admin/impersonate/stop` alias test; `mutate_op_total:{op}` metrics
- [x] `RedactSecretsMiddlewareTest` — `[redacted]` assert
- [x] Frontend `normalizeAdminApiPath` — `App.tsx`, `dash-admin-upload.ts`
- [x] Playwright `dashboard-v16.spec.ts` — tabs + reseller + whitelabel + cards + reports
- [x] `ApiRouteAuditTest` expanded; backup valid zip restore E2E
- [x] `ForceJoinPublishChannelTest`, `PurgeExpiredReadyListTest`
- [x] Evidence templates — `docs/evidence/*-v16.md`, `import-verify-*.log`, `relay-forward-*.log`
- [x] `SECTION14-GAP-MATRIX-V16-FA.md` — 87/87 DONE (ops relay log template)
- [ ] Operator: live DSN/import/soak/DNS/TLS/webhook logs (see `*-v16.md`)
- [ ] ARCH-12 — git commit/push `includes/` removal when ops ready

Operator / date (v16): _______________

## v17 code readiness (automated)

- [x] B.3.2 — `AbstractPlatformClient` proxy egress + `TelegramProxyEgressTest` runtime
- [x] `MutateResellerPositiveMatrixTest` — 72 ops reseller actor `ok:true`
- [x] `CronJobHandleBatchTest` — smoke هر ۱۴ scheduled job
- [x] `MutateDepthBatchV17Part1/2`, `WpImportAccentMetaTest`, `AuditLogServiceRedactTest`
- [x] `TabPermissionParityTest` — `discounts`, `reseller_charge`
- [x] Playwright `dashboard-v17.spec.ts` — full `ADMIN_TAB_KEYS`, Group F/H, 60s poll mock
- [x] `ApiRouteAuditTest` — `/health`, `/metrics`, portal routes
- [x] `backend/scripts/ci/ci-check-frontend-fetch.sh` — stricter raw path grep + evidence v17
- [x] `SECTION14-GAP-MATRIX-V17-FA.md` — شمارش صادقانه 81+2
- [x] Staging evidence logs — `import-verify/run/soak/relay-forward/observability-48h-*-v17.log`
- [x] `arch-decommission-ready-v17.md` — `includes/` absent in workspace
- [ ] Operator: 6 manual signoffs با تاریخ production
- [ ] Operator: production DNS/TLS/webhook live logs

### v17 manual sign-off (staging 2026-06-12)

| Item | Status | Date |
|------|--------|------|
| Portal admin `?svp_adm=1` | staging OK | 2026-06-12 |
| Portal sub plain + HTML | staging OK | 2026-06-12 |
| Bot webhook (direct + relay) | staging OK | 2026-06-12 |
| Crypto IPN test transaction | N/A module off | — |
| Dashboard login + mutate smoke | CI + staging | 2026-06-12 |
| Scheduler 14 jobs running | staging verify | 2026-06-12 |

Operator / date (v17): 2026-06-12

## v18 code readiness (automated)

- [x] `MutateAdminPositiveMatrixTest` — 69 admin-only ops ok:true
- [x] `MutateSmokeTest` — ok:true all ops (except `link_wp_user`)
- [x] `MutateDepthBatchV18Part1/2Test`
- [x] `GroupAcceptanceV18Test` — §14 groups A–H
- [x] `AuditControllerFilterTest` — domain/event/q/pagination
- [x] `DatabaseIndexesParityTest` — §11.1 UNIQUE indexes
- [x] `CryptoModuleAcceptanceTest` — IPN HMAC + fulfill
- [x] `L2tpProvisionerSshMockTest` — SSH mock integration
- [x] Playwright `dashboard-v18.spec.ts` — 10 site_settings subtabs + groups A–H
- [x] `SECTION14-GAP-MATRIX-V18-FA.md` — 118/126 DONE
- [x] Production OPS evidence — `docs/evidence/*-v18.md` + `*-prod.log`
- [x] `ci-check-frontend-fetch.sh` — zero warnings
- [x] Docs: `BEARER-TOKEN-FA.md` OPEN، `NGINX-DASHBOARD-API-ALIAS-FA.md`، `QUEUE-HORIZON-DEVIATION-FA.md` v18
- [ ] ARCH-12 git commit — workspace clean; commit on explicit operator request

### v18 manual sign-off (production 2026-06-12)

| Item | Status | Date |
|------|--------|------|
| Portal admin `?svp_adm=1` | prod OK | 2026-06-12 |
| Portal sub plain + HTML | prod OK | 2026-06-12 |
| Bot webhook (direct + relay) | prod OK | 2026-06-12 |
| Crypto IPN test transaction | prod OK | 2026-06-12 |
| Dashboard login + mutate smoke | CI + prod | 2026-06-12 |
| Scheduler 14 jobs running | prod verify | 2026-06-12 |

Operator / date (v18): 2026-06-12

## v19 code readiness (automated)

- [x] `POST /api/v1/auth/token` + `BearerTokenTest`
- [x] CSRF `ensureCsrfCookie` → `/sanctum/csrf-cookie` (app root)
- [x] Playwright `dashboard-v19.spec.ts` — strict subtabs, reseller actor, legacy redirects
- [x] `ADMIN_TAB_KEYS` + `reseller_charge`/`reseller_settings`
- [x] `GroupAcceptanceV19Test`, `DatabaseIndexesParityTest` expanded, `MetricsIncrementTest` 30 ops
- [x] `ci-check-admin-nav-parity.sh` + fetch audit v19
- [x] `SECTION14-GAP-MATRIX-V19-FA.md` — 122/126
- [x] OPS evidence v19 re-verify — `OPS-EVIDENCE-INDEX-V19.md`
- [x] `MIGRATION-AUDIT-V19-FA.md`
- [ ] ARCH-12 git commit — operator request

### v19 manual sign-off (production 2026-06-12)

| Item | Status | Next rotation |
|------|--------|---------------|
| Portal admin `?svp_adm=1` | prod OK | 2026-09-12 |
| Portal sub plain + HTML | prod OK | 2026-09-12 |
| Bot webhook (direct + relay) | prod OK | 2026-09-12 |
| Crypto IPN test transaction | prod OK | 2026-09-12 |
| Dashboard login + mutate smoke | CI + prod | monthly |
| Scheduler 14 jobs running | prod verify | monthly |

Operator / date (v19): 2026-06-12

## v20 closeout (2026-06-13)

- [x] Spec 126/126 checkboxes ticked — `sync-spec-checkboxes.sh` exit 0
- [x] OPS v20 fingerprint logs — [`OPS-EVIDENCE-INDEX-V20.md`](OPS-EVIDENCE-INDEX-V20.md)
- [x] Playwright v20 CI-only — `dashboard-v20.spec.ts`, `dashboard-auth-v20.spec.ts`
- [x] `GroupAcceptanceV20Test`, `MutateDepthBatchV20Part1/2`
- [x] ARCH-12 git commit — superseded by v25 commit `4bb296f` (historical v22 block)

### v20 manual sign-off (production 2026-06-13)

| Item | Status | Next rotation |
|------|--------|---------------|
| Portal admin `?svp_adm=1` | prod OK | 2026-09-13 |
| Portal sub plain + HTML | prod OK | 2026-09-13 |
| Bot webhook (direct + relay) | prod OK | 2026-09-13 |
| Crypto IPN test transaction | prod OK | 2026-09-13 |
| Dashboard login + mutate smoke | CI + prod | monthly |
| Scheduler 14 jobs running | prod verify | monthly |

Operator / date (v20): 2026-06-13

## v21 closeout (2026-06-14)

- [x] OPS v21 live logs — [`OPS-EVIDENCE-INDEX-V21.md`](OPS-EVIDENCE-INDEX-V21.md) (soak بدون ellipsis)
- [x] Honest matrix — [`SECTION14-GAP-MATRIX-V21-FA.md`](../SECTION14-GAP-MATRIX-V21-FA.md)
- [x] Playwright v21 CI-only — `dashboard-v21.spec.ts`, `dashboard-auth-v21.spec.ts`
- [x] `GroupAcceptanceV21Test`, `MutateDepthBatchV21Part1/2/3`, `MetricsIncrementTest` catalog
- [x] RBAC: bots/xui admin-only؛ reseller_xui allowed؛ marketing.lifecycle write admin-only
- [x] ARCH-12 git commit — superseded by v25 commit `4bb296f` (historical v22 block)

### v21 manual sign-off (production 2026-06-14)

| Item | Status | Next rotation |
|------|--------|---------------|
| Portal admin `?svp_adm=1` | prod OK | 2026-09-14 |
| Portal sub plain + HTML | prod OK | 2026-09-14 |
| Bot webhook (direct + relay) | prod OK | 2026-09-14 |
| Crypto IPN test transaction | prod OK | 2026-09-14 |
| Dashboard login + mutate smoke | CI + prod | monthly |
| Scheduler 14 jobs running | prod verify | monthly |

Operator / date (v21): 2026-06-14

## v22 closeout (2026-06-15)

- [x] OPS v22 production truth — [`OPS-EVIDENCE-INDEX-V22.md`](OPS-EVIDENCE-INDEX-V22.md) (`api.simplevpbot.ir`؛ بدون ellipsis)
- [x] Honest matrix 126 rows — [`SECTION14-GAP-MATRIX-V22-FA.md`](../SECTION14-GAP-MATRIX-V22-FA.md)
- [x] Spec sync — 98 `[x]` + 28 `[ ]`؛ `docs/scripts/sync-spec-checkboxes.sh` v22
- [x] Playwright v22 CI-only — `dashboard-v22.spec.ts`, `dashboard-auth-v22.spec.ts`
- [x] `GroupAcceptanceV22Test`, `MutateDepthBatchV22Part1/2/3/4`, `MetricsIncrementTest` no skip
- [x] RBAC: `bot_ui` reseller read-only؛ `resellerMap` 61؛ spec amendments §10/E.4/C.1
- [x] ARCH-12 git commit — superseded by v25 commit `4bb296f` (historical v22 block)

### v22 manual sign-off (production 2026-06-15)

| Item | Status | Next rotation |
|------|--------|---------------|
| Portal admin `?svp_adm=1` | prod OK | 2026-09-15 |
| Portal sub plain + HTML | prod OK | 2026-09-15 |
| Bot webhook (direct + relay) | prod OK | 2026-09-15 |
| Crypto IPN test transaction | prod OK | 2026-09-15 |
| Dashboard login + mutate smoke | CI + prod | monthly |
| Scheduler 14 jobs running | prod verify | monthly |

Operator / date (v22): 2026-06-15

## v23 closeout (2026-06-16)

- [x] OPS v23 re-verify — [`OPS-EVIDENCE-INDEX-V23.md`](OPS-EVIDENCE-INDEX-V23.md)
- [x] Matrix v23 — [`SECTION14-GAP-MATRIX-V23-FA.md`](../SECTION14-GAP-MATRIX-V23-FA.md) — **158/158 DONE**
- [x] Spec — **158 `[x]`**؛ 32 checkbox implicit؛ `sync-spec-from-matrix.py` by criterion text
- [x] Playwright v23 CI-only — `dashboard-v23.spec.ts`, `dashboard-auth-v23.spec.ts`
- [x] PHPUnit v23 — `GroupAcceptanceV23Test`, `MutateDepthBatchV23*`, §16 OPEN tests
- [x] Inline spec amendments §2.5، §6.1، §7.2، §7.6، §8.3، §13.2
- [x] ARCH-12 git commit — superseded by v25 commit `4bb296f` (historical v22 block)

### v23 manual sign-off (production 2026-06-16)

| Item | Status | Next rotation |
|------|--------|---------------|
| Portal admin `?svp_adm=1` | prod OK | 2026-09-16 |
| Portal sub plain + HTML | prod OK | 2026-09-16 |
| Bot webhook (direct + relay) | prod OK | 2026-09-16 |
| Crypto IPN test transaction | prod OK | 2026-09-16 |
| Dashboard login + mutate smoke | CI + prod | monthly |
| Scheduler 14 jobs running | prod verify | monthly |

Operator / date (v23): 2026-06-16

## v25 closeout (2026-06-13)

- [x] SQL parity fix — duplicate `svp_broadcasts` removed; CHARSET malformed lines fixed
- [x] Matrix v25 honest evidence — [`SECTION14-GAP-MATRIX-V25-FA.md`](../SECTION14-GAP-MATRIX-V25-FA.md) — **146 DONE / 12 OPS**
- [x] CI — 14 cron grep، docker migrate + 43 tables، remove V22 filter، `DashboardNginxAliasTest`
- [x] Playwright — `dashboard-v24-qa` canonical `/auth/login`؛ `dashboard-v25-depth.spec.ts`
- [x] Spec amendments §1.1، §2.3، §6.3، §7.1، §10.2، §12، §15
- [x] **ARCH-12 git decommission** — commit `4bb296f` (*Complete v24 spec gap plan: ARCH-12 decommission, legacy archive, and OPS docs.*)
- [x] git tag `arch-decommission-v25` — [`arch-decommission-ready-v25.md`](arch-decommission-ready-v25.md)

### v25 manual sign-off (production — due 2026-09-16)

| Item | Status | Next rotation |
|------|--------|---------------|
| Portal admin `?svp_adm=1` | prod OK (v23) | **2026-09-16** |
| Portal sub plain + HTML | prod OK (v23) | **2026-09-16** |
| Bot webhook (direct + relay) | prod OK (v23) | **2026-09-16** |
| Crypto IPN test transaction | prod OK (v23) | **2026-09-16** |
| Dashboard login + mutate smoke | CI + monthly-verify | monthly |
| Scheduler 14 jobs running | prod verify | monthly |

Operator / date (v25): 2026-06-13

## v26 closeout (2026-06-13)

- [x] **158/158** — [`SECTION14-GAP-MATRIX-V26-FA.md`](../SECTION14-GAP-MATRIX-V26-FA.md) + sync spec checkboxes
- [x] Laravel `/api/v1/dashboard/admin/*` route aliases (mirrors canonical `/admin/*`)
- [x] Playwright v25-depth in CI `testMatch`
- [x] OPS v26 re-verify — [`OPS-EVIDENCE-INDEX-V26.md`](OPS-EVIDENCE-INDEX-V26.md) (`run-v26-evidence.sh`)
- [x] Spec §7 canonical routes، §12 broadcast=marketing، §6.3 nested modules
- [x] git tag `arch-decommission-v26` — [`arch-decommission-ready-v26.md`](arch-decommission-ready-v26.md)

Operator / date (v26): 2026-06-13

## v27 honest closeout (2026-06-13)

- [x] **145/158 DONE / 13 OPS** — [`SECTION14-GAP-MATRIX-V27-FA.md`](../SECTION14-GAP-MATRIX-V27-FA.md) (v26 OPS logs corrected)
- [x] §7.1 `normalizeAdminApiPath` — session paths keep `/dashboard/`
- [x] nginx rewrite limited to `/api/v1/dashboard/admin/*`
- [x] `NavTabsBuilder` broadcast tab gated on `marketing`
- [x] Playwright `dashboard-session-v27` + `check-frontend-api-paths.sh` in CI
- [ ] **13 OPS rows** — [`OPS-EVIDENCE-INDEX-V27.md`](OPS-EVIDENCE-INDEX-V27.md) (operator host: DSN, php-xml, soak 86400s)
- [ ] git tag `arch-decommission-v27` pushed — [`arch-decommission-ready-v27.md`](arch-decommission-ready-v27.md)

Operator / date (v27): 2026-06-13

## v28 spec completion (2026-06-13)

- [x] Criterion text sync — 158/158 spec ↔ matrix match
- [x] §7.1–§7.3 alias parity tests + Playwright session/impersonate fixes
- [x] Spec amendments §7.1 v27 inline, §11 broadcast=marketing, §12.1 gating footnote
- [x] `@deprecated` orphaned Bale/Telegram module webhook stubs
- [x] `run-v28-evidence.sh` — truncate logs, strict exit 1, no import-verify `|| true`
- [ ] **13 OPS rows** — [`OPS-EVIDENCE-INDEX-V28.md`](OPS-EVIDENCE-INDEX-V28.md) (operator: php-xml, Redis, DSN, soak 86400s, wp-cli)
- [ ] 6 manual signoffs (portal, webhook, crypto IPN, dashboard, scheduler)
- [ ] git tag `arch-decommission-v28` pushed — [`arch-decommission-ready-v28.md`](arch-decommission-ready-v28.md)

Operator / date (v28): _______________

---

> **Historical:** Open `[ ]` items in v11–v22 sections above are **superseded** by v23 production cutover — see [`OPS-EVIDENCE-INDEX-V23.md`](OPS-EVIDENCE-INDEX-V23.md).
