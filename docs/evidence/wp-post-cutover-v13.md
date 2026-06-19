# WP Post-Cutover v13

| Step | Action | Evidence |
|------|--------|----------|
| Parallel WP+Laravel 7d | [`phase16-parallel-v13.md`](phase16-parallel-v13.md) | runbook log |
| `wp-disable-staging.sh` | `WP_PATH=... CONFIRM=1` | script stdout |
| 48h monitor | error rate, webhooks, cron | ticket |
| 30-day WP snapshot | retention policy | backup store |
| `includes/` commit | ops sign-off only | git (see ARCH-12) |
