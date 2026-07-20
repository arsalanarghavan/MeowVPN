import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { useTheme } from "next-themes"
import { AppSidebar } from "@/components/app-sidebar"
import { DashboardHeaderToolbar } from "@/components/dashboard-header-toolbar"
import { DashboardSearch } from "@/components/sidebar-search"
import { DashboardAdminView } from "@/components/dashboard-admin-view"
import { DashboardUserPortal } from "@/components/dashboard-user-portal"
import type { ReceiptsListFilters } from "@/components/dashboard-receipts-admin"
import type { UsersListFilters } from "@/components/dashboard-users-admin"
import { ImpersonationBanner } from "@/components/impersonation-banner"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"
import { DashboardLogin } from "@/components/dashboard-login"
import { InstallWizard } from "@/components/install-wizard"
import { ACCENT_BRANDING_VAR_KEYS, normalizeAccent } from "@/lib/accent"
import { botPlatformUrl } from "@/lib/bot-links"
import { overviewPlatformEnabled } from "@/lib/enabled-platforms"
import { buildAdminStateQuery } from "@/lib/dash-pagination"
import { buildDashboardTabUrl, mapTabForReseller, parseActiveDashTab, parseDashFromPath } from "@/lib/dash-tab"
import { resolveLegacyPlansTab } from "@/lib/plans-subview"
import { resolveLegacySiteTab, writeSiteSubtabToUrl } from "@/lib/site-settings-subtab"
import { formatNumber } from "@/lib/format-locale"
import {
  ADMIN_NAV_SECTIONS,
  filterAdminNavByFeatures,
  filterAdminNavForReseller,
  injectL2tpNavTab,
  type DashboardFeatures,
  type AdminNavSection,
} from "@/config/admin-nav"
import { saveUiPreferences, type UiTheme } from "@/lib/dash-ui-preferences"
import type { DashLang } from "@/lib/dash-locale"
import { DashLocaleProvider } from "@/lib/dash-locale-context"
import { apiBase, apiHeaders, ensureCsrfCookie, normalizeAdminApiPath } from "@/lib/api-base"
import { fetchSetupStatus, readInstallToken } from "@/lib/setup-wizard-api"
import { cn } from "@/lib/utils"

type DashData = {
  navTabs?: NavTab[]
  user?: { label?: string }
} & Record<string, unknown>
type NavTab = { key: string; label: string }
type DashPersona = "admin" | "reseller" | "user"

const RESELLER_ALLOWED_BY_PERMISSION: Record<string, string | null> = {
  dashboard: null,
  monitoring: "services.manage",
  users: "users.manage",
  resellers: "users.manage",
  users_bulk: "users.bulk",
  plans: "plans.manage",
  plan_cats: "plans.manage",
  cards: "plans.manage",
  referral: "users.manage",
  referral_reports: "users.manage",
  reseller_reports: "users.manage",
  marketing_lifecycle: "marketing.lifecycle",
  discounts: "plans.manage",
  reseller_bots: "services.manage",
  bot_ui: "services.manage",
  broadcast: "broadcast.send",
  receipts: "receipts.review",
  reseller_charge: "plans.manage",
  reseller_settings: null,
  reseller_workspace: null,
}

