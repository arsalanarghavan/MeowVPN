# Modules

Each third-party integration is an isolated module under `backend/app/Modules/<Name>/`.

## Enable / disable

`.env`:

```
SVP_MODULE_TELEGRAM=true
SVP_MODULE_BALE=true
SVP_MODULE_XUI_PANEL=true
SVP_MODULE_PASARGUARD=true
SVP_MODULE_RIAL=true
SVP_MODULE_CRYPTO=false
SVP_MODULE_RELAY=false
SVP_MODULE_L2TP=false
SVP_MODULE_MARKETING=true
SVP_MODULE_BACKUP=true
SVP_MODULE_XRAY_CORE=false
SVP_MODULE_TUNNEL=false
```

## PasarGuard

Provider key on panel rows: `panel_provider = pasarguard`.

Client: `App\Modules\PasarGuard\Services\PasarGuardClient`

Factory: `PanelClientFactory` selects XUI vs PasarGuard.

## XuiPanel

3x-ui cookie/Bearer client, inbound sync, rebuild, purge expired.

## Rial (IRR gateways)

Module key: `rial`. Gateways: ZarinPal, Zibal, AqayePardakht.

Callbacks: `GET /api/v1/{zarinpal,zibal,aqayepardakht}-callback/{secret}` — see [Payment Callbacks](./payment-callbacks.md).

Mutate: `rial_settings` (merchant ids, sandbox flags, path secrets).

## Crypto (NOWPayments / TetraPay)

IPN + Tetra callback controllers when module enabled — see [Payment Callbacks](./payment-callbacks.md).

## Telegram / Bale

Platform clients, webhook mutations, force-join, admin IDs.
