# Subscription Endpoints

Public (unauthenticated) routes served by `PortalSubscriptionController`.

## GET /sub/{token}

Subscription / portal entry for a service token (`sub_id` or email-style token).

- When the request matches a portal serve path (signed / tokenized), returns portal HTML or subscription payload.
- Otherwise returns JSON `{ "ok": true, "note": "portal_html" }` (probe / fallback).

Nginx forwards `/sub/` to Laravel (see `backend/docker/nginx`).

## GET /info

Same controller as `/sub/{token}` — used by some panel clients and portal themes for service info without a path token (query / headers may still identify the service).

## Related

- Signed portal SPA: `/{locale}/portal` (HMAC) — see [Portal & Payments](./portal-payments.md)
- Usage chart: `GET /api/v1/portal/usage`
