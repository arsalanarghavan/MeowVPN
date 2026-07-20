"use client"

import { createContext, useContext, useMemo, type ReactNode } from "react"
import { useLocale } from "next-intl"
import {
  dashActionsClass,
  dashDir,
  dashLtrCell,
  dashPageHeaderClass,
  dashPageRootClass,
  dashTableCellClass,
  dashTableHeadClass,
  dashSheetSide,
  isDashFa,
  type DashLang,
} from "@/lib/dash-locale"
import { cn } from "@/lib/utils"

export type DashLocaleValue = {
  lang: DashLang
  isFa: boolean
  dir: "rtl" | "ltr"
  pageRootClass: (extra?: string) => string
  pageHeaderClass: (extra?: string) => string
  actionsClass: (extra?: string) => string
  tableHeadClass: (extra?: string) => string
  tableCellClass: (opts?: { numeric?: boolean; extra?: string }) => string
  ltrCell: (extra?: string) => string
  iconGapClass: (extra?: string) => string
  dialogClass: (extra?: string) => string
  sheetSide: "left" | "right"
}

const DashLocaleContext = createContext<DashLocaleValue | null>(null)

function buildValue(lang: DashLang): DashLocaleValue {
  const isFa = isDashFa(lang)
  return {
    lang,
    isFa,
    dir: dashDir(isFa),
    pageRootClass: dashPageRootClass,
    pageHeaderClass: dashPageHeaderClass,
    actionsClass: dashActionsClass,
    tableHeadClass: dashTableHeadClass,
    tableCellClass: dashTableCellClass,
    ltrCell: dashLtrCell,
    iconGapClass: (extra?: string) => cn("inline-flex items-center gap-2", extra),
    dialogClass: (extra?: string) => cn("text-start", extra),
    sheetSide: dashSheetSide(isFa),
  }
}

export function DashLocaleProvider({
  lang,
  children,
}: {
  lang?: DashLang
  children: ReactNode
}) {
  const locale = useLocale()
  const resolved: DashLang = lang ?? (locale === "fa" ? "fa" : "en")
  const value = useMemo(() => buildValue(resolved), [resolved])
  return <DashLocaleContext.Provider value={value}>{children}</DashLocaleContext.Provider>
}

export function useDashLocale(): DashLocaleValue {
  const ctx = useContext(DashLocaleContext)
  const locale = useLocale()
  return ctx ?? buildValue(locale === "fa" ? "fa" : "en")
}

export function useDashLocaleOptional(): DashLocaleValue {
  return useDashLocale()
}
