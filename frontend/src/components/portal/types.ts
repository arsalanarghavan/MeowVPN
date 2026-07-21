export type PortalUser = {
  username: string
  status: string
  data_limit: number
  quota_hidden_from_user?: number
  used_traffic: number
  lifetime_used_traffic?: number
  expire: string | null
  online_at?: string | null
  online_at_display?: string
  created_at?: string
  id?: number
  proxy_settings?: Record<string, unknown>
  data_limit_reset_strategy?: string
  on_hold_expire_duration?: number
  on_hold_timeout?: string | null
  group_ids?: number[]
  next_plan?: unknown
  edit_at?: string | null
}

export type LinkItem = { uri: string; label: string }

export type PortalCard = {
  service_id: number
  subscription_url: string
  links: string[]
  link_items: LinkItem[]
  user: PortalUser
  chart: {
    up: number
    down: number
    total: number
    used: number
    ranges?: unknown[]
  }
}

export type PortalApp = {
  id: string
  name: string
  platform: string
  deeplink?: string
  url?: string
}

export type AppClient = {
  name: string
  icon_url: string
  import_url: string
  description: Record<string, string>
  recommended: boolean
  platform: string
  download_links: Array<{ name: string; url: string; language: string }>
}

export type PortalAppsGrouped = {
  android?: PortalApp[]
  ios?: PortalApp[]
  windows?: PortalApp[]
  linux?: PortalApp[]
}

export type PortalInitialData = {
  user: PortalUser | null
  links: string[]
  link_items?: LinkItem[]
  service_id?: number
  apps?: PortalAppsGrouped | AppClient[] | null
  apps_clients?: AppClient[]
  meta: {
    subscription_url?: string
    support_url?: string
    usage_endpoint?: string
    auth_qs?: string
    theme?: string
    datepicker?: "gregorian" | "jalali"
    branding?: { name?: string; tagline?: string; logo?: string }
    appearance?: { primary_light?: string; primary_dark?: string; radius?: string }
    locale?: string
    siteTimeZone?: string
    headers?: {
      announce?: string
      "announce-url"?: string
      "support-url"?: string
    }
  }
  cards?: PortalCard[]
  chart?: PortalCard["chart"]
}

export type UsagePoint = {
  t: string
  value: number
  label?: string
}

export type UsageChartResponse = {
  points: UsagePoint[]
  total_in_range: number
  unit_label?: string
  period?: string
  start?: string
  end?: string
  stats?: Record<string, Array<{ total_traffic: number; period_start: string }>>
}

declare global {
  interface Window {
    __SIMPLEVPBOT_PORTAL__?: PortalInitialData
    __INITIAL_DATA__?: PortalInitialData
  }
}

/** SSR / prop-injected bootstrap (checked before window globals). */
let injectedPortalData: PortalInitialData | null = null

export function setPortalBootstrap(data: PortalInitialData | null | undefined): void {
  injectedPortalData = data ?? null
  if (typeof window !== "undefined" && data) {
    window.__SIMPLEVPBOT_PORTAL__ = data
    window.__INITIAL_DATA__ = data
  }
}

export function getInitialData(): PortalInitialData {
  if (injectedPortalData) {
    return injectedPortalData
  }
  if (typeof window !== "undefined" && window.__SIMPLEVPBOT_PORTAL__) {
    return window.__SIMPLEVPBOT_PORTAL__
  }
  if (typeof window !== "undefined" && window.__INITIAL_DATA__) {
    return window.__INITIAL_DATA__
  }
  return {
    user: null,
    links: [],
    apps: null,
    meta: {},
  }
}

export function getAppsClients(data: PortalInitialData): AppClient[] {
  if (Array.isArray(data.apps)) {
    return data.apps
  }
  if (Array.isArray(data.apps_clients)) {
    return data.apps_clients
  }
  const grouped = data.apps as PortalAppsGrouped | null | undefined
  if (!grouped) return []
  const out: AppClient[] = []
  for (const platform of ["android", "ios", "windows", "linux"] as const) {
    const list = grouped[platform] ?? []
    for (const app of list) {
      out.push({
        name: app.name,
        icon_url: "",
        import_url: app.deeplink ?? "",
        description: { en: app.name, fa: app.name },
        recommended: false,
        platform,
        download_links: app.url ? [{ name: app.name, url: app.url, language: "en" }] : [],
      })
    }
  }
  return out
}
