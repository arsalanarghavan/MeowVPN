const fa = {
  loading: "در حال بارگذاری اطلاعات...",
  usedTraffic: "حجم مصرفی",
  expireDate: "تاریخ انقضا",
  dataRemaining: "حجم باقی مانده",
  totalQuota: "حجم کل",
  unlimited: "نامحدود",
  configLinks: "لینک کانفیگ ها",
  subscriptionLink: "لینک اشتراک",
  copyLink: "کپی لینک",
  copyBase64: "کپی Base64",
  copied: "کپی شد!",
  trafficStats: "آمار مصرف",
  chartNoData: "برای بازه انتخاب‌شده داده‌ای وجود ندارد",
  chartError: "خطا در بارگذاری داده‌های نمودار",
  qrTitle: "کد QR",
  closeQr: "بستن",
  themeToggle: "تغییر تم",
  support: "پشتیبانی",
  statusActive: "فعال",
  statusDisabled: "غیرفعال",
  statusExpired: "منقضی شده",
  statusLimited: "محدود",
  noService: "سرویسی ثبت نشده است.",
  downloadTraffic: "دانلود",
  upload: "آپلود",
  usedPercent: "مصرف شده",
  range1h: "۱ ساعت",
  range12h: "۱۲ ساعت",
  range24h: "۲۴ ساعت",
  range7d: "۷ روز",
  range30d: "۳۰ روز",
  range90d: "۹۰ روز",
  protocolSub: "SUB",
  justNow: "همین الان",
  minutesAgo: "دقیقه پیش",
  hoursAgo: "ساعت پیش",
  daysAgo: "روز پیش",
}

export type Dict = typeof fa
export type UsageRange = "1h" | "12h" | "24h" | "7d" | "30d" | "90d"
export const USAGE_RANGES: UsageRange[] = ["1h", "12h", "24h", "7d", "30d", "90d"]

export function t(key: keyof Dict): string {
  return fa[key] ?? key
}

export function rangeLabel(range: UsageRange): string {
  switch (range) {
    case "1h":
      return t("range1h")
    case "12h":
      return t("range12h")
    case "24h":
      return t("range24h")
    case "7d":
      return t("range7d")
    case "30d":
      return t("range30d")
    case "90d":
      return t("range90d")
    default:
      return range
  }
}

export function formatBytes(n: number): string {
  if (!Number.isFinite(n) || n <= 0) return "0 B"
  const units = ["B", "KB", "MB", "GB", "TB"]
  let v = n
  let i = 0
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024
    i++
  }
  return `${v.toFixed(i > 0 ? 2 : 0)} ${units[i]}`
}

export function getPortalTimeZone(): string {
  const z =
    (typeof window !== "undefined"
      ? window.__SIMPLEVPBOT_PORTAL__?.meta?.siteTimeZone ?? window.__INITIAL_DATA__?.meta?.siteTimeZone
      : undefined)?.trim()
  return z || "Asia/Tehran"
}

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return t("unlimited")
  try {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return iso
    const tz = getPortalTimeZone()
    return new Intl.DateTimeFormat("fa-IR", {
      dateStyle: "medium",
      timeStyle: "short",
      timeZone: tz,
    }).format(d)
  } catch {
    return iso
  }
}

export function formatRelativeTime(iso: string | Date | null | undefined): string {
  if (!iso) return t("justNow")
  const d = iso instanceof Date ? iso : new Date(iso)
  if (Number.isNaN(d.getTime())) return t("justNow")
  const diffMs = Date.now() - d.getTime()
  if (diffMs < 60_000) return t("justNow")
  const mins = Math.floor(diffMs / 60_000)
  if (mins < 60) return `${mins.toLocaleString("fa-IR")} ${t("minutesAgo")}`
  const hours = Math.floor(mins / 60)
  if (hours < 48) return `${hours.toLocaleString("fa-IR")} ${t("hoursAgo")}`
  const days = Math.floor(hours / 24)
  return `${days.toLocaleString("fa-IR")} ${t("daysAgo")}`
}

