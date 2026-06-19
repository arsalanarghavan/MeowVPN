# سیاست نگهداری Snapshot وردپرس

## خلاصه

پس از cutover به Laravel، snapshotهای MySQL وردپرس **حداقل ۳۰ روز** نگهداری می‌شوند.

## محدوده

| نوع | مسیر / نام | مدت |
|-----|------------|-----|
| Dump نهایی pre-cutover | `wp-final-YYYY-MM-DD.sql` | 30 روز |
| Dump میانی import | `docs/evidence/mysql-dump-prod-v*.log` | 90 روز (evidence) |
| فایل‌های backup ZIP WP | `storage/app/backups/` (import شده) | طبق تنظیم backup Laravel |

## اقدامات

1. **روز ۰ (cutover):** `mysqldump wordpress_db > wp-final-$(date +%F).sql`
2. **روز ۱–۳۰:** snapshot فقط read-only؛ rollback ممکن طبق [`WP-DECOMMISSION-FA.md`](WP-DECOMMISSION-FA.md)
3. **روز ۳۱+:** حذف dump پس از تأیید ops (ticket + log)

## Rollback window

تا پایان روز ۳۰: restore snapshot + DNS revert + relay forward به WP (موقت).

## Evidence

- [`wp-post-cutover-v23.md`](evidence/wp-post-cutover-v23.md)
- [`OPS-EVIDENCE-INDEX-V23.md`](evidence/OPS-EVIDENCE-INDEX-V23.md) p01

Operator / date: 2026-06-13
