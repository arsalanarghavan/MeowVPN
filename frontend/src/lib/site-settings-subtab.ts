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

/** Legacy top-level tabs folded into site_settings hub. */
export function resolveLegacySiteTab(tab: string): { tab: string; subtab?: SiteSettingsSubtab } {
  if (tab === "notifications") return { tab: "site_settings", subtab: "notifications" }
  if (tab === "logs") return { tab: "site_settings", subtab: "logs" }
  return { tab }
}
