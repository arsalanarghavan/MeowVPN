# Webhooks, Portal & Relay

## Public bot webhooks

Telegram/Bale platforms POST updates to Laravel. Secrets are per-bot (site or reseller).

| Path | Auth | Purpose |
|------|------|---------|
| `POST /api/v1/webhook/{platform}/{secret}` | path secret + IP rate limit | Site bot ingress |
| `POST /api/v1/webhook/{platform}/reseller/{resellerId}/{secret}` | path secret + IP/reseller rate limit | Reseller bot ingress |
| `GET\|HEAD\|POST /api/v1/webhook/telegram/mirror/{mirrorId}/{secret}` | path secret (+ optional Telegram header) | Telegram **mirror** bot ingress |

`{platform}` is `telegram` or `bale`. Valid updates are enqueued; the worker drains the queue.

### Telegram mirror bots

Mirror bots are extra Telegram tokens (admin **Bots** tab) that share the site bot router but enqueue with `mirror_bot_id`.

| Method | Behavior |
|--------|----------|
| `GET` / `HEAD` | Health: `{ ok: true, alive: true, scope: "mirror" }` |
| `POST` | Validate encrypted path secret; optional `X-Telegram-Bot-Api-Secret-Token` when configured; enqueue update with `mirror_bot_id` |

Mutate ops: `telegram_mirror_save`, `telegram_mirror_delete`, `telegram_mirror_set_webhook`, `telegram_mirror_delete_webhook`, `telegram_mirror_toggle`, `telegram_mirror_test`, `telegram_mirror_diagnostics`. Webhook URL is built as `{public_base}/api/v1/webhook/telegram/mirror/{id}/{secret}` when registering via `telegram_mirror_set_webhook`.

Public webhook base URLs are configured in the setup wizard (`POST /api/v1/setup/domains/register-webhooks`) and via `php artisan svp:register-webhooks`.

## Webhook queue drain

| Path | Auth | Purpose |
|------|------|---------|
| `POST /api/v1/webhook-queue/drain` | Internal CIDR only (`EnsureInternalWebhookDrain`) | Process queued webhook jobs |

Allowed client ranges: loopback, RFC1918, `::1`. External callers receive `403`.

Scheduled fallback: `InboundQueueDrainJob` runs every minute as `svp:inbound_queue_drain` (`backend/routes/console.php`). Each webhook POST also dispatches a drain job via `InboundQueueService`.

## Internal bot RPC

| Path | Auth | Purpose |
|------|------|---------|
| `POST /api/internal/bot/process-update` | Bot service auth | Ingest platform update |
| `POST /api/internal/bot/mutate` | Bot service auth | Bot-side mutate RPC |

## Subscription portal

| Path | Auth | Purpose |
|------|------|---------|
| `GET /{locale}/portal` | signed HMAC / initial payload | Customer subscription UI |
| `GET /api/v1/portal/usage` | portal auth QS | Usage chart samples |
| `GET /sub/{token}` | token | Subscription / portal serve — see [Subscription Endpoints](./subscription-endpoints.md) |
| `GET /info` | none / token QS | Same controller as `/sub` |

Themes: `modern`, `pasarguard_builtin`, `pasarguard_v1`, `pasarguard_v2`, `xui`.

## Relay

Relay settings live under Site settings → Relay (feature-flagged). Control plane mutates use `POST /api/v1/admin/mutate` with relay ops; health is reflected in admin state.

## Crypto & rial callbacks

Payment return / IPN routes (path secret):

| Path | Module |
|------|--------|
| `POST /api/v1/crypto-ipn/{secret}` | crypto |
| `POST /api/v1/tetra-callback/{secret}` | crypto |
| `GET /api/v1/zarinpal-callback/{secret}` | rial |
| `GET /api/v1/zibal-callback/{secret}` | rial |
| `GET /api/v1/aqayepardakht-callback/{secret}` | rial |

Details: [Payment Callbacks](./payment-callbacks.md).

## Dashboard login (Telegram)

| Path | Purpose |
|------|---------|
| `POST /api/v1/dashboard/login/telegram` | Telegram Login Widget session bootstrap |
