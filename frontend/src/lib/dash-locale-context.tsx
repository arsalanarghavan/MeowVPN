"use client"

import {
  createContext,
  useContext,
  useMemo,
  type ReactNode,
} from "react"

import {
  dashDialogClass,
  dashDir,
  dashIconGapClass,
  dashLtrCell,
  dashPageRootClass,
  dashSheetSide,
  dashTableCellClass,
  dashTableHeadClass,
  isDashFa,
  type DashLang,
} from "@/lib/dash-locale"

export type DashLocaleValue = {
  lang: DashLang
  isFa: boolean
  dir: "rtl" | "ltr"
  sheetSide: "left" | "right"
  pageRootClass: (extra?: string) => string
  dialogClass: (extra?: string) => string
  tableHeadClass: (extra?: string) => string
  tableCellClass: (opts?: { numeric?: boolean; extra?: string }) => string
  ltrCell: (extra?: string) => string
  iconGapClass: (extra?: string) => string
}

const DashLocaleContext = createContext<DashLocaleValue | null>(null)

export function DashLocaleProvider({
  lang,
  children,
}: {
  lang: DashLang
  children: ReactNode
}) {
  const value = useMemo((): DashLocaleValue => {
    const isFa = isDashFa(lang)
    return {
      lang,
      isFa,
      dir: dashDir(isFa),
      sheetSide: dashSheetSide(isFa),
      pageRootClass: dashPageRootClass,
      dialogClass: dashDialogClass,
      tableHeadClass: dashTableHeadClass,
      tableCellClass: dashTableCellClass,
      ltrCell: dashLtrCell,
      iconGapClass: dashIconGapClass,
    }
  }, [lang])

  return (
    <DashLocaleContext.Provider value={value}>{children}</DashLocaleContext.Provider>
  )
}

export function useDashLocale(): DashLocaleValue {
  const ctx = useContext(DashLocaleContext)
  if (!ctx) {
    throw new Error("useDashLocale must be used within DashLocaleProvider")
  }
  return ctx
}

/** Safe when provider is absent (e.g. tests); falls back to FA. */
export function useDashLocaleOptional(): DashLocaleValue {
  const ctx = useContext(DashLocaleContext)
  const lang: DashLang = ctx?.lang ?? "fa"
  const isFa = isDashFa(lang)
  return (
    ctx ?? {
      lang,
      isFa,
      dir: dashDir(isFa),
      sheetSide: dashSheetSide(isFa),
      pageRootClass: dashPageRootClass,
      dialogClass: dashDialogClass,
      tableHeadClass: dashTableHeadClass,
      tableCellClass: dashTableCellClass,
      ltrCell: dashLtrCell,
      iconGapClass: dashIconGapClass,
    }
  )
}
