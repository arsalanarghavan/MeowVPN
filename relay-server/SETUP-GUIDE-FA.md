# راهنمای کامل SimpleVPBot Telegram Relay

این سند همه‌چیز را برای **نصب، راه‌اندازی و کار روزمره** سرور واسط (Relay) توضیح می‌دهد. Relay یک سرویس Node.js روی VPS جدا است که webhook تلگرام را سریع جواب می‌دهد (`200 OK`) و آپدیت‌ها را به وردپرس forward می‌کند.

**چه چیزی کجا مدیریت می‌شود؟**

| کار | کجا |
|-----|-----|
| نصب VPS، SSL، nginx، سرویس، دامنه | ترمینال VPS (`svp-relay`) |
| توکن ربات، webhook، نمایندگان، تنظیمات کسب‌وکار | داشبورد وردپرس (SimpleVPBot) |

---

## فهرست

1. [چرا Relay؟](#۱-چرا-relay)
2. [معماری](#۲-معماری)
3. [پیش‌نیازها](#۳-پیش‌نیازها)
4. [نصب اولیه روی VPS](#۴-نصب-اولیه-روی-vps)
5. [کنترل پنل ترمینال](#۵-کنترل-پنل-ترمینال)
6. [SSL و nginx](#۶-ssl-و-nginx)
7. [اتصال وردپرس](#۷-اتصال-وردپرس)
8. [ربات‌های نماینده (Reseller)](#۸-ربات‌های-نماینده-reseller)
9. [به‌روزرسانی Relay](#۹-به‌روزرسانی-relay)
10. [Deploy وردپرس](#۱۰-deploy-وردپرس)
11. [عیب‌یابی](#۱۱-عیب‌یابی)
12. [مرجع CLI](#۱۲-مرجع-cli)
13. [فایل‌ها و متغیرهای محیطی](#۱۳-فایل‌ها-و-متغیرهای-محیطی)
14. [امنیت](#۱۴-امنیت)
15. [چک‌لیست راه‌اندازی سریع](#۱۵-چک‌لیست-راه‌اندازی-سریع)

---

## ۱. چرا Relay؟

وقتی webhook تلگرام مستقیم به وردپرس روی هاست اشتراکی می‌خورد، ممکن است:

- پاسخ دیر برسد → تلگرام **504** می‌دهد
- پردازش PHP سنگین شود → آپدیت‌ها از دست می‌روند

Relay این کار را می‌کند:

1. درخواست webhook را **فوراً** با `{ ok: true }` تأیید می‌کند
2. آپدیت را در صف می‌گذارد و **در پس‌زمینه** به وردپرس می‌فرستد
3. درخواست‌های Bot API (`/bot<token>/...`) را هم پروکسی می‌کند

---

## ۲. معماری

```
تلگرام
   │  HTTPS webhook
   ▼
[ nginx روی VPS ]  ← دامنه relay مثل tg.example.com
   │  proxy → :8787
   ▼
[ svp-relay (Node) ]
   │  POST async به WP
   ▼
[ وردپرس + SimpleVPBot ]  ← goatvps.ir یا دامنه اصلی سایت
```

**جریان تنظیمات:**

- وردپرس با secret مشترک، config ربات را به `POST /internal/config` می‌فرستد
- Relay در `data/tenants/{tenant_id}.json` ذخیره می‌کند
- دامنه‌های webhook از WP sync می‌شوند + روی VPS در nginx ثبت می‌شوند

---

## ۳. پیش‌نیازها

### VPS Relay

- Ubuntu/Debian (یا مشابه)
- دسترسی `root` / `sudo`
- پورت 80 و 443 باز
- یک **ساب‌دامنه** با DNS به IP همان VPS (مثلاً `tg.goatvps.ir`)

### وردپرس

- پلاگین SimpleVPBot نصب و داشبورد React build شده
- دسترسی ادمین به **تنظیمات سایت → تب Telegram relay**

### DNS نمونه

| رکورد | نوع | مقدار |
|-------|-----|--------|
| `tg` | A | IP سرور VPS relay |

---

## ۴. نصب اولیه روی VPS

### روش ۱ — یک خط از GitHub (توصیه‌شده)

**با SSL (certbot):**

```bash
curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/install-from-github.sh | sudo bash -s -- \
  --domain tg.example.com \
  --email you@example.com \
  --ssl certbot
```

**فقط HTTP (SSL بعداً از پنل):**

```bash
curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/install-from-github.sh | sudo bash -s -- \
  --domain tg.example.com
```

**با acme.sh به‌جای certbot:**

```bash
curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/install-from-github.sh | sudo bash -s -- \
  --domain tg.example.com \
  --email you@example.com \
  --ssl acme
```

### روش ۲ — از روی repo کلون‌شده

```bash
cd relay-server
sudo bash scripts/install.sh --domain tg.example.com --email you@example.com --ssl certbot
```

### فلگ‌های `install.sh`

| فلگ | توضیح |
|-----|--------|
| `--domain HOST` | دامنه relay (بدون https) |
| `--email ADDR` | ایمیل Let's Encrypt |
| `--ssl certbot\|acme` | روش صدور گواهی |
| `--wp-url URL` | فقط یادآوری لینک تنظیمات WP در خروجی |
| `--no-nginx` | بدون nginx |
| `--no-systemd` | بدون سرویس systemd |

### بعد از نصب چه ساخته می‌شود؟

| مسیر | نقش |
|------|-----|
| `/opt/svp-relay` | کد، build، `.env` |
| `/opt/svp-relay/data/tenants/` | config هر سایت WP |
| `/etc/systemd/system/svp-relay.service` | سرویس |
| `/usr/local/bin/svp-relay` | کنترل پنل ترمینال |
| `/etc/nginx/sites-available/svp-relay.conf` | پیکربندی nginx |

### secret مهم

در اولین نصب، installer چاپ می‌کند:

```
RELAY_MASTER_SECRET (copy to WordPress relay shared secret):
xxxxxxxx...
```

این مقدار را **کپی کنید** — بعداً در وردپرس لازم است. اگر گم شد:

```bash
sudo grep RELAY_MASTER_SECRET /opt/svp-relay/.env
```

یا از پنل: **WordPress setup → Reveal secret**

### بررسی سلامت

```bash
curl -s http://127.0.0.1:8787/health
# {"ok":true,"service":"simplevpbot-telegram-relay"}

systemctl status svp-relay
```

---

## ۵. مرکز کنترل (دو راه)

### الف) داشبورد وردپرس (اصلی)

**تنظیمات سایت → Telegram relay** — تب بنفش **Relay Control Center** با زیرتب‌ها:

- **Overview** — uptime، صف، systemd، nginx
- **Connection** — IP سرور، Admin URL (`https://IP`)، secret، SSL verify
- **Telegram** — دامنه عمومی، sync، webhook
- **SSL** — صدور/تمدید گواهی از WP
- **Server** — nginx، restart، update، لاگ
- **Setup** — wizard راه‌اندازی

**مهم:** وردپرس فقط به `https://IP_VPS` (پورت 443، cert خودامضا) وصل می‌شود. **دامنه** فقط برای webhook تلگرام است.

بعد از Save، **auto-sync** خودکار: config + domains + nginx render با IP وردپرس.

### ب) پنل ترمینال VPS (whiptail — شبیه Hiddify)

```bash
sudo svp-relay
```

منوی dialog بنفش با whiptail. برای اکثر کارهای سرویس/nginx/SSL به **sudo** نیاز دارید.

اگر دستور پیدا نشد:

```bash
sudo node /opt/svp-relay/dist/cli/svp-relay.js panel
```

### منوی اصلی

| # | منو | کاربرد |
|---|-----|--------|
| 1 | Dashboard | وضعیت: uptime، صف forward، tenantها، دامنه‌ها، systemd، nginx |
| 2 | Service | start / stop / restart / status |
| 3 | Tenants | لیست tenantهای sync‌شده از WP |
| 4 | Domains | افزودن / حذف hostname برای nginx |
| 5 | SSL | صدور یا تمدید گواهی (certbot یا acme.sh) |
| 6 | Nginx | render، تست (`nginx -t`)، reload |
| 7 | WordPress setup | چک‌لیست + نمایش secret |
| 8 | Logs | `journalctl` آخرین خطوط یا follow |
| 9 | Doctor | بررسی Node، secret، tenant، nginx |
| 10 | Install / update | اجرای مجدد installer |
| 0 | Exit | خروج |

### نکات پنل

- **توکن ربات و ثبت webhook** از پنل VPS انجام نمی‌شود — فقط از داشبورد WP
- بعد از تغییر دامنه: Domains → Nginx render → SSL (در صورت نیاز) → reload
- برای اسکریپت‌نویسی همان subcommandها موجودند (بخش [مرجع CLI](#۱۲-مرجع-cli))

---

## ۶. SSL و nginx

### سناریوی معمول (نصب بدون SSL)

اگر اول بدون `--ssl` نصب کردید:

1. `sudo svp-relay` → **Domains** → دامنه را اضافه کنید (`tg.example.com`)
2. **SSL** → Issue certificate → domain + certbot + email
3. **Nginx** → Render config → Test → Reload

### دستورات CLI معادل

```bash
svp-relay domain add tg.example.com
svp-relay nginx render
svp-relay ssl issue tg.example.com --method certbot --email you@example.com
nginx -t && systemctl reload nginx
```

### تمدید SSL

از پنل: **SSL → Renew**

یا:

```bash
svp-relay ssl renew --method certbot
```

certbot معمولاً با cron خودکار تمدید می‌کند؛ بعد از تمدید nginx را reload کنید.

### چند دامنه

هر hostname جدید (مثلاً برای نماینده):

```bash
svp-relay domain add tg2.example.com
svp-relay nginx render
# SSL جدا برای هر دامنه
svp-relay ssl issue tg2.example.com --method certbot --email you@example.com
```

در وردپرس هم **Sync domains** بزنید تا لیست به relay برسد.

---

## ۷. اتصال وردپرس

### مسیر در داشبورد

**تنظیمات سایت → Telegram relay** (تب relay)

### فیلدها

| فیلد | مقدار نمونه | توضیح |
|------|-------------|--------|
| **Enabled** | روشن | فعال‌سازی relay |
| **Force** | معمولاً خاموش | حتی بدون enabled، اگر base URL باشد relay اجباری شود |
| **VPS IP** | `203.0.113.5` | IP سرور relay |
| **Admin URL** | `https://203.0.113.5` | API مدیریت از وردپرس (443، self-signed) |
| **Public URL** | `https://tg.example.com` | فقط webhook تلگرام |
| **Admin SSL verify** | خاموش | برای cert خودامضای IP |
| **WP forward URL** | خالی یا `https://goatvps.ir` | اگر WP روی دامنه دیگری است؛ خالی = همان site_url |
| **Allowed IPs** | IP هاست WP | اختیاری؛ محدود کردن internal API به IP وردپرس |
| **Shared secret** | همان `RELAY_MASTER_SECRET` | باید دقیقاً با `.env` روی VPS یکی باشد |

### ترتیب دقیق راه‌اندازی

```
۱. Save تنظیمات relay در WP (enabled + URLs + secret)
۲. Sync config        → tenant_id روی WP ذخیره می‌شود
۳. Sync domains       → لیست دامنه‌ها به relay می‌رود
۴. روی VPS: domain add + nginx render (+ SSL)
۵. Register webhook via relay  → تلگرام webhook را روی relay ثبت می‌کند
۶. Test connection    → بررسی ارتباط WP ↔ relay
```

### دکمه‌های داشبورد

| دکمه | عملکرد |
|------|--------|
| Save | ذخیره فیلدهای فرم |
| Refresh status | وضعیت relay (uptime، queue، domains) |
| Test connection | تست `/internal/health` |
| Sync config | ارسال توکن ربات اصلی + تنظیمات به relay |
| Sync domains | همگام‌سازی لیست دامنه‌ها |
| Set webhook | `setWebhook` تلگرام با URL relay |
| Rotate secret | secret جدید — **باید روی VPS `.env` هم به‌روز شود** |

### آدرس webhook نهایی

برای ربات اصلی (شکل کلی):

```
https://tg.example.com/webhook/telegram/<webhook_secret>
```

`webhook_secret` از تنظیمات ربات در WP می‌آید؛ relay آن را در path می‌گذارد.

### اگر Sync config خطا داد

- Base URL درست است؟ (`curl` از سرور WP به relay)
- Shared secret یکسان است؟
- فایروال VPS پورت 443 را باز کرده؟
- در VPS: `svp-relay` → Doctor

---

## ۸. ربات‌های نماینده (Reseller)

هر نماینده می‌تواند دامنه relay جدا داشته باشد.

### در وردپرس

1. پروفایل ربات نماینده → فیلد **Telegram relay public URL** (اختیاری)
2. اگر خالی باشد از Public URL پیش‌فرض tenant استفاده می‌شود
3. Sync config / domains / set webhook برای آن نماینده

### در VPS

```bash
svp-relay domain add tg-reseller.example.com
svp-relay nginx render
svp-relay ssl issue tg-reseller.example.com --method certbot --email you@example.com
```

Webhook نماینده:

```
https://tg-reseller.example.com/webhook/telegram/reseller/<id>/<secret>
```

---

## ۹. به‌روزرسانی Relay

پوشه `/opt/svp-relay` **repo گیت نیست**. برای آپدیت:

```bash
curl -fsSL https://raw.githubusercontent.com/arsalanarghavan/simplevpbot/main/relay-server/scripts/update-from-github.sh | sudo bash
```

این کار:

- `.env` و `data/tenants/` را **حفظ** می‌کند
- کد جدید را می‌آورد و `npm ci && npm run build` می‌زند
- `/usr/local/bin/svp-relay` را نصب می‌کند
- سرویس را restart می‌کند

### قوانین مهم

```bash
# ❌ اشتباه — مالکیت فایل‌ها خراب می‌شود
sudo npm ci

# ✅ درست
sudo -u svp-relay npm ci
sudo -u svp-relay npm run build
```

### آپدیت دستی با rsync (از ماشین توسعه)

```bash
rsync -av --delete \
  --exclude node_modules --exclude data --exclude .env \
  ./relay-server/ user@VPS:/opt/svp-relay/

# روی VPS:
sudo chown -R svp-relay:svp-relay /opt/svp-relay
cd /opt/svp-relay && sudo -u svp-relay npm ci && sudo -u svp-relay npm run build
sudo bash /opt/svp-relay/scripts/install-cli-bin.sh /opt/svp-relay
sudo systemctl restart svp-relay
```

---

## ۱۰. Deploy وردپرس

بعد از تغییر PHP یا داشبورد relay در repo:

1. فایل‌های پلاگین را روی `goatvps.ir` آپلود کنید
2. `frontend/dist/` build جدید داشبورد را deploy کنید
3. migration دیتابیس خودکار با نسخه پلاگین اجرا می‌شود (`telegram_relay_*` settings، `telegram_relay_public_url` برای reseller)

```bash
# در ماشین توسعه
cd frontend && npm run build
# خروجی در frontend/dist/
```

---

## ۱۱. عیب‌یابی

### `504` از تلگرام هنوز می‌آید

- webhook واقعاً روی relay است؟ (دکمه Set webhook + diagnostics در WP)
- `curl -I https://tg.example.com/health` از بیرون
- relay up است؟ `systemctl status svp-relay`

### `svp-relay: command not found`

```bash
sudo bash /opt/svp-relay/scripts/install-cli-bin.sh /opt/svp-relay
which svp-relay
```

### خطای `Unexpected identifier 'pipefail'`

فایل `dist/cli/svp-relay.js` با اسکریپت bash overwrite شده (باگ symlink قدیمی). رفع:

```bash
cd /opt/svp-relay
sudo -u svp-relay npm run build
sudo rm -f /usr/local/bin/svp-relay
sudo bash /opt/svp-relay/scripts/install-cli-bin.sh /opt/svp-relay
head -1 /opt/svp-relay/dist/cli/svp-relay.js   # باید #!/usr/bin/env node باشد
```

### `fatal: not a git repository` در `/opt/svp-relay`

طبیعی است. از `update-from-github.sh` استفاده کنید، نه `git pull`.

### Sync config → forbidden / 403

- Shared secret یکسان نیست
- `ALLOWED_WP_IPS` روی VPS تنظیم شده و IP WP در لیست نیست

### Host mismatch در لاگ relay

Public URL در WP با `Host` درخواست webhook فرق دارد. دامنه را در Domains و nginx و WP یکسان کنید.

### صف forward پر می‌ماند

- WP down یا کند است
- `telegram_relay_wp_forward_url` اشتباه
- لاگ: `svp-relay` → Logs

### بررسی سریع

```bash
svp-relay doctor
svp-relay status
curl -s http://127.0.0.1:8787/health
journalctl -u svp-relay -n 50 --no-pager
```

---

## ۱۲. مرجع CLI

```bash
svp-relay                    # پنل تعاملی
svp-relay panel              # همان پنل
svp-relay help

svp-relay status             # JSON وضعیت
svp-relay tenants list
svp-relay tenant show <tenant_id>
svp-relay domains list
svp-relay domain add <host> [--tenant id]
svp-relay domain remove <host> [--tenant id]
svp-relay nginx render [--out path]
svp-relay ssl issue <host> [--method certbot|acme] [--email addr]
svp-relay ssl renew [--method certbot|acme]
svp-relay config migrate
svp-relay doctor
svp-relay install [flags]
```

### API داخلی (برای debug)

هدر احراز هویت: `X-SVP-Relay-Secret: <RELAY_MASTER_SECRET>`

| مسیر | کاربرد |
|------|--------|
| `GET /health` | بدون auth — فقط زنده بودن process |
| `GET /internal/status` | uptime، queue، tenants |
| `POST /internal/config` | upsert tenant از WP |
| `POST /internal/set-webhook` | ثبت webhook |
| `POST /webhook/telegram/:secret` | inbound تلگرام (ربات اصلی) |

---

## ۱۳. فایل‌ها و متغیرهای محیطی

### `.env` در `/opt/svp-relay`

| متغیر | پیش‌فرض | توضیح |
|-------|---------|--------|
| `PORT` | `8787` | پورت داخلی relay |
| `RELAY_MASTER_SECRET` | (نصب تصادفی) | secret اصلی + CLI |
| `RELAY_SHARED_SECRET` | همان master | سازگاری legacy |
| `DATA_DIR` | `/opt/svp-relay/data` | داده |
| `TENANTS_DIR` | `.../data/tenants` | JSON هر tenant |
| `NGINX_CONFIG_PATH` | `/etc/nginx/sites-available/svp-relay.conf` | خروجی nginx render |
| `ALLOWED_WP_IPS` | خالی | IPهای مجاز WP (اختیاری) |

### tenant JSON

`/opt/svp-relay/data/tenants/<uuid>.json` — شامل توکن‌ها (حساس). دسترسی فقط user `svp-relay`.

---

## ۱۴. امنیت

- `.env` و tenant files را backup بگیرید؛ در git commit **نکنید**
- `RELAY_MASTER_SECRET` را مثل رمز عبور نگه دارید
- در production `ALLOWED_WP_IPS` را با IP واقعی سرور WP پر کنید
- relay فقط پورت 80/443 را از بیرون expose کند؛ `8787` فقط localhost
- بعد از Rotate secret در WP، حتماً `.env` VPS را به‌روز کنید

---

## ۱۵. چک‌لیست راه‌اندازی سریع

### VPS

- [ ] DNS ساب‌دامنه به IP VPS
- [ ] `install-from-github.sh` یا `install.sh`
- [ ] `RELAY_MASTER_SECRET` کپی شد
- [ ] `curl http://127.0.0.1:8787/health` → ok
- [ ] `sudo svp-relay` → Dashboard سبز
- [ ] Domain + SSL + nginx render/reload

### وردپرس

- [ ] پلاگین + dashboard deploy
- [ ] Relay enabled، URLs، shared secret
- [ ] Save → Sync config → Sync domains
- [ ] Register webhook via relay
- [ ] Test connection
- [ ] پیام تست به ربات در تلگرام

### بعد از راه‌اندازی

- [ ] `svp-relay doctor` بدون خطای جدی
- [ ] webhook info تلگرام URL relay را نشان می‌دهد
- [ ] لاگ `journalctl -u svp-relay` خطای مکرر ندارد

---

## پیوندها

- README فنی انگلیسی: [README.md](./README.md)
- Repo: https://github.com/arsalanarghavan/simplevpbot

---

*آخرین به‌روزرسانی: هم‌راستا با کنترل پنل `svp-relay` و اسکریپت‌های `install.sh` / `update-from-github.sh`.*
