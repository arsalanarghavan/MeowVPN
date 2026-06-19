# Runbook عملیات Production — Laravel

راهنمای عملیاتی پس از cutover به Laravel. برای staging و import به [`CUTOVER-STAGING-FA.md`](CUTOVER-STAGING-FA.md) مراجعه کنید.

## سرویس‌ها

| سرویس Docker | نقش |
|--------------|-----|
| `app` | PHP-FPM / API |
| `web` | Nginx |
| `mysql` | دیتابیس |
| `redis` | cache, queue, rate limit |
| `scheduler` | `php artisan schedule:run` هر دقیقه — **الزامی** |
| `queue-worker` (profile `workers`) | پردازش queue (`php artisan queue:work redis`) — **الزامی** برای broadcast/bulk/webhook drain |

```bash
cd backend
docker compose up -d mysql redis app web scheduler
docker compose --profile workers up -d queue-worker   # در صورت نیاز
```

## Observability smoke

```bash
SVP_BASE_URL=https://your-host SVP_HEALTH_DEEP_TOKEN=... backend/scripts/ops/alert-smoke.sh
SVP_BASE_URL=https://your-host SVP_LOAD_REQUESTS=100 backend/scripts/ops/load-smoke.sh
```

Nightly soak artifact: `.github/workflows/nightly-soak.yml` (۵ دقیقه؛ production ۲۴h با `SVP_SOAK_DURATION_SEC=86400`).

## TLS (production nginx)

نمونه SSL در [`backend/docker/nginx/ssl.example.conf`](../backend/docker/nginx/ssl.example.conf). برای staging/production:

```bash
# certbot (host nginx یا mount به container web)
certbot certonly --nginx -d api.example.com
# wire ssl_certificate + ssl_certificate_key در nginx conf
curl -sSI https://api.example.com/health | head -5
```

Evidence: `docs/evidence/network-webhook-checklist-v17.md` (#14 HTTPS curl).

## Health checks

| URL | معنی |
|-----|------|
| `GET /up` | Laravel liveness |
| `GET /health` | liveness JSON (`ok: true`) |
| `GET /health/ready` | DB + cache — برای load balancer |
| `GET /health/deep` | probe یک پنل 3x-ui — نیاز به `X-Health-Token` اگر `SVP_HEALTH_DEEP_TOKEN` تنظیم شده |

```bash
curl -sS http://YOUR_HOST/health/ready | jq .
```

## Alerting (cron `svp:admin_alerts`)

هر ۵ دقیقه `AdminAlertsJob` اجرا می‌شود:

| Alert | شرط | اقدام |
|-------|------|-------|
| Panel down | login 3x-ui fail | بررسی URL/رمز پنل، فایروال، SSL |
| Queue backlog | pending > 1000 | scale `queue-worker`، بررسی Redis |
| Backup stale | آخرین backup > 2× interval | `php artisan svp:backup-run`، لاگ job |
| Relay down | ۳ fail متوالی health | VPS relay، `RELAY_MASTER_SECRET` |

تنظیمات: `notify_admin_panel_down`, `admin_telegram_ids`, `admin_bale_ids` در settings.

## لاگ‌ها

| فایل | محتوا |
|------|--------|
| `storage/logs/svp.log` | عمومی |
| `storage/logs/svp-webhook.log` | webhook ingress |
| `storage/logs/svp-panel.log` | probe پنل |
| `storage/logs/svp-relay.log` | relay health |

## Backup و restore

```bash
# دستی
docker compose exec app php artisan svp:backup-run

# از داشبورد: تب backup
# API: POST /api/v1/admin/backup/run
```

Restore: از تب backup یا `POST /api/v1/admin/backup/restore` — **قبل از restore snapshot MySQL بگیرید**.

## Rollback

1. DNS به سرور وردپرس
2. relay `laravel_base_url` → URL قدیمی WP (موقت)
3. restore snapshot MySQL

Drill script: `backend/scripts/ops/rollback-drill.sh` — evidence در `docs/evidence/rollback-drill.log`.

جزئیات: [`CUTOVER-STAGING-FA.md`](CUTOVER-STAGING-FA.md) § Rollback.

## Soak 24h

چک‌لیست کامل: [`LOAD-TEST-FA.md`](LOAD-TEST-FA.md).

## Rate limits

| Endpoint | حد |
|----------|-----|
| Webhook | 120/min per IP (تنظیم settings) |
| Dashboard login | 10/min per IP |
| `admin/mutate` | 300/min per user |
| `admin/state` | 60/min per user |

Behind nginx reverse proxy, set `SVP_RATE_LIMIT_TRUST_FORWARDED_FOR=true` (maps to `settings.rate_limit_trust_forwarded_for`) so webhook/login limits use the client IP from `X-Forwarded-For`.

## Production cutover (ops)

1. `backend/scripts/ops/import-verify.sh` با `SVP_MYSQL_DSN`
2. `backend/scripts/ops/staging-cutover-runbook.sh` روی staging
3. Soak 24h: `SVP_SOAK_DURATION_SEC=86400 backend/scripts/ops/soak-24h.sh`
4. DNS → Laravel؛ relay `laravel_base_url` sync
5. `backend/scripts/ops/wp-disable-staging.sh` سپس production WP off
6. Sign-off: [`evidence/CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md)
7. آرشیو WP: `backend/scripts/ops/archive-wp-plugin.sh`

## خاموش کردن WordPress

پس از پایدار شدن Laravel: [`WP-DECOMMISSION-FA.md`](WP-DECOMMISSION-FA.md). پرتال و webhook روی Laravel — [`evidence/CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md).
