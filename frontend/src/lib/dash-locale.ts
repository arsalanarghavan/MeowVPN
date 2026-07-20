import { cn } from "@/lib/utils"

export type DashLang = "fa" | "en"

export function isDashFa(lang: string): boolean {
  return lang === "fa"
}

export function dashDir(isFa: boolean): "rtl" | "ltr" {
  return isFa ? "rtl" : "ltr"
}

export function dashPageRootClass(extra?: string): string {
  return cn("space-y-6 text-start", extra)
}

export function dashPageHeaderClass(extra?: string): string {
  return cn(
    "flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between",
    extra
  )
}

export function dashActionsClass(extra?: string): string {
  return cn("flex shrink-0 flex-wrap items-center gap-2", extra)
}

export function dashTableHeadClass(extra?: string): string {
  return cn("text-start", extra)
}

export function dashTableCellClass(opts?: { numeric?: boolean; extra?: string }): string {
  return cn(
    "align-top",
    opts?.numeric ? "text-end tabular-nums" : "text-start",
    opts?.extra
  )
}

export function dashLtrCell(extra?: string): string {
  return cn("dir-ltr text-start tabular-nums", extra)
}

export function dashIconGapClass(extra?: string): string {
  return cn("flex flex-wrap items-center gap-2", extra)
}

export type DashDatePickerCalendar = "jalali" | "gregorian"

export function dashDatePickerCalendar(isFa: boolean): DashDatePickerCalendar {
  return isFa ? "jalali" : "gregorian"
}

export function dashDatePickerRootClass(extra?: string): string {
  return cn("space-y-2 text-start", extra)
}

export function dashSheetSide(isFa: boolean): "left" | "right" {
  return isFa ? "left" : "right"
}

/** Gap utility for icon+label rows in forms. */
export function iconGapClass(extra?: string): string {
  return ["inline-flex items-center gap-2", extra ?? ""].filter(Boolean).join(" ")
}
