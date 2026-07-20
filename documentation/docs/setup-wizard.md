# Setup Wizard API

Used on first install (bootstrap mode) when domains are deferred.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/setup/status` | Wizard open/closed |
| GET | `/api/v1/setup/domains` | Current domain map |
| POST | `/api/v1/setup/domains` | Save domains |
| POST | `/api/v1/setup/domains/probe` | DNS/TLS probe |
| POST | `/api/v1/setup/domains/register-webhooks` | Register TG/Bale webhooks |
| POST | `/api/v1/setup/backup/restore` | Restore MeowVPN backup |
| POST | `/api/v1/setup/backup/wordpress` | Import WordPress dump |
| POST | `/api/v1/setup/admin-credentials` | Set admin user |
| POST | `/api/v1/setup/complete` | Close wizard |

Middleware: `install.wizard.open` on mutating routes.
