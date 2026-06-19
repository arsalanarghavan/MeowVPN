# svp-bot-core

Shared library for MeowVPN bot workers (`telegram_bot`, `bale_bot`).

## Contents

- `Contracts/BotBackendClient` — HTTP contract to Laravel backend internal API
- `Http/HttpBotBackendClient` — stateless client (no Laravel required)
- `WebhookIngress` — validate webhook secrets and forward updates to backend

## Architecture (Model C)

Bot workers are **stateless ingress** containers. Business logic and DB access remain in `backend/` via:

`POST /api/v1/internal/bot/process-update`

Future phases can move `App\Modules\Core\Bot` handlers into this package with full `BotBackendClient` coverage.

Located under `backend/packages/svp-bot-core/` (no separate top-level `packages/` directory).
