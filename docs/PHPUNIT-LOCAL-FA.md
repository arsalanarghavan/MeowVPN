# PHPUnit local — CI parity (v21)

Install: `sudo apt install php8.3-xml php8.3-mbstring php8.3-sqlite3`

```bash
cd backend && php artisan test
bash scripts/ci/ci-check-frontend-fetch.sh
bash scripts/ci/ci-check-admin-nav-parity.sh
bash ../docs/scripts/sync-spec-checkboxes.sh
```

CI uses `shivammathur/setup-php` with `dom, xml, xmlwriter`. Playwright CI runs `dashboard-v21` + `dashboard-auth-v21` only.

Depth: `MutateDepthBatchV21Part1/2/3` (44 ops). Metrics: `MetricsIncrementTest` uses full `MutateOpCatalog` (skips non-ok fixture ops).

Operator / date: 2026-06-14
