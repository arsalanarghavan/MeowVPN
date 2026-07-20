# Panel merge & orphan clients

Admin UI: **Dashboard → 3x-ui panels** (`/{locale}/dashboard/xui_panels`).

Writes use `POST /api/v1/admin/mutate` unless noted. Reads for orphan scan use dedicated JSON routes.

## Panel merge

Use merge when retiring a panel or consolidating plans/services onto another panel of the **same provider** (`xui` or `pasarguard`).

### Flow

1. Open the source panel row menu → **Merge into another panel…**
2. Choose a target panel (same provider only).
3. **Preview** — `panel_merge_preview` returns plan lists, service counts, and a suggested `plan_map`.
4. Map every source plan (including orphan plan id `0`) to a target plan.
5. Acknowledge that the source panel may be removed.
6. **Execute** — `panel_merge_execute` moves services and optionally deactivates or deletes the source panel.

### Mutate payloads

**Preview**

```json
{
  "op": "panel_merge_preview",
  "source_panel_id": 1,
  "target_panel_id": 2,
  "service_ids": [101, 102]
}
```

`service_ids` is optional; omit to include all services on the source panel.

**Execute**

```json
{
  "op": "panel_merge_execute",
  "source_panel_id": 1,
  "target_panel_id": 2,
  "plan_map": { "12": 34, "0": 35 },
  "deactivate_source": true,
  "delete_source_after": false,
  "service_ids": []
}
```

| Field | Description |
|-------|-------------|
| `plan_map` | Source plan id → target plan id. Key `0` maps services without a plan. |
| `deactivate_source` | Disable source panel after success |
| `delete_source_after` | Remove source panel row when safe |
| `service_ids` | Optional subset; empty = all services on source |

Common errors: `provider_mismatch`, `unmapped_plans`, `preview_failed`.

## Orphan client scan & delete

Orphans are panel clients that belong to a bot user but are **not** linked to any `svp_services` row on that panel.

### User-scoped scan (recommended)

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/v1/admin/panel/orphan-clients/scan` | List orphan emails for a user + panel |
| `POST` | `/api/v1/admin/panel/orphan-clients/delete` | Delete selected emails from the panel |

**Scan body**

```json
{
  "panel_id": 1,
  "user_id": 100,
  "service_id": 0
}
```

`service_id` is optional (narrows linked-service context).

**Delete body**

```json
{
  "panel_id": 1,
  "emails": ["user@example.com"],
  "confirm": true
}
```

The dashboard shows linked email count, a selectable table, and **Delete selected**.

### Panel-wide orphan purge (3x-ui v3 only)

| Op | Payload | Notes |
|----|---------|-------|
| `configs_panel_del_orphans` | `{ "panel_id": 1 }` | Calls panel API `clientsDelOrphansV3`. **3x-ui only**; PasarGuard returns `del_orphans_not_supported`. |

Requires confirmation in the admin UI. Irreversible on the panel.

## Related admin routes

See [Admin API](./admin-api.md) for `configs-sync`, inbound rebuild, and live traffic helpers on the same tab.
