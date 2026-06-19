# Phase 16 — Parallel WP + Laravel Staging v12

Runbook window: **7 days** before DNS cutover.

| Day | WP stack | Laravel stack |
|-----|----------|---------------|
| D-7 | webhooks on WP | import + verify only |
| D-5 | read-only WP DB | workers + cron active |
| D-3 | compare receipt counts | `import-verify.sh` diff = 0 |
| D-1 | freeze WP writes | final mysqldump + import |
| D0 | DNS → Laravel | `staging-cutover-runbook.sh` |

Evidence: `import-verify-YYYY-MM-DD.log`, `soak-24h-YYYY-MM-DD.log`, `CUTOVER-SIGNOFF-FA.md`.
