#!/usr/bin/env python3
"""Fix common TS issues in ported admin components."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ADMIN = ROOT / "frontend" / "src" / "components" / "admin"


def dedupe_imports(text: str) -> str:
    lines = text.splitlines()
    seen_use_trans = False
    out = []
    for line in lines:
        if line.strip() == 'import { useTranslations } from "next-intl"':
            if seen_use_trans:
                continue
            seen_use_trans = True
        out.append(line)
    return "\n".join(out) + ("\n" if text.endswith("\n") else "")


def fix_helpers(text: str) -> str:
    # helpers that receive tp but body uses t
    text = re.sub(
        r"function targetsLabel\(raw: string, tp: \(k: string\) => string\): string \{\n  if \(raw === \"both\"\) return t\(",
        'function targetsLabel(raw: string, tp: (k: string) => string): string {\n  if (raw === "both") return tp("',
        text,
    )
    text = text.replace('if (raw === "telegram") return t("targetsTelegram")', 'if (raw === "telegram") return tp("targetsTelegram")')
    text = text.replace('if (raw === "bale") return t("targetsBale")', 'if (raw === "bale") return tp("targetsBale")')

    text = re.sub(
        r"function broadcastStatusLabel\(st: string, tp: \(k: string\) => string\): string \{\n  const key = `broadcastStatus_\$\{st\}`\n  const tr = t\(key\)",
        "function broadcastStatusLabel(st: string, tp: (k: string) => string): string {\n  const key = `broadcastStatus_${st}`\n  const tr = tp(key)",
        text,
    )

    text = text.replace("t: TFunction", "t: (k: string, opts?: Record<string, string>) => string")

    text = text.replace('return t("configLineN", { n: idx + 1 })', 'return tl("configLineN", { n: idx + 1 })')

    # asChild tooltip/collapsible/button -> render
    text = re.sub(
        r"<TooltipTrigger asChild>\s*\n\s*<Button",
        "<TooltipTrigger\n              render={\n                <Button",
        text,
    )
    text = re.sub(
        r"</Button>\s*\n\s*</TooltipTrigger>",
        "</Button>\n              }\n            />",
        text,
    )
    text = re.sub(
        r"<CollapsibleTrigger asChild>\s*\n\s*<Button",
        "<CollapsibleTrigger\n                    render={\n                      <Button",
        text,
    )
    text = re.sub(
        r"</Button>\s*\n\s*</CollapsibleTrigger>",
        "</Button>\n                    }\n                  />",
        text,
    )
    text = text.replace(" asChild", "")

    return text


def add_tinbound(text: str) -> str:
    if "configs-admin-core" not in text and "configs/configs-admin-core" not in text:
        return text
    if "tInbound" in text and "useTranslations(\"inboundLinkAdmin\")" in text:
        return text
    return text.replace(
        'const t = useTranslations("configsAdmin")',
        'const t = useTranslations("configsAdmin")\n  const tInbound = useTranslations("inboundLinkAdmin")',
        1,
    )


def fix_discounts_export(text: str, path: Path) -> str:
    if path.name != "discounts-admin-client.tsx":
        return text
    if "export type UsageSummary" in text:
        return text
    text = text.replace("type UsageSummary =", "export type UsageSummary =")
    return text


def process(path: Path) -> None:
    text = path.read_text(encoding="utf-8")
    orig = text
    text = dedupe_imports(text)
    text = fix_helpers(text)
    text = add_tinbound(text)
    text = fix_discounts_export(text, path)
    if text != orig:
        path.write_text(text, encoding="utf-8")
        print(f"fixed {path.relative_to(ROOT)}")


def main() -> None:
    for path in ADMIN.rglob("*.tsx"):
        process(path)
    br = ROOT / "frontend/src/components/broadcast-rich-editor.tsx"
    if br.exists():
        text = br.read_text(encoding="utf-8")
        text = text.replace('import { useTranslation } from "react-i18next"', 'import { useTranslations } from "next-intl"')
        text = text.replace("const { t } = useTranslation()", 'const t = useTranslations("broadcastAdmin")')
        text = text.replace('t("broadcastAdmin.', 't("')
        br.write_text(text, encoding="utf-8")
        print("fixed broadcast-rich-editor.tsx")


if __name__ == "__main__":
    main()
