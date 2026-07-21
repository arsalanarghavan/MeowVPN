# Signed portal links (HMAC)

Customer and admin portal access can use **signed query strings** without a dashboard session. Source of truth for operators also lives in repo `docs/PORTAL-SIGNED-LINKS-FA.md`.

## Customer (`/info` / portal themes)

| Param | Meaning |
|-------|---------|
| `uid` | SVP user id |
| `exp` | Unix expiry |
| `sig` | `HMAC-SHA256("{uid}\|{exp}", portal_link_secret)` |

- Secret: `svp_settings.portal_link_secret`
- After `exp`, the link is rejected
- Plain subscription: `format=plain` (or subscription token routes — see [Subscription Endpoints](./subscription-endpoints.md))

Next route `/{locale}/portal` reads signed query params (`uid`/`exp`/`sig`, plus legacy `svp_u`/`svp_e`/`svp_s`) and bootstraps theme data via Laravel `GET /info` (`fetchPortalBootstrap`), then injects the payload into `window.__SIMPLEVPBOT_PORTAL__` / `__INITIAL_DATA__` before rendering the theme. Without those params the theme shell still renders (empty / unsigned).

## Admin (`svp_adm=1`)

| Param | Meaning |
|-------|---------|
| `svp_adm` | `1` |
| `exp` | Unix expiry |
| `sig` | HMAC with portal / admin portal secret |
| `tab` | optional dashboard tab hint |

TTL: `portal_admin_link_ttl_sec` (typically 3600).

## Related tests

- `PortalSignedLinkTtlTest`
- `PortalSubscriptionAcceptanceTest`
- Frontend e2e: `frontend/e2e/residual-closeout-p2.spec.ts` — unsigned/invalid-sig **smoke** (page must not crash); valid signed hydrate needs a live fixture secret
