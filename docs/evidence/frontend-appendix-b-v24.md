# Spec appendix B — frontend migration verify v24

Date: 2026-06-13

```bash
rg "wp-json|X-WP-Nonce|admin-ajax" frontend/
# (no matches)
```

| Change | Status |
|--------|--------|
| `restUrl` → `/api/v1` | OK — `api-base.ts` |
| Sanctum CSRF (no X-WP-Nonce) | OK |
| Portal → `/api/v1/portal/admin` | OK |
| tab keys unchanged | OK — `admin-nav.ts` |

Operator: automated grep 2026-06-13
