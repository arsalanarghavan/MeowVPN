# Staging Infra v14 (§17 #26–37)

| # | Verify | Evidence |
|---|--------|----------|
| 26–27 | workers + 14 cron | `docker compose ps`, `schedule:list` |
| 28–29 | Redis + MySQL backup | config + cron ticket |
| 30–32 | env parity | redacted `.env` diff |
| 33–35 | observability + soak 86400 | Grafana + `soak-24h-*.log` |
| 36–37 | cutover checklist + 6 signoffs | `CUTOVER-SIGNOFF-FA.md` |
