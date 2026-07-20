import { getInitialData } from "@/components/portal/types"
import { getPortalTimeZone } from "@/components/portal/lib"

export function isJalaliDatepicker(): boolean {
  const dp = getInitialData().meta?.datepicker
  return dp !== "gregorian"
}

export function formatPortalDate(iso: string | null | undefined, unlimitedLabel: string): string {
  if (!iso) return unlimitedLabel
  try {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return iso
    const tz = getPortalTimeZone()
    const locale = isJalaliDatepicker() ? "fa-IR-u-ca-persian" : "en-GB"
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
      timeZone: tz,
    }).format(d)
  } catch {
    return iso
  }
}
