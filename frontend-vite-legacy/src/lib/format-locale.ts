import { gregorianToJalali } from "@/lib/jalali"

const ASCII_DIGITS = "0123456789"
const FA_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
const AR_DIGITS = "٠١٢٣٤٥٦٧٨٩"

function getSiteTimeZone(): string | undefined {
  const z = (typeof window !== "undefined" ? window.__SIMPLEVPBOT_DASH__?.siteTimeZone : undefined) as
    | string
    | undefined
  const t = z?.trim()
  return t || undefined
}

/** Gregorian calendar parts in a given IANA zone (or local). */
function gregorianPartsInZone(d: Date, timeZone?: string): { y: number; m: number; day: number; h: number; mi: number } {
  const tz = timeZone || getSiteTimeZone()
  const opts: Intl.DateTimeFormatOptions = {
    year: "numeric",
    month: "numeric",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
    ...(tz ? { timeZone: tz } : {}),
  }
  const f = new Intl.DateTimeFormat("en-US", opts)
  const parts = f.formatToParts(d)
  const map: Record<string, string> = {}
  for (const p of parts) {
    if (p.type !== "literal") map[p.type] = p.value
  }
  return {
    y: Number(map.year),
    m: Number(map.month),
    day: Number(map.day),
    h: Number(map.hour || 0),
    mi: Number(map.minute || 0),
  }
}

function pad2(n: number): string {
  return n < 10 ? `0${n}` : String(n)
}

function formatJalaliFromGregorianZone(d: Date, timeZone?: string): string {
  const { y, m, day, h, mi } = gregorianPartsInZone(d, timeZone)
  const [jy, jm, jd] = gregorianToJalali(y, m, day)
  return `${jy}/${pad2(jm)}/${pad2(jd)}، ${pad2(h)}:${pad2(mi)}`
}

function formatGregorianZone(d: Date, timeZone?: string): string {
  const tz = timeZone || getSiteTimeZone()
  try {
    return new Intl.DateTimeFormat("en-US", {
      calendar: "gregory",
      dateStyle: "medium",
      timeStyle: "short",
      ...(tz ? { timeZone: tz } : {}),
    }).format(d)
  } catch {
    return d.toISOString().slice(0, 16).replace("T", " ")
  }
}

/** Map MySQL datetime (WP site wall clock) to a UTC instant. */
function parseMysqlDatetimeInSiteZone(s: string, timeZone: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(s.trim())
  if (!m) return null
  const y = Number(m[1])
  const mo = Number(m[2])
  const da = Number(m[3])
  const hh = m[4] != null ? Number(m[4]) : 0
  const mm = m[5] != null ? Number(m[5]) : 0
  const ss = m[6] != null ? Number(m[6]) : 0
  let guess = Date.UTC(y, mo - 1, da, hh, mm, ss)
  for (let i = 0; i < 5; i++) {
    const p = gregorianPartsInZone(new Date(guess), timeZone)
    if (p.y === y && p.m === mo && p.day === da && p.h === hh && p.mi === mm) {
      break
    }
    let dayDelta = 0
    if (p.y < y || (p.y === y && p.m < mo) || (p.y === y && p.m === mo && p.day < da)) {
      dayDelta = 1
    } else if (p.y > y || (p.y === y && p.m > mo) || (p.y === y && p.m === mo && p.day > da)) {
      dayDelta = -1
    }
    const targetMin = hh * 60 + mm
    const actualMin = p.h * 60 + p.mi
    const minDelta = targetMin - actualMin + dayDelta * 24 * 60
    if (minDelta === 0 && dayDelta === 0) {
      break
    }
    guess += minDelta * 60 * 1000
  }
  const d = new Date(guess)
  return Number.isNaN(d.getTime()) ? null : d
}

function parseToDate(input: string | number | Date): Date | null {
  if (input instanceof Date) {
    return Number.isNaN(input.getTime()) ? null : input
  }
  if (typeof input === "number") {
    const d = new Date(input < 1e12 ? input * 1000 : input)
    return Number.isNaN(d.getTime()) ? null : d
  }
  const s = String(input).trim()
  if (!s) return null
  const tz = getSiteTimeZone()
  // "YYYY-MM-DD" or "YYYY-MM-DD HH:mm:ss" (MySQL-style, WP site local wall clock)
  const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(s)
  if (m) {
    if (tz) {
      const zoned = parseMysqlDatetimeInSiteZone(s, tz)
      if (zoned) return zoned
    }
    const y = Number(m[1])
    const mo = Number(m[2])
    const da = Number(m[3])
    const hh = m[4] != null ? Number(m[4]) : 12
    const mm = m[5] != null ? Number(m[5]) : 0
    const ss = m[6] != null ? Number(m[6]) : 0
    const d = new Date(y, mo - 1, da, hh, mm, ss)
    return Number.isNaN(d.getTime()) ? null : d
  }
  const d = new Date(s.replace(" ", "T"))
  return Number.isNaN(d.getTime()) ? null : d
}

