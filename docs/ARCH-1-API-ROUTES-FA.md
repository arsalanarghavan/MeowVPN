# ARCH-1 — مسیرهای REST داشبورد

## انحراف آگاهانه (ثبت v11+، تأیید v14)

| Spec | پیاده‌سازی |
|------|------------|
| `/api/v1/dashboard/bootstrap` | `/api/v1/bootstrap` |
| `/api/v1/dashboard/auth/login` | `/api/v1/auth/login` |
| `/api/v1/dashboard/admin/*` | `/api/v1/admin/*` |
| `/api/v1/dashboard/ui-preferences` | `/api/v1/dashboard/ui-preferences` (همان مسیر؛ alias داخلی) |

## Frontend

همه درخواست‌های admin از `normalizeAdminApiPath()` در `frontend/src/lib/admin-api.ts` عبور می‌کنند تا `/api/v1/admin/` یکسان بماند.

## Alias اختیاری (nginx)

در صورت نیاز سازگاری با spec قدیمی:

```nginx
location /api/v1/dashboard/ {
    rewrite ^/api/v1/dashboard/(.*)$ /api/v1/$1 break;
    proxy_pass http://app:9000;
}
```

**تصمیم v14:** alias در nginx اختیاری است؛ مسیر canonical همان `/api/v1/*` بدون پیشوند `dashboard` برای admin API است.
