# nginx — dashboard API alias (v27)

## Spec §7

- **§7.2/§7.3 admin:** optional prefix `/api/v1/dashboard/admin/*` as alias to `/api/v1/admin/*`
- **§7.1 session:** `/api/v1/dashboard/persona`, `ui-preferences`, `impersonate/*` — **no rewrite** (Laravel registers these paths directly)

## پیاده‌سازی v27

| Route | nginx | Laravel |
|-------|-------|---------|
| `/api/v1/admin/*` | canonical | registered |
| `/api/v1/dashboard/admin/*` | **rewrite → `/api/v1/admin/*`** | registered + nginx |
| `/api/v1/dashboard/persona` etc. | **pass-through** | registered at full path |

[`backend/docker/nginx/default.conf`](../backend/docker/nginx/default.conf):

```nginx
location ~ ^/api/v1/dashboard/admin/(.*)$ {
    rewrite ^/api/v1/dashboard/admin/(.*)$ /api/v1/admin/$1 last;
}
```

SPA uses `normalizeAdminApiPath`: only `/dashboard/admin/*` → `/admin/*`; session paths keep `/dashboard/`.

Operator / date: 2026-06-13 (v27)