export function daysUntilExpiry(iso: string | null | undefined): number | null {
  if (!iso) return null
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return null
  const diff = d.getTime() - Date.now()
  return Math.max(0, Math.ceil(diff / 86_400_000))
}

export function usedPercent(used: number, total: number): number {
  if (total <= 0 || !Number.isFinite(total)) return 0
  return Math.min(100, Math.round((used / total) * 100))
}

export function statusLabel(status: string): string {
  switch (status) {
    case "active":
      return t("statusActive")
    case "disabled":
      return t("statusDisabled")
    case "expired":
      return t("statusExpired")
    case "limited":
      return t("statusLimited")
    default:
      return status
  }
}

export function statusClass(status: string): string {
  switch (status) {
    case "active":
      return "status-pill status-pill--active"
    case "disabled":
      return "status-pill status-pill--disabled"
    case "expired":
      return "status-pill status-pill--expired"
    case "limited":
      return "status-pill status-pill--limited"
    default:
      return "status-pill"
  }
}

export async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    const ta = document.createElement("textarea")
    ta.value = text
    ta.style.position = "fixed"
    ta.style.left = "-9999px"
    document.body.appendChild(ta)
    ta.select()
    const ok = document.execCommand("copy")
    document.body.removeChild(ta)
    return ok
  }
}

export function labelFromUri(uri: string): string {
  const hash = uri.indexOf("#")
  if (hash >= 0 && uri.length > hash + 1) {
    try {
      return decodeURIComponent(uri.slice(hash + 1))
    } catch {
      return uri.slice(hash + 1)
    }
  }
  return uri.slice(0, 48) + (uri.length > 48 ? "…" : "")
}

const COUNTRY_FLAGS: Record<string, string> = {
  finland: "🇫🇮",
  france: "🇫🇷",
  germany: "🇩🇪",
  netherlands: "🇳🇱",
  usa: "🇺🇸",
  "united states": "🇺🇸",
  uk: "🇬🇧",
  "united kingdom": "🇬🇧",
  turkey: "🇹🇷",
  iran: "🇮🇷",
  canada: "🇨🇦",
  sweden: "🇸🇪",
  norway: "🇳🇴",
  italy: "🇮🇹",
  spain: "🇪🇸",
  poland: "🇵🇱",
  russia: "🇷🇺",
  japan: "🇯🇵",
  korea: "🇰🇷",
  singapore: "🇸🇬",
  india: "🇮🇳",
  australia: "🇦🇺",
  brazil: "🇧🇷",
  uae: "🇦🇪",
  dubai: "🇦🇪",
}

function flagFromCountryName(text: string): string {
  const upper = text.toUpperCase()
  for (const [name, flag] of Object.entries(COUNTRY_FLAGS)) {
    if (upper.includes(name.toUpperCase())) {
      return flag
    }
  }
  return ""
}

export function flagFromLabel(label: string): { flag: string; text: string } {
  const trimmed = label.trim()
  if (!trimmed) return { flag: "", text: trimmed }
  const parts = [...trimmed]
  const first = parts[0]
  if (first && /\p{Extended_Pictographic}/u.test(first)) {
    return { flag: first, text: parts.slice(1).join("").trim() }
  }
  const fallback = flagFromCountryName(trimmed)
  return { flag: fallback, text: trimmed }
}

export function protocolFromUri(uri: string): string {
  const idx = uri.indexOf("://")
  if (idx <= 0) return "?"
  const scheme = uri.slice(0, idx).toLowerCase()
  if (scheme === "vless") return "VLESS"
  if (scheme === "vmess") return "VMESS"
  if (scheme === "trojan") return "TROJAN"
  if (scheme === "ss") return "SS"
  if (scheme === "hysteria2") return "HY2"
  return scheme.toUpperCase()
}
