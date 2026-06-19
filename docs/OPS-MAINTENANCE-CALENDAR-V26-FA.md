# OPS maintenance calendar — v26

## Quarterly production sign-off

| Item | آخرین تأیید | بعدی |
|------|-------------|------|
| Portal admin `?svp_adm=1` | 2026-06-13 (v26 bundle) | **2026-09-16** |
| Portal sub plain + HTML | 2026-06-13 | **2026-09-16** |
| Bot webhook (direct + relay) | 2026-06-13 | **2026-09-16** |
| Crypto IPN test transaction | 2026-06-13 | **2026-09-16** |

```bash
SVP_BASE_URL=https://api.simplevpbot.ir backend/scripts/ops/quarterly-signoff.sh
SVP_BASE_URL=https://api.simplevpbot.ir backend/scripts/ops/run-v26-evidence.sh
```

## Monthly verify

```bash
SVP_BASE_URL=https://api.simplevpbot.ir \
  SVP_VERIFY_LOG=docs/evidence/monthly-verify-$(date +%F)-v26.log \
  backend/scripts/ops/monthly-verify.sh
curl -sSI https://api.simplevpbot.ir/health 2>&1 | tee docs/evidence/tls-curl-$(date +%F)-v26.log
```

## Index

- OPS v26: [`OPS-EVIDENCE-INDEX-V26.md`](evidence/OPS-EVIDENCE-INDEX-V26.md)
- Matrix: [`SECTION14-GAP-MATRIX-V26-FA.md`](SECTION14-GAP-MATRIX-V26-FA.md) — **158/158 DONE**

Operator / date: 2026-06-13
