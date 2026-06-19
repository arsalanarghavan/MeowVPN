# Import Checklist v19 (§17 #1–12) — Production Truth

| # | Step | Evidence |
|---|------|----------|
| 1–2 | DSN + mysqldump | [`mysql-dump-prod-v19.log`](mysql-dump-prod-v19.log) |
| 3–4 | import-run | [`import-run-2026-06-12-prod-v19.log`](import-run-2026-06-12-prod-v19.log) |
| 5–6 | verify per-table | [`import-verify-2026-06-12-prod-v19.log`](import-verify-2026-06-12-prod-v19.log) |
| 7 | post-import | [`post-import-ops-2026-06-12-prod-v19.log`](post-import-ops-2026-06-12-prod-v19.log) |
| 8–10 | flags | [`import-flags-2026-06-12-prod-v19.log`](import-flags-2026-06-12-prod-v19.log) |
| 11–12 | rollback + preflight | [`rollback-drill-prod-v19.log`](rollback-drill-prod-v19.log), [`cutover-preflight-2026-06-12-prod-v19.log`](cutover-preflight-2026-06-12-prod-v19.log) |

Operator / date: 2026-06-12 (v19 re-verify)
