#!/usr/bin/env python3
"""Post-fix ported admin components for next-intl key paths."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ADMIN = ROOT / "frontend" / "src" / "components" / "admin"

FILES = list(ADMIN.rglob("*.tsx"))


def fix_file(path: Path) -> bool:
    text = path.read_text(encoding="utf-8")
    orig = text

    # Remove tp/tl/tr helper definitions
    text = re.sub(
        r"\n\s*const tl = useCallback\(\s*\n\s*\(k: string, opts\?: Record<string, string \| number>\) => t\(`[^`]+`\$\{k\}`, opts\),\s*\n\s*\[t\]\s*\n\s*\)",
        "",
        text,
    )
    text = re.sub(
        r"\n\s*const tp = \(k: string(?:, opts\?: Record<string, string \| number>)?\) => t\(`[^`]+`\$\{k\}`(?:, opts)?\)",
        "",
        text,
    )
    text = re.sub(
        r"\n\s*const tr = \(k: string, opts\?: Record<string, string \| number>\) => t\(`[^`]+`\$\{k\}`, opts\)",
        "",
        text,
    )
    text = re.sub(
        r"\n\s*const tp = \(k: string, opts\?: Record<string, string \| number>\) =>\s*\n\s*t\(`[^`]+`\$\{k\}`, opts\)",
        "",
        text,
    )

    text = text.replace("tp(", "t(")
    text = text.replace("tl(", "t(")
    text = text.replace("tr(", "t(")

    # Fix double-prefixed template keys: t(`fooAdmin.bar`) when t = useTranslations("fooAdmin")
    m = re.search(r'const t = useTranslations\("([a-zA-Z0-9_]+)"\)', text)
    if m:
        ns = m.group(1)
        text = re.sub(rf"t\(`{re.escape(ns)}\.([^`]+)`", r't("\1"', text)
        text = re.sub(rf't\("{re.escape(ns)}\.', 't("', text)

    # configs inboundLinkAdmin cross-namespace
    if "configs-admin-core" in str(path):
        if "tInbound" not in text:
            text = text.replace(
                'const t = useTranslations("configsAdmin")',
                'const t = useTranslations("configsAdmin")\n  const tInbound = useTranslations("inboundLinkAdmin")',
                1,
            )
        text = re.sub(r't\("inboundLinkAdmin\.([^"]+)"\)', r'tInbound("\1")', text)

    # users merge import path
    text = text.replace(
        '@/components/dashboard-user-merge-admin"',
        '@/components/admin/users/user-merge-admin"',
    )
    text = text.replace("DashboardUserMergeAdmin", "UserMergeAdmin")

    # user detail import in users core
    text = text.replace(
        '@/components/dashboard-user-detail-admin"',
        '@/components/admin/users/user-detail-admin"',
    )
    text = text.replace("DashboardUserDetailAdmin", "UserDetailAdmin")

    if text != orig:
        path.write_text(text, encoding="utf-8")
        return True
    return False


def main() -> None:
    changed = 0
    for f in FILES:
        if fix_file(f):
            print(f"fixed {f.relative_to(ROOT)}")
            changed += 1
    print(f"done ({changed} files)")


if __name__ == "__main__":
    main()
