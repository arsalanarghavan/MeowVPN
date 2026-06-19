# SimpleVPBot Telegram Relay

Standalone Node.js service: fast webhook `200 OK`, multi-tenant config, per-bot domains, Bot API proxy, WordPress forward.

**Full setup guide (Persian):** [SETUP-GUIDE-FA.md](./SETUP-GUIDE-FA.md)

## Quick install (VPS) — one command from GitHub

```bash
curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/install-from-github.sh | sudo bash -s -- --domain tg.example.com --email you@example.com --ssl certbot
```

Without SSL (HTTP only, add cert later):

```bash
curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/install-from-github.sh | sudo bash -s -- --domain tg.example.com
```

With acme.sh instead of certbot:

```bash
curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/install-from-github.sh | sudo bash -s -- --domain tg.example.com --email you@example.com --ssl acme
```

After install: copy the printed `RELAY_MASTER_SECRET` into WordPress → Site settings → Telegram relay → Shared secret, then **Sync config**.

## Quick install (already cloned)

```bash
cd relay-server
sudo bash scripts/install.sh --domain tg.example.com --email you@example.com --ssl certbot
```

Copy `RELAY_MASTER_SECRET` from install output into WordPress **Site settings → Telegram relay → Shared secret**, then **Sync config**.

## Update existing VPS (no git in `/opt/svp-relay`)

The install directory is not a git clone. Pull updates with:

```bash
curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/update-from-github.sh | sudo bash
```

This keeps your `.env` and `data/tenants/`, rebuilds, installs `/usr/local/bin/svp-relay`, and restarts the service.

If `svp-relay` is still not found:

```bash
sudo node /opt/svp-relay/dist/cli/svp-relay.js panel
```

Do **not** run `npm ci` as root inside `/opt/svp-relay` — the update script runs build as the `svp-relay` user.

## Manual install

```bash
cp .env.example .env
# Set RELAY_MASTER_SECRET and match RELAY_SHARED_SECRET with WordPress tenant secret
npm ci && npm run build
npm start
```

## Control centers

**WordPress (primary):** Site settings → Telegram relay → Relay Control Center (purple tabs).  
WP talks to relay via `https://VPS_IP` (admin, port 443). Telegram domain is separate.

**VPS terminal (whiptail, Hiddify-style):**

```bash
sudo svp-relay
```

| Menu | Purpose |
|------|---------|
| Dashboard | Uptime, queue depth, tenants, domains, systemd, nginx |
| Service | start / stop / restart / status |
| Tenants | List and inspect synced WP tenants |
| Domains | Add / remove relay hostnames |
| SSL | Issue or renew certificates (certbot or acme.sh) |
| Nginx | Render config, test, reload |
| WordPress setup | Checklist + optional master secret reveal |
| Logs | journalctl tail or follow |
| Doctor | Environment and dependency checks |
| Install / update | Re-run `install.sh` |

Service control, SSL, and nginx reload usually need **root** (`sudo svp-relay`). Bot tokens and webhook registration stay in the WordPress dashboard.

Explicit panel entry: `svp-relay panel`

## CLI subcommands (scripting)

```bash
svp-relay status
svp-relay tenants list
svp-relay domain add tg.example.com --tenant <tenant-uuid>
svp-relay nginx render
svp-relay ssl issue tg.example.com --method certbot
svp-relay ssl issue tg.example.com --method acme --email you@example.com
svp-relay doctor
```

## Multi-tenant

Each WordPress site pushes config with its own `X-SVP-Relay-Secret`. Tenants are stored under `data/tenants/{tenant_id}.json`.

Legacy single `data/config.json` is migrated to `data/tenants/default.json` on first boot.

## Multi-domain (per bot)

- **Tenant default**: `relay_public_url` in WordPress relay settings
- **Reseller override**: `telegram_relay_public_url` on reseller bot profile
- Run `svp-relay domain add <host>` and `nginx render` for each hostname
- WordPress **Sync domains** pushes the domain list to the relay

## Endpoints

| Path | Auth | Purpose |
|------|------|---------|
| `POST /webhook/telegram/:secret` | Path secret | Main bot inbound |
| `POST /webhook/telegram/reseller/:id/:secret` | Path secret | Reseller inbound |
| `POST /bot{token}/*` | Token in URL | Bot API proxy |
| `POST /internal/config` | `X-SVP-Relay-Secret` | Upsert tenant config |
| `GET /internal/status` | Secret | Tenant or master status |
| `GET /internal/health` | Secret | Lightweight health |
| `GET /internal/domains` | Secret | Domain list |
| `POST /internal/domains/sync` | Secret | Sync domains from WP |
| `POST /internal/set-webhook` | Secret | Register Telegram webhook |
| `POST /internal/diagnostics` | Secret | getMe + getWebhookInfo |

## Environment

| Variable | Description |
|----------|-------------|
| `PORT` | Listen port (default 8787) |
| `RELAY_MASTER_SECRET` | CLI / master internal auth |
| `RELAY_SHARED_SECRET` | Legacy single-tenant fallback |
| `TENANTS_DIR` | Per-tenant JSON directory |
| `ALLOWED_WP_IPS` | Optional comma-separated WP server IPs |
| `NGINX_CONFIG_PATH` | Output path for `nginx render` |

## systemd

See `scripts/install.sh` or use the unit template printed during install.

## WordPress

1. Site settings → **Telegram relay** → enable, set URLs, save
2. **Sync config** (stores `tenant_id`)
3. **Sync domains** + `svp-relay domain add` on VPS
4. **Register webhook via relay**
5. Resellers: optional per-bot relay URL → sync → set webhook
