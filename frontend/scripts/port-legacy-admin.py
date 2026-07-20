#!/usr/bin/env python3
"""Adapt vite-legacy dashboard admin components to Next.js + next-intl."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LEGACY = ROOT / "frontend-vite-legacy" / "src" / "components"
OUT = ROOT / "frontend" / "src" / "components" / "admin"

PORTS = [
    {
        "src": "dashboard-configs-admin.tsx",
        "dest": "configs/configs-admin-core.tsx",
        "export_from": "DashboardConfigsAdmin",
        "export_to": "ConfigsAdminCore",
        "namespace": "configsAdmin",
        "extra_namespaces": {"inboundLinkAdmin": "inboundLinkAdmin"},
    },
    {
        "src": "dashboard-plan-cats-admin.tsx",
        "dest": "plan-cats-admin-client.tsx",
        "export_from": "DashboardPlanCatsAdmin",
        "export_to": "PlanCatsAdminClient",
        "namespace": "planCatsAdmin",
    },
    {
        "src": "dashboard-broadcast-admin.tsx",
        "dest": "broadcast-admin-client.tsx",
        "export_from": "DashboardBroadcastAdmin",
        "export_to": "BroadcastAdminClient",
        "namespace": "broadcastAdmin",
    },
    {
        "src": "dashboard-users-bulk-admin.tsx",
        "dest": "users-bulk-admin-client.tsx",
        "export_from": "DashboardUsersBulkAdmin",
        "export_to": "UsersBulkAdminClient",
        "namespace": "usersBulkAdmin",
    },
    {
        "src": "dashboard-resellers-admin.tsx",
        "dest": "resellers-admin-client.tsx",
        "export_from": "DashboardResellersAdmin",
        "export_to": "ResellersAdminClient",
        "namespace": "resellersAdmin",
    },
    {
        "src": "dashboard-reseller-reports-admin.tsx",
        "dest": "reseller-reports-admin-client.tsx",
        "export_from": "DashboardResellerReportsAdmin",
        "export_to": "ResellerReportsAdminClient",
        "namespace": "resellerReportsAdmin",
    },
    {
        "src": "dashboard-discounts-admin.tsx",
        "dest": "discounts-admin-client.tsx",
        "export_from": "DashboardDiscountsAdmin",
        "export_to": "DiscountsAdminClient",
        "namespace": "discountsAdmin",
    },
    {
        "src": "dashboard-referral-admin.tsx",
        "dest": "referral-admin-client.tsx",
        "export_from": "DashboardReferralAdmin",
        "export_to": "ReferralAdminClient",
        "namespace": "referralAdmin",
    },
    {
        "src": "dashboard-marketing-lifecycle-admin.tsx",
        "dest": "marketing-lifecycle-admin-client.tsx",
        "export_from": "DashboardMarketingLifecycleAdmin",
        "export_to": "MarketingLifecycleAdminClient",
        "namespace": "marketingLifecycleAdmin",
    },
    {
        "src": "dashboard-unit-economics-admin.tsx",
        "dest": "unit-economics-admin-client.tsx",
        "export_from": "DashboardUnitEconomicsAdmin",
        "export_to": "UnitEconomicsAdminClient",
        "namespace": "unitEconomicsAdmin",
    },
    {
        "src": "dashboard-users-admin.tsx",
        "dest": "users/users-admin-core.tsx",
        "export_from": "DashboardUsersAdmin",
        "export_to": "UsersAdminCore",
        "namespace": "usersAdmin",
    },
    {
        "src": "dashboard-user-detail-admin.tsx",
        "dest": "users/user-detail-admin.tsx",
        "export_from": "DashboardUserDetailAdmin",
        "export_to": "UserDetailAdmin",
        "namespace": "userDetailAdmin",
    },
    {
        "src": "dashboard-user-merge-admin.tsx",
        "dest": "users/user-merge-admin.tsx",
        "export_from": "DashboardUserMergeAdmin",
        "export_to": "UserMergeAdmin",
        "namespace": "userMergeAdmin",
    },
]


def port_content(content: str, namespace: str, export_from: str, export_to: str, extra_namespaces: dict[str, str] | None = None) -> str:
    extra_namespaces = extra_namespaces or {}

    content = re.sub(r'import \{ useTranslation \} from "react-i18next"\n', "", content)
    content = re.sub(r'import type \{ TFunction \} from "i18next"\n', "", content)

    if "useTranslations" not in content:
        content = content.replace(
            '"use client"\n\n',
            '"use client"\n\nimport { useTranslations } from "next-intl"\n',
            1,
        )

    # Remove tl helper block
    content = re.sub(
        r"\n\s*const tl = useCallback\(\s*\n\s*\(k: string, opts\?: Record<string, string \| number>\) => t\(`[^`]+`\$\{k\}`, opts\),\s*\n\s*\[t\]\s*\n\s*\)\n",
        "\n",
        content,
    )
    content = re.sub(
        r"\n\s*const tl = useCallback\(\s*\n\s*\(k: string, opts\?: Record<string, string \| number>\) => t\(`[^`]+`\$\{k\}`, opts\),\s*\n\s*\[t\]\s*\n\s*\)",
        "",
        content,
    )
    content = re.sub(
        r"\n\s*const tp = useCallback\(\s*\n\s*\(k: string\) => t\(`[^`]+`\$\{k\}`\),\s*\n\s*\[t\]\s*\n\s*\)\n",
        "\n",
        content,
    )

    # Replace useTranslation hook
    content = re.sub(
        r"const \{ t(?:, i18n)? \} = useTranslation\(\)",
        f'const t = useTranslations("{namespace}")',
        content,
    )

    for cross_ns, var_name in extra_namespaces.items():
        if f't("{cross_ns}.' in content or f"t('{cross_ns}." in content:
            insert = f'  const {var_name.replace("Admin", "T") if var_name.endswith("Admin") else "t" + cross_ns.title().replace("_", "")} = useTranslations("{cross_ns}")\n'
            if var_name == "inboundLinkAdmin":
                insert = f'  const tInbound = useTranslations("{cross_ns}")\n'
            marker = f'const t = useTranslations("{namespace}")'
            if marker in content and "tInbound" not in content and cross_ns == "inboundLinkAdmin":
                content = content.replace(marker, marker + "\n" + insert.strip())

    content = content.replace('t("inboundLinkAdmin.clearPick")', 'tInbound("clearPick")')

    # tl/tp -> t
    content = re.sub(r"\btl\(", "t(", content)
    content = re.sub(r"\btp\(", "t(", content)

    content = content.replace(f"export function {export_from}", f"export function {export_to}")
    content = content.replace(f"export const {export_from}", f"export const {export_to}")

    return content


def main() -> int:
    targets = PORTS
    if len(sys.argv) > 1:
        names = set(sys.argv[1:])
        targets = [p for p in PORTS if p["export_to"] in names or p["src"] in names]

    for spec in targets:
        src = LEGACY / spec["src"]
        dest = OUT / spec["dest"]
        dest.parent.mkdir(parents=True, exist_ok=True)
        raw = src.read_text(encoding="utf-8")
        ported = port_content(
            raw,
            spec["namespace"],
            spec["export_from"],
            spec["export_to"],
            spec.get("extra_namespaces"),
        )
        dest.write_text(ported, encoding="utf-8")
        print(f"ported {spec['src']} -> {dest.relative_to(ROOT)}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
