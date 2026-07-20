"use client"

import type { ReactNode } from "react"
import { useLocale, useTranslations } from "next-intl"

import { ThemeToggle } from "@/components/portal/components/ThemeToggle"
import { useTheme } from "@/components/portal/hooks/useTheme"
import { cn } from "@/lib/utils"

type Props = {
  themeClass: string
  brandName: string
  tagline?: string
  logo?: string
  children: ReactNode
}

export function PgShell({ themeClass, brandName, tagline, logo, children }: Props) {
  const locale = useLocale()
  const t = useTranslations("portal")
  const { mode, setMode } = useTheme()

  return (
    <div className={cn("shell pg-theme-root", themeClass)} dir={locale === "fa" ? "rtl" : "ltr"} lang={locale}>
      <header className="pg-sub-header">
        <div className="pg-sub-header__brand">
          {logo ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logo} alt="" className="pg-sub-logo" />
          ) : null}
          <div>
            <h1>{brandName}</h1>
            {tagline ? <p className="muted">{tagline}</p> : null}
          </div>
        </div>
        <div className="pg-sub-header__actions">
          <span className="sr-only">{t("title")}</span>
          <ThemeToggle mode={mode} onChange={setMode} />
        </div>
      </header>
      {children}
    </div>
  )
}
