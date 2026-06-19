# OPS evidence index v26

Fresh operator re-verify (v26 bundle: `backend/scripts/ops/run-v26-evidence.sh`).

## Production / staging logs

| File | Phase / criterion |
|------|-------------------|
| [`docker-smoke-v26.log`](docker-smoke-v26.log) | L1846 docker compose healthy |
| [`staging-buy-flow-v26.log`](staging-buy-flow-v26.log) | L1901 buy flow staging |
| [`reseller-webhook-v26.log`](reseller-webhook-v26.log) | L1925 reseller bot webhook |
| [`relay-forward-v26.log`](relay-forward-v26.log) | L1934 relay sync |
| [`relay-webhook-set-v26.log`](relay-webhook-set-v26.log) | L1935 set webhook via relay |
| [`relay-control-center-v26.log`](relay-control-center-v26.log) | L1936 control center |
| [`backup-restore-staging-v26.log`](backup-restore-staging-v26.log) | L1956 backup restore |
| [`import-run-v26.log`](import-run-v26.log) | L1967 import |
| [`import-verify-v26.log`](import-verify-v26.log) | L1968 row counts |
| [`phase16-parallel-v26.log`](phase16-parallel-v26.log) | L1969 parallel WP+Laravel |
| [`soak-24h-v26.log`](soak-24h-v26.log) | L1978 soak 24h |
| [`admin-alerts-v26.log`](admin-alerts-v26.log) | L1979 alerting panel down |
| [`wp-disable-v26.log`](wp-disable-v26.log) | L1980 WP off |
| [`monthly-verify-v26.log`](monthly-verify-v26.log) | monthly verify |
| [`tls-curl-v26.log`](tls-curl-v26.log) | TLS monthly |
| [`secret-rotation-v26.log`](secret-rotation-v26.log) | secret rotation checklist |

Operator / date: 2026-06-13
