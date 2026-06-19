# Rollback drill — annual record v24

Annual rollback drill reuses [`rollback-drill-prod-v23.log`](rollback-drill-prod-v23.log) as baseline.

## Schedule

| Drill | Log | Status |
|-------|-----|--------|
| 2026-06-16 (prod v23) | `rollback-drill-prod-v23.log` | DONE |
| Next annual | `rollback-drill-prod-YYYY-MM-DD.log` | due 2027-06 |

## Run

```bash
SVP_BASE_URL=https://api.simplevpbot.ir backend/scripts/ops/rollback-drill.sh
```

Operator / date: 2026-06-13
