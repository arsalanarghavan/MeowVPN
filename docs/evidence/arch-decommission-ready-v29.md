# ARCH-12 decommission ready — v29

Date: 2026-06-14  
Matrix: [`SECTION14-GAP-MATRIX-V28-FA.md`](../SECTION14-GAP-MATRIX-V28-FA.md)

## v29 deliverables (DONE)

- Removed deprecated Telegram/Bale webhook stubs and legacy jobs
- Dropped orphan Laravel `users` table; runtime auth = `dashboard_users`
- Mutate catalog 139 ops (removed `link_wp_user`, `reseller_bot_secret_rotate`)
- `PortalPagesBuilder` Laravel-native pages + `portalPages` in admin state
- `InboundAutolinkService` with fuzzy remark matching
- Factories: `SvpPlanFactory`, `SvpCardFactory`, `SvpReceiptFactory`
- Docs: [`WP-LEGACY-COLUMNS-FA.md`](../WP-LEGACY-COLUMNS-FA.md)
- OPS: [`run-v28-evidence-complete.sh`](../../backend/scripts/ops/run-v28-evidence-complete.sh)
- Archive cleanup: removed broken WP integration tests + deprecated i18n scripts

## Close 158/158 OPS (operator host)

```bash
export SVP_BASE_URL=https://api.example.com   # or http://127.0.0.1:8080
bash backend/scripts/ops/run-v28-evidence-complete.sh
```

Prerequisites: Docker, php-xml, Redis ext — see [`ensure-prereqs.sh`](../../backend/scripts/ops/ensure-prereqs.sh)

## Git / release

```bash
git tag -a arch-decommission-v29 -m "ARCH-12 v29 WP migration cleanup"
gh pr create --base main --head v29-spec-completion
```

Operator / date: 2026-06-14
