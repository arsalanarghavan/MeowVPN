# ARCH-12 decommission ready — v27

Date: 2026-06-13  
Matrix: [`SECTION14-GAP-MATRIX-V27-FA.md`](../SECTION14-GAP-MATRIX-V27-FA.md) — **145/158 DONE**, **13 OPS honest**

## v27 code deliverables (DONE)

- [`frontend/src/lib/api-base.ts`](../../frontend/src/lib/api-base.ts) — §7.1 session paths keep `/dashboard/`
- [`backend/docker/nginx/default.conf`](../../backend/docker/nginx/default.conf) — admin-only alias rewrite
- [`backend/app/Services/NavTabsBuilder.php`](../../backend/app/Services/NavTabsBuilder.php) — broadcast tab gated on marketing
- [`backend/scripts/ci/check-frontend-api-paths.sh`](../../backend/scripts/ci/check-frontend-api-paths.sh) — CI path parity guard
- [`frontend/e2e/dashboard-session-v27.spec.ts`](../../frontend/e2e/dashboard-session-v27.spec.ts) — Playwright §7.1 smoke
- Expanded `ApiRouteAuditTest`, `DashboardNginxAliasTest`, docker-smoke canonical+alias curl

## OPS pending (13 rows)

See [`OPS-EVIDENCE-INDEX-V27.md`](OPS-EVIDENCE-INDEX-V27.md). Re-run on operator host with:

- `php-xml` extensions, Redis, `gh`
- `SVP_MYSQL_DSN`, `SVP_STAGING_BUY_FLOW=1`
- `SVP_SOAK_DURATION_SEC=86400` for L1978

## Git / release

```bash
git checkout v27-spec-completion   # or merge v26 + v27 commits
git push -u origin v27-spec-completion --tags
gh pr create --base main --head v27-spec-completion
git tag -a arch-decommission-v27 -m "ARCH-12 v27 honest closeout"
```

Tag `arch-decommission-v27` created locally after merge.
