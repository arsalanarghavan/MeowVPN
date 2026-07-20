"use client"

import type { ReactNode } from "react"
import { useLocale, useTranslations } from "next-intl"

import { ThemeToggle } from "@/components/portal/components/ThemeToggle"
import { useTheme } from "@/components/portal/hooks/useTheme"
import { cn } from "@/lib/utils"

export function PortalThemeShell({
  title,
  children,
  className,
}: {
  title: string
  children: ReactNode
  className?: string
}) {
  const t = useTranslations("portal")
  const locale = useLocale()
  const { mode, setMode } = useTheme()

  return (
    <div className={cn("shell", className)} dir={locale === "fa" ? "rtl" : "ltr"} lang={locale}>
      <header className="pg-sub-header">
        <h1 className="text-2xl font-semibold">{title}</h1>
        <ThemeToggle mode={mode} onChange={setMode} />
      </header>
      <div className="space-y-4">
        <p className="sr-only">{t("title")}</p>
        {children}
      </div>
    </div>
  )
}
