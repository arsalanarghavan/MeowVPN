# Webhook Reseller Secret — انحراف schema (v15)

## Spec §13.2

Per-platform columns: `{platform}_webhook_secret` on `svp_reseller_bot_profiles`.

## پیاده‌سازی Laravel

یک ستون encrypted `webhook_secret` برای هر reseller profile (`ResellerBotProfileService`).

هر دو مسیر URL و header `X-SVP-Webhook-Secret` با همان secret مقایسه می‌شوند.

## تصمیم v15

**DEVIATION ثبت‌شده:** unified secret کافی برای single-bot-per-reseller model. Migration به per-platform columns فقط در صورت multi-platform secret جدا لازم است.
