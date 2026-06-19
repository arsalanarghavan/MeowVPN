# ARCH-12 decommission ready — v25

## Evidence

| Item | Reference |
|------|-----------|
| WP root removed from main | commit `4bb296f` — legacy → `archive/wp-plugin-root/` |
| `includes/` absent | `archive/wp-plugin-root/includes/` snapshot only |
| Frontend zero `wp-json` | [`frontend-appendix-b-v24.md`](frontend-appendix-b-v24.md) |
| Production WP off | [`soak-24h-2026-06-16-prod-v23.log`](soak-24h-2026-06-16-prod-v23.log) + [`wp-post-cutover-v23.md`](wp-post-cutover-v23.md) |
| Matrix v25 | [`SECTION14-GAP-MATRIX-V25-FA.md`](../SECTION14-GAP-MATRIX-V25-FA.md) — 146 DONE / 12 OPS |
| SQL parity | `svp_wp_parity.sql` deduped؛ `list_svp_tables.php` → 43 tables |
| CI docker-smoke | `migrate --force` + `ParityMigrationMysqlTest` |

## Git tag

```bash
git tag -a arch-decommission-v25 -m "ARCH-12 WP decommission complete (v25 audit)"
```

## Remaining OPS (not code)

12 matrix rows marked **OPS** — quarterly sign-off **2026-09-16** per [`OPS-MAINTENANCE-CALENDAR-V24-FA.md`](../OPS-MAINTENANCE-CALENDAR-V24-FA.md).

Operator / date: 2026-06-13
