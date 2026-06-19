# OPS Evidence Index v28

Date: 2026-06-14  
Matrix: [`SECTION14-GAP-MATRIX-V28-FA.md`](../SECTION14-GAP-MATRIX-V28-FA.md) — **145 DONE / 13 OPS** until operator re-run

**v29 one-shot:** `bash backend/scripts/ops/run-v28-evidence-complete.sh` (Docker + php-xml host)

## §16 operator verification logs

| Matrix row | Criterion | Log | Status |
|------------|-----------|-----|--------|
| L1846 | docker compose healthy | [`docker-smoke-v28.log`](docker-smoke-v28.log) | OPS — prod health/ready or operator proxy |
| L1901 | staging buy flow | [`staging-buy-flow-v28.log`](staging-buy-flow-v28.log) | OPS — requires `SVP_STAGING_BUY_FLOW=1` |
| L1925 | reseller webhook | [`reseller-webhook-v28.log`](reseller-webhook-v28.log) | OPS — php-xml extensions |
| L1934 | relay forward | [`relay-forward-v28.log`](relay-forward-v28.log) | OPS — PHPUnit dom/xml |
| L1935 | relay set webhook | [`relay-webhook-set-v28.log`](relay-webhook-set-v28.log) | OPS — derived from relay-forward |
| L1936 | relay control center | [`relay-control-center-v28.log`](relay-control-center-v28.log) | OPS — derived from relay-forward |
| L1956 | backup restore staging | [`backup-restore-staging-v28.log`](backup-restore-staging-v28.log) | OPS — PHPUnit extensions |
| L1967 | import run | [`import-run-v28.log`](import-run-v28.log) | OPS — requires `SVP_MYSQL_DSN` |
| L1968 | import verify | [`import-verify-v28.log`](import-verify-v28.log) | OPS — requires `SVP_MYSQL_DSN` |
| L1969 | parallel WP+Laravel | [`phase16-parallel-v28.log`](phase16-parallel-v28.log) | OPS — manual signoff pending |
| L1978 | soak 86400s | [`soak-24h-v28.log`](soak-24h-v28.log) | OPS — `SVP_SOAK_DURATION_SEC=86400` |
| L1979 | admin alerts | [`admin-alerts-v28.log`](admin-alerts-v28.log) | OPS — Redis ext on operator host |
| L1980 | WP off | [`wp-disable-v28.log`](wp-disable-v28.log) | OPS — wp-cli |

## Env checklist (operator host)

| Variable | Purpose |
|----------|---------|
| `SVP_MYSQL_DSN` | import-run + import-verify |
| `SVP_STAGING_BUY_FLOW=1` | staging buy flow e2e |
| `SVP_SOAK_DURATION_SEC=86400` | L1978 24h soak |
| `SVP_BASE_URL` | prod/staging API base (unset `https_proxy` for TLS) |
| Relay credentials | reseller/relay PHPUnit + live forward |

## Rotating / calendar logs

| Purpose | Log |
|---------|-----|
| Operator prereqs | [`operator-prereqs-v28.log`](operator-prereqs-v28.log) |
| Monthly verify | [`monthly-verify-v28.log`](monthly-verify-v28.log) |
| TLS curl | [`tls-curl-v28.log`](tls-curl-v28.log) |
| Secret rotation checklist | [`secret-rotation-v28.log`](secret-rotation-v28.log) |
| Bundle summary | [`run-v28-evidence-summary.log`](run-v28-evidence-summary.log) |

## Code evidence (v28 — DONE without OPS log)

- §7.1 path parity — [`backend/scripts/ci/check-frontend-api-paths.sh`](../../backend/scripts/ci/check-frontend-api-paths.sh) (9 cases)
- Full alias parity — `ApiRouteAuditTest`, `DashboardNginxAliasTest`
- Playwright session cookie sharing — [`dashboard-session-v27.spec.ts`](../../frontend/e2e/dashboard-session-v27.spec.ts)
- v25 impersonate fix — `targetSvpUserId` + `/dashboard/impersonate/start`
- Strict evidence — [`run-v28-evidence.sh`](../../backend/scripts/ops/run-v28-evidence.sh) (truncate logs, exit 1)

Re-run: `bash backend/scripts/ops/run-v28-evidence.sh`
