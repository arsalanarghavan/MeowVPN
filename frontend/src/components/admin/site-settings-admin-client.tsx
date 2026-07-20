"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import type { DashboardFeatures } from "@/config/admin-nav"
import { getAdminState } from "@/lib/dash-admin-mutate"
import {
  isSiteSettingsSubtab,
  readSiteSubtabFromUrl,
  writeSiteSubtabToUrl,
  type SiteSettingsSubtab,
} from "@/lib/site-settings-subtab"
import { SiteSettingsCronTab } from "@/components/site-settings/site-settings-cron-tab"
import { SiteSettingsFinanceTab } from "@/components/site-settings/site-settings-finance-tab"
import { SiteSettingsLandingTab } from "@/components/site-settings/site-settings-landing-tab"
import { SiteSettingsLogsTab } from "@/components/site-settings/site-settings-logs-tab"
import { SiteSettingsNotificationsTab } from "@/components/site-settings/site-settings-notifications-tab"
import { SiteSettingsProxyTab } from "@/components/site-settings/site-settings-proxy-tab"
import { SiteSettingsPurgeTab } from "@/components/site-settings/site-settings-purge-tab"
import { SiteSettingsRelayTab } from "@/components/site-settings/site-settings-relay-tab"
import { SiteSettingsResellersTab } from "@/components/site-settings/site-settings-resellers-tab"
import { SiteSettingsServiceNamingTab } from "@/components/site-settings/site-settings-service-naming-tab"
import { SiteSettingsSubscriptionPortalTab } from "@/components/site-settings/site-settings-subscription-portal-tab"
import { SiteSettingsWhitelabelTab } from "@/components/site-settings/site-settings-whitelabel-tab"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"

type DashRecord = Record<string, unknown>
type PortalPage = { id: number; title: string }

function recordArray(raw: unknown): DashRecord[] {
  return Array.isArray(raw) ? (raw as DashRecord[]) : []
}

function portalPagesFrom(raw: unknown): PortalPage[] {
  if (!Array.isArray(raw)) return []
  return (raw as { id?: unknown; title?: unknown }[])
    .map((p) => ({ id: Number(p.id), title: String(p.title ?? "") }))
    .filter((p) => Number.isFinite(p.id) && p.id > 0)
}

function currentDashboardBaseUrl(): string {
  if (typeof window === "undefined") return "/dashboard"
  return `${window.location.origin}/dashboard`
}

function isSubtabAllowed(subtab: SiteSettingsSubtab, features: DashboardFeatures | null): boolean {
  const relayOn = features?.relay === true && features?.telegram === true
  const telegramOn = features?.telegram === true
  const resellerOn = features?.reseller === true
  const xuiOn = features?.xui_panel === true
  if (subtab === "proxy") return telegramOn
  if (subtab === "relay") return relayOn
  if (subtab === "purge_expired") return xuiOn
  if (subtab === "resellers") return resellerOn
  return true
}

