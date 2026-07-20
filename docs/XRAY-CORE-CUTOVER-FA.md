# مهاجرت به Xray Core بومی (Cutover)

این سند مراحل جایگزینی تدریجی 3x-ui با control plane بومی MeowVPN را توضیح می‌دهد.

## پیش‌نیاز

1. Migration را اجرا کنید: `php artisan migrate`
2. در `.env` فعال کنید:
   ```env
   SVP_MODULE_XRAY_CORE=true
   SVP_MODULE_TUNNEL=true
   ```
3. روی VPS نود مرکزی `meow-node-agent` را نصب و اجرا کنید ([node-agent/README.md](../node-agent/README.md))
4. در داشبورد → **Xray Core** یک node با `agent_url` و `public_ip` ثبت کنید

## Dual-run (همزمان با 3x-ui)

- پلن‌های فعلی بدون تغییر از `panel_driver=xui` (پیش‌فرض) استفاده می‌کنند
- برای پلن native:
  - `panel_driver` = `native`
  - `xray_inbound_ref` = شناسه inbound در `svp_xray_inbounds`
- `ServiceProvisioner` بر اساس پلن، `XuiPanelDriver` یا `NativeXrayDriver` را انتخاب می‌کند

## Import یک‌باره از 3x-ui

```bash
php artisan svp:xray_import_xui --panel-id=1 --dry-run
php artisan svp:xray_import_xui --panel-id=1
```

سپس inboundهای import‌شده را در تب **Hosts** تکمیل کنید و پلن را به native cut over کنید.

## Edge tunnels

1. تب **Edge tunnels** → endpoint با provider (`frp`, `gost`, `xray_reverse`, `wireguard`)
2. SSH credentials edge server
3. **Deploy** → config روی edge نصب می‌شود
4. برای هر edge یک **Host** با IP عمومی edge در subscription اضافه کنید

## Rollback

- پلن را به `panel_driver=xui` برگردانید
- سرویس‌های native را disable/delete کنید؛ سرویس xui قبلی روی panel باقی می‌ماند
- `SVP_MODULE_XRAY_CORE=false` — provisioning فقط از 3x-ui

## Cron

- `svp:xray_traffic_sync` هر ۵ دقیقه — sync ترافیک و auto-disable روی limit/expiry

## معماری

```
MeowVPN (Laravel) ──mTLS──► meow-node-agent ──► Xray (یک instance، همه UUIDها)
Edge VPS ──frp/gost/wg──► نود مرکزی
```

داشبورد React + ربات admin تنها UI مدیریت است؛ پنل 3x-ui یا Marzban جداگانه لازم نیست.
