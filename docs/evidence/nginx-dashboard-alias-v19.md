# nginx dashboard API alias — optional docker smoke (v19)

See [`NGINX-DASHBOARD-API-ALIAS-FA.md`](NGINX-DASHBOARD-API-ALIAS-FA.md).

Canonical API prefix: `/api/v1/admin/*`. Legacy `/api/v1/dashboard/admin/*` is **not** configured in [`backend/docker/nginx/default.conf`](../backend/docker/nginx/default.conf).

Optional rewrite documented for external clients; SPA uses `normalizeAdminApiPath`.

Operator / date: 2026-06-12
