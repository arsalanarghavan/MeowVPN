# TLS cert rotation verify — v24

Baseline: [`tls-curl-2026-06-16-prod-v23.log`](tls-curl-2026-06-16-prod-v23.log)

## Monthly verify

```bash
curl -sSI https://api.simplevpbot.ir/health | head -10
openssl s_client -connect api.simplevpbot.ir:443 -servername api.simplevpbot.ir </dev/null 2>/dev/null | openssl x509 -noout -dates
```

Log output to `docs/evidence/tls-curl-$(date +%F).log`

Operator / date: 2026-06-13
