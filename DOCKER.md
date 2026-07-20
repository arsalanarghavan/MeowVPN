# MeowVPN — Docker microservices

```bash
# API + MySQL + Redis + Next.js frontend + Docusaurus docs
docker compose up -d --build web frontend docs mysql redis

# Full stack (bots, workers, xray)
docker compose --profile full up -d --build
```

| Service | Port (default) | Role |
|---------|----------------|------|
| `web` | 8080 | Laravel API (nginx → php-fpm) |
| `frontend` | 3000 | Next.js 14.2.35 SSR dashboard/portal |
| `docs` | 3002 | Docusaurus API docs |
| `mysql` | — | MySQL 8.4 |
| `redis` | — | Cache / queue / session |

OpenAPI (Scramble): `http://localhost:8080/docs/api`

Locales: `/fa/...` (RTL, Jalali) and `/en/...` (LTR, Gregorian).

Legacy Vite SPA preserved at `frontend-vite-legacy/` for reference.