export function formatNumber(value: number, isFa: boolean): string {
  const n = Number.isFinite(value) ? value : 0
  const s = new Intl.NumberFormat(isFa ? "fa-IR" : "en-US", {
    maximumFractionDigits: 2,
  }).format(n)
  return s
}

/** Integer account-style IDs: ASCII digits only, no thousands separators (Telegram/Bale/internal id). */
export function formatPlainLatinInt(value: number | null | undefined): string {
  const n = Number(value)
  if (!Number.isFinite(n)) return "0"
  return String(Math.trunc(n))
}

/** Replace ASCII digits in a string with Persian digits when `isFa` is true. */
export function formatDigits(str: string, isFa: boolean): string {
  if (!isFa) return str
  let out = ""
  for (const ch of str) {
    const i = ASCII_DIGITS.indexOf(ch)
    out += i >= 0 ? FA_DIGITS[i]! : ch
  }
  return out
}

/** Persian digits → ASCII (for English UI). */
export function digitsToLatin(str: string): string {
  let out = ""
  for (const ch of str) {
    const i = FA_DIGITS.indexOf(ch)
    if (i >= 0) {
      out += ASCII_DIGITS[i]!
      continue
    }
    const ai = AR_DIGITS.indexOf(ch)
    out += ai >= 0 ? ASCII_DIGITS[ai]! : ch
  }
  return out
}

/**
 * Normalize any numeric-looking string for display: FA digits when `isFa`,
 * Latin digits when English (including normalizing FA digits from API).
 */
export function formatNumericString(str: string, isFa: boolean): string {
  if (!str) return str
  const latin = digitsToLatin(str)
    .replace(/٫/g, ".")
    .replace(/[٬،]/g, ",")
  return isFa ? formatDigits(latin, true) : latin
}

/** Convert localized numeric text (FA/AR digits and separators) into Number-safe format. */
export function normalizeLocalizedNumberString(str: string): string {
  if (!str) return ""
  return digitsToLatin(str)
    .replace(/[\u200c\u200f\u202a-\u202e\s]/g, "")
    .replace(/[٬،,](?=\d{3}\b)/g, "")
    .replace(/[٫]/g, ".")
}

export function parseLocalizedNumber(value: string | number | null | undefined): number | null {
  if (value == null) return null
  if (typeof value === "number") return Number.isFinite(value) ? value : null
  const normalized = normalizeLocalizedNumberString(String(value))
  if (!normalized) return null
  const n = Number(normalized)
  return Number.isFinite(n) ? n : null
}

export function formatBytes(value: number | null | undefined, isFa: boolean): string {
  if (value == null || !Number.isFinite(value) || value < 0) return "—"
  const units = isFa
    ? ["بایت", "کیلوبایت", "مگابایت", "گیگابایت", "ترابایت"]
    : ["B", "KB", "MB", "GB", "TB"]
  let v = value
  let u = 0
  while (v >= 1024 && u < units.length - 1) {
    v /= 1024
    u++
  }
  const rounded = u === 0 ? Math.round(v) : Math.round(v * 10) / 10
  return `${formatNumber(rounded, isFa)} ${units[u]}`
}

/** Service card quota line — logical order under dir=rtl (FA) or ltr (EN). */
export function formatServiceQuotaLine(
  quotaGb: number,
  usedGb: number,
  isFa: boolean,
  labels: { usedShort: string; gbSuffix: string }
): string {
  if (!isFa) {
    const q = `${formatNumber(quotaGb, false)} GB`
    if (usedGb <= 0) return q
    return `${q} (${labels.usedShort} ${formatNumber(usedGb, false)} GB)`
  }
  const q = `${formatNumber(quotaGb, true)} ${labels.gbSuffix}`
  if (usedGb <= 0) return q
  const usedPart =
    usedGb < 1
      ? formatBytes(usedGb * 1024 * 1024 * 1024, true)
      : `${formatNumber(usedGb, true)} ${labels.gbSuffix}`
  return `${q} — ${labels.usedShort} ${usedPart}`
}

