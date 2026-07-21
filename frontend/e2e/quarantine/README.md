# Quarantined Playwright specs (Vite-era)

These specs target the **legacy Vite SPA** (`/dashboard?tab=…`, `data-testid=dash-tab-*`, `frontend/src/components/dashboard-*-admin.tsx`). They are **not** run in CI and must not be cited as Next App Router evidence.

**Runtime dashboard:** Next App Router under `frontend/src/app/[locale]/dashboard/` with `*-admin-client.tsx` components.

**Active Next e2e:** `shell.spec.ts`, `shell-depth.spec.ts`, `admin-tabs.spec.ts`, `admin-mutate.spec.ts`, `admin-depth.spec.ts`, `residual-closeout-p2.spec.ts`, `residual-closeout-wave-d.spec.ts`.

Quarantined files:

- `dashboard-v23.spec.ts`, `dashboard-auth-v23.spec.ts`
- `dashboard-v24-qa.spec.ts`, `dashboard-v25-depth.spec.ts`
- `dashboard-session-v27.spec.ts`

Older history: `frontend/e2e/archive/` (v14–v22).
