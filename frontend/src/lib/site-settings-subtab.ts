export const SITE_SETTINGS_SUBTABS = [
  "landing",
  "subscription_portal",
  "cron",
  "whitelabel",
  "service_naming",
  "proxy",
  "relay",
  "notifications",
  "purge_expired",
  "finance",
  "logs",
  "resellers",
] as const

export type SiteSettingsSubtab = (typeof SITE_SETTINGS_SUBTABS)[number]

export function isSiteSettingsSubtab(v: string): v is SiteSettingsSubtab {
  return (SITE_SETTINGS_SUBTABS as readonly string[]).includes(v)
}

export function readSiteSubtabFromUrl(): SiteSettingsSubtab {
  if (typeof window === "undefined") return "whitelabel"
  const raw = new URLSearchParams(window.location.search).get("site_subtab") || "whitelabel"
  return isSiteSettingsSubtab(raw) ? raw : "whitelabel"
}

export function writeSiteSubtabToUrl(subtab: SiteSettingsSubtab) {
  if (typeof window === "undefined") return
  const url = new URL(window.location.href)
  url.searchParams.set("site_subtab", subtab)
  window.history.replaceState(window.history.state, "", url.toString())
}
