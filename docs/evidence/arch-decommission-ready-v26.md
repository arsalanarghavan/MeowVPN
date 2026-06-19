# ARCH-12 decommission ready — v26

## Evidence

| Item | Reference |
|------|-----------|
| WP root archived | `archive/wp-plugin-root/` |
| Spec 158/158 | [`SECTION14-GAP-MATRIX-V26-FA.md`](../SECTION14-GAP-MATRIX-V26-FA.md) |
| OPS v26 re-verify | [`OPS-EVIDENCE-INDEX-V26.md`](OPS-EVIDENCE-INDEX-V26.md) |
| Laravel dashboard alias | `routes/api.php` + nginx `default.conf` |
| Playwright CI | v23 + v24-qa + **v25-depth** |
| Docker smoke | `ci.yml` + `docker-smoke-v26.log` |

## Git tag

```bash
git tag -a arch-decommission-v26 -m "ARCH-12 complete — 158/158 spec v26"
```

Operator / date: 2026-06-13
