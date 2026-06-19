# انحراف‌های آگاهانه — خلاصه v24 (یک‌صفحه)

> جزئیات تاریخی: نسخه‌های v11–v23 در همین فایل.

| موضوع | Spec اصلی | Runtime v24 | تصمیم |
|-------|-----------|-------------|--------|
| Queue | Horizon / database | Redis `queue-worker` | **نگه‌داری** — [`QUEUE-HORIZON-DEVIATION-FA.md`](QUEUE-HORIZON-DEVIATION-FA.md) |
| Commerce module | `MODULE_COMMERCE` | Core `CommerceMutations` | **نگه‌داری** |
| API prefix | `/api/v1/dashboard/admin/*` | `/api/v1/admin/*` + nginx rewrite alias | **rewrite اضافه شد** v24 |
| Portal envelope | `{ok,message}` | `{success,data}` | **WP parity** |
| Auth table | `users` | `dashboard_users` | **نگه‌داری** — [`ORPHAN-USERS-TABLE-FA.md`](ORPHAN-USERS-TABLE-FA.md) |
| Webhook secret | per-platform columns | unified `webhook_secret` | **نگه‌داری** |
| Migrations | 43 فایل | `svp_wp_parity.sql` | **نگه‌داری** — split optional |
| RBAC | Spatie (optional) | `permissions_json` | **نگه‌داری** — no Spatie v24 |
| Events | `app/Events/` | module jobs + services | **نگه‌داری** — [`ARCH-EVENTS-NOTE-FA.md`](ARCH-EVENTS-NOTE-FA.md) |
| Monitoring | real-time | 60s poll + visibility-aware | **defer v25** — WebSocket/SSE not needed؛ polling sufficient |
| Mutate alias | `bot_reseller_secret_rotate` | `reseller_bot_secret_rotate` deprecated | **v24 alias** |
| Env flags | `MODULE_*_ENABLED` | `SVP_MODULE_*` | **spec amended** v24/v25 |
| Docker nginx service | `nginx` | `web` | **spec amended** v24/v25 |
| MySQL | 8.0 | 8.4 | **spec amended** v24/v25 |
| WP root artifacts | in repo root | `archive/wp-plugin-root/` | **v24/v25 cleanup** |
| Spatie RBAC | optional package | `permissions_json` | **no change v25** — confirmed |
| Split 43 migrations | per-table files | `svp_wp_parity.sql` | **no change v25** — `ParityMigrationMysqlTest` guards |
| Events layer | `app/Events/` | module jobs | **no change v25** — [`ARCH-EVENTS-NOTE-FA.md`](ARCH-EVENTS-NOTE-FA.md) |

Operator / date: 2026-06-13 (v25 confirmed)

---
