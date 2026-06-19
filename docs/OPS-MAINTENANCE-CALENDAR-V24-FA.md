# تقویم نگهداری OPS — v24

## Quarterly production sign-off

| Item | آخرین تأیید | بعدی |
|------|-------------|------|
| Portal admin `?svp_adm=1` | 2026-06-16 | **2026-09-16** |
| Portal sub plain + HTML | 2026-06-16 | **2026-09-16** |
| Bot webhook (direct + relay) | 2026-06-16 | **2026-09-16** |
| Crypto IPN test transaction | 2026-06-16 | **2026-09-16** |

مرجع: [`CUTOVER-SIGNOFF-FA.md`](evidence/CUTOVER-SIGNOFF-FA.md) § v23 manual sign-off

```bash
SVP_BASE_URL=https://api.simplevpbot.ir backend/scripts/ops/quarterly-signoff.sh
```

## Monthly verify

| Item | فرکانس | اسکریپت |
|------|--------|---------|
| Scheduler 14 jobs | monthly | `backend/scripts/ops/monthly-verify.sh` |
| Dashboard login + mutate smoke | monthly | همان اسکریپت |
| TLS cert expiry | monthly | `curl -sSI https://$HOST/health` → log `docs/evidence/tls-curl-YYYY-MM-DD.log` |

**Reminder:** Run on the 1st of each month:

```bash
SVP_BASE_URL=https://api.simplevpbot.ir SVP_VERIFY_LOG=docs/evidence/monthly-verify-$(date +%F).log \
  backend/scripts/ops/monthly-verify.sh
curl -sSI https://api.simplevpbot.ir/health 2>&1 | tee docs/evidence/tls-curl-$(date +%F).log
```

## Annual

| Item | فرکانس | Evidence |
|------|--------|----------|
| Rollback drill | yearly | `rollback-drill-prod-v*.log` |
| Secret rotation full pass | yearly | [`SECRET-ROTATION-CALENDAR-FA.md`](SECRET-ROTATION-CALENDAR-FA.md) |

## Index

- OPS v23: [`OPS-EVIDENCE-INDEX-V23.md`](evidence/OPS-EVIDENCE-INDEX-V23.md)
- Runbook: [`RUNBOOK-PRODUCTION-FA.md`](RUNBOOK-PRODUCTION-FA.md)

Operator / date: 2026-06-13
