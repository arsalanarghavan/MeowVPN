/// <reference types="vite/client" />

declare module "*.svg" {
  const src: string
  export default src
}

interface Window {
  __SIMPLEVPBOT_DASH__?: {
    restUrl?: string
    lang?: "fa" | "en"
    locale?: string
    isRtl?: boolean
    isLoggedIn?: boolean
    isAdmin?: boolean
    isReseller?: boolean
    activePersona?: "admin" | "reseller" | "user"
    availablePersonas?: Array<"admin" | "reseller" | "user">
    /** Reseller permission map from PHP (same keys as server-side). */
    actorPermissions?: Record<string, boolean>
    svpUserId?: number
    /** Actor bot-user row summary for sidebar footer (label + messenger IDs). */
    user?: {
      label?: string
      tg_user_id?: number
      bale_user_id?: number
    }
    /** True when a site admin is viewing the dashboard as a reseller (signed cookie). */
    impersonating?: boolean
    impersonationTargetId?: number
    impersonationTargetLabel?: string
    logoutUrl?: string
    dashboardUrl?: string
    dashboardLoginUrl?: string
    /** First segment of /dashboard/{segment}/ (from PHP) */
    dashPath?: string
    siteName?: string
    siteIconUrl?: string
    branding?: {
      scope?: string
      siteName?: string
      logoUrl?: string
      faviconUrl?: string
      themePrimary?: string
      themeAccent?: string
      customDomain?: string
      cssVariables?: Record<string, string>
    }
    /** IANA timezone from site (e.g. Asia/Tehran) for consistent date display */
    siteTimeZone?: string
    /** User accent preset (default, red, blue, …) */
    uiAccent?: string
    /** Saved dashboard language (fa|en); empty = site locale */
    uiLang?: string
    /** Saved theme (light|dark|system) */
    uiTheme?: string
    /** Saved sidebar (expanded|collapsed) */
    uiSidebar?: string
  }
}