function App() {
  const { t, i18n } = useTranslation()
  const { theme, setTheme } = useTheme()
  const boot = useMemo(() => window.__SIMPLEVPBOT_DASH__ || {}, [])
  const restBase = useMemo(() => apiBase(boot as Record<string, unknown>), [boot])
  const uiAccent = normalizeAccent(boot.uiAccent)

  useEffect(() => {
    document.documentElement.setAttribute("data-accent", uiAccent)
  }, [uiAccent])

  useEffect(() => {
    const vars = (boot as { branding?: { cssVariables?: Record<string, string> } }).branding?.cssVariables
    if (!vars) return
    const root = document.documentElement
    const skipAccentVars = uiAccent !== "default"
    for (const [key, val] of Object.entries(vars)) {
      if (!val) continue
      if (skipAccentVars && ACCENT_BRANDING_VAR_KEYS.has(key)) continue
      root.style.setProperty(key, val)
    }
    return () => {
      for (const key of Object.keys(vars)) {
        root.style.removeProperty(key)
      }
    }
  }, [boot, uiAccent])

  const isAdmin = Boolean(boot.isAdmin)
  const isReseller = Boolean(boot.isReseller)
  const isOperator = isAdmin || isReseller
  const availablePersonas: DashPersona[] = useMemo(() => {
    const raw = boot.availablePersonas
    if (!Array.isArray(raw)) return []
    return raw.filter((x): x is DashPersona => x === "admin" || x === "reseller" || x === "user")
  }, [boot])
  const activePersona: DashPersona = useMemo(() => {
    const a = boot.activePersona
    if (a === "admin" || a === "reseller" || a === "user") return a
    return availablePersonas[0] ?? "user"
  }, [boot.activePersona, availablePersonas])
  const [data, setData] = useState<DashData | null>(null)
  const [impersonationError, setImpersonationError] = useState<string | null>(null)

  useEffect(() => {
    const st = data?.settings as Record<string, unknown> | undefined
    const wl = st?.whitelabel
    const vars =
      wl && typeof wl === "object"
        ? ((wl as Record<string, unknown>).cssVariables as Record<string, string> | undefined)
        : undefined
    if (!vars || typeof vars !== "object") return
    const root = document.documentElement
    const skipAccentVars = uiAccent !== "default"
    for (const [key, val] of Object.entries(vars)) {
      if (!val) continue
      if (skipAccentVars && ACCENT_BRANDING_VAR_KEYS.has(key)) continue
      root.style.setProperty(key, val)
    }
  }, [data?.settings, uiAccent])

  const dashStateAbortRef = useRef<AbortController | null>(null)
  /** Query params for GET admin/state list pagination (e.g. users_page). */
  const [listQuery, setListQuery] = useState<Record<string, string>>({})

  const receiptsListFilters = useMemo(
    (): ReceiptsListFilters => ({
      q: listQuery.receipts_q ?? "",
      status: listQuery.receipts_status ?? "all",
      sort: listQuery.receipts_sort ?? "created_desc",
      dateFrom: listQuery.receipts_date_from ?? "",
      dateTo: listQuery.receipts_date_to ?? "",
      amountMin: listQuery.receipts_amount_min ?? "",
      amountMax: listQuery.receipts_amount_max ?? "",
    }),
    [listQuery]
  )

  const onReceiptsListFiltersChange = useCallback((patch: Partial<ReceiptsListFilters>) => {
    setListQuery((prev) => {
      const next: Record<string, string> = { ...prev, receipts_page: "1" }
      const apply = (key: string, val: string, omitWhen: string[] = [""]) => {
        const v = val.trim()
        if (omitWhen.includes(v)) {
          delete next[key]
        } else {
          next[key] = v
        }
      }
      if ("q" in patch) apply("receipts_q", patch.q ?? "")
      if ("status" in patch) apply("receipts_status", patch.status ?? "all", ["", "all"])
      if ("sort" in patch) apply("receipts_sort", patch.sort ?? "created_desc", ["", "created_desc"])
      if ("dateFrom" in patch) apply("receipts_date_from", patch.dateFrom ?? "")
      if ("dateTo" in patch) apply("receipts_date_to", patch.dateTo ?? "")
      if ("amountMin" in patch) apply("receipts_amount_min", patch.amountMin ?? "")
      if ("amountMax" in patch) apply("receipts_amount_max", patch.amountMax ?? "")
      return next
    })
  }, [])

  const usersListFilters = useMemo(
    (): UsersListFilters => ({
      status: listQuery.users_status ?? "all",
      role: listQuery.users_role ?? "all",
      platform: listQuery.users_platform ?? "all",
      segment: listQuery.users_segment ?? "all",
      sort: listQuery.users_sort ?? "created_desc",
      dateFrom: listQuery.users_date_from ?? "",
      dateTo: listQuery.users_date_to ?? "",
      minSvc: listQuery.users_min_svc ?? "",
      maxSvc: listQuery.users_max_svc ?? "",
    }),
    [listQuery]
  )

  const onUsersListFiltersChange = useCallback((patch: Partial<UsersListFilters>) => {
    setListQuery((prev) => {
      const next: Record<string, string> = { ...prev, users_page: "1", pendingUsers_page: "1" }
      const apply = (key: string, val: string, omitWhen: string[] = [""]) => {
        const v = val.trim()
        if (omitWhen.includes(v)) {
          delete next[key]
        } else {
          next[key] = v
        }
      }
      if ("status" in patch) apply("users_status", patch.status ?? "all", ["", "all"])
      if ("role" in patch) apply("users_role", patch.role ?? "all", ["", "all"])
      if ("platform" in patch) apply("users_platform", patch.platform ?? "all", ["", "all"])
      if ("segment" in patch) apply("users_segment", patch.segment ?? "all", ["", "all"])
      if ("sort" in patch) apply("users_sort", patch.sort ?? "created_desc", ["", "created_desc"])
      if ("dateFrom" in patch) apply("users_date_from", patch.dateFrom ?? "")
      if ("dateTo" in patch) apply("users_date_to", patch.dateTo ?? "")
      if ("minSvc" in patch) apply("users_min_svc", patch.minSvc ?? "")
      if ("maxSvc" in patch) apply("users_max_svc", patch.maxSvc ?? "")
      return next
    })
  }, [])
  const [lang, setLang] = useState<"fa" | "en">(() =>
    boot.lang === "fa" || boot.lang === "en" ? boot.lang : "fa"
  )
  const [setupGate, setSetupGate] = useState<"loading" | "open" | "closed">("loading")
  const sidebarDefaultOpen = boot.uiSidebar !== "collapsed"

  useEffect(() => {
    let cancelled = false
    void fetchSetupStatus()
      .then((st) => {
        if (cancelled) return
        const path = window.location.pathname.replace(/\/+$/, "") || "/"
        const onSetup = /\/setup(?:\/|$)/.test(path) || /\/dashboard\/setup(?:\/|$)/.test(path)
        if (st.completed && onSetup) {
          const login = st.dashboard_login_url || "/dashboard/login/"
          window.location.replace(login)
          return
        }
        if (st.open && !onSetup) {
          const token = readInstallToken()
          const q = token ? `?token=${encodeURIComponent(token)}` : ""
          window.location.replace(`/setup/${q}`)
          return
        }
        setSetupGate(st.open ? "open" : "closed")
      })
      .catch(() => {
        if (!cancelled) setSetupGate("closed")
      })
    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (!isOperator || !restBase) return
    void ensureCsrfCookie()
  }, [isOperator, restBase])
  const [isFullscreen, setIsFullscreen] = useState(false)
  const [activeTab, setActiveTab] = useState(() => {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    if (!b.isAdmin && !b.isReseller) return "home"
    let tab =
      typeof window !== "undefined"
        ? parseDashFromPath(window.location.pathname, { reseller: Boolean(b.isReseller) }).tab
        : parseActiveDashTab(b)
    const legSite = resolveLegacySiteTab(tab)
    if (legSite.subtab && typeof window !== "undefined") {
      writeSiteSubtabToUrl(legSite.subtab)
    }
    const legPlans = resolveLegacyPlansTab(legSite.tab)
    return legPlans.tab
  })
  const [userDetailId, setUserDetailId] = useState<number | null>(() => {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    if (!b.isAdmin && !b.isReseller) return null
    if (typeof window !== "undefined")
      return parseDashFromPath(window.location.pathname, { reseller: Boolean(b.isReseller) }).userDetailId
    return null
  })
  const [resellerContextId, setResellerContextId] = useState<number | null>(() => {
    const b = window.__SIMPLEVPBOT_DASH__ || {}
    if (!b.isAdmin && !b.isReseller) return null
    if (typeof window !== "undefined")
      return (
        parseDashFromPath(window.location.pathname, { reseller: Boolean(b.isReseller) }).resellerContextId ?? null
      )
    return null
  })

  const dashboardBaseUrl = boot.dashboardUrl || `${window.location.origin}/dashboard/`
  const dashboardLoginUrl = `${dashboardBaseUrl.replace(/\/?$/, "")}/login/`
  const allowedResellerTabs = useMemo(() => {
    if (!isReseller) return new Set<string>()
    const server = data?.resellerAllowedTabs
    if (server && typeof server === "object") {
      const out = new Set<string>()
      for (const [k, v] of Object.entries(server as Record<string, unknown>)) {
        if (v === true) out.add(k)
      }
      return out
    }
    const permsFromData =
      data?.actorPermissions && typeof data.actorPermissions === "object"
        ? (data.actorPermissions as Record<string, boolean>)
        : null
    const permsFromBoot =
      boot.actorPermissions && typeof boot.actorPermissions === "object"
        ? (boot.actorPermissions as Record<string, boolean>)
        : null
    const perms = permsFromData ?? permsFromBoot ?? {}
    const out = new Set<string>()
    for (const [tab, perm] of Object.entries(RESELLER_ALLOWED_BY_PERMISSION)) {
      if (perm == null) {
        out.add(tab)
      } else if (perms[perm] === true) {
        out.add(tab)
      }
    }
    return out
  }, [isReseller, data, boot])
  const safeResellerTab = useCallback(
    (tab: string) => {
      if (!isReseller) return tab
      return allowedResellerTabs.has(tab) ? tab : "dashboard"
    },
    [isReseller, allowedResellerTabs]
  )

  const selectTab = useCallback(
    (key: string) => {
      if (!isOperator) {
        if (key === "home") setActiveTab("home")
        return
      }
      let mapped = key === "general" ? (isReseller ? "dashboard" : "monitoring") : key
      mapped = mapTabForReseller(mapped, isReseller)
      const tabKey = safeResellerTab(mapped)
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      const url = buildDashboardTabUrl(base, tabKey)
      window.history.pushState({ tab: tabKey }, "", url)
      setActiveTab(tabKey)
      setUserDetailId(null)
      setResellerContextId(null)
    },
    [isOperator, dashboardBaseUrl, safeResellerTab, isReseller]
  )

  const openUserDetail = useCallback(
    (id: number) => {
      if (!isOperator || !Number.isFinite(id) || id < 1) return
      if (isReseller && !allowedResellerTabs.has("users")) return
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      window.history.pushState({ tab: "users", userDetailId: id }, "", `${base}/users/u/${id}/`)
      setActiveTab("users")
      setUserDetailId(id)
      setResellerContextId(null)
    },
    [isOperator, dashboardBaseUrl, isReseller, allowedResellerTabs]
  )

  const onImpersonateReseller = useCallback(
    async (svpUserId: number) => {
      if (!restBase || !Number.isFinite(svpUserId) || svpUserId < 1) return
      setImpersonationError(null)
      try {
        await ensureCsrfCookie()
        const r = await fetch(`${restBase}${normalizeAdminApiPath("/dashboard/impersonate/start")}`, {
          method: "POST",
          credentials: "include",
          headers: apiHeaders(),
          body: JSON.stringify({ targetSvpUserId: svpUserId }),
        })
        if (r.ok) {
          window.location.reload()
          return
        }
        try {
          const json = (await r.json()) as { message?: string; reason?: string }
          setImpersonationError(String(json.message ?? json.reason ?? `http_${r.status}`))
        } catch {
          setImpersonationError(`http_${r.status}`)
        }
      } catch {
        setImpersonationError(t("layout.impersonateFailed"))
      }
    },
    [restBase, t]
  )

  const isLoggedIn = boot.isLoggedIn !== false

  const redirectToDashboardLogin = useCallback(() => {
    if (!isLoggedIn) return
    const path = window.location.pathname.replace(/\/+$/, "") || "/"
    if (/\/dashboard\/login$/i.test(path)) return
    window.location.href = dashboardLoginUrl
  }, [dashboardLoginUrl, isLoggedIn])

  const fetchDashState = useCallback(
    (opts?: { refreshPanelHealth?: boolean; refreshLivePanelMetrics?: boolean }) => {
      if (!isLoggedIn) return
      if (!restBase) return
      const headers = apiHeaders()
      const handleAuthResponse = (r: Response) => {
        if (r.status === 401 || r.status === 403) {
          redirectToDashboardLogin()
          return null
        }
        return r.json()
      }
      if (!isOperator) {
        dashStateAbortRef.current?.abort()
        const ac = new AbortController()
        dashStateAbortRef.current = ac
        const { signal } = ac
        void fetch(`${restBase}/me/state`, {
          headers,
          credentials: "include",
          signal,
        })
          .then(handleAuthResponse)
          .then((json) => {
            if (signal.aborted || json === null) return
            setData(json)
          })
          .catch((err: unknown) => {
            if (signal.aborted) return
            if (err instanceof DOMException && err.name === "AbortError") return
            setData({ ok: false })
          })
        return
      }
      dashStateAbortRef.current?.abort()
      const ac = new AbortController()
      dashStateAbortRef.current = ac
      const { signal } = ac
      const tab = activeTab
      const feat = (data?.settings as Record<string, unknown> | undefined)?.features
      const l2tpOn: boolean =
        typeof feat === "object" &&
        feat !== null &&
        (feat as Record<string, unknown>).l2tp === true
      const q = buildAdminStateQuery(listQuery, {
        refreshPanelHealth: opts?.refreshPanelHealth,
        refreshLivePanelMetrics: opts?.refreshLivePanelMetrics,
        activeTab: tab,
        resellerOperator: isReseller,
        l2tpEnabled: l2tpOn,
        includePlansForUserDetail:
          tab === "users" && userDetailId != null && userDetailId > 0,
      })
      const applyJson = (json: unknown) => {
        if (signal.aborted || json === null) return
        setData(json as DashData)
      }
      const onFetchError = (err: unknown) => {
        if (signal.aborted) return
        if (err instanceof DOMException && err.name === "AbortError") return
        setData({ ok: false })
      }
      if (isAdmin && resellerContextId && resellerContextId > 0) {
        const sep = q.includes("?") ? "&" : "?"
        const q2 = `${q}${sep}resellerContextId=${encodeURIComponent(String(resellerContextId))}`
        void fetch(`${restBase}${normalizeAdminApiPath("/dashboard/admin/state")}${q2}`, {
          headers,
          credentials: "include",
          signal,
        })
          .then(handleAuthResponse)
          .then(applyJson)
          .catch(onFetchError)
        return
      }
      void fetch(`${restBase}${normalizeAdminApiPath("/dashboard/admin/state")}${q}`, {
        headers,
        credentials: "include",
        signal,
      })
        .then(handleAuthResponse)
        .then(applyJson)
        .catch(onFetchError)
    },
    [
      restBase,
      isLoggedIn,
      isOperator,
      isAdmin,
      isReseller,
      listQuery,
      activeTab,
      resellerContextId,
      userDetailId,
      redirectToDashboardLogin,
    ]
  )

  useEffect(() => {
    if (!isLoggedIn) return
    fetchDashState()
  }, [isLoggedIn, fetchDashState])

  useEffect(() => {
    if (!isOperator) return
    const path = window.location.pathname.replace(/\/+$/, "") || "/"
    if (/\/dashboard\/dashboard$/i.test(path)) {
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      window.history.replaceState({ tab: "dashboard" }, "", buildDashboardTabUrl(base, "dashboard"))
      setActiveTab("dashboard")
    }
  }, [isOperator, dashboardBaseUrl])

  useEffect(() => {
    if (!isOperator) return
    const path = window.location.pathname.replace(/\/+$/, "") || "/"
    if (path.endsWith("/dashboard/general") || /\/dashboard\/general$/i.test(path)) {
      const base = dashboardBaseUrl.replace(/\/?$/, "")
      const tab = isReseller ? "dashboard" : "monitoring"
      window.history.replaceState({ tab }, "", buildDashboardTabUrl(base, tab))
      setActiveTab(tab)
    }
  }, [isOperator, dashboardBaseUrl, isReseller])

  useEffect(() => {
    if (!isOperator) return
    const legSite = resolveLegacySiteTab(activeTab)
    const legPlans = resolveLegacyPlansTab(legSite.tab)
    const nextTab = legPlans.tab
    if (nextTab === activeTab && !legSite.subtab) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    if (legSite.subtab) writeSiteSubtabToUrl(legSite.subtab)
    let url: string
    if (legSite.subtab != null) {
      url = `${base}/site_settings/?site_subtab=${encodeURIComponent(legSite.subtab)}`
    } else {
      url = buildDashboardTabUrl(base, nextTab)
    }
    window.history.replaceState({ tab: nextTab }, "", url)
    setActiveTab(nextTab)
  }, [isOperator, activeTab, dashboardBaseUrl])

  useEffect(() => {
    if (!isReseller) return
    const safeTab = safeResellerTab(activeTab)
    if (safeTab === activeTab) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    window.history.replaceState({ tab: safeTab }, "", buildDashboardTabUrl(base, safeTab))
    setActiveTab(safeTab)
    setUserDetailId(null)
  }, [isReseller, activeTab, safeResellerTab, dashboardBaseUrl])

  useEffect(() => {
    if (!isReseller || activeTab !== "reseller_workspace") return
    const id = resellerContextId
    if (id == null || id < 1) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    window.history.replaceState({ tab: "users", userDetailId: id }, "", `${base}/users/u/${id}/`)
    setActiveTab("users")
    setUserDetailId(id)
    setResellerContextId(null)
  }, [isReseller, activeTab, resellerContextId, dashboardBaseUrl])

  /** `/dashboard/reseller_workspace/` without numeric id falls through to unknown tab — redirect. */
  useEffect(() => {
    if (!isOperator || activeTab !== "reseller_workspace") return
    if (resellerContextId != null && resellerContextId > 0) return
    const base = dashboardBaseUrl.replace(/\/?$/, "")
    const fallback = isAdmin ? "resellers" : "dashboard"
    window.history.replaceState({ tab: fallback }, "", buildDashboardTabUrl(base, fallback))
    setActiveTab(fallback)
    setResellerContextId(null)
  }, [isOperator, isAdmin, activeTab, resellerContextId, dashboardBaseUrl])

  useEffect(() => {
    const pollHere =
      activeTab === "dashboard" || activeTab === "monitoring"
    if (!isOperator || !pollHere) return
    const ms = 25000
    const id = window.setInterval(() => {
      if (document.visibilityState === "hidden") return
      fetchDashState()
    }, ms)
    const onVis = () => {
      if (document.visibilityState === "visible") fetchDashState()
    }
    document.addEventListener("visibilitychange", onVis)
    return () => {
      window.clearInterval(id)
      document.removeEventListener("visibilitychange", onVis)
    }
  }, [isOperator, isReseller, activeTab, fetchDashState])

  useEffect(() => {
    const isFa = lang === "fa"
    void i18n.changeLanguage(lang)
    document.documentElement.lang = isFa ? "fa" : "en"
    document.documentElement.dir = isFa ? "rtl" : "ltr"
    document.body.dir = isFa ? "rtl" : "ltr"
  }, [i18n, lang])

  const themeSaveRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  useEffect(() => {
    if (!theme || !restBase) return
    const t = theme as UiTheme
    if (t !== "light" && t !== "dark" && t !== "system") return
    if (themeSaveRef.current) clearTimeout(themeSaveRef.current)
    themeSaveRef.current = setTimeout(() => {
      void saveUiPreferences({ ui_theme: t }, { restUrl: restBase })
    }, 400)
    return () => {
      if (themeSaveRef.current) clearTimeout(themeSaveRef.current)
    }
  }, [theme, restBase])

  const toggleLang = useCallback(() => {
    const next: "fa" | "en" = lang === "fa" ? "en" : "fa"
    setLang(next)
    if (restBase) {
      void saveUiPreferences({ ui_lang: next }, { restUrl: restBase })
    }
  }, [lang, restBase])

  const onSidebarOpenChange = useCallback(
    (open: boolean) => {
      if (!restBase) return
      void saveUiPreferences(
        { ui_sidebar: open ? "expanded" : "collapsed" },
        { restUrl: restBase }
      )
    },
    [restBase]
  )

  useEffect(() => {
    const onFsChange = () => setIsFullscreen(Boolean(document.fullscreenElement))
    document.addEventListener("fullscreenchange", onFsChange)
    return () => document.removeEventListener("fullscreenchange", onFsChange)
  }, [])

  const toggleFullscreen = useCallback(async () => {
    try {
      if (!document.fullscreenElement) {
        await document.documentElement.requestFullscreen()
      } else {
        await document.exitFullscreen()
      }
    } catch {
      /* ignore */
    }
  }, [])

  useEffect(() => {
    const onPop = () => {
      if (!isOperator) {
        setActiveTab("home")
        return
      }
      const loc = parseDashFromPath(window.location.pathname, { reseller: isReseller })
      const legSite = resolveLegacySiteTab(loc.tab)
      const legPlans = resolveLegacyPlansTab(legSite.tab)
      if (legSite.subtab) writeSiteSubtabToUrl(legSite.subtab)
      setActiveTab(safeResellerTab(legPlans.tab))
      setUserDetailId(loc.userDetailId)
      setResellerContextId(loc.resellerContextId ?? null)
    }
    window.addEventListener("popstate", onPop)
    return () => window.removeEventListener("popstate", onPop)
  }, [isOperator, safeResellerTab, isReseller])

  const isFa = lang === "fa"
  const sidebarSide: "left" | "right" = isFa ? "right" : "left"
  const navTabs: NavTab[] = useMemo(
    () => (isOperator ? [] : [{ key: "home", label: t("layout.breadcrumbHome") }]),
    [isOperator, t],
  )
  const dashboardFeatures = useMemo((): DashboardFeatures | null => {
    const feat = (data?.settings as Record<string, unknown> | undefined)?.features
    if (!feat || typeof feat !== "object") return null
    return feat as DashboardFeatures
  }, [data?.settings])

  const l2tpEnabled = dashboardFeatures?.l2tp === true

  const baseNavSections = useMemo(
    () =>
      filterAdminNavByFeatures(
        injectL2tpNavTab(ADMIN_NAV_SECTIONS, l2tpEnabled),
        dashboardFeatures
      ),
    [l2tpEnabled, dashboardFeatures]
  )

  const adminNavSections = baseNavSections

  const operatorNavSections: AdminNavSection[] | undefined = useMemo(() => {
    if (!isReseller) return undefined
    return filterAdminNavForReseller(baseNavSections, allowedResellerTabs)
  }, [isReseller, allowedResellerTabs, baseNavSections])

  const currentSectionLabel = useMemo(() => {
    if (!isOperator) {
      return t("layout.breadcrumbHome")
    }
    if (activeTab === "users" && userDetailId != null && userDetailId > 0) {
      return t("layout.userDetailTitle", { id: userDetailId })
    }
    return t(`sidebar.items.${activeTab}`, { defaultValue: activeTab })
  }, [isOperator, activeTab, userDetailId, t])

  const sidebarProfile = useMemo(() => {
    const d = data?.user as
      | { label?: string; tg_user_id?: unknown; bale_user_id?: unknown }
      | undefined
    const b = boot.user as typeof d
    const tg = Number(d?.tg_user_id ?? b?.tg_user_id ?? 0) || 0
    const bl = Number(d?.bale_user_id ?? b?.bale_user_id ?? 0) || 0
    const labelRaw = String(d?.label ?? b?.label ?? "").trim()
    return { label: labelRaw, tg_user_id: tg, bale_user_id: bl }
  }, [data?.user, boot])

  const user = {
    name:
      sidebarProfile.label ||
      `#${formatNumber(boot.svpUserId || 0, isFa)}`,
    tgUserId: sidebarProfile.tg_user_id,
    baleUserId: sidebarProfile.bale_user_id,
    avatar: "",
    logoutUrl: boot.logoutUrl || dashboardBaseUrl,
  }
  const impersonating = Boolean(boot.impersonating)
  const impersonationTargetLabel = String(boot.impersonationTargetLabel ?? "")
  const langLabel = isFa ? t("layout.langSwitchToEn") : t("layout.langSwitchToFa")
  const langShortLabel = isFa ? "EN" : "FA"
  const effectiveActiveTab = isAdmin || isReseller ? activeTab : "home"

  const botLinks = useMemo(() => {
    const overview = data?.overview as { bot?: Record<string, unknown> } | undefined
    const bot = overview?.bot ?? {}
    return {
      telegram: overviewPlatformEnabled(bot, "telegram")
        ? botPlatformUrl("telegram", String(bot.telegram_bot_username ?? ""))
        : null,
      bale: overviewPlatformEnabled(bot, "bale")
        ? botPlatformUrl("bale", String(bot.bale_bot_username ?? ""))
        : null,
    }
  }, [data])

  const headerToolbarProps = {
    botLinks,
    langLabel,
    langShortLabel,
    onToggleLang: toggleLang,
    onToggleFullscreen: toggleFullscreen,
    isFullscreen,
    theme,
    onToggleTheme: () => setTheme(theme === "dark" ? "light" : "dark"),
    uiAccent: boot.uiAccent,
    restUrl: restBase,
  }

  const mobileHeaderToolbar = (
    <DashboardHeaderToolbar variant="sidebar" {...headerToolbarProps} />
  )

  const sidebarVariant: "admin" | "reseller" | "user" =
    activePersona === "user" ? "user" : activePersona === "reseller" ? "reseller" : "admin"

  const sidebarEl = (
    <AppSidebar
      side={sidebarSide}
      variant={sidebarVariant}
      navTabs={navTabs}
      user={user}
      activeTabKey={effectiveActiveTab}
      onSelectTab={selectTab}
      dashboardBaseUrl={dashboardBaseUrl}
      siteName={String(boot.siteName ?? "")}
      siteIconUrl={boot.siteIconUrl}
      adminSections={isReseller ? operatorNavSections : adminNavSections}
      activePersona={activePersona}
      availablePersonas={availablePersonas}
      personaRestUrl={restBase}
      personaSwitchBlocked={impersonating}
      mobileHeaderToolbar={mobileHeaderToolbar}
    />
  )

  const insetEl = (
    <SidebarInset className="flex min-h-0 flex-1 flex-col">
      <header
        data-slot="dashboard-header"
        className="flex h-16 w-full shrink-0 items-center gap-2 border-b px-4"
      >
        <div className="flex min-w-0 flex-1 items-center gap-2 md:hidden">
          <SidebarTrigger className={cn("-ms-1 shrink-0", isFa && "rotate-180")} />
          {isOperator ? (
            <DashboardSearch
              placement="header"
              className="min-w-0 flex-1 max-w-none"
              onSelectTab={selectTab}
              onOpenUserDetail={openUserDetail}
              restUrl={restBase}
              resellerContextId={resellerContextId ?? 0}
              sections={isReseller ? operatorNavSections : adminNavSections}
            />
          ) : (
            <div className="flex-1" />
          )}
        </div>
        <div className="hidden min-w-0 shrink-0 items-center gap-2 md:flex">
          <SidebarTrigger className={cn("-ms-1 shrink-0", isFa && "rotate-180")} />
          <Separator orientation="vertical" className="me-2 h-4 shrink-0" />
          <Breadcrumb className="min-w-0 max-w-[14rem] sm:max-w-xs">
            <BreadcrumbList>
              <BreadcrumbItem>{t("dashboard")}</BreadcrumbItem>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                <BreadcrumbPage>
                  {isOperator ? currentSectionLabel : t("myPanel")}
                </BreadcrumbPage>
              </BreadcrumbItem>
            </BreadcrumbList>
          </Breadcrumb>
        </div>
        {isOperator ? (
          <div className="hidden min-w-0 flex-1 justify-center px-2 md:flex">
            <DashboardSearch
              placement="header"
              onSelectTab={selectTab}
              onOpenUserDetail={openUserDetail}
              restUrl={restBase}
              resellerContextId={resellerContextId ?? 0}
              sections={isReseller ? operatorNavSections : adminNavSections}
            />
          </div>
        ) : (
          <div className="hidden flex-1 md:block" />
        )}
        <DashboardHeaderToolbar
          variant="header"
          className="hidden md:flex"
          {...headerToolbarProps}
        />
      </header>
      <div
        className="dashboard-main-scroll flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-auto p-4 md:p-6"
      >
        {impersonationError ? (
          <div
            role="alert"
            className="mb-4 rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
          >
            {impersonationError}
          </div>
        ) : null}
        {!data ? (
          <p className="text-sm text-muted-foreground">{t("loading")}</p>
        ) : isOperator ? (
          <div data-testid={`dash-tab-${effectiveActiveTab}`} data-dash-tab={effectiveActiveTab}>
          <DashboardAdminView
            data={data}
            activeTab={effectiveActiveTab}
            userDetailId={userDetailId}
            isReseller={isReseller}
            allowedNavTabs={isReseller ? allowedResellerTabs : null}
            dashboardBaseUrl={dashboardBaseUrl}
            onSelectTab={selectTab}
            onOpenUserDetail={openUserDetail}
            onCloseUserDetail={() => {
              const base = dashboardBaseUrl.replace(/\/?$/, "")
              window.history.pushState({ tab: "users" }, "", `${base}/users/`)
              setUserDetailId(null)
            }}
            onOpenResellerWorkspace={(rid) => {
              const id = Number(rid)
              if (!Number.isFinite(id) || id < 1) return
              if (isReseller) {
                openUserDetail(id)
                return
              }
              const base = dashboardBaseUrl.replace(/\/?$/, "")
              window.history.pushState({ tab: "reseller_workspace", resellerContextId: id }, "", `${base}/reseller_workspace/${id}/`)
              setResellerContextId(id)
              setActiveTab("reseller_workspace")
            }}
            setListQuery={setListQuery}
            usersSearchQuery={listQuery.users_q ?? ""}
            onUsersSearchQueryChange={(q) => {
              setListQuery((prev: Record<string, string>) => {
                const next: Record<string, string> = { ...prev, users_page: "1", pendingUsers_page: "1" }
                if (q.trim() === "") {
                  delete next.users_q
                } else {
                  next.users_q = q.trim()
                }
                return next
              })
            }}
            usersListFilters={usersListFilters}
            onUsersListFiltersChange={onUsersListFiltersChange}
            resellersSearchQuery={listQuery.resellers_q ?? ""}
            resellersStatusFilter={listQuery.resellers_status ?? "all"}
            onResellersFiltersChange={(patch) => {
              setListQuery((prev: Record<string, string>) => {
                const next: Record<string, string> = { ...prev, resellers_page: "1" }
                if (patch.q !== undefined) {
                  if (patch.q.trim() === "") delete next.resellers_q
                  else next.resellers_q = patch.q.trim()
                }
                if (patch.status !== undefined) {
                  if (patch.status === "all") delete next.resellers_status
                  else next.resellers_status = patch.status
                }
                return next
              })
            }}
            resellerReportsSearchQuery={listQuery.reseller_reports_q ?? ""}
            resellerReportsWindowDays={
              [7, 30, 90].includes(Number(listQuery.reseller_reports_days))
                ? Number(listQuery.reseller_reports_days)
                : 30
            }
            resellerReportsSort={listQuery.reseller_reports_sort ?? "sales"}
            onResellerReportsFiltersChange={(patch) => {
              setListQuery((prev: Record<string, string>) => {
                const next: Record<string, string> = { ...prev, resellerReports_page: "1" }
                if (patch.q !== undefined) {
                  if (patch.q.trim() === "") delete next.reseller_reports_q
                  else next.reseller_reports_q = patch.q.trim()
                }
                if (patch.days !== undefined && [7, 30, 90].includes(patch.days)) {
                  next.reseller_reports_days = String(patch.days)
                }
                if (patch.sort !== undefined) {
                  next.reseller_reports_sort = patch.sort
                }
                return next
              })
            }}
            overviewMetricsWindowDays={
              [7, 30, 90].includes(Number(listQuery.overviewMetricsDays))
                ? Number(listQuery.overviewMetricsDays)
                : 30
            }
            onOverviewMetricsWindowChange={(days) => {
              setListQuery((prev: Record<string, string>) => ({
                ...prev,
                overviewMetricsDays: String(days),
              }))
            }}
            statsDay={
              [0, 1, 2, 3, 4, 5, 6, 7].includes(Number(listQuery.statsDay))
                ? Number(listQuery.statsDay)
                : 0
            }
            onStatsDayChange={(day) => {
              setListQuery((prev: Record<string, string>) => ({
                ...prev,
                statsDay: String(day),
              }))
            }}
            marketingLifecycleWindowDays={
              [7, 30, 90].includes(Number(listQuery.marketing_lifecycle_days))
                ? Number(listQuery.marketing_lifecycle_days)
                : 30
            }
            onMarketingLifecycleWindowDaysChange={(days) => {
              setListQuery((prev: Record<string, string>) => ({
                ...prev,
                marketing_lifecycle_days: String(days),
                marketingOffers_page: "1",
              }))
            }}
            marketingOffersStatus={listQuery.marketingOffers_status ?? ""}
            onMarketingOffersStatusChange={(status) => {
              setListQuery((prev: Record<string, string>) => ({
                ...prev,
                marketingOffers_status: status,
                marketingOffers_page: "1",
              }))
            }}
            onViewMarketingSegmentUsers={(segment) => {
              setUserDetailId(null)
              setActiveTab("users")
              setListQuery((prev: Record<string, string>) => ({
                ...prev,
                users_segment: segment,
                users_page: "1",
              }))
              const base = dashboardBaseUrl.replace(/\/?$/, "")
              window.history.pushState({ tab: "users" }, "", `${base}/users/?users_segment=${encodeURIComponent(segment)}`)
            }}
            customerChargesType={listQuery.customerChargesType ?? "all"}
            customerChargesDateFrom={listQuery.customerChargesDateFrom ?? ""}
            customerChargesDateTo={listQuery.customerChargesDateTo ?? ""}
            onCustomerChargesTypeChange={(type) => {
              setListQuery((prev: Record<string, string>) => {
                const next: Record<string, string> = { ...prev, customerChargesPage: "1" }
                if (type === "all" || type === "") {
                  delete next.customerChargesType
                } else {
                  next.customerChargesType = type
                }
                return next
              })
            }}
            onCustomerChargesDateFromChange={(value) => {
              setListQuery((prev: Record<string, string>) => {
                const next: Record<string, string> = { ...prev, customerChargesPage: "1" }
                if (value) {
                  next.customerChargesDateFrom = value
                } else {
                  delete next.customerChargesDateFrom
                }
                return next
              })
            }}
            onCustomerChargesDateToChange={(value) => {
              setListQuery((prev: Record<string, string>) => {
                const next: Record<string, string> = { ...prev, customerChargesPage: "1" }
                if (value) {
                  next.customerChargesDateTo = value
                } else {
                  delete next.customerChargesDateTo
                }
                return next
              })
            }}
            receiptsListFilters={receiptsListFilters}
            onReceiptsListFiltersChange={onReceiptsListFiltersChange}
            onRefreshPanelHealth={() => fetchDashState({ refreshPanelHealth: true })}
            onRefreshLivePanelMetrics={() => fetchDashState({ refreshLivePanelMetrics: true })}
            onAdminMutateSuccess={() => fetchDashState()}
            onImpersonateReseller={isAdmin && !impersonating ? onImpersonateReseller : undefined}
          />
          </div>
        ) : Number(boot.svpUserId) > 0 ? (
          <DashboardUserPortal restUrl={restBase} />
        ) : (
          <p className="text-sm text-muted-foreground">{t("noLinkedUser")}</p>
        )}
      </div>
    </SidebarInset>
  )

  const dashLang: DashLang = lang === "en" ? "en" : "fa"

  const pathTab =
    typeof window !== "undefined"
      ? parseDashFromPath(window.location.pathname, { reseller: Boolean(boot.isReseller) }).tab
      : activeTab

  if (pathTab === "setup") {
    return (
      <DashLocaleProvider lang={boot.lang === "en" ? "en" : "fa"}>
        <InstallWizard />
      </DashLocaleProvider>
    )
  }

  if (setupGate === "loading") {
    return (
      <DashLocaleProvider lang={boot.lang === "en" ? "en" : "fa"}>
        <div className="flex min-h-svh items-center justify-center text-sm text-muted-foreground">
          {t("loading")}
        </div>
      </DashLocaleProvider>
    )
  }

  if (boot.isLoggedIn === false) {
    return (
      <DashLocaleProvider lang={boot.lang === "en" ? "en" : "fa"}>
        <DashboardLogin />
      </DashLocaleProvider>
    )
  }

  return (
    <DashLocaleProvider lang={dashLang}>
      <SidebarProvider
        dir={isFa ? "rtl" : "ltr"}
        className="flex-col"
        defaultOpen={sidebarDefaultOpen}
        onOpenChange={onSidebarOpenChange}
      >
        {impersonating && impersonationTargetLabel ? (
          <ImpersonationBanner
            targetLabel={impersonationTargetLabel}
            restBase={restBase}
            dashboardBaseUrl={dashboardBaseUrl}
          />
        ) : null}
        <div className="flex min-h-0 w-full flex-1">
          {sidebarEl}
          {insetEl}
        </div>
      </SidebarProvider>
    </DashLocaleProvider>
  )
}

export default App
