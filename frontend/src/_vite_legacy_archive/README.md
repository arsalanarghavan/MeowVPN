# Vite legacy SPA archive

Dead entrypoints and dashboard tab components that were only reachable from
`App.tsx` / `main.tsx` / `dashboard-admin-view.tsx` after the Next.js App Router
migration. Kept for reference — not imported by the live Next app.

Do not re-import these into `src/app` or `src/components/admin` without
reconciling against the `*-admin-client.tsx` implementations.
