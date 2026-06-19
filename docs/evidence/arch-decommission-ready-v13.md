# ARCH-12 — WP Decommission Commit Readiness v13

**Status:** `includes/` staged for removal (v11 `CONFIRM=1 remove-includes-from-main.sh`).

**Before commit/push:**
- [ ] Ops sign-off on import-verify log
- [ ] 6 manual signoffs in CUTOVER-SIGNOFF
- [ ] DNS cutover complete

**Commands (operator):**
```bash
git status  # verify includes/ deleted
git commit -m "chore: remove WP plugin sources after Laravel cutover"
git push
```

Agent does not auto-commit per project policy.
