# Cutover evidence logs

Place operator-generated artifacts here during staging/production cutover.

| File pattern | Source |
|--------------|--------|
| `import-verify-YYYY-MM-DD.log` | `SVP_MYSQL_DSN=... backend/scripts/ops/import-verify.sh` |
| `cutover-preflight-YYYY-MM-DD.log` | `backend/scripts/ops/cutover-preflight.sh` |
| `soak-24h-YYYY-MM-DD.log` | `SVP_SOAK_DURATION_SEC=86400 backend/scripts/ops/soak-24h.sh` |
| `soak-nightly-YYYY-MM-DD.log` | GitHub Actions `nightly-soak.yml` artifact |
| `rollback-drill.log` | `backend/scripts/ops/rollback-drill.sh` |

CI runs short soak/load/preflight smoke automatically; full 24h soak requires staging `SVP_BASE_URL`.
