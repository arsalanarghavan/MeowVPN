"use client"

import * as React from "react"
import type { ReactNode } from "react"
import Link from "next/link"
import { useLocale, useTranslations } from "next-intl"
import { usePathname } from "next/navigation"
import { CheckIcon, GalleryVerticalEndIcon, UserRoundCogIcon } from "lucide-react"
import { cn } from "@/lib/utils"
import {
  ADMIN_NAV_SECTIONS,
  filterAdminNavByFeatures,
  filterAdminNavForReseller,
  injectL2tpNavTab,
  type AdminNavSection,
  type DashboardFeatures,
} from "@/config/admin-nav"
import { NavMain } from "@/components/nav-main"
import { NavUser } from "@/components/nav-user"
import { Button } from "@/components/ui/button"
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import { apiBase, apiHeaders, ensureCsrfCookie, normalizeAdminApiPath } from "@/lib/api"

type DashPersona = "admin" | "reseller" | "user"

function tabHref(locale: string, tabKey: string) {
  if (tabKey === "dashboard") return `/${locale}/dashboard`
  return `/${locale}/dashboard/${tabKey}`
}

function sectionsToNavMain(
  sections: AdminNavSection[],
  locale: string,
  t: (key: string) => string,
  tTab: (key: string) => string,
  pathname: string
) {
  return sections.flatMap((sec) =>
    sec.entries.map((ent) => {
      if (ent.kind === "leaf") {
        const url = tabHref(locale, ent.tabKey)
        return {
          title: tTab(ent.tabKey),
          url,
          icon: ent.icon ? <ent.icon /> : undefined,
          isActive: pathname === url || pathname.startsWith(`${url}/`),
        }
      }
      return {
        title: t(ent.labelKey),
        url: "#",
        icon: ent.icon ? <ent.icon /> : undefined,
        items: ent.children.map((ch) => ({
          title: tTab(ch.tabKey),
          url: tabHref(locale, ch.tabKey),
        })),
      }
    })
  )
}

