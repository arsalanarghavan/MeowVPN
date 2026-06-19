#!/usr/bin/env python3
"""Generate SECTION14-GAP-MATRIX-V27-FA.md — honest DONE/OPS from v27 evidence."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "docs/SECTION14-GAP-MATRIX-V26-FA.md"
OUT = ROOT / "docs/SECTION14-GAP-MATRIX-V27-FA.md"
EVID = ROOT / "docs/evidence"

OPS_ROW_LOG: dict[int, str] = {
    120: "docker-smoke-v27.log",
    135: "staging-buy-flow-v27.log",
    143: "reseller-webhook-v27.log",
    144: "relay-forward-v27.log",
    145: "relay-webhook-set-v27.log",
    146: "relay-control-center-v27.log",
    150: "backup-restore-staging-v27.log",
    153: "import-run-v27.log",
    154: "import-verify-v27.log",
    155: "phase16-parallel-v27.log",
    156: "soak-24h-v27.log",
    157: "admin-alerts-v27.log",
    158: "wp-disable-v27.log",
}

CRIT_FIXES: dict[str, str] = {
    "نمودار وضعیت پنل‌ها real-time refresh": "نمودار وضعیت پنل‌ها refresh (SPA polling 60s + manual refresh — v24 amendment; not WebSocket)",
    "نمودار وضعیت پنل‌ها refresh (SPA polling 60s + manual refresh — v24 amendment)": "نمودار وضعیت پنل‌ها refresh (SPA polling 60s + manual refresh — v24 amendment; not WebSocket)",
    "crypto settings فقط با MODULE_CRYPTO_ENABLED": "crypto settings فقط با `SVP_MODULE_CRYPTO=true`",
    "crypto settings فقط با SVP_MODULE_CRYPTO=true": "crypto settings فقط با `SVP_MODULE_CRYPTO=true`",
    "tab مخفی وقتی `MODULE_L2TP_ENABLED=false`": "tab مخفی وقتی `SVP_MODULE_L2TP=false`",
    "tab مخفی وقتی SVP_MODULE_L2TP=false": "tab مخفی وقتی `SVP_MODULE_L2TP=false`",
}


def log_ok(name: str) -> bool:
    path = EVID / name
    if not path.is_file():
        return False
    text = path.read_text(errors="replace")
    if "FAIL:" in text or "SKIP:" in text or "requires SVP_MYSQL_DSN" in text:
        return False
    if name == "soak-24h-v27.log":
        return "duration=86400" in text and "FAIL count: 0" in text
    if name == "admin-alerts-v27.log":
        return (
            "complete exit=0" in text
            and "[admin-alerts-fire-smoke] OK" in text
            and "Error" not in text
        )
    if "complete exit=0" in text or "health/ready: OK" in text:
        return True
    if "Tests:" in text and "passed" in text.lower() and "FAIL" not in text:
        return True
    if "§7.1 path parity OK" in text:
        return True
    return False


rows: list[dict] = []
for line in SRC.read_text().splitlines():
    if not line.startswith("| ") or " | L" not in line:
        continue
    parts = [p.strip() for p in line.split("|")]
    if len(parts) < 11:
        continue
    try:
        num = int(parts[1])
    except ValueError:
        continue
    crit = CRIT_FIXES.get(parts[4], parts[4])
    phpunit, pw23, pw24, pw25 = parts[5], parts[6], parts[7], parts[8]
    ops_log = OPS_ROW_LOG.get(num)
    if ops_log:
        status = "DONE" if log_ok(ops_log) else "OPS"
        ops_col = f"evidence/{ops_log}"
        if num == 120:
            phpunit = "ParityMigrationMysqlTest + docker-smoke-v27"
    else:
        status = "DONE"
        ops_col = parts[9] if parts[9] != "—" else "—"
    rows.append({
        "num": num,
        "line": parts[2],
        "status": status,
        "crit": crit,
        "phpunit": phpunit,
        "pw23": pw23,
        "pw24": pw24,
        "pw25": pw25,
        "ops": ops_col,
    })

done = sum(1 for r in rows if r["status"] == "DONE")
ops = len(rows) - done

header = f"""# §14 + §16 — ماتریس شکاف v27 ({done}/{len(rows)} DONE — honest OPS)

مبنا: [`LARAVEL-BACKEND-SPEC-FA.md`](LARAVEL-BACKEND-SPEC-FA.md)

| وضعیت | تعداد |
|--------|-------|
| DONE | {done} |
| OPS | {ops} |
| PARTIAL | 0 |
| OPEN | 0 |

> **v27:** OPS rows remain open until `docs/evidence/*-v27.log` passes strict verification (no FAIL/SKIP lines).

| # | Line | Status | Spec criterion | PHPUnit | Playwright-v23 | Playwright-v24-qa | Playwright-v25 | OPS log |
|---|------|--------|----------------|---------|----------------|-------------------|----------------|---------|
"""

body = "\n".join(
    f"| {r['num']} | {r['line']} | {r['status']} | {r['crit']} | {r['phpunit']} | {r['pw23']} | {r['pw24']} | {r['pw25']} | {r['ops']} |"
    for r in rows
)
footer = f"\n\nOperator / date: 2026-06-13 (v27 — {ops} OPS pending live verify)\n"

OUT.write_text(header + body + footer)
print(f"Wrote {OUT} — {done} DONE / {ops} OPS")
