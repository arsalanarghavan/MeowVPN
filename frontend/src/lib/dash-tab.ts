export type DashLocation = {
  tab: string
  userDetailId: number | null
  resellerContextId?: number | null
}

export type ParseDashOpts = {
  /** When true, `/dashboard/general/` maps to `dashboard` instead of `monitoring`. */
  reseller?: boolean
}

/** Map URL slugs (incl. hyphenated) to internal tab keys used by the SPA. */
const TAB_SLUG_ALIASES: Record<string, string> = {
  wallet: "receipts",
  reseller_finance: "reseller_charge",
  "reseller-charge": "reseller_charge",
  reseller_charge: "reseller_charge",
  charge: "reseller_charge",
  "users-bulk": "users_bulk",
  "reseller-bots": "reseller_bots",
  "plan-cats": "plan_cats",
  "site-settings": "site_settings",
  "bot-ui": "bot_ui",
  "xui-panels": "xui_panels",
  "referral-reports": "referral_reports",
  referral_reports: "referral_reports",
  "reseller-reports": "reseller_reports",
  reseller_reports: "reseller_reports",
  "marketing-lifecycle": "marketing_lifecycle",
  marketing_lifecycle: "marketing_lifecycle",
  "l2tp-servers": "l2tp_servers",
  "wholesale-lines": "plans",
  "reseller-xui-panels": "reseller_xui_panels",
  reseller_xui_panels: "reseller_xui_panels",
  "reseller-settings": "reseller_settings",
  reseller_settings: "reseller_settings",
  "panel-inbounds": "configs",
}

export function normalizeDashTabKey(raw: string): string {
  const t = String(raw || "").trim()
  if (!t) return "dashboard"
  const lower = t.toLowerCase()
  if (TAB_SLUG_ALIASES[lower]) return TAB_SLUG_ALIASES[lower]
  return t
}

/** Build SPA path for a tab. Home tab `dashboard` → bare `/dashboard/` (no duplicate segment). */
export function buildDashboardTabUrl(base: string, tabKey: string): string {
  const root = String(base || "").replace(/\/?$/, "")
  const tab = String(tabKey || "").trim()
  if (!tab || tab === "dashboard") return `${root}/`
  return `${root}/${encodeURIComponent(tab)}/`
}

/** Resellers use `reseller_bots`; `bots` is admin-only in nav. */
export function mapTabForReseller(tab: string, isReseller: boolean): string {
  if (!isReseller) return tab
  if (tab === "bots") return "reseller_bots"
  return tab
}

/**
 * Parse `/dashboard/...` path: list tab, optional user detail `/dashboard/users/u/{id}/`.
 */
export function parseDashFromPath(pathname: string, opts?: ParseDashOpts): DashLocation {
  const path = (pathname || "").replace(/\/+$/, "") || "/"
  if (/\/dashboard\/login(?:\/|$)/.test(path)) {
    return { tab: "login", userDetailId: null }
  }
  const userM = path.match(/\/dashboard\/users\/u\/(\d+)(?:\/|$)/)
  if (userM) {
    const id = Number(userM[1])
    return { tab: "users", userDetailId: Number.isFinite(id) && id > 0 ? id : null, resellerContextId: null }
  }
  const resellerM = path.match(/\/dashboard\/reseller_workspace\/(\d+)(?:\/|$)/)
  if (resellerM) {
    const id = Number(resellerM[1])
    return { tab: "reseller_workspace", userDetailId: null, resellerContextId: Number.isFinite(id) && id > 0 ? id : null }
  }
  const sub = path.match(/\/dashboard\/([^/]+)$/)
  if (sub) {
    let tab = normalizeDashTabKey(sub[1])
    if (tab === "inbound_link") {
      tab = "xui_panels"
    }
    if (tab === "panel_inbounds") {
      tab = "configs"
    }
    if (tab === "general") {
      tab = opts?.reseller ? "dashboard" : "monitoring"
    }
    if (tab === "wholesale_lines") {
      tab = "plans"
    }
    tab = mapTabForReseller(tab, Boolean(opts?.reseller))
    return { tab, userDetailId: null, resellerContextId: null }
  }
  if (/\/dashboard$/.test(path)) return { tab: "dashboard", userDetailId: null, resellerContextId: null }
  return { tab: "dashboard", userDetailId: null, resellerContextId: null }
}

/** Parse the first /dashboard/{tab}/ segment. Bare /dashboard/ → dashboard (SPA home). */
export function parseActiveDashTab(boot: { dashPath?: string; isReseller?: boolean } | undefined | null): string {
  if (typeof window !== "undefined") {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    return parseDashFromPath(window.location.pathname, { reseller: Boolean(b.isReseller) }).tab
  }
  if (boot?.dashPath) {
    const s = String(boot.dashPath).trim()
    const parts = s.split("/").filter(Boolean)
    if (parts[0] === "users" && parts[1] === "u" && parts[2] && /^\d+$/.test(parts[2])) {
      return "users"
    }
    let k = parts[0] ? normalizeDashTabKey(parts[0]) : ""
    if (k) {
      if (k === "login") return "login"
      if (k === "inbound_link") return "xui_panels"
      if (k === "panel_inbounds") return "configs"
      if (k === "general") return boot?.isReseller ? "dashboard" : "monitoring"
      return mapTabForReseller(k, Boolean(boot?.isReseller))
    }
  }
  return "dashboard"
}