function RoleSwitcher({
  activePersona,
  availablePersonas,
  personaSwitchBlocked,
}: {
  activePersona: DashPersona
  availablePersonas: DashPersona[]
  personaSwitchBlocked?: boolean
}) {
  const t = useTranslations()
  const [switchError, setSwitchError] = React.useState<string | null>(null)

  const label =
    activePersona === "admin"
      ? t("sidebar.role.admin")
      : activePersona === "reseller"
        ? t("sidebar.role.reseller")
        : t("sidebar.role.user")

  const setPersona = (persona: DashPersona) => {
    if (persona === activePersona) return
    void (async () => {
      setSwitchError(null)
      try {
        await ensureCsrfCookie()
        const r = await fetch(`${apiBase()}${normalizeAdminApiPath("/dashboard/persona")}`, {
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

  if (availablePersonas.length < 2) return null

  if (personaSwitchBlocked) {
    return (
      <TooltipProvider delay={200}>
        <Tooltip>
          <TooltipTrigger>
            <span className="inline-flex">
              <Button
                type="button"
                variant="outline"
                size="icon"
                className="h-8 w-8 shrink-0"
                disabled
                aria-label={label}
              >
                <UserRoundCogIcon className="size-4 opacity-50" />
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent side="top" className="max-w-xs text-start">
            <p>{t("layout.personaSwitchBlockedImpersonation")}</p>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    )
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        className="inline-flex size-8 items-center justify-center rounded-lg border border-border bg-background hover:bg-muted"
        aria-label={label}
        title={label}
      >
        <UserRoundCogIcon className="size-4" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-48 text-start">
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
              <CheckIcon className="size-4 shrink-0 opacity-90" />
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
              <CheckIcon className="size-4 shrink-0 opacity-90" />
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
              <CheckIcon className="size-4 shrink-0 opacity-90" />
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
  user,
  features,
  isReseller,
  allowedResellerTabs,
  personaSwitchBlocked,
  activePersona,
  availablePersonas,
  siteName,
  siteIconUrl,
  mobileHeaderToolbar,
  ...props
}: React.ComponentProps<typeof Sidebar> & {
  user?: { name: string; email: string; avatar?: string }
  features?: DashboardFeatures | null
  isReseller?: boolean
  allowedResellerTabs?: string[] | null
  personaSwitchBlocked?: boolean
  activePersona?: string
  availablePersonas?: string[]
  siteName?: string
  siteIconUrl?: string
  mobileHeaderToolbar?: ReactNode
}) {
  const t = useTranslations()
  const locale = useLocale()
  const pathname = usePathname()
  const tTab = (key: string) => {
    try {
      return t(`sidebar.items.${key}`)
    } catch {
      try {
        return t(`sidebar.tabs.${key}`)
      } catch {
        return key
      }
    }
  }

  const filteredSections = React.useMemo(() => {
    const l2tpOn = features?.l2tp === true
    let sections = injectL2tpNavTab(ADMIN_NAV_SECTIONS, l2tpOn)
    sections = filterAdminNavByFeatures(sections, features)
    if (isReseller) {
      const allowed = new Set(
        Array.isArray(allowedResellerTabs) && allowedResellerTabs.length > 0
          ? allowedResellerTabs
          : [
              "dashboard",
              "users",
              "plans",
              "cards",
              "payments",
              "receipts",
              "reseller_bots",
              "reseller_charge",
              "reseller_settings",
              "reseller_xui_panels",
              "configs",
              "bot_ui",
              "discounts",
            ]
      )
      sections = filterAdminNavForReseller(sections, allowed)
    }
    return sections
  }, [features, isReseller, allowedResellerTabs])

  const navMain = React.useMemo(
    () =>
      sectionsToNavMain(
        filteredSections,
        locale,
        (k) => t(k),
        tTab,
        pathname
      ),
    // tTab is derived from t each render; intentional deps
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [filteredSections, locale, pathname, t]
  )

  const displayUser = user ?? {
    name: t("layout.adminUser"),
    email: "admin@meowvpn.local",
    avatar: "",
  }

  const personas = (availablePersonas ?? []).filter((p): p is DashPersona =>
    p === "admin" || p === "reseller" || p === "user"
  )
  const persona: DashPersona =
    activePersona === "admin" || activePersona === "reseller" || activePersona === "user"
      ? activePersona
      : isReseller
        ? "reseller"
        : "admin"

  const mobileToolbarBlock = mobileHeaderToolbar ? (
    <div className="w-full border-t border-sidebar-border pt-2 pb-1 md:hidden">
      {mobileHeaderToolbar}
    </div>
  ) : null

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader
        className={cn(
          "h-auto min-h-16 shrink-0 gap-0",
          mobileHeaderToolbar && "flex flex-col items-stretch"
        )}
      >
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              size="lg"
              render={<Link href={`/${locale}/dashboard`} />}
            >
                <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                  {siteIconUrl ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={siteIconUrl} alt="" className="size-8 object-cover" />
                  ) : (
                    <GalleryVerticalEndIcon className="size-4" />
                  )}
                </div>
                <div className="grid flex-1 text-start text-sm leading-tight">
                  <span className="truncate font-semibold">
                    {siteName?.trim() || t("sidebar.siteFallback")}
                  </span>
                  <span className="truncate text-xs">{t("layout.dashboard")}</span>
                </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
        {mobileToolbarBlock}
      </SidebarHeader>
      <SidebarContent>
        <NavMain items={navMain} />
      </SidebarContent>
      <SidebarFooter>
        <div className="flex items-center gap-2 px-2 pb-1">
          <RoleSwitcher
            activePersona={persona}
            availablePersonas={personas}
            personaSwitchBlocked={personaSwitchBlocked}
          />
        </div>
        <NavUser user={displayUser} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
