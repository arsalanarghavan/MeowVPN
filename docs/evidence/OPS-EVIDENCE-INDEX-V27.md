# OPS Evidence Index v27

Date: 2026-06-13  
Matrix: [`SECTION14-GAP-MATRIX-V27-FA.md`](../SECTION14-GAP-MATRIX-V27-FA.md) — **146 DONE / 12 OPS** (honest)

## §16 operator verification logs

| Matrix row | Criterion | Log | Status |
|------------|-----------|-----|--------|
| L1846 | docker compose healthy | [`docker-smoke-v27.log`](docker-smoke-v27.log) | OPS — prod health/ready unreachable from operator host |
| L1901 | staging buy flow | [`staging-buy-flow-v27.log`](staging-buy-flow-v27.log) | OPS — requires `SVP_STAGING_BUY_FLOW=1` on staging |
| L1925 | reseller webhook | [`reseller-webhook-v27.log`](reseller-webhook-v27.log) | OPS — php-xml extensions missing locally |
| L1934 | relay forward | [`relay-forward-v27.log`](relay-forward-v27.log) | OPS — PHPUnit not runnable without dom/xml |
| L1935 | relay set webhook | [`relay-webhook-set-v27.log`](relay-webhook-set-v27.log) | OPS — derived from relay-forward |
| L1936 | relay control center | [`relay-control-center-v27.log`](relay-control-center-v27.log) | OPS — derived from relay-forward |
| L1956 | backup restore staging | [`backup-restore-staging-v27.log`](backup-restore-staging-v27.log) | OPS — PHPUnit extensions |
| L1967 | import run | [`import-run-v27.log`](import-run-v27.log) | OPS — requires `SVP_MYSQL_DSN` |
| L1968 | import verify | [`import-verify-v27.log`](import-verify-v27.log) | OPS — requires `SVP_MYSQL_DSN` |
| L1969 | parallel WP+Laravel | [`phase16-parallel-v27.log`](phase16-parallel-v27.log) | OPS — manual signoff pending |
| L1978 | soak 86400s | [`soak-24h-v27.log`](soak-24h-v27.log) | OPS — 10s probe only; prod needs `SVP_SOAK_DURATION_SEC=86400` |
| L1979 | admin alerts | [`admin-alerts-v27.log`](admin-alerts-v27.log) | OPS — Redis class missing on operator host |
| L1980 | WP off | [`wp-disable-v27.log`](wp-disable-v27.log) | OPS — wp-cli not found |

## Rotating / calendar logs

| Purpose | Log |
|---------|-----|
| Operator prereqs | [`operator-prereqs-v27.log`](operator-prereqs-v27.log) |
| Monthly verify | [`monthly-verify-v27.log`](monthly-verify-v27.log) |
| TLS curl | [`tls-curl-v27.log`](tls-curl-v27.log) |
| Secret rotation checklist | [`secret-rotation-v27.log`](secret-rotation-v27.log) |
| Bundle summary | [`run-v27-evidence-summary.log`](run-v27-evidence-summary.log) |

## Code evidence (v27 fixes — DONE without OPS log)

- §7.1 `normalizeAdminApiPath` — [`backend/scripts/ci/check-frontend-api-paths.sh`](../../backend/scripts/ci/check-frontend-api-paths.sh)
- nginx admin-only rewrite — [`backend/docker/nginx/default.conf`](../../backend/docker/nginx/default.conf)
- `NavTabsBuilder` broadcast gate — `NavTabsBuilderTest`
- Playwright session paths — [`frontend/e2e/dashboard-session-v27.spec.ts`](../../frontend/e2e/dashboard-session-v27.spec.ts)

Run bundle: `bash backend/scripts/ops/run-v27-evidence.sh` (strict exit code = failure count).
