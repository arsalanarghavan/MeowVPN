# MeowVPN

Multi-service VPN bot platform.

## Top-level layout (only these project directories)

| Path | Description |
|------|-------------|
| [`backend/`](backend/) | Laravel API — DB owner, commerce, dashboard API, bot processing |
| [`frontend/`](frontend/) | React dashboard SPA |
| [`telegram_bot/`](telegram_bot/) | Stateless Telegram webhook worker |
| [`bale_bot/`](bale_bot/) | Stateless Bale webhook worker |
| [`relay-server/`](relay-server/) | Telegram relay (webhook + Bot API proxy) |
| [`docs/`](docs/) | Specs, runbooks, deployment |

Shared bot library: [`backend/packages/svp-bot-core/`](backend/packages/svp-bot-core/)

## Production install (recommended)

On Ubuntu/Debian as root:

```bash
sudo bash backend/scripts/ops/install.sh
```

Interactive menu:

1. Install All
2. Install Dashboard (Backend + Frontend)
3. Install Telegram Bot
4. Install Bale Bot
5. Install Dashboard Backend
6. Install Dashboard Frontend
7. Install Relay

For **Install All**, you will be prompted for:

- Dashboard Core Domain (API)
- Dashboard Domain (SPA)
- Telegram Bot Domain
- Bale Bot Domain
- Relay Domain

Leave any domain empty to use the server’s public IP (HTTP only, no Let’s Encrypt).  
With a hostname, choose **certbot** or **acme.sh** for SSL.

Non-interactive example:

```bash
sudo bash backend/scripts/ops/install.sh --mode all --non-interactive \
  --core-domain api.example.com \
  --dashboard-domain panel.example.com \
  --telegram-domain tg.example.com \
  --bale-domain bale.example.com \
  --relay-domain relay.example.com \
  --ssl certbot \
  --email admin@example.com
```

## Manual / dev quick start

```bash
bash frontend/scripts/build.sh
cd backend
docker compose -f docker-compose.yml \
  -f scripts/ops/install/docker-compose.install.override.yml \
  --profile workers --profile full up -d --build
```

See [`docs/DEPLOYMENT-MULTI-HOST.md`](docs/DEPLOYMENT-MULTI-HOST.md).
