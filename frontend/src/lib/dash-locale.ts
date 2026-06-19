/**
 * Dashboard FA/EN locale helpers.
 *
 * Contract: FA → dir=rtl, text-start, sheet from left; EN → dir=ltr, text-start, sheet from right.
 * URLs/codes: dashLtrCell() or dir="ltr" on the cell. Do not use text-right/text-left for RTL layout.
 * Date pickers: DashboardDatePicker / DashboardDateTimePicker — dashDatePickerCalendar(isFa).
 * Components: useDashLocale() + DashPage / DashSheetContent / DashDialogContent — see .cursor/rules/dashboard-rtl.mdc
 */
import { cn } from "@/lib/utils"

export type DashLang = "fa" | "en"

export function isDashFa(lang: string): boolean {
  return lang === "fa"
}

/** Document direction for dashboard content regions. */
export function dashDir(isFa: boolean): "rtl" | "ltr" {
  return isFa ? "rtl" : "ltr"
}

/** Page root spacing only — alignment comes from ancestor `dir`. */
export function dashPageRootClass(extra?: string): string {
  return cn("space-y-6 text-start", extra)
}

/** Full-width admin tab shell (overview-style; no max-w cap). */
export function dashAdminPageClass(extra?: string): string {
  return cn("w-full", extra)
}

/** @deprecated Use dashPageRootClass(extra) — isFa ignored. */
export function dashContentClass(_isFa: boolean, extra?: string): string {
  return dashPageRootClass(extra)
}

/** Page header row: title block start, actions end (respects dir). */
export function dashPageHeaderClass(extra?: string): string {
  return cn(
    "flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between",
    extra
  )
}

/** Action button cluster in page headers / toolbars. */
export function dashActionsClass(extra?: string): string {
  return cn("flex shrink-0 flex-wrap items-center gap-2", extra)
}

/** Sheet slides from inline-start side of viewport (FA=left, EN=right). */
export function dashSheetSide(isFa: boolean): "left" | "right" {
  return isFa ? "left" : "right"
}

/** Dialog / sheet shell — mobile-safe width, scroll, logical text start. */
export function dashDialogShellClass(extra?: string): string {
  return cn(
    "text-start",
    "max-h-[min(90dvh,calc(100vh-2rem))] overflow-y-auto overscroll-contain",
    "w-[calc(100%-1rem)] max-w-[min(calc(100vw-1rem),100%)]",
    extra
  )
}

/** Dialog / sheet body — logical text start + mobile shell (use on DashDialog/Sheet). */
export function dashDialogClass(extra?: string): string {
  return dashDialogShellClass(extra)
}

/** Default table header/cell alignment (follows dir). */
export function dashTableHeadClass(extra?: string): string {
  return cn("text-start", extra)
}

/** Table cell; numeric columns use tabular-nums + text-end inside dir context. */
export function dashTableCellClass(opts?: { numeric?: boolean; extra?: string }): string {
  return cn(
    "align-top",
    opts?.numeric ? "text-end tabular-nums" : "text-start",
    opts?.extra
  )
}

/** LTR island for URLs, tokens, codes (numbers may use numeric table cell instead). */
export function dashLtrCell(extra?: string): string {
  return cn("dir-ltr text-start tabular-nums", extra)
}

/** Icon + label rows — order follows parent dir. */
export function dashIconGapClass(extra?: string): string {
  return cn("flex flex-wrap items-center gap-2", extra)
}

export type DashDatePickerCalendar = "jalali" | "gregorian"

/** FA → Jalali (Persian); EN → Gregorian. Use via DashboardDatePicker / DashboardDateTimePicker. */
export function dashDatePickerCalendar(isFa: boolean): DashDatePickerCalendar {
  return isFa ? "jalali" : "gregorian"
}

/** Root class for date/datetime picker blocks (logical text-start under dir). */
export function dashDatePickerRootClass(extra?: string): string {
  return cn("space-y-2 text-start", extra)
}

/** @deprecated Use text-start under a dir container. */
export function dashTextAlign(_isFa: boolean): string {
  return "text-start"
}

/** @deprecated Use dashTableHeadClass / text-start. */
export function dashLogicalAlign(_isFa?: boolean): string {
  return "text-start"
}

/** @deprecated Use text-start under dir, not physical align. */
export function dashPhysicalAlign(_isFa: boolean): string {
  return "text-start"
}

/** @deprecated Use dashIconGapClass or dashActionsClass. */
export function dashFlexRowClass(_isFa: boolean, extra?: string): string {
  return dashIconGapClass(extra)
}
