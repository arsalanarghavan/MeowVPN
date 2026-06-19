# API Route Audit v13 (§7)

Spec prefix: `/api/v1/dashboard/*` → implementation: `/api/v1/*` + [`normalizeAdminApiPath`](../../frontend/src/lib/api-base.ts).

| Spec route | Laravel route | Test |
|------------|---------------|------|
| `GET admin/state` | `GET /api/v1/admin/state` | GroupA* |
| `POST admin/mutate` | `POST /api/v1/admin/mutate` | MutateSmokeTest |
| `POST admin/configs-sync` | `POST /api/v1/admin/configs-sync` | ConfigsSyncFeatureTest |
| `GET admin/inbound-display-catalog` | `GET /api/v1/admin/inbound-display-catalog` | GroupEServersAcceptanceTest |
| `POST admin/backup/run` | `POST /api/v1/admin/backup/run` | BackupRestHttpTest |
| `POST webhook/{platform}/{secret}` | same under `/api/v1` | Webhook* tests |
| `POST portal/admin` | `POST /api/v1/portal/admin` | PortalAdminExtendedTest |

Deviations: [`SPEC-DEVIATIONS-FA.md`](../SPEC-DEVIATIONS-FA.md) ARCH-1.
