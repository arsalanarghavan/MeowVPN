"use client"

import { Check, LayoutDashboard, LifeBuoy, MessageSquareQuote, UserRoundCog } from "lucide-react"
import type { MouseEvent, ReactNode } from "react"
import { useState } from "react"
import { useTranslation } from "react-i18next"

import { apiHeaders, ensureCsrfCookie, normalizeAdminApiPath } from "@/lib/api-base"
import { NavGrouped } from "@/components/nav-grouped"
import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/components/ui/sidebar"
import { Button } from "@/components/ui/button"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import type { AdminNavSection } from "@/config/admin-nav"
import { menuBtnCollapsedIcon } from "@/lib/sidebar-menu-classes"
import { cn } from "@/lib/utils"
import { buildDashboardTabUrl } from "@/lib/dash-tab"
import { writeSiteSubtabToUrl } from "@/lib/site-settings-subtab"
import { useDashLocale } from "@/lib/dash-locale-context"

type NavTab = {
  key: string
  label: string
}

function SidebarQuickLinks({
  variant,
  dashboardBaseUrl,
  onSelectTab,
}: {
  variant: "admin" | "reseller" | "user"
  dashboardBaseUrl: string
  onSelectTab: (tabKey: string) => void
}) {
  const { dir } = useDashLocale()
  const { t } = useTranslation()
  const base = dashboardBaseUrl.replace(/\/?$/, "")

  const openSupportSettings = (e: MouseEvent) => {
    e.preventDefault()
    if (variant !== "admin") return
    writeSiteSubtabToUrl("whitelabel")
    onSelectTab("site_settings")
    const url = `${base}/site_settings/?site_subtab=whitelabel#whitelabel-support`
    window.history.pushState({ tab: "site_settings" }, "", url)
    window.setTimeout(() => {
      document.getElementById("whitelabel-support")?.scrollIntoView({ behavior: "smooth", block: "start" })
    }, 200)
  }

  const host = typeof window !== "undefined" ? window.location.hostname : "localhost"
  const resellerSupportHref = `mailto:support@${host}?subject=${encodeURIComponent(t("sidebar.footer.support"))}`

  return (
    <SidebarMenu
      className="border-b border-sidebar-border px-0 pb-3"
      dir={dir}
    >
      <SidebarMenuItem>
        <SidebarMenuButton
          asChild={variant !== "admin"}
          size="default"
          tooltip={t("sidebar.footer.support")}
          className={menuBtnCollapsedIcon}
          onClick={variant === "admin" ? openSupportSettings : undefined}
        >
          {variant === "admin" ? (
            <>
              <LifeBuoy />
              <span className="truncate">{t("sidebar.footer.support")}</span>
            </>
          ) : (
            <a href={resellerSupportHref}>
              <LifeBuoy />
              <span className="truncate">{t("sidebar.footer.support")}</span>
            </a>
          )}
        </SidebarMenuButton>
      </SidebarMenuItem>
      <SidebarMenuItem>
        <SidebarMenuButton
          asChild
          size="default"
          tooltip={t("sidebar.footer.feedback")}
          className={menuBtnCollapsedIcon}
        >
          <a href="#feedback" onClick={(e) => e.preventDefault()}>
            <MessageSquareQuote />
            <span className="truncate">{t("sidebar.footer.feedback")}</span>
          </a>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}

function RoleSwitcher({
  activePersona,
  availablePersonas,
  restUrl,
  personaSwitchBlocked,
}: {
  activePersona: "admin" | "reseller" | "user"
  availablePersonas: Array<"admin" | "reseller" | "user">
  restUrl: string
  /** True while impersonating a reseller — persona API returns 403 until stopped. */
  personaSwitchBlocked?: boolean
}) {
  const { dir } = useDashLocale()
  const { t } = useTranslation()
  const label =
    activePersona === "admin"
      ? t("sidebar.role.admin")
      : activePersona === "reseller"
        ? t("sidebar.role.reseller")
        : t("sidebar.role.user")

  const [switchError, setSwitchError] = useState<string | null>(null)

  const setPersona = (persona: "admin" | "reseller" | "user") => {
    if (persona === activePersona) return
    const base = restUrl.replace(/\/$/, "")
    void (async () => {
      setSwitchError(null)
      try {
        await ensureCsrfCookie()
        const r = await fetch(`${base}${normalizeAdminApiPath("/dashboard/persona")}`, {
          method: "POST",
          headers: apiHeaders(),
          credentials: "include",
          body: JSON.stringify({ persona }),
        })
        const json = (await r.json()) as { ok?: boolean; message?: string; reason?: string }
        if (r.ok && json?.ok) {
          window.location.reload()
          return
        }
        setSwitchError(String(json.message ?? json.reason ?? `http_${r.status}`))
      } catch {
        setSwitchError(t("layout.personaSwitchFailed"))
      }
    })()
  }

  if (personaSwitchBlocked) {
    return (
      <TooltipProvider delayDuration={200}>
        <Tooltip>
          <TooltipTrigger asChild>
            <span className="inline-flex">
              <Button
                type="button"
                variant="outline"
                size="icon"
                className="h-8 w-8 shrink-0"
                disabled
                aria-label={label}
              >
                <UserRoundCog className="size-4 opacity-50" />
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent side="bottom" className="max-w-xs text-start">
            <p>{t("layout.personaSwitchBlockedImpersonation")}</p>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    )
  }

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="h-8 w-8 shrink-0"
          aria-label={label}
          title={label}
        >
          <UserRoundCog className="size-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent
        align="end"
        style={{ direction: dir }}
        className="min-w-48 text-start"
      >
        <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
          {t("sidebar.role.switchLabel")}
        </DropdownMenuLabel>
        {switchError ? (
          <p className="px-2 py-1 text-xs text-destructive" role="alert">
            {switchError}
          </p>
        ) : null}
        <DropdownMenuSeparator />
        {availablePersonas.includes("admin") ? (
          <DropdownMenuItem
            disabled={activePersona === "admin"}
            className="gap-2 text-sm"
            onClick={() => setPersona("admin")}
          >
            {activePersona === "admin" ? (
              <Check className="size-4 shrink-0 opacity-90" />
            ) : (
              <span className="inline-block w-4 shrink-0" aria-hidden />
            )}
            {t("sidebar.role.admin")}
          </DropdownMenuItem>
        ) : null}
        {availablePersonas.includes("reseller") ? (
          <DropdownMenuItem
            disabled={activePersona === "reseller"}
            className="gap-2 text-sm"
            onClick={() => setPersona("reseller")}
          >
            {activePersona === "reseller" ? (
              <Check className="size-4 shrink-0 opacity-90" />
            ) : (
              <span className="inline-block w-4 shrink-0" aria-hidden />
            )}
            {t("sidebar.role.reseller")}
          </DropdownMenuItem>
        ) : null}
        {availablePersonas.includes("user") ? (
          <DropdownMenuItem
            disabled={activePersona === "user"}
            className="gap-2 text-sm"
            onClick={() => setPersona("user")}
          >
            {activePersona === "user" ? (
              <Check className="size-4 shrink-0 opacity-90" />
            ) : (
              <span className="inline-block w-4 shrink-0" aria-hidden />
            )}
            {t("sidebar.role.user")}
          </DropdownMenuItem>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

export function AppSidebar({
  side,
  variant,
  navTabs,
  user,
  activeTabKey,
  onSelectTab,
  dashboardBaseUrl,
  siteName,
  siteIconUrl,
  adminSections,
  activePersona,
  availablePersonas,
  personaRestUrl,
  personaSwitchBlocked = false,
  mobileHeaderToolbar,
}: {
  side: "left" | "right"
  variant: "admin" | "reseller" | "user"
  navTabs: NavTab[]
  user: {
    name: string
    tgUserId: number
    baleUserId: number
    avatar: string
    logoutUrl?: string
  }
  activeTabKey: string
  onSelectTab: (tabKey: string) => void
  dashboardBaseUrl: string
  siteName: string
  siteIconUrl?: string
  adminSections?: AdminNavSection[]
  activePersona?: "admin" | "reseller" | "user"
  availablePersonas?: Array<"admin" | "reseller" | "user">
  personaRestUrl?: string
  personaSwitchBlocked?: boolean
  mobileHeaderToolbar?: ReactNode
}) {
  const { t } = useTranslation()
  const base = dashboardBaseUrl.replace(/\/?$/, "")
  const subItemUrl = (tabKey: string) => buildDashboardTabUrl(base, tabKey)

  const displayName = siteName.trim() || t("sidebar.siteFallback")
  const isAdmin = variant === "admin"
  const persona: "admin" | "reseller" | "user" =
    activePersona ?? (isAdmin ? "admin" : variant === "reseller" ? "reseller" : "user")
  const personas = availablePersonas ?? [persona]
  const showRoleSwitcher =
    Boolean(personaRestUrl) && (personas.length > 1 || personaSwitchBlocked)

  const userMainItems = [
    {
      title: t("myPanel"),
      url: `${base}/`,
      icon: LayoutDashboard,
      isActive: true,
      items: navTabs.map((tab) => ({
        title: tab.label,
        url: subItemUrl(tab.key),
        tabKey: tab.key,
      })),
    },
  ]

  const showOperatorHeader = variant === "admin" || variant === "reseller"

  const siteLogoInitial =
    displayName.trim().charAt(0).toUpperCase() || "?"

  const brandRowClass = cn(
    "flex h-full min-w-0 flex-1 items-center gap-2",
    "group-data-[collapsible=icon]:justify-center"
  )

  const brandLogo = siteIconUrl ? (
    <img
      src={siteIconUrl}
      alt=""
      className="size-8 shrink-0 rounded-md object-cover"
    />
  ) : (
    <div
      className="flex size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-accent text-xs font-semibold text-sidebar-accent-foreground"
      aria-hidden
    >
      {siteLogoInitial !== "?" ? (
        siteLogoInitial
      ) : (
        <LayoutDashboard className="size-4 opacity-80" />
      )}
    </div>
  )

  const showSidebarHeader =
    showOperatorHeader || (variant === "user" && showRoleSwitcher)

  const mobileToolbarBlock = mobileHeaderToolbar ? (
    <div className="w-full border-t border-sidebar-border pt-2 pb-1 md:hidden">
      {mobileHeaderToolbar}
    </div>
  ) : null

  return (
    <Sidebar side={side} collapsible="icon">
      {showSidebarHeader && (
        <SidebarHeader
          className={cn(
            "h-auto min-h-16 shrink-0 gap-0 border-b border-sidebar-border px-4 py-2 md:h-16 md:py-0",
            mobileHeaderToolbar && "flex flex-col items-stretch"
          )}
        >
          <div
            className={cn(
              "flex h-12 w-full items-center gap-2 md:h-full",
              "group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0"
            )}
          >
            {brandLogo}
            <div
              className={cn(
                brandRowClass,
                "sidebar-brand-expanded group-data-[collapsible=icon]:hidden"
              )}
            >
              <p className="sidebar-brand-label min-w-0 flex-1 truncate text-sm font-semibold leading-tight">
                {displayName}
              </p>
              {showRoleSwitcher ? (
                <RoleSwitcher
                  activePersona={persona}
                  availablePersonas={personas}
                  restUrl={personaRestUrl!}
                  personaSwitchBlocked={personaSwitchBlocked}
                />
              ) : null}
            </div>
            {showRoleSwitcher ? (
              <div className="hidden shrink-0 group-data-[collapsible=icon]:block">
                <RoleSwitcher
                  activePersona={persona}
                  availablePersonas={personas}
                  restUrl={personaRestUrl!}
                  personaSwitchBlocked={personaSwitchBlocked}
                />
              </div>
            ) : null}
          </div>
          {mobileToolbarBlock}
        </SidebarHeader>
      )}
      <SidebarContent>
        {!showSidebarHeader && mobileToolbarBlock ? (
          <div className="border-b border-sidebar-border px-4 py-2 md:hidden">
            {mobileHeaderToolbar}
          </div>
        ) : null}
        {variant === "admin" || variant === "reseller" ? (
          <NavGrouped
            activeTabKey={activeTabKey}
            onSelectTab={onSelectTab}
            subItemUrl={subItemUrl}
            sections={adminSections}
          />
        ) : (
          <NavMain
            items={userMainItems}
            groupLabel={t("myPanel")}
            activeTabKey={activeTabKey}
            onSubItemClick={onSelectTab}
            subItemUrl={subItemUrl}
          />
        )}
      </SidebarContent>
      <SidebarFooter className="gap-0">
        {(variant === "admin" || variant === "reseller") && (
          <SidebarQuickLinks
            variant={variant}
            dashboardBaseUrl={dashboardBaseUrl}
            onSelectTab={onSelectTab}
          />
        )}
        <div className={cn((variant === "admin" || variant === "reseller") && "px-0 pt-1")}>
          <NavUser user={user} />
        </div>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
