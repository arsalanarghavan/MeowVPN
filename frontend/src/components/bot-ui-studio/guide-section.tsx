"use client"

import { Info } from "lucide-react"
import { useTranslations } from "next-intl"

export function GuideSection() {
  const t = useTranslations("botUiStudio")
  const items = ["guideLayout", "guideColors", "guideGlass", "guideGroups", "guideInline"] as const

  return (
    <div className="rounded-xl border border-border/80 bg-card/50 p-4 backdrop-blur-sm">
      <div className="mb-3 flex items-center gap-2 text-sm font-medium">
        <Info className="size-4 text-primary" />
        {t("guideTitle")}
      </div>
      <ul className="space-y-2 text-sm text-muted-foreground">
        {items.map((k) => (
          <li key={k} className="leading-relaxed">
            {t(k)}
          </li>
        ))}
      </ul>
    </div>
  )
}
