"use client"

import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react"
import { useLocale, useTranslations } from "next-intl"
import { usePathname, useRouter } from "next/navigation"
import { useTheme } from "next-themes"
import { AppSidebar } from "@/components/app-sidebar"
import { DashboardShellProvider, useDashboardShell } from "@/components/dashboard-shell-provider"
import { DashboardToolbar } from "@/components/dashboard-toolbar"
import { DashboardUserPortal } from "@/components/dashboard-user-portal"
import { DashboardSearch } from "@/components/sidebar-search"
import { InstallWizard } from "@/components/install-wizard"
import { DashLocaleProvider } from "@/lib/dash-locale-context"
import { apiBase } from "@/lib/api"
import { ACCENT_BRANDING_VAR_KEYS, normalizeAccent } from "@/lib/accent"
import { saveUiPreferences, type UiTheme } from "@/lib/dash-ui-preferences"
import { fetchSetupStatus, readInstallToken } from "@/lib/setup-wizard-api"
import {
  ADMIN_NAV_SECTIONS,
  filterAdminNavByFeatures,
  filterAdminNavForReseller,
  injectL2tpNavTab,
  type AdminNavSection,
} from "@/config/admin-nav"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import { SidebarInset, SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar"
import { cn } from "@/lib/utils"
import { buildAllowedResellerTabs } from "@/lib/safe-reseller-tab"

function useIsSetupPath(pathname: string): boolean {
  return /\/(?:dashboard\/)?setup(?:\/|$)/.test(pathname)
}

function useBreadcrumbLabel(): string {
  const pathname = usePathname()
  const t = useTranslations()
  const parts = pathname.split("/").filter(Boolean)
  const dashIdx = parts.indexOf("dashboard")
  const afterDash = dashIdx >= 0 ? parts.slice(dashIdx + 1) : []

  if (afterDash[0] === "users" && afterDash[1] === "u" && afterDash[2]) {
    try {
      return t("layout.userDetailTitle", { id: afterDash[2] })
    } catch {
      return `User #${afterDash[2]}`
    }
  }

  if (afterDash[0] === "reseller_workspace") {
    try {
      return t("sidebar.groups.resellerWorkspace")
    } catch {
      return "reseller_workspace"
    }
  }

  const tab = afterDash[0] && afterDash[0] !== "setup" ? afterDash[0] : "dashboard"
  try {
    return t(`sidebar.items.${tab}`)
  } catch {
    try {
      return t(`sidebar.tabs.${tab}`)
    } catch {
      return tab
    }
  }
}

function buildSearchSections(
  features: ReturnType<typeof useDashboardShell>["features"],
  isReseller: boolean,
  allowedResellerTabs: string[] | null,
  actorPermissions?: Record<string, boolean> | null
): AdminNavSection[] {
  let sections = injectL2tpNavTab(ADMIN_NAV_SECTIONS, features?.l2tp === true)
  sections = filterAdminNavByFeatures(sections, features)
  if (isReseller) {
    const allowed = buildAllowedResellerTabs(allowedResellerTabs, actorPermissions)
    sections = filterAdminNavForReseller(sections, allowed)
  }
  return sections
}

export function DashboardShellInner({ children }: { children: ReactNode }) {
  const locale = useLocale()
  const router = useRouter()
  const pathname = usePathname()
  const t = useTranslations()
  const { setTheme } = useTheme()
  const {
    me,
    features,
    isReseller,
    allowedResellerTabs,
    actorPermissions,
    impersonating,
    loading,
    openUserDetail,
  } = useDashboardShell()
  const breadcrumbLabel = useBreadcrumbLabel()
  const isSetup = useIsSetupPath(pathname)
  const isFa = locale === "fa"
  const sidebarSide: "left" | "right" = isFa ? "right" : "left"

  const [sidebarOpen, setSidebarOpen] = useState(true)
  const [setupGate, setSetupGate] = useState<"loading" | "open" | "closed">("loading")
  const prefsApplied = useRef(false)

  useEffect(() => {
    let cancelled = false
    void fetchSetupStatus()
      .then((st) => {
        if (cancelled) return
        const path = window.location.pathname.replace(/\/+$/, "") || "/"
        const onSetup = /\/(?:dashboard\/)?setup(?:\/|$)/.test(path)
        if (st.completed && onSetup) {
          const login = st.dashboard_login_url || `/${locale}/login`
          window.location.replace(login)
          return
        }
        if (st.open && !onSetup) {
          const token = readInstallToken()
          const q = token ? `?token=${encodeURIComponent(token)}` : ""
          window.location.replace(`/${locale}/setup/${q}`)
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
  }, [locale])

  useEffect(() => {
    if (loading || prefsApplied.current) return
    prefsApplied.current = true
    if (!me) return

    const accent = normalizeAccent(String(me.uiAccent ?? me.ui_accent ?? ""))
    if (accent === "default") {
      document.documentElement.removeAttribute("data-accent")
    } else {
      document.documentElement.setAttribute("data-accent", accent)
    }
    try {
      localStorage.setItem("svp-ui-accent", accent)
    } catch {
      /* ignore */
    }

    const themeRaw = String(me.uiTheme ?? me.ui_theme ?? "").toLowerCase()
    if (themeRaw === "light" || themeRaw === "dark" || themeRaw === "system") {
      setTheme(themeRaw as UiTheme)
    }

    const sidebar = String(me.uiSidebar ?? me.ui_sidebar ?? "")
    setSidebarOpen(sidebar !== "collapsed")

    const branding = me.branding as { cssVariables?: Record<string, string> } | undefined
    const vars = branding?.cssVariables
    if (vars && typeof vars === "object") {
      const root = document.documentElement
      const skipAccentVars = accent !== "default"
      for (const [key, val] of Object.entries(vars)) {
        if (!val) continue
        if (skipAccentVars && ACCENT_BRANDING_VAR_KEYS.has(key)) continue
        root.style.setProperty(key, val)
      }
    }
  }, [loading, me, setTheme])

  const onSidebarOpenChange = useCallback((open: boolean) => {
    setSidebarOpen(open)
    void saveUiPreferences({ ui_sidebar: open ? "expanded" : "collapsed" })
  }, [])

  const selectTab = useCallback(
    (tabKey: string) => {
      const key = tabKey === "dashboard" ? "" : tabKey
      router.push(key ? `/${locale}/dashboard/${key}` : `/${locale}/dashboard`)
    },
    [locale, router]
  )

  const searchSections = useMemo(
    () => buildSearchSections(features, isReseller, allowedResellerTabs, actorPermissions),
    [features, isReseller, allowedResellerTabs, actorPermissions]
  )

  const displayUser = me?.user
    ? {
        name: String(me.user.display_name || me.user.name || me.user.username || "User"),
        email: String(me.user.email || ""),
        avatar: "",
      }
    : undefined

  const siteName = String(me?.siteName ?? me?.site_name ?? "")
  const siteIconUrl = String(me?.siteIconUrl ?? me?.site_icon_url ?? "") || undefined

  const isUserPersona = !loading && me?.activePersona === "user" && !impersonating
  const isOperator = !isUserPersona

  if (isSetup) {
    return <InstallWizard />
  }

  if (setupGate === "loading") {
    return (
      <div className="flex min-h-svh items-center justify-center text-sm text-muted-foreground">
        {t("loading")}
      </div>
    )
  }

  const mobileHeaderToolbar = <DashboardToolbar variant="sidebar" />

  return (
    <SidebarProvider open={sidebarOpen} onOpenChange={onSidebarOpenChange}>
      <AppSidebar
        side={sidebarSide}
        user={displayUser}
        features={features}
        isReseller={isReseller}
        allowedResellerTabs={allowedResellerTabs}
        personaSwitchBlocked={impersonating}
        activePersona={me?.activePersona}
        availablePersonas={
          Array.isArray(me?.availablePersonas) ? (me.availablePersonas as string[]) : undefined
        }
        siteName={siteName}
        siteIconUrl={siteIconUrl}
        mobileHeaderToolbar={mobileHeaderToolbar}
      />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
          <div className="flex w-full min-w-0 items-center gap-2 px-4">
            <div className="flex min-w-0 flex-1 items-center gap-2 md:hidden">
              <SidebarTrigger className={cn("-ms-1 shrink-0", isFa && "rotate-180")} />
              {isOperator ? (
                <DashboardSearch
                  placement="header"
                  className="min-w-0 flex-1 max-w-none"
                  onSelectTab={selectTab}
                  onOpenUserDetail={openUserDetail}
                  restUrl={apiBase()}
                  sections={searchSections}
                />
              ) : (
                <div className="flex-1" />
              )}
            </div>
            <div className="hidden min-w-0 shrink-0 items-center gap-2 md:flex">
              <SidebarTrigger className={cn("-ms-1 shrink-0", isFa && "rotate-180")} />
              <Separator orientation="vertical" className="me-2 data-[orientation=vertical]:h-4" />
              <Breadcrumb className="min-w-0 max-w-[14rem] sm:max-w-xs">
                <BreadcrumbList>
                  <BreadcrumbItem className="hidden md:block">
                    <BreadcrumbLink href={`/${locale}/dashboard`}>
                      {siteName.trim() || t("layout.dashboard")}
                    </BreadcrumbLink>
                  </BreadcrumbItem>
                  <BreadcrumbSeparator className="hidden md:block" />
                  <BreadcrumbItem>
                    <BreadcrumbPage>
                      {isUserPersona ? t("myPanel") : breadcrumbLabel}
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
                  restUrl={apiBase()}
                  sections={searchSections}
                />
              </div>
            ) : (
              <div className="hidden flex-1 md:block" />
            )}
            <DashboardToolbar variant="header" className="hidden md:flex" />
          </div>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
          {isUserPersona ? (
            <DashboardUserPortal restUrl={apiBase()} />
          ) : (
            children
          )}
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}

export function DashboardShell({ children }: { children: ReactNode }) {
  return (
    <DashboardShellProvider>
      <DashLocaleProvider>
        <DashboardShellInner>{children}</DashboardShellInner>
      </DashLocaleProvider>
    </DashboardShellProvider>
  )
}
