# MeowVPN — Multi-host Docker deployment

## Standard installer

```bash
sudo bash backend/scripts/ops/install.sh
```

| Menu option | Docker profiles | Host nginx vhost |
|-------------|-----------------|------------------|
| Install All | core + `workers` + `frontend` + `telegram` + `bale` + `relay` | Core, Dashboard, Telegram, Bale, Relay |
| Install Dashboard | core + `workers` + `frontend` | Core + Dashboard |
| Install Telegram Bot | `telegram` | Telegram |
| Install Bale Bot | `bale` | Bale |
| Install Dashboard Backend | core + `workers` | Core |
| Install Dashboard Frontend | `frontend` | Dashboard |
| Install Relay | systemd relay (see `relay-server/scripts/install.sh`) | Relay |

Install state and secrets: `backend/.install/state.env` (gitignored).

Compose override used by the installer (localhost bind):

`backend/scripts/ops/install/docker-compose.install.override.yml`

## Layout

| Directory | Role | Needs MySQL/Redis |
|-----------|------|-------------------|
| `backend/` | Laravel API, commerce, cron, queue drain | Yes |
| `frontend/` | React dashboard (nginx image) | No |
| `telegram_bot/` | Telegram webhook worker → backend internal API | No |
| `bale_bot/` | Bale webhook worker → backend internal API | No |
| `relay-server/` | Telegram egress + webhook forward | No |
| `backend/packages/svp-bot-core/` | Shared HTTP client + webhook ingress | No |

## Domain map

| Prompt | Service | Internal port |
|--------|---------|---------------|
| Dashboard Core Domain | Laravel API (`app` + `web` + mysql + redis + queue) | `127.0.0.1:8080` |
| Dashboard Domain | React SPA (`frontend`) | `127.0.0.1:3001` |
| Telegram Bot Domain | `telegram-bot` worker | `127.0.0.1:8091` |
| Bale Bot Domain | `bale-bot` worker | `127.0.0.1:8092` |
| Relay Domain | `relay` (Docker) or relay-server (standalone menu) | `127.0.0.1:8787` |

Empty domain → server public IP, HTTP only (no certbot/acme).

## Manual single server

```bash
bash frontend/scripts/build.sh
cd backend
docker compose \
  -f docker-compose.yml \
  -f scripts/ops/install/docker-compose.install.override.yml \
  --profile workers --profile full up -d --build
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --class=AdminUserSeeder --force
```

Core stack services (`app`, `web`, `mysql`, `redis`, `scheduler`) start **without** a profile.  
Always add `--profile workers` for `queue-worker`.

Services with `--profile full`: `frontend`, `telegram-bot`, `bale-bot`, `relay`.

API: `http://127.0.0.1:8080/api/v1` (or your Core domain via host nginx).

## Split servers

### App server

```bash
sudo bash backend/scripts/ops/install.sh --mode dashboard --non-interactive \
  --core-domain api.example.com --dashboard-domain panel.example.com \
  --ssl certbot --email you@example.com
```

Env highlights:

- `APP_URL` / `public_site_url` → Core domain
- `SANCTUM_STATEFUL_DOMAINS` → Dashboard + Core hostnames
- `SVP_BOT_SERVICE_SECRET` — shared with bot workers
- `SVP_LEGACY_WEBHOOK_ON_BACKEND=false`

### Telegram server

```bash
sudo bash backend/scripts/ops/install.sh --mode telegram
# provide Core URL when prompted (e.g. https://api.example.com)
```

Relay (separate host or Install Relay menu):

```bash
sudo bash backend/scripts/ops/install.sh --mode relay
```

Point Telegram webhook to relay public URL. Relay forwards to telegram bot (`/api/v1/webhook/…` rewritten to `/webhook/…` on the bot host).

### Bale server

```bash
sudo bash backend/scripts/ops/install.sh --mode bale
```

Bale webhook URL: `https://{bale-domain}/api/v1/webhook/bale/{secret}` (nginx rewrites to worker).

## Internal bot API

Bot workers call backend (never touch DB directly):

```
POST /api/v1/internal/bot/process-update
Headers: X-SVP-Bot-Service-Secret, X-SVP-Platform: telegram|bale
```

## Post-install

Installer runs automatically:

- `php artisan migrate --force`
- `php artisan db:seed --class=AdminUserSeeder`
- `php artisan svp:install-apply-settings` (domains + secrets)
- `php artisan svp:register-webhooks --platform=both`

Configure bot tokens in the dashboard, then re-run webhooks if needed.

## Firewall

- Backend MySQL/Redis: private network only.
- Bot workers: expose 8091/8092 via host nginx (443) only.
- Relay: public 443 for webhooks; block `/internal/` on public vhost.
