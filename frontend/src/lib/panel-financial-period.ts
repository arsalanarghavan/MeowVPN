/**
 * Date range presets for panel financial reports (Jalali month for FA, Gregorian for EN).
 */

import { gregorianToJalali, jalaliToGregorian } from "@/lib/jalali"

export type PanelFinancialPeriod = {
  from: string
  to: string
}

export type PanelFinancialPreset = "this_month" | "last_month" | "custom"

export function siteTimeZone(): string {
  const z =
    typeof window !== "undefined"
      ? (window.__SIMPLEVPBOT_DASH__?.siteTimeZone as string | undefined)
      : undefined
  return z && z.trim() !== "" ? z.trim() : "Asia/Tehran"
}

/** YYYY-MM-DD for a calendar date in site timezone. */
export function formatYmdInTimeZone(date: Date, tz: string): string {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: tz,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(date)
  const y = parts.find((p) => p.type === "year")?.value ?? "2000"
  const m = parts.find((p) => p.type === "month")?.value ?? "01"
  const d = parts.find((p) => p.type === "day")?.value ?? "01"
  return `${y}-${m}-${d}`
}

function todayPartsInTz(tz: string): { gy: number; gm: number; gd: number } {
  const now = new Date()
  const parts = new Intl.DateTimeFormat("en-US", {
    timeZone: tz,
    year: "numeric",
    month: "numeric",
    day: "numeric",
  }).formatToParts(now)
  return {
    gy: Number(parts.find((p) => p.type === "year")?.value ?? 2000),
    gm: Number(parts.find((p) => p.type === "month")?.value ?? 1),
    gd: Number(parts.find((p) => p.type === "day")?.value ?? 1),
  }
}

function isoFromGregorian(gy: number, gm: number, gd: number): string {
  const pad = (n: number) => String(n).padStart(2, "0")
  return `${gy}-${pad(gm)}-${pad(gd)}`
}

function jalaliMonthLastDay(jy: number, jm: number): number {
  for (let d = 31; d >= 29; d--) {
    const [gy, gm, gd] = jalaliToGregorian(jy, jm, d)
    const [bjy, bjm, bjd] = gregorianToJalali(gy, gm, gd)
    if (bjy === jy && bjm === jm && bjd === d) return d
  }
  return 29
}

function gregorianMonthLastDay(gy: number, gm: number): number {
  return new Date(gy, gm, 0).getDate()
}

/** First day of current calendar month → today (Jalali for FA, Gregorian for EN). */
export function currentMonthRange(isFa: boolean): PanelFinancialPeriod {
  const tz = siteTimeZone()
  const { gy, gm, gd } = todayPartsInTz(tz)
  if (isFa) {
    const [jy, jm] = gregorianToJalali(gy, gm, gd)
    const [fromGy, fromGm, fromGd] = jalaliToGregorian(jy, jm, 1)
    return {
      from: isoFromGregorian(fromGy, fromGm, fromGd),
      to: isoFromGregorian(gy, gm, gd),
    }
  }
  return {
    from: isoFromGregorian(gy, gm, 1),
    to: isoFromGregorian(gy, gm, gd),
  }
}

/** Full previous calendar month. */
export function previousMonthRange(isFa: boolean): PanelFinancialPeriod {
  const tz = siteTimeZone()
  const { gy, gm, gd } = todayPartsInTz(tz)
  if (isFa) {
    const [jy, jm] = gregorianToJalali(gy, gm, gd)
    const prevJy = jm > 1 ? jy : jy - 1
    const prevJm = jm > 1 ? jm - 1 : 12
    const last = jalaliMonthLastDay(prevJy, prevJm)
    const [fromGy, fromGm, fromGd] = jalaliToGregorian(prevJy, prevJm, 1)
    const [toGy, toGm, toGd] = jalaliToGregorian(prevJy, prevJm, last)
    return {
      from: isoFromGregorian(fromGy, fromGm, fromGd),
      to: isoFromGregorian(toGy, toGm, toGd),
    }
  }
  const prevGm = gm > 1 ? gm - 1 : 12
  const prevGy = gm > 1 ? gy : gy - 1
  const last = gregorianMonthLastDay(prevGy, prevGm)
  return {
    from: isoFromGregorian(prevGy, prevGm, 1),
    to: isoFromGregorian(prevGy, prevGm, last),
  }
}

export function calendarParam(isFa: boolean): "jalali" | "gregorian" {
  return isFa ? "jalali" : "gregorian"
}
