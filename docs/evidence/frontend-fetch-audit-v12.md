# Frontend API Path Audit v12

## `normalizeAdminApiPath`

All dashboard fetches use `frontend/src/lib/api-base.ts`:

- `/dashboard/admin/*` → `/api/v1/admin/*`
- `/dashboard/impersonate/*` → `/api/v1/impersonate/*`
- `/dashboard/ui-preferences` → `/api/v1/dashboard/ui-preferences`

Verified call sites: `dash-admin-mutate.ts`, `app-sidebar.tsx`, `sidebar-search.tsx`, `dashboard-user-detail-admin.tsx`, `impersonation-banner.tsx`, `dash-ui-preferences.ts`.

## `FEATURE_TAB_MAP` parity (`admin-nav.ts`)

| tabKey | feature key |
|--------|-------------|
| xui_panels, configs, unit_economics | xui_panel |
| backup | backup |
| marketing_lifecycle | marketing |
| resellers, reseller_* | reseller |
| reseller_xui_panels | reseller + xui_panel (special) |
| l2tp_servers | l2tp |
| proxy | telegram |
| cards | crypto |
| bots, bot_ui, texts, plan_cats | telegram OR bale |

Matches `EnsureAdminStateModule` gates in backend.
