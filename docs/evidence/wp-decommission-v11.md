# WP Decommission Evidence v11

| Step | Status | Notes |
|------|--------|-------|
| Archive branch `archive/wp-plugin` | DONE | local branch exists |
| `includes/` removed from main | DONE | `CONFIRM=1 remove-includes-from-main.sh` |
| `simplevpbot.php` removed | DONE | git rm |
| Root WP tests removed | DONE | `tests/*.php` (archive in `tests/wp-plugin-archive/`) |
| wp-disable-staging on target host | OPEN | `wp-disable-staging.sh` |
| 30-day snapshot retention | OPEN | operator backup policy |
| 48h post-cutover monitoring | OPEN | `CUTOVER-SIGNOFF-FA.md` |

Rollback: `backend/scripts/ops/rollback-drill.sh` → `rollback-drill.log`
