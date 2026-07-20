#!/usr/bin/env python3
"""Fix broken i18n from first fix pass."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ADMIN = ROOT / "frontend" / "src" / "components" / "admin"

TARGETS = [
    ADMIN / "broadcast-admin-client.tsx",
    ADMIN / "discounts-admin-client.tsx",
    ADMIN / "marketing-lifecycle-admin-client.tsx",
    ADMIN / "plan-cats-admin-client.tsx",
    ADMIN / "referral-admin-client.tsx",
    ADMIN / "reseller-reports-admin-client.tsx",
    ADMIN / "resellers-admin-client.tsx",
    ADMIN / "unit-economics-admin-client.tsx",
    ADMIN / "users-bulk-admin-client.tsx",
    ADMIN / "configs/configs-admin-core.tsx",
    ADMIN / "users/users-admin-core.tsx",
    ADMIN / "users/user-detail-admin.tsx",
    ADMIN / "users/user-merge-admin.tsx",
]


def fix(text: str) -> str:
    # Remove broken tp/tl/tr helpers
    text = re.sub(
        r"\n\s*const tl = useCallback\(\s*\n\s*\(k: string, opts\?: Record<string, string \| number>\) => t\(\"\\$\{k\}\", opts\),\s*\n\s*\[t\]\s*\n\s*\)",
        "",
        text,
    )
    text = re.sub(
        r"\n\s*const tp = \(k: string(?:, opts\?: Record<string, string \| number>)?\) => t\(\"\\$\{k\}\"(?:, opts)?\)",
        "",
        text,
    )
    text = re.sub(
        r"\n\s*const tr = \(k: string, opts\?: Record<string, string \| number>\) => t\(\"\\$\{k\}\", opts\)",
        "",
        text,
    )
    text = re.sub(
        r"\n\s*const tp = \(k: string, opts\?: Record<string, string \| number>\) =>\s*\n\s*t\(\"\\$\{k\}\", opts\)",
        "",
        text,
    )

    text = text.replace("tp(", "t(")
    text = text.replace("tl(", "t(")
    text = text.replace("tr(", "t(")

    # Fix accidental double-quoted template keys -> backticks
    text = re.sub(r't\("([^"]*\$\{[^"]+\})"', r"t(`\1`", text)

    return text


def add_cross_ns(text: str, path: Path) -> str:
    if "resellers-admin-client" in str(path) or "reseller-reports-admin-client" in str(path):
        if 'useTranslations("usersAdmin")' not in text:
            text = text.replace(
                'const t = useTranslations("resellersAdmin")',
                'const t = useTranslations("resellersAdmin")\n  const tUsers = useTranslations("usersAdmin")',
                1,
            ) if "resellers-admin" in str(path) else text.replace(
                'const t = useTranslations("resellerReportsAdmin")',
                'const t = useTranslations("resellerReportsAdmin")\n  const tUsers = useTranslations("usersAdmin")',
                1,
            )
        text = text.replace('t(`usersAdmin.status_${st}`)', 'tUsers(`status_${st}`)')
        text = text.replace('t("usersAdmin.status_pending")', 'tUsers("status_pending")')
        text = text.replace('t("usersAdmin.status_approved")', 'tUsers("status_approved")')
        text = text.replace('t("usersAdmin.status_rejected")', 'tUsers("status_rejected")')
        text = text.replace('t("usersAdmin.status_blocked")', 'tUsers("status_blocked")')
        text = text.replace('t("usersAdmin.colPhone")', 'tUsers("colPhone")')

    if "user-detail-admin" in str(path):
        if 'useTranslations("usersAdmin")' not in text:
            text = text.replace(
                'const t = useTranslations("userDetailAdmin")',
                'const t = useTranslations("userDetailAdmin")\n  const tUsers = useTranslations("usersAdmin")',
                1,
            )
        text = text.replace('t(`usersAdmin.status_${st}`', 'tUsers(`status_${st}`')

    return text


def main() -> None:
    for path in TARGETS:
        if not path.exists():
            continue
        text = fix(path.read_text(encoding="utf-8"))
        text = add_cross_ns(text, path)
        path.write_text(text, encoding="utf-8")
        print(f"fixed {path.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