/** Service card expiry — FA: «۱۲ تیر ۱۴۰۵ — ۱۳:۰۰» without forcing ltr on Persian text. */
export function formatServiceExpiryLine(
  input: string | number | Date | null | undefined,
  isFa: boolean
): string {
  if (input == null || input === "") return "—"
  const d = parseToDate(input)
  if (!d) return "—"
  const tz = getSiteTimeZone()
  if (!isFa) return formatGregorianZone(d, tz)
  try {
    const datePart = new Intl.DateTimeFormat("fa-IR", {
      calendar: "persian",
      dateStyle: "long",
      ...(tz ? { timeZone: tz } : {}),
    }).format(d)
    const timePart = new Intl.DateTimeFormat("fa-IR", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
      ...(tz ? { timeZone: tz } : {}),
    }).format(d)
    return `${formatDigits(datePart, true)} — ${formatDigits(timePart, true)}`
  } catch {
    return formatDateTime(input, true)
  }
}

/**
 * Date + time for dashboard lists. Persian: Shamsi + FA digits; English: Gregorian Latin.
 */
export function formatDateTime(input: string | number | Date | null | undefined, isFa: boolean): string {
  if (input == null || input === "") return "—"
  const d = parseToDate(input)
  if (!d) return "—"
  const tz = getSiteTimeZone()
  if (isFa) {
    try {
      const s = new Intl.DateTimeFormat("fa-IR", {
        calendar: "persian",
        dateStyle: "medium",
        timeStyle: "short",
        ...(tz ? { timeZone: tz } : {}),
      }).format(d)
      return formatDigits(s, true)
    } catch {
      const raw = formatJalaliFromGregorianZone(d, tz)
      return formatDigits(raw, true)
    }
  }
  return formatGregorianZone(d, tz)
}

/** Date-only strings (e.g. stat_date YYYY-MM-DD) without odd TZ shifts. */
export function formatDateOnly(input: string | null | undefined, isFa: boolean): string {
  if (input == null || input === "") return "—"
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(input).trim())
  if (!m) return formatDateTime(input, isFa)
  const y = Number(m[1])
  const mo = Number(m[2])
  const da = Number(m[3])
  if (isFa) {
    const [jy, jm, jd] = gregorianToJalali(y, mo, da)
    return formatDigits(`${jy}/${pad2(jm)}/${pad2(jd)}`, true)
  }
  try {
    const d = new Date(y, mo - 1, da, 12, 0, 0)
    return new Intl.DateTimeFormat("en-US", { calendar: "gregory", dateStyle: "medium" }).format(d)
  } catch {
    return `${y}-${pad2(mo)}-${pad2(da)}`
  }
}

/**
 * Short axis label for charts: from ISO date "YYYY-MM-DD" → MM-DD (en) or jm/jd (fa).
 */
export function formatChartDayLabel(isoDate: string, isFa: boolean): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(isoDate.trim())
  if (!m) return isFa ? formatDigits(isoDate, true) : isoDate
  const y = Number(m[1])
  const mo = Number(m[2])
  const da = Number(m[3])
  if (!isFa) return `${m[2]}-${m[3]}`
  const [, jm, jd] = gregorianToJalali(y, mo, da)
  return formatDigits(`${pad2(jm)}/${pad2(jd)}`, true)
}

/** Tooltip / detail line for chart point: full date in locale calendar. */
export function formatChartTooltipDate(isoDate: string, isFa: boolean): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(isoDate.trim())
  if (!m) return formatDateTime(isoDate, isFa)
  const y = Number(m[1])
  const mo = Number(m[2])
  const da = Number(m[3])
  const d = new Date(y, mo - 1, da, 12, 0, 0)
  return formatDateTime(d, isFa)
}

/** Human-readable uptime from seconds (e.g. panel server status). */
export function formatUptimeSeconds(totalSec: number | null | undefined, isFa: boolean): string {
  if (totalSec == null || !Number.isFinite(totalSec) || totalSec < 0) return "—"
  const s = Math.floor(totalSec)
  const days = Math.floor(s / 86400)
  let rem = s % 86400
  const hours = Math.floor(rem / 3600)
  rem %= 3600
  const mins = Math.floor(rem / 60)
  if (isFa) {
    const parts: string[] = []
    if (days > 0) parts.push(`${formatNumber(days, true)} روز`)
    if (hours > 0 || days > 0) parts.push(`${formatNumber(hours, true)} ساعت`)
    if (days === 0 && hours === 0) parts.push(`${formatNumber(mins, true)} دقیقه`)
    return parts.join("، ") || formatNumber(0, true)
  }
  const parts: string[] = []
  if (days > 0) parts.push(`${formatNumber(days, false)}d`)
  if (hours > 0 || days > 0) parts.push(`${formatNumber(hours, false)}h`)
  if (days === 0 && hours === 0) parts.push(`${formatNumber(mins, false)}m`)
  return parts.join(" ") || "0m"
}
