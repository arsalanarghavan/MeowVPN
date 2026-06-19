# Rollback Drill Checklist v13 (§17 #11–12)

| Step | Command | Evidence |
|------|---------|----------|
| Rollback drill | `backend/scripts/ops/rollback-drill.sh` | `rollback-drill.log` |
| Cutover runbook | `SVP_MYSQL_DSN=... staging-cutover-runbook.sh` | exit 0 |
| Preflight | `SVP_BASE_URL=... cutover-preflight.sh` | `cutover-preflight-*.log` |

CI: `cutover-preflight.sh` در job `backend`.
