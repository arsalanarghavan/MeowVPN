# WP Post-Cutover v12

| Step | Status | Notes |
|------|--------|-------|
| `includes/` removed from main | DONE (v11 staged) | `archive/wp-plugin` branch |
| `wp-disable-staging.sh` on host | OPEN | `WP_PATH=... CONFIRM=1 backend/scripts/ops/wp-disable-staging.sh` |
| 30-day WP snapshot retention | OPEN | policy ticket |
| 48h post-cutover monitoring | OPEN | error rate, webhook, cron |
| Rollback drill (live) | OPEN | `rollback-drill.sh` → `rollback-drill.log` |
| Phase 16 parallel WP+Laravel | OPEN | run both stacks 1 week before DNS |

Repo readiness: `WP-DECOMMISSION-FA.md`, `remove-includes-from-main.sh`.
