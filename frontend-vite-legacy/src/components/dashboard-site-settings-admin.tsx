"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { SiteSettingsLogsTab } from "@/components/site-settings/site-settings-logs-tab"
import { DashPage } from "@/components/dash-page"
import { SiteSettingsNotificationsTab } from "@/components/site-settings/site-settings-notifications-tab"
import { SiteSettingsProxyTab } from "@/components/site-settings/site-settings-proxy-tab"
import { SiteSettingsRelayTab } from "@/components/site-settings/site-settings-relay-tab"
import { SiteSettingsResellersTab } from "@/components/site-settings/site-settings-resellers-tab"
import { SiteSettingsServiceNamingTab } from "@/components/site-settings/site-settings-service-naming-tab"
import { SiteSettingsFinanceTab } from "@/components/site-settings/site-settings-finance-tab"
import { SiteSettingsPurgeTab } from "@/components/site-settings/site-settings-purge-tab"
import { SiteSettingsWhitelabelTab } from "@/components/site-settings/site-settings-whitelabel-tab"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  readSiteSubtabFromUrl,
  writeSiteSubtabToUrl,
  type SiteSettingsSubtab,
} from "@/lib/site-settings-subtab"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"
import type { DashboardFeatures } from "@/config/admin-nav"

const siteSettingsTabTriggerClass =
  "flex-none shrink-0 grow-0 justify-start px-3"

type DashRecord = Record<string, unknown>
type PortalPage = { id: number; title: string }

export function DashboardSiteSettingsAdmin({
  settings,
  portalPages,
  plans,
  panels,
  resellers,
  resellerPermissionsMap,
  dashboardBaseUrl,
  features,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  features?: DashboardFeatures | null
  portalPages: PortalPage[]
  plans: DashRecord[]
  panels?: DashRecord[]
  resellers: DashRecord[]
  resellerPermissionsMap: Record<string, Record<string, boolean>>
  dashboardBaseUrl: string
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const { dir } = useDashLocale()
  const tp = (k: string) => t(`siteSettings.${k}`)
  const [subtab, setSubtab] = useState<SiteSettingsSubtab>(() => readSiteSubtabFromUrl())

  useEffect(() => {
    setSubtab(readSiteSubtabFromUrl())
  }, [])

  useEffect(() => {
    if (typeof window === "undefined") return
    if (window.location.hash !== "#whitelabel-support") return
    setSubtab("whitelabel")
    writeSiteSubtabToUrl("whitelabel")
  }, [])

  const onSubtabChange = useCallback((v: string) => {
    const next = v as SiteSettingsSubtab
    setSubtab(next)
    writeSiteSubtabToUrl(next)
  }, [])

  const pages = portalPages ?? []
  const resellerMap = resellerPermissionsMap ?? {}
  const relayOn = features?.relay === true && features?.telegram === true
  const telegramOn = features?.telegram === true
  const resellerOn = features?.reseller === true
  const xuiOn = features?.xui_panel === true

  useEffect(() => {
    if (!relayOn && subtab === "relay") {
      setSubtab("whitelabel")
      writeSiteSubtabToUrl("whitelabel")
    }
    if (!telegramOn && subtab === "proxy") {
      setSubtab("whitelabel")
      writeSiteSubtabToUrl("whitelabel")
    }
  }, [relayOn, telegramOn, subtab])

  const panelRows = useMemo(
    () =>
      (panels ?? [])
        .map((p) => ({
          id: Number(p.id) || 0,
          name: String(p.name ?? p.title ?? "").trim() || `#${p.id}`,
        }))
        .filter((p) => p.id > 0),
    [panels]
  )

  return (
    <DashPage className={"space-y-4"}>
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />

      <Tabs dir={dir} value={subtab} onValueChange={onSubtabChange} className="w-full">
        <TabsList
          variant="line"
          className={cn(
            "h-auto w-full flex-wrap justify-start gap-1 bg-transparent p-0 text-start"
          )}
        >
          <TabsTrigger value="whitelabel" className={siteSettingsTabTriggerClass}>
            {tp("tabWhitelabel")}
          </TabsTrigger>
          <TabsTrigger value="service_naming" className={siteSettingsTabTriggerClass}>
            {tp("tabServiceNaming")}
          </TabsTrigger>
          {telegramOn ? (
            <TabsTrigger value="proxy" className={siteSettingsTabTriggerClass}>
              {tp("tabProxy")}
            </TabsTrigger>
          ) : null}
          {relayOn ? (
            <TabsTrigger value="relay" className={siteSettingsTabTriggerClass}>
              {tp("tabRelay")}
            </TabsTrigger>
          ) : null}
          <TabsTrigger value="notifications" className={siteSettingsTabTriggerClass}>
            {tp("tabNotifications")}
          </TabsTrigger>
          {xuiOn ? (
            <TabsTrigger value="purge_expired" className={siteSettingsTabTriggerClass}>
              {tp("tabPurgeExpired")}
            </TabsTrigger>
          ) : null}
          <TabsTrigger value="finance" className={siteSettingsTabTriggerClass}>
            {tp("tabFinance")}
          </TabsTrigger>
          <TabsTrigger value="logs" className={siteSettingsTabTriggerClass}>
            {tp("tabLogs")}
          </TabsTrigger>
          {resellerOn ? (
            <TabsTrigger value="resellers" className={siteSettingsTabTriggerClass}>
              {tp("tabResellers")}
            </TabsTrigger>
          ) : null}
        </TabsList>

        <TabsContent value="whitelabel" className="mt-4 text-start">
          <SiteSettingsWhitelabelTab
            settings={settings}
            portalPages={pages}
            plans={plans}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
        <TabsContent value="service_naming" className="mt-4 text-start">
          <SiteSettingsServiceNamingTab
            settings={settings}
            panels={panelRows}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
        {telegramOn ? (
          <TabsContent value="proxy" className="mt-4 text-start">
            <SiteSettingsProxyTab settings={settings} onMutateSuccess={onMutateSuccess} />
          </TabsContent>
        ) : null}
        {relayOn ? (
          <TabsContent value="relay" className="mt-4 text-start">
            <SiteSettingsRelayTab settings={settings} onMutateSuccess={onMutateSuccess} />
          </TabsContent>
        ) : null}
        <TabsContent value="notifications" className="mt-4 text-start">
          <SiteSettingsNotificationsTab settings={settings} onMutateSuccess={onMutateSuccess} />
        </TabsContent>
        {xuiOn ? (
          <TabsContent value="purge_expired" className="mt-4 text-start">
            <SiteSettingsPurgeTab settings={settings} panels={panelRows} onMutateSuccess={onMutateSuccess} />
          </TabsContent>
        ) : null}
        <TabsContent value="finance" className="mt-4 text-start">
          <SiteSettingsFinanceTab
            settings={settings}
            features={features}
            dashboardBaseUrl={dashboardBaseUrl}
            onMutateSuccess={onMutateSuccess}
          />
        </TabsContent>
        <TabsContent value="logs" className="mt-4 text-start">
          <SiteSettingsLogsTab />
        </TabsContent>
        {resellerOn ? (
          <TabsContent value="resellers" className="mt-4 text-start">
            <SiteSettingsResellersTab
              settings={settings}
              resellers={resellers}
              resellerPermissionsMap={resellerMap}
              onMutateSuccess={onMutateSuccess}
            />
          </TabsContent>
        ) : null}
      </Tabs>
    </DashPage>
  )
}
