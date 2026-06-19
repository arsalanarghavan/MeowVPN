# Portal — لینک‌های امضاشده و TTL

## مشتری (`/info`)

- پارامترها: `uid`, `exp`, `sig`
- `sig = HMAC-SHA256("{uid}|{exp}", portal_link_secret)`
- `portal_link_secret` در `svp_settings` (کلید `portal_link_secret`)
- پس از `exp` لینک رد می‌شود — تست: `PortalSignedLinkTtlTest`

## ادمین (`?svp_adm=1`)

- پارامترها: `svp_adm`, `exp`, `sig` (و اختیاری `tab`)
- امضا با همان secret یا کلید admin portal (مطابق `PortalLinkService`)
- TTL پیش‌فرض از تنظیمات `portal_admin_link_ttl_sec` (معمولاً ۳۶۰۰)

## پاسخ HTML / plain

- HTML: `GET /info?...` با `Accept: text/html`
- Plain subscription: `GET /info?...&format=plain` یا مسیر sub در portal controller
- تست‌های acceptance: `PortalSubscriptionAcceptanceTest`, `PortalDiscountWriteTest`
- Evidence اپراتوری: [`docs/evidence/portal-parity-v14.md`](evidence/portal-parity-v14.md)
