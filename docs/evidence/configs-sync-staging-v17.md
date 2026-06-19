# configs-sync staging v17

```bash
curl -sS -b cookies.txt "$SVP_BASE_URL/api/v1/admin/configs-snapshot?panel_id=1" | jq '.ok'
# mutate configs sync via panel — ConfigsSyncFeatureTest
```

| Step | Result | Date |
|------|--------|------|
| GET configs-snapshot panel 1 | ok=true | 2026-06-12 |
| inbound clients cache cron | svp:inbound_clients_cache | 2026-06-12 |

Evidence: CI `ConfigsSyncFeatureTest`
