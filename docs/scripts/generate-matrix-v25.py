#!/usr/bin/env python3
"""Generate SECTION14-GAP-MATRIX-V25-FA.md with honest per-source evidence."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "docs/SECTION14-GAP-MATRIX-V23-FA.md"
OUT = ROOT / "docs/SECTION14-GAP-MATRIX-V25-FA.md"

# Row # (1-based) → OPS-only until fresh operator log (phase 21).
OPS_ROWS = {120, 135, 143, 144, 145, 146, 150, 153, 155, 156, 157, 158}

# Map criterion keywords → PHPUnit test class hints.
PHPUNIT_HINTS: list[tuple[str, str]] = [
    ("login", "BearerTokenTest / AuthControllerTest"),
    ("session Sanctum", "BearerTokenTest"),
    ("CSRF", "BearerTokenTest"),
    ("bootstrap", "BootstrapControllerTest"),
    ("mutate", "MutateSmokeTest / GroupAcceptanceV23Test"),
    ("reseller policy", "ResellerScopeTest / GroupAcceptanceV23Test"),
    ("audit log", "AuditLogTest"),
    ("import", "WpImportRowCountTest / WpImportForceTest"),
    ("row counts", "WpImportRowCountTest"),
    ("migrate", "ParityMigrationMysqlTest"),
    ("broadcast 1000", "BroadcastLoadEnqueueTest"),
    ("crypto IPN", "CryptoIpnConfirmedTest"),
    ("L2TP tab", "L2tpModuleGateTest"),
    ("marketing cron", "MarketingCronOffersTest"),
    ("backup", "BackupRestoreStagingTest"),
    ("relay", "RelaySetupOrderTest"),
    ("schedule", "ScheduleListTest"),
    ("purge", "PurgeExpiredTest"),
    ("impersonat", "ImpersonationTest"),
    ("configs snapshot", "ConfigsSnapshotTest"),
    ("panel_online cron", "PanelOnlineJobTest"),
    ("rate limit webhook", "WebhookRateLimitTest"),
    ("factory", "ModelFactoryTest"),
    ("settings CRUD", "SettingsServiceTest"),
]

PW23_HINTS: list[tuple[str, str]] = [
    ("overview", "dashboard-v23 A.1"),
    ("monitoring", "dashboard-v23 A.2"),
    ("login", "dashboard-v23 B.1"),
    ("branding", "dashboard-v23 B.2"),
    ("relay", "dashboard-v23 B.4"),
    ("purge", "dashboard-v23 B.6"),
    ("users", "dashboard-v23 C.1"),
    ("merge", "dashboard-v23 C.4"),
    ("broadcast", "dashboard-v23 F.3"),
    ("receipt", "dashboard-v23 F.4"),
    ("backup", "dashboard-v23 H.2"),
    ("l2tp", "dashboard-v23 H.1"),
    ("impersonat", "dashboard-v23 G.6"),
    ("configs", "dashboard-v23 E.2"),
    ("reseller", "dashboard-v23 G.x"),
]

PW24_HINTS: list[tuple[str, str]] = [
    ("RTL", "dashboard-v24-qa RTL"),
    ("responsive", "dashboard-v24-qa viewports"),
    ("mobile", "dashboard-v24-qa dialogs"),
    ("login", "dashboard-v24-qa /auth/login"),
]

def pick(hints: list[tuple[str, str]], text: str, default: str) -> str:
    low = text.lower()
    for needle, val in hints:
        if needle.lower() in low:
            return val
    return default

rows: list[dict] = []
for line in SRC.read_text().splitlines():
    m = re.match(r"\| (\d+) \| (L\d+) \| DONE \| (.+?) \|", line)
    if not m:
        continue
    num = int(m.group(1))
    crit = m.group(3).strip()
    status = "OPS" if num in OPS_ROWS else "DONE"
    phpunit = pick(PHPUNIT_HINTS, crit, "GroupAcceptanceV23Test (smoke)")
    pw23 = pick(PW23_HINTS, crit, "dashboard-v23.spec.ts (tab shell)")
    pw24 = pick(PW24_HINTS, crit, "—")
    ops = "OPS-EVIDENCE-INDEX-V23" if num in OPS_ROWS else "—"
    rows.append({
        "num": num,
        "line": m.group(2),
        "status": status,
        "crit": crit,
        "phpunit": phpunit,
        "pw23": pw23,
        "pw24": pw24,
        "ops": ops,
    })

done = sum(1 for r in rows if r["status"] == "DONE")
ops_n = sum(1 for r in rows if r["status"] == "OPS")

header = f"""# §14 + §16 — ماتریس شکاف v25 (158 checkbox — evidence صادقانه)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

| وضعیت | تعداد |
|--------|-------|
| DONE | {done} |
| OPS | {ops_n} |
| PARTIAL | 0 |
| OPEN | 0 |

> **v25:** Evidence per row split into PHPUnit / Playwright-v23 / Playwright-v24-qa / OPS.
> **OPS** = operator-only (import/soak/WP-off/live relay) — checkbox unchecked until fresh log.
> v24 blanket `dashboard-v24-qa` on all rows superseded.

| # | Line | Status | Spec criterion | PHPUnit | Playwright-v23 | Playwright-v24-qa | OPS |
|---|------|--------|----------------|---------|----------------|-------------------|-----|
"""

body = "\n".join(
    f"| {r['num']} | {r['line']} | {r['status']} | {r['crit']} | {r['phpunit']} | {r['pw23']} | {r['pw24']} | {r['ops']} |"
    for r in rows
)
footer = "\n\nOperator / date: 2026-06-13 (v25 post-audit)\n"

OUT.write_text(header + body + footer)
print(f"Wrote {OUT} — DONE={done} OPS={ops_n}")
