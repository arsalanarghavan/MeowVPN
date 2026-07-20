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

Next route `/{locale}/portal` renders themes; signed bootstrap may be injected as `window.__SIMPLEVPBOT_PORTAL__` / `__INITIAL_DATA__` by the backend HTML entry, or consumed via `/info` APIs.

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
