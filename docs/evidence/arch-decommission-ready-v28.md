# ARCH-12 decommission ready — v28

Date: 2026-06-13  
Matrix: [`SECTION14-GAP-MATRIX-V28-FA.md`](../SECTION14-GAP-MATRIX-V28-FA.md) — target **158/158** when `*-v28.log` pass

## v28 code deliverables (DONE)

- Criterion text sync — spec ↔ matrix 158/158 text match (`sync-spec-from-matrix.py`)
- [`ApiRouteAuditTest`](../../backend/tests/Feature/Http/ApiRouteAuditTest.php) — full §7.2/§7.3 alias parity + dashboard impersonate
- [`DashboardNginxAliasTest`](../../backend/tests/Feature/Http/DashboardNginxAliasTest.php) — session + impersonate paths
- [`NavTabsBuilderTest`](../../backend/tests/Unit/NavTabsBuilderTest.php) — `marketing_lifecycle` gate
- Playwright cookie sharing + v25 impersonate path fix
- [`backend/scripts/e2e/e2e-dashboard-api.sh`](../../backend/scripts/e2e/e2e-dashboard-api.sh) — ui-preferences + impersonate
- [`backend/scripts/ci/check-frontend-api-paths.sh`](../../backend/scripts/ci/check-frontend-api-paths.sh) — canonical `/admin/*` edge cases
- CI docker-smoke — §7.1 `dashboard/persona` curl + `docker-smoke-v28.log`
- Spec §7.1 v27 amendment inline, §7.2 controllers/auth, §11 broadcast=marketing, §12.1 gating footnote
- `@deprecated` Bale/Telegram module `WebhookController` + `ProcessBaleUpdateJob` stub
- [`run-v28-evidence.sh`](../../backend/scripts/ops/run-v28-evidence.sh) — strict, truncated logs

## OPS pending (until operator host)

See [`OPS-EVIDENCE-INDEX-V28.md`](OPS-EVIDENCE-INDEX-V28.md). Prerequisites:

- `php-xml`, Redis ext, `gh`, `wp-cli`
- Unset `https_proxy` for prod TLS curl
- `SVP_MYSQL_DSN`, `SVP_STAGING_BUY_FLOW=1`, `SVP_SOAK_DURATION_SEC=86400`

## Git / release

```bash
git checkout v28-spec-completion
git push -u origin v28-spec-completion --tags
gh pr create --base main --head v28-spec-completion
git tag -a arch-decommission-v28 -m "ARCH-12 v28 spec completion"
```
