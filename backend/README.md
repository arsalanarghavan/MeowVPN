# SimpleVPBot — Laravel Backend

Laravel 11 API replacing the WordPress plugin. See [`../docs/LARAVEL-BACKEND-SPEC-FA.md`](../docs/LARAVEL-BACKEND-SPEC-FA.md) for the full specification.

## Quick start (Docker)

```bash
cp .env.example .env
# Set APP_KEY after first compose run:
docker compose run --rm app php artisan key:generate

docker compose up -d mysql redis app web scheduler
docker compose run --rm app php artisan migrate --seed
```

The **scheduler** container is required for cron jobs (backup, admin panel alerts, inbound queue drain).

## Health

| URL | Purpose |
|-----|---------|
| `GET /health` | Liveness |
| `GET /health/ready` | DB + Redis |
| `GET /health/deep` | Sample 3x-ui panel probe (optional `X-Health-Token` if `SVP_HEALTH_DEEP_TOKEN` is set) |

## Operations

- Production runbook: [`../docs/RUNBOOK-PRODUCTION-FA.md`](../docs/RUNBOOK-PRODUCTION-FA.md)
- Load / soak test: [`../docs/LOAD-TEST-FA.md`](../docs/LOAD-TEST-FA.md)
- WP decommission: [`../docs/WP-DECOMMISSION-FA.md`](../docs/WP-DECOMMISSION-FA.md)

```bash
php scripts/load-test/smoke-load.php --base=http://127.0.0.1:8080 --requests=50
```

Dashboard SPA: build `frontend` and set `VITE_API_URL=/api/v1`.

Default admin: `admin` / `changeme` (override via `SVP_ADMIN_USERNAME`, `SVP_ADMIN_PASSWORD`).

## PHPUnit (local)

CI installs `dom`, `xml`, `xmlwriter` automatically. For local runs:

```bash
# Debian/Ubuntu
sudo apt install php8.3-xml php8.3-dom
cd backend && php artisan test
```

## Queue workers

| Mode | Command |
|------|---------|
| Default compose | `scheduler` container |
| Queue worker | `docker compose --profile workers up -d queue-worker` (`php artisan queue:work redis`) |

## Rate limits (production nginx)

Set `SVP_RATE_LIMIT_TRUST_FORWARDED_FOR=true` when nginx passes real client IP via `X-Forwarded-For`. See [`../docs/RUNBOOK-PRODUCTION-FA.md`](../docs/RUNBOOK-PRODUCTION-FA.md).

## Observability (optional)

```bash
docker compose --profile observability up -d prometheus grafana
```

Metrics: `GET /metrics` — Grafana template in `docker/grafana/`.

## API (WP parity)

| Endpoint | Description |
|----------|-------------|
| `POST /api/v1/auth/login` | Dashboard login |
| `GET /api/v1/bootstrap` | Bootstrap + modules |
| `GET /api/v1/admin/state` | Admin state |
| `POST /api/v1/admin/mutate` | `{ op, ... }` mutations |
| `POST /api/webhook/telegram/{secret}` | Telegram webhook |
| `POST /api/webhook/bale/{secret}` | Bale webhook |
| `POST /api/crypto/ipn/{secret}` | NOWPayments IPN |

## Modules

Enable/disable via `.env`: `SVP_MODULE_TELEGRAM`, `SVP_MODULE_RELAY`, etc. See `config/modules.php`.

## Data migration

WordPress `wp:import` was removed after Laravel cutover. Use `php artisan migrate --force` and seeders for fresh installs.

## Schema

SVP table DDL is in `database/schema/svp_schema.sql`.
