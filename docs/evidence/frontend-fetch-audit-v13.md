# Frontend Fetch Audit v13

All dashboard API paths use `normalizeAdminApiPath` except intentional bootstrap:

- `GET /api/v1/bootstrap` — no prefix (direct)
- `GET /api/v1/admin/state` — via `App.tsx` / hooks using normalized paths

Verified files: `dash-admin-mutate.ts`, `app-sidebar.tsx`, `sidebar-search.tsx`, `dashboard-user-detail-admin.tsx`, `impersonation-banner.tsx`, `dash-ui-preferences.ts`.

`FEATURE_TAB_MAP` in [`admin-nav.ts`](../../frontend/src/config/admin-nav.ts): xui_panel, backup, marketing, reseller, l2tp, telegram, crypto parity with `EnsureAdminStateModule`.
