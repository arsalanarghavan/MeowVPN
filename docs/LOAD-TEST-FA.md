# Load Test و Soak Test — فاز ۱۲

## Smoke load (سریع)

از داخل کانتینر یا host:

```bash
cd backend
php scripts/load-test/smoke-load.php \
  --base=http://127.0.0.1:8080 \
  --requests=50 \
  --webhook-secret=YOUR_TELEGRAM_WEBHOOK_SECRET
```

خروجی: error rate و latency p50/p95 برای:

- `GET /health/ready`
- `POST /api/v1/webhook/telegram/{secret}` (اختیاری)

### آستانه پیشنهادی (staging)

| Endpoint | p95 | Error rate |
|----------|-----|------------|
| `/health/ready` | < 200ms | 0% |
| Webhook ingress | < 500ms | < 1% |

## Soak test ۲۴ ساعت

قبل از cutover production:

1. **مانیتور health:** هر ۱ دقیقه `GET /health/ready` (Uptime Kuma / cron + alert)
2. **لاگ‌ها:** `storage/logs/svp*.log` — بدون spike خطای `panel.probe_failed` یا `webhook.queue_backlog`
3. **صف webhook:** `svp_inbound_queue` pending < 1000
4. **Scheduler:** container `scheduler` باید running باشد (`svp:admin_alerts` هر ۵ دقیقه)
5. **Horizon/worker:** queue drain بدون backlog مداوم

### چک‌لیست پایان ۲۴h

- [ ] هیچ alert تلگرام/بله panel-down ناخواسته
- [ ] backup موفق در بازه interval
- [ ] dashboard login و `admin/state` پایدار
- [ ] webhook ربات اصلی و reseller (در صورت فعال) پاسخ 200

## Docker

```bash
docker compose exec app php scripts/load-test/smoke-load.php \
  --base=http://web \
  --requests=100 \
  --webhook-secret="$(grep telegram_webhook_secret storage/... 2>/dev/null || echo test)"
```

در compose، `web` سرویس nginx داخلی است؛ از host از پورت `8080` استفاده کنید.

## خارج از scope

- k6 / Grafana dashboards (optional در spec §۱۸.۵)
- تست بار `admin/mutate` با session — نیاز به Sanctum cookie دارد؛ در staging با ابزار مرورگر یا Postman انجام شود