export function SiteSettingsAdminClient() {
  const t = useTranslations("siteSettings")
  const [data, setData] = useState<DashRecord>({})
  const [settings, setSettings] = useState<DashRecord>({})
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [subtab, setSubtab] = useState<SiteSettingsSubtab>("whitelabel")

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("site_settings")
      const s =
        data.settings && typeof data.settings === "object"
          ? (data.settings as DashRecord)
          : {}
      setData(data)
      setSettings(s)
    } catch {
      setError(t("common.saveNetworkError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (typeof window === "undefined") return
    const next = readSiteSubtabFromUrl()
    setSubtab(next)
    if (window.location.hash === "#whitelabel-support") {
      setSubtab("whitelabel")
      writeSiteSubtabToUrl("whitelabel")
    }
  }, [])

  const featuresSource = data.features ?? settings.features
  const features =
    featuresSource && typeof featuresSource === "object" ? (featuresSource as DashboardFeatures) : null

  const relayOn = features?.relay === true && features?.telegram === true
  const telegramOn = features?.telegram === true
  const resellerOn = features?.reseller === true
  const xuiOn = features?.xui_panel === true

  useEffect(() => {
    if (!isSubtabAllowed(subtab, features)) {
      setSubtab("whitelabel")
      writeSiteSubtabToUrl("whitelabel")
    }
  }, [features, subtab, relayOn, telegramOn, resellerOn, xuiOn])

  const onSubtabChange = useCallback((value: string) => {
    if (!isSiteSettingsSubtab(value)) return
    setSubtab(value)
    writeSiteSubtabToUrl(value)
  }, [])

  const portalPages = portalPagesFrom(data.portalPages ?? data.wpPages)
  const plans = recordArray(data.plans)
  const panels = recordArray(data.panels)
  const resellers = recordArray(data.resellers)
  const resellerPermissionsMap =
    data.resellerPermissionsMap && typeof data.resellerPermissionsMap === "object"
      ? (data.resellerPermissionsMap as Record<string, Record<string, boolean>>)
      : {}
  const panelRows = useMemo(
    () =>
      panels
        .map((p) => ({
          id: Number(p.id) || 0,
          name: String(p.name ?? p.title ?? "").trim() || `#${p.id}`,
        }))
        .filter((p) => p.id > 0),
    [panels]
  )
  const dashboardBaseUrl = currentDashboardBaseUrl()
  const onMutateSuccess = useCallback(() => void load(), [load])

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">{t("title")}</h1>
        <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("cron.loading")}</p> : null}
      <Tabs value={subtab} onValueChange={onSubtabChange}>
        <TabsList variant="line" className="h-auto flex-wrap">
          <TabsTrigger value="landing">{t("tabLanding")}</TabsTrigger>
          <TabsTrigger value="subscription_portal">{t("tabSubscriptionPortal")}</TabsTrigger>
          <TabsTrigger value="cron">{t("tabCron")}</TabsTrigger>
          {relayOn ? <TabsTrigger value="relay">{t("tabRelay")}</TabsTrigger> : null}
          <TabsTrigger value="whitelabel">{t("tabWhitelabel")}</TabsTrigger>
          <TabsTrigger value="service_naming">{t("tabServiceNaming")}</TabsTrigger>
          {telegramOn ? <TabsTrigger value="proxy">{t("tabProxy")}</TabsTrigger> : null}
          <TabsTrigger value="notifications">{t("tabNotifications")}</TabsTrigger>
          {xuiOn ? <TabsTrigger value="purge_expired">{t("tabPurgeExpired")}</TabsTrigger> : null}
          <TabsTrigger value="finance">{t("tabFinance")}</TabsTrigger>
          <TabsTrigger value="logs">{t("tabLogs")}</TabsTrigger>
          {resellerOn ? <TabsTrigger value="resellers">{t("tabResellers")}</TabsTrigger> : null}
        </TabsList>
        <TabsContent value="landing" className="mt-4">
          <SiteSettingsLandingTab settings={settings} onMutateSuccess={onMutateSuccess} />
        </TabsContent>
        <TabsContent value="subscription_portal" className="mt-4">
          <SiteSettingsSubscriptionPortalTab
            settings={settings}
            portalBaseUrl={dashboardBaseUrl}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
        <TabsContent value="cron" className="mt-4">
          <SiteSettingsCronTab settings={settings} onMutateSuccess={onMutateSuccess} />
        </TabsContent>
        {relayOn ? (
          <TabsContent value="relay" className="mt-4">
            <SiteSettingsRelayTab settings={settings} onMutateSuccess={onMutateSuccess} />
          </TabsContent>
        ) : null}
        <TabsContent value="whitelabel" className="mt-4">
          <SiteSettingsWhitelabelTab
            settings={settings}
            portalPages={portalPages}
            plans={plans}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
        <TabsContent value="service_naming" className="mt-4">
          <SiteSettingsServiceNamingTab
            settings={settings}
            panels={panelRows}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
        {telegramOn ? (
          <TabsContent value="proxy" className="mt-4">
            <SiteSettingsProxyTab settings={settings} onMutateSuccess={onMutateSuccess} />
          </TabsContent>
        ) : null}
        <TabsContent value="notifications" className="mt-4">
          <SiteSettingsNotificationsTab settings={settings} onMutateSuccess={onMutateSuccess} />
        </TabsContent>
        {xuiOn ? (
          <TabsContent value="purge_expired" className="mt-4">
            <SiteSettingsPurgeTab
              settings={settings}
              panels={panelRows}
              onMutateSuccess={onMutateSuccess}
            />
          </TabsContent>
        ) : null}
        <TabsContent value="finance" className="mt-4">
          <SiteSettingsFinanceTab
            settings={settings}
            dashboardBaseUrl={dashboardBaseUrl}
            features={features}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
        <TabsContent value="logs" className="mt-4">
          <SiteSettingsLogsTab />
        </TabsContent>
        {resellerOn ? (
          <TabsContent value="resellers" className="mt-4">
            <SiteSettingsResellersTab
              settings={settings}
              resellers={resellers}
              resellerPermissionsMap={resellerPermissionsMap}
              onMutateSuccess={onMutateSuccess}
            />
          </TabsContent>
        ) : null}
      </Tabs>
    </div>
  )
}
