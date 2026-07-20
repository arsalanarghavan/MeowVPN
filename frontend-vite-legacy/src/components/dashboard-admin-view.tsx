import { useCallback, useEffect, useMemo, type Dispatch, type SetStateAction } from "react"
import { useTranslation } from "react-i18next"
import { DashboardAuditAdmin } from "@/components/dashboard-audit-admin"
import { DashboardBackupAdmin } from "@/components/dashboard-backup-admin"
import { DashboardBotUiStudio } from "@/components/dashboard-bot-ui-studio"
import { DashboardBotsAdmin } from "@/components/dashboard-bots-admin"
import { DashboardBroadcastAdmin } from "@/components/dashboard-broadcast-admin"
import { DashboardCardsAdmin } from "@/components/dashboard-cards-admin"
import { DashboardDiscountsAdmin } from "@/components/dashboard-discounts-admin"
import { DashboardSiteSettingsAdmin } from "@/components/dashboard-site-settings-admin"
import { DashboardMonitoring } from "@/components/dashboard-monitoring"
import { DashboardOverview, type OverviewPayload } from "@/components/dashboard-overview"
import { DashboardConfigsAdmin } from "@/components/dashboard-configs-admin"
import { DashboardL2tpAdmin } from "@/components/dashboard-l2tp-admin"
import { DashboardVpnServerAdmin } from "@/components/dashboard-vpn-server-admin"
import { DashboardPanelsAdmin } from "@/components/dashboard-panels-admin"
import { DashboardPlanCatsAdmin } from "@/components/dashboard-plan-cats-admin"
import { DashboardPlansAdmin, type ResellerPanelAccessDiagnostics } from "@/components/dashboard-plans-admin"
import { readPlansViewFromUrl } from "@/lib/plans-subview"
import {
  DashboardReceiptsAdmin,
  type ReceiptsListFilters,
} from "@/components/dashboard-receipts-admin"
import { DashboardResellerChargeAdmin } from "@/components/dashboard-reseller-charge-admin"
import { DashboardResellerSettings } from "@/components/dashboard-reseller-settings"
import { DashboardReferralAdmin } from "@/components/dashboard-referral-admin"
import { effectiveEnabledPlatforms, mainEnabledPlatforms } from "@/lib/enabled-platforms"
import type { BotPlatformId } from "@/config/bot-platforms"
import { DashboardUnitEconomicsAdmin } from "@/components/dashboard-unit-economics-admin"
import {
  DashboardResellerReportsAdmin,
  type ResellerReportDaily,
  type ResellerReportRow,
  type ResellerReportsStats,
} from "@/components/dashboard-reseller-reports-admin"
import {
  DashboardMarketingLifecycleAdmin,
  type MarketingFunnelDay,
  type MarketingLifecycleStats,
  type MarketingOfferRow,
  type MarketingRuleRow,
  type MarketingRuleStatRow,
} from "@/components/dashboard-marketing-lifecycle-admin"
import { DashboardResellersAdmin } from "@/components/dashboard-resellers-admin"
import { DashboardTextsAdmin } from "@/components/dashboard-texts-admin"
import {
  DashboardUsersAdmin,
  type UsersListFilters,
} from "@/components/dashboard-users-admin"
import { DashboardUsersBulkAdmin } from "@/components/dashboard-users-bulk-admin"
import { DashboardUserDetailAdmin } from "@/components/dashboard-user-detail-admin"
import {
  listQuerySetPage,
  parsePaginationMeta,
  type ListQueryKey,
  type PaginationMeta,
} from "@/lib/dash-pagination"
import { DashboardResellerPanelsAdmin } from "@/components/dashboard-reseller-panels-admin"
type NavTab = { key: string; label: string }

type DashData = {
  settings?: Record<string, unknown> & { enabled?: boolean }
  navTabs?: NavTab[]
  overview?: Record<string, unknown>
} & Record<string, unknown>

function pickPagination(data: DashData, key: string): PaginationMeta | null {
  const raw = data.pagination
  if (!raw || typeof raw !== "object") return null
  return parsePaginationMeta((raw as Record<string, unknown>)[key])
}

function asRecordArray(x: unknown): Record<string, unknown>[] {
  return Array.isArray(x) ? (x as Record<string, unknown>[]) : []
}

function parseUnitEconomicsGlobal(unitEconomics: unknown): {
  total_sold_volume_gb?: number
  selling_price_per_gb?: number
  volume_mode?: string
  volume_window_days?: number
} {
  if (!unitEconomics || typeof unitEconomics !== "object") return {}
  const inputs = (unitEconomics as Record<string, unknown>).inputs
  if (!inputs || typeof inputs !== "object") return {}
  const i = inputs as Record<string, unknown>
  return {
    total_sold_volume_gb: Number(i.effective_volume_gb ?? i.total_sold_volume_gb) || 0,
    selling_price_per_gb: Number(i.selling_price_per_gb) || 0,
    volume_mode: String(i.volume_mode ?? "auto_sales"),
    volume_window_days: Math.max(1, Number(i.volume_window_days) || 30),
  }
}

function dashActorSvpUserId(data: DashData): number {
  const u = data.user as Record<string, unknown> | undefined
  const x = Number(u?.svp_user_id)
  return Number.isFinite(x) && x > 0 ? Math.trunc(x) : 0
}

type Props = {
  data: DashData
  activeTab: string
  userDetailId: number | null
  /** Reseller operator (non–WP admin); tighter UI and catalog defaults. */
  isReseller?: boolean
  /** When set, overview/quick links only offer these tabs (already server-filtered). */
  allowedNavTabs?: Set<string> | null
  dashboardBaseUrl: string
  onSelectTab: (tabKey: string) => void
  onOpenUserDetail: (svpUserId: number) => void
  onOpenResellerWorkspace?: (resellerId: number) => void
  onCloseUserDetail: () => void
  setListQuery: Dispatch<SetStateAction<Record<string, string>>>
  usersSearchQuery: string
  onUsersSearchQueryChange: (q: string) => void
  usersListFilters: UsersListFilters
  onUsersListFiltersChange: (patch: Partial<UsersListFilters>) => void
  resellersSearchQuery: string
  resellersStatusFilter: string
  onResellersFiltersChange: (patch: { q?: string; status?: string }) => void
  receiptsListFilters: ReceiptsListFilters
  onReceiptsListFiltersChange: (patch: Partial<ReceiptsListFilters>) => void
  onRefreshPanelHealth?: () => void
  onRefreshLivePanelMetrics?: () => void
  onAdminMutateSuccess?: () => void
  onImpersonateReseller?: (svpUserId: number) => void
  resellerReportsSearchQuery?: string
  resellerReportsWindowDays?: number
  resellerReportsSort?: string
  onResellerReportsFiltersChange?: (patch: {
    q?: string
    days?: number
    sort?: string
  }) => void
  overviewMetricsWindowDays?: number
  onOverviewMetricsWindowChange?: (days: number) => void
  statsDay?: number
  onStatsDayChange?: (day: number) => void
  marketingLifecycleWindowDays?: number
  onMarketingLifecycleWindowDaysChange?: (days: number) => void
  marketingOffersStatus?: string
  onMarketingOffersStatusChange?: (status: string) => void
  onViewMarketingSegmentUsers?: (segment: string) => void
  customerChargesType?: string
  customerChargesDateFrom?: string
  customerChargesDateTo?: string
  onCustomerChargesTypeChange?: (type: string) => void
  onCustomerChargesDateFromChange?: (value: string) => void
  onCustomerChargesDateToChange?: (value: string) => void
}

export function DashboardAdminView({
  data,
  activeTab,
  userDetailId,
  isReseller = false,
  allowedNavTabs = null,
  dashboardBaseUrl,
  onSelectTab,
  onOpenUserDetail,
  onOpenResellerWorkspace,
  onCloseUserDetail,
  setListQuery,
  usersSearchQuery,
  onUsersSearchQueryChange,
  usersListFilters,
  onUsersListFiltersChange,
  resellersSearchQuery,
  resellersStatusFilter,
  onResellersFiltersChange,
  receiptsListFilters,
  onReceiptsListFiltersChange,
  onRefreshPanelHealth,
  onRefreshLivePanelMetrics,
  onAdminMutateSuccess,
  onImpersonateReseller,
  resellerReportsSearchQuery = "",
  resellerReportsWindowDays = 30,
  resellerReportsSort = "sales",
  onResellerReportsFiltersChange,
  overviewMetricsWindowDays = 30,
  onOverviewMetricsWindowChange,
  statsDay = 0,
  onStatsDayChange,
  marketingLifecycleWindowDays = 30,
  onMarketingLifecycleWindowDaysChange,
  marketingOffersStatus = "",
  onMarketingOffersStatusChange,
  onViewMarketingSegmentUsers,
  customerChargesType = "all",
  customerChargesDateFrom = "",
  customerChargesDateTo = "",
  onCustomerChargesTypeChange,
  onCustomerChargesDateFromChange,
  onCustomerChargesDateToChange,
}: Props) {
  const { t } = useTranslation()
  const navAllowed = allowedNavTabs
  const setPage = useCallback(
    (key: ListQueryKey, page: number) => {
      setListQuery((q) => listQuerySetPage(q, key, page))
    },
    [setListQuery]
  )
  const setPer = useCallback(
    (key: ListQueryKey, per: number) => {
      setListQuery((q) => listQuerySetPage(q, key, 1, per))
    },
    [setListQuery]
  )

  const settings = data.settings as Record<string, unknown> | undefined
  const enabledPlatforms: BotPlatformId[] = isReseller
    ? effectiveEnabledPlatforms(
        settings,
        (Array.isArray(data.botsList) ? (data.botsList[0] as Record<string, unknown>) : undefined)
      )
    : mainEnabledPlatforms(settings)
  const dashboardFeatures = useMemo(() => {
    const f = settings?.features
    return f && typeof f === "object" ? (f as Record<string, boolean>) : null
  }, [settings?.features])
  const l2tpEnabled = dashboardFeatures?.l2tp === true
  const xrayCoreEnabled = dashboardFeatures?.xray_core === true
  const tunnelEnabled = dashboardFeatures?.tunnel === true
  const xrayNodes = asRecordArray((data as Record<string, unknown>).xrayNodes)
  const xrayInbounds = asRecordArray((data as Record<string, unknown>).xrayInbounds)
  const xrayHosts = asRecordArray((data as Record<string, unknown>).xrayHosts)
  const tunnelEndpoints = asRecordArray((data as Record<string, unknown>).tunnelEndpoints)
  const featureTabAllowed = useCallback(
    (tabKey: string): boolean => {
      const f = dashboardFeatures
      if (!f) return true
      if (tabKey === "l2tp_servers") return f.l2tp === true
      if (tabKey === "backup") return f.backup === true
      if (tabKey === "xui_panels" || tabKey === "configs") return f.xui_panel === true
      if (tabKey === "vpn_server") return f.xray_core === true || f.tunnel === true
      if (tabKey === "xray_core" || tabKey === "xray_inbounds" || tabKey === "xray_hosts") return f.xray_core === true
      if (tabKey === "tunnel_nodes") return f.tunnel === true
      if (tabKey === "marketing_lifecycle") return f.marketing === true
      if (tabKey === "broadcast") return f.marketing === true
      if (
        tabKey === "resellers" ||
        tabKey === "reseller_bots" ||
        tabKey === "reseller_reports" ||
        tabKey === "reseller_settings" ||
        tabKey === "reseller_charge" ||
        tabKey === "reseller_workspace"
      ) {
        return f.reseller === true
      }
      if (tabKey === "cards") return f.crypto === true
      return true
    },
    [dashboardFeatures]
  )
  useEffect(() => {
    if (!featureTabAllowed(activeTab)) {
      onSelectTab("dashboard")
    }
  }, [activeTab, featureTabAllowed, onSelectTab])
  const panels = asRecordArray(data.panels)
  const plans = asRecordArray(data.plans)
  const planCategories = asRecordArray(data.planCategories)
  const cards = asRecordArray(data.cards)
  const l2tp = asRecordArray(data.l2tpServers)
  const texts = asRecordArray(data.texts)
  const users = asRecordArray(data.usersList)
  const pending = asRecordArray(data.pendingUsers)
  const receipts = asRecordArray(data.receipts)
  const disc = asRecordArray(data.discountCodes)
  const discountUsageSummary = (data.discountUsageSummary as Record<string, unknown> | undefined) ?? null
  const broadcasts = asRecordArray(data.broadcasts)
  const resellers = asRecordArray(data.resellers)
  const wholesaleLinesCatalog = asRecordArray((data as Record<string, unknown>).wholesaleLinesCatalog)
  const wholesaleLinesReseller = asRecordArray((data as Record<string, unknown>).wholesaleLines)
  const actorPermissions = (data.actorPermissions as Record<string, boolean> | undefined) ?? {}
  const notFound = (
    <p className="text-sm text-muted-foreground">{t("layout.adminUnknownSection")}</p>
  )

  if (activeTab === "dashboard") {
    const uBal = data.user as { balance?: unknown } | undefined
    const actorBal = typeof uBal?.balance === "number" ? uBal.balance : undefined
    const overviewMetrics = (data as Record<string, unknown>).resellerOverviewMetrics as
      | Record<string, unknown>
      | null
      | undefined
    const metricsWindow = [7, 30, 90].includes(overviewMetricsWindowDays)
      ? overviewMetricsWindowDays
      : 30
    return (
      <DashboardOverview
        overview={data.overview as OverviewPayload | undefined}
        panels={panels}
        panelsPagination={pickPagination(data, "panels")}
        dashboardBaseUrl={dashboardBaseUrl}
        allowedNavTabs={navAllowed}
        onSelectTab={onSelectTab}
        onRefreshPanelHealth={onRefreshPanelHealth}
        onPanelsPageChange={(p) => setPage("panels", p)}
        onPanelsPerPageChange={(n) => setPer("panels", n)}
        compactHealthOnly={false}
        prependResellerFinance={isReseller}
        actorBalance={actorBal}
        resellerOverviewMetrics={isReseller ? overviewMetrics : null}
        overviewMetricsWindowDays={metricsWindow}
        onOverviewMetricsWindowChange={onOverviewMetricsWindowChange}
        statsDay={statsDay}
        onStatsDayChange={onStatsDayChange}
        recentUsers={users.slice(0, 8)}
        recentReceipts={receipts.slice(0, 8)}
        pendingUsersPreview={pending.slice(0, 8)}
        recentResellers={resellers.slice(0, 8)}
        recentBroadcasts={broadcasts.slice(0, 5)}
        isReseller={isReseller}
        onOpenUserDetail={onOpenUserDetail}
        onOpenResellerWorkspace={onOpenResellerWorkspace}
        onReceiptsFilterNavigate={(status) => {
          onSelectTab("receipts")
          if (status) onReceiptsListFiltersChange({ status })
        }}
        onEconomicsRefresh={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "monitoring") {
    return (
      <DashboardMonitoring
        overview={data.overview as OverviewPayload | undefined}
        panels={panels}
        panelsPagination={pickPagination(data, "panels")}
        monitorHosts={asRecordArray(data.monitorHosts)}
        onRefreshPanelHealth={onRefreshPanelHealth}
        onRefreshLivePanelMetrics={onRefreshLivePanelMetrics}
        compactHealthOnly={false}
        isReseller={isReseller}
      />
    )
  }

  if (activeTab === "site_settings") {
    const portalPagesRaw = data.portalPages ?? data.wpPages
    const portalPages = Array.isArray(portalPagesRaw)
      ? (portalPagesRaw as { id?: unknown; title?: unknown }[])
          .map((p) => ({ id: Number(p.id), title: String(p.title ?? "") }))
          .filter((p) => Number.isFinite(p.id) && p.id > 0)
      : []
    const permMap =
      data.resellerPermissionsMap && typeof data.resellerPermissionsMap === "object"
        ? (data.resellerPermissionsMap as Record<string, Record<string, boolean>>)
        : {}
    const siteFeatures =
      settings?.features && typeof settings.features === "object"
        ? (settings.features as import("@/config/admin-nav").DashboardFeatures)
        : null
    return (
      <DashboardSiteSettingsAdmin
        settings={settings}
        portalPages={portalPages}
        plans={plans}
        panels={panels}
        resellers={resellers}
        resellerPermissionsMap={permMap}
        dashboardBaseUrl={dashboardBaseUrl}
        features={siteFeatures}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "bots") {
    return (
      <DashboardBotsAdmin
        settings={settings}
        botsList={[]}
        botsPagination={null}
        variant="site"
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("botsList", p)}
        onPerPageChange={(n) => setPer("botsList", n)}
      />
    )
  }

  if (activeTab === "reseller_bots") {
    return (
      <DashboardBotsAdmin
        settings={settings}
        botsList={asRecordArray(data.botsList)}
        botsPagination={pickPagination(data, "botsList")}
        variant={isReseller ? "reseller_self" : "reseller_admin"}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("botsList", p)}
        onPerPageChange={(n) => setPer("botsList", n)}
      />
    )
  }

  if (activeTab === "reseller_settings" && isReseller) {
    return (
      <DashboardResellerSettings
        settings={settings}
        botsList={asRecordArray(data.botsList)}
        panels={panels}
        actorSvpUserId={dashActorSvpUserId(data)}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "reseller_xui_panels") {
    return (
      <DashboardResellerPanelsAdmin
        panels={panels}
        resellerPanelPricesMap={
          (data.resellerPanelPricesMap as Record<string, Array<Record<string, unknown>> | undefined>) ?? {}
        }
        />
    )
  }

  if (activeTab === "xui_panels") {
    const globalEc = parseUnitEconomicsGlobal(data.unitEconomics)
    return (
      <DashboardPanelsAdmin
        panels={panels}
        pagination={pickPagination(data, "panels")}
        panelEconomicsMap={
          (data.panelEconomicsMap as Record<string, import("@/components/dashboard-panel-economics-sheet").PanelEconomicsEntry>) ??
          undefined
        }
        globalEconomicsConfig={globalEc}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("panels", p)}
        onPerPageChange={(n) => setPer("panels", n)}
      />
    )
  }

  if (activeTab === "vpn_server" && (xrayCoreEnabled || tunnelEnabled)) {
    return (
      <DashboardVpnServerAdmin
        nodes={xrayNodes}
        inbounds={xrayInbounds}
        hosts={xrayHosts}
        endpoints={tunnelEndpoints}
        xrayCoreEnabled={xrayCoreEnabled}
        tunnelEnabled={tunnelEnabled}
        onMutateSuccess={onAdminMutateSuccess}
        onOpenConfigs={() => onSelectTab("configs")}
      />
    )
  }

  if (activeTab === "configs") {
    return (
      <DashboardConfigsAdmin
        panels={panels}
        plans={plans}
        configsActive={activeTab === "configs"}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "plan_cats") {
    return (
      <DashboardPlanCatsAdmin
        planCategories={planCategories}
        panels={panels}
        pagination={pickPagination(data, "planCategories")}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("planCategories", p)}
        onPerPageChange={(n) => setPer("planCategories", n)}
      />
    )
  }

  if (activeTab === "plans") {
    return (
      <DashboardPlansAdmin
        plans={plans}
        panels={panels}
        planCategories={planCategories}
        l2tpServers={l2tp}
        resellerChoices={resellers}
        wholesaleLinesCatalog={wholesaleLinesCatalog}
        wholesaleLines={wholesaleLinesReseller}
        initialPlansView={readPlansViewFromUrl()}
        resellerPlanFloors={asRecordArray((data as Record<string, unknown>).resellerPlanFloors)}
        resellerMode={isReseller}
        actorSvpUserId={isReseller ? dashActorSvpUserId(data) : 0}
        panelAccessDiagnostics={
          isReseller
            ? ((data.resellerPanelAccessDiagnostics ?? null) as ResellerPanelAccessDiagnostics | null)
            : null
        }
        pagination={pickPagination(data, "plans")}
        settings={settings}
        showCatalogDefaultsSave={!isReseller}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("plans", p)}
        onPerPageChange={(n) => setPer("plans", n)}
      />
    )
  }

  if (activeTab === "cards") {
    const pm = (data as Record<string, unknown>).paymentMethods as
      | import("@/components/dashboard-cards-admin").PaymentMethodsPayload
      | null
      | undefined
    return (
      <DashboardCardsAdmin
        cards={cards}
        pagination={pickPagination(data, "cards")}
        settings={settings}
        paymentMethods={pm ?? null}
        isReseller={isReseller}
        canEditDisplayMode={!isReseller}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("cards", p)}
        onPerPageChange={(n) => setPer("cards", n)}
      />
    )
  }

  if (activeTab === "reseller_charge" && isReseller) {
    const uBal = data.user as { balance?: unknown } | undefined
    const actorBal = typeof uBal?.balance === "number" ? uBal.balance : undefined
    const charges = Array.isArray((data as Record<string, unknown>).resellerCustomerCharges)
      ? ((data as Record<string, unknown>).resellerCustomerCharges as Record<string, unknown>[])
      : []
    const chargesPagination = parsePaginationMeta(
      (data as Record<string, unknown>).resellerCustomerChargesPagination
    )
    return (
      <DashboardResellerChargeAdmin
        actorBalance={actorBal}
        customerCharges={charges}
        customerChargesPagination={chargesPagination}
        chargeTypeFilter={customerChargesType}
        chargeDateFrom={customerChargesDateFrom}
        chargeDateTo={customerChargesDateTo}
        onChargeTypeFilterChange={onCustomerChargesTypeChange}
        onChargeDateFromChange={onCustomerChargesDateFromChange}
        onChargeDateToChange={onCustomerChargesDateToChange}
        onCustomerChargesPageChange={(p) =>
          setListQuery((q) => ({ ...q, customerChargesPage: String(Math.max(1, p)) }))
        }
        onCustomerChargesPerPageChange={(n) =>
          setListQuery((q) => ({
            ...q,
            customerChargesPerPage: String(Math.max(1, n)),
            customerChargesPage: "1",
          }))
        }
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "receipts") {
    const canReviewReceipts = !isReseller || actorPermissions["receipts.review"] === true
    return (
      <DashboardReceiptsAdmin
        receipts={receipts}
        receiptAggregates={data.receiptAggregates}
        settings={settings}
        pagination={pickPagination(data, "receipts")}
        isReseller={isReseller}
        canReviewReceipts={canReviewReceipts}
        listFilters={receiptsListFilters}
        onListFiltersChange={onReceiptsListFiltersChange}
        dashboardBaseUrl={dashboardBaseUrl}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("receipts", p)}
        onPerPageChange={(n) => setPer("receipts", n)}
      />
    )
  }

  if (activeTab === "broadcast") {
    return (
      <DashboardBroadcastAdmin
        broadcasts={broadcasts}
        broadcastQueueAggregates={data.broadcastQueueAggregates}
        pagination={pickPagination(data, "broadcasts")}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("broadcasts", p)}
        onPerPageChange={(n) => setPer("broadcasts", n)}
        enabledPlatforms={enabledPlatforms}
        isReseller={isReseller}
      />
    )
  }

  if (activeTab === "texts") {
    const textsMeta = pickPagination(data, "texts")
    if (!texts.length && (!textsMeta || textsMeta.total === 0)) {
      return <>{notFound}</>
    }
    const textDefaults =
      data.textDefaults && typeof data.textDefaults === "object"
        ? (data.textDefaults as Record<string, { fa: string; en: string } | string>)
        : {}
    return (
      <DashboardTextsAdmin
        texts={texts}
        textDefaults={textDefaults}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "bot_ui") {
    return (
      <DashboardBotUiStudio
        uiLayout={data.uiLayout as Record<string, unknown> | undefined}
        uiRegistry={data.uiRegistry as Record<string, unknown> | undefined}
        textDefaults={
          data.textDefaults && typeof data.textDefaults === "object"
            ? (data.textDefaults as Record<string, unknown>)
            : undefined
        }
        layoutReadOnly={isReseller}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "users" && userDetailId != null && userDetailId > 0) {
    const canReviewReceipts = !isReseller || actorPermissions["receipts.review"] === true
    return (
      <DashboardUserDetailAdmin
        userId={userDetailId}
        plans={plans}
        planCategories={planCategories}
        settings={settings}
        isReseller={isReseller}
        actorPermissions={actorPermissions}
        canReviewReceipts={canReviewReceipts}
        onBack={onCloseUserDetail}
        onMutateSuccess={onAdminMutateSuccess}
        onOpenUserDetail={onOpenUserDetail}
        enabledPlatforms={enabledPlatforms}
      />
    )
  }

  if (activeTab === "users_bulk") {
    return (
      <DashboardUsersBulkAdmin
        panels={panels}
        onMutateSuccess={onAdminMutateSuccess}
        canRunBulkWorker={!isReseller}
      />
    )
  }

  if (activeTab === "users") {
    return (
      <DashboardUsersAdmin
        users={users}
        pending={pending}
        usersPagination={pickPagination(data, "usersList")}
        pendingPagination={pickPagination(data, "pendingUsers")}
        isReseller={isReseller}
        actorPermissions={
          (data.actorPermissions as Record<string, boolean> | undefined) ?? undefined
        }
        onMutateSuccess={onAdminMutateSuccess}
        onUsersPageChange={(p) => setPage("usersList", p)}
        onUsersPerPageChange={(n) => setPer("usersList", n)}
        onPendingPageChange={(p) => setPage("pendingUsers", p)}
        onPendingPerPageChange={(n) => setPer("pendingUsers", n)}
        onOpenUserDetail={onOpenUserDetail}
        usersSearchQuery={usersSearchQuery}
        onUsersSearchQueryChange={onUsersSearchQueryChange}
        listFilters={usersListFilters}
        onListFiltersChange={onUsersListFiltersChange}
        enabledPlatforms={enabledPlatforms}
      />
    )
  }

  if (activeTab === "resellers") {
    return (
      <DashboardResellersAdmin
        rows={resellers}
        panels={panels}
        resellerPermissionsMap={(data.resellerPermissionsMap as Record<string, Record<string, boolean>>) || {}}
        resellersSearchQuery={resellersSearchQuery}
        resellersStatusFilter={resellersStatusFilter}
        onResellersFiltersChange={onResellersFiltersChange}
        resellerPanelPricesMap={(data.resellerPanelPricesMap as Record<string, Array<{ panel_id?: number; price_per_gb?: number | string; panel_access?: boolean | number }>>) || {}}
        wholesaleCatalogByPanel={(data.wholesaleCatalogByPanel as Record<string, { price_per_gb?: number; wholesale_line_label?: string }>) || {}}
        wholesaleLinesCatalog={wholesaleLinesCatalog}
        resellerWholesaleLineIdsMap={
          (data.resellerWholesaleLineIdsMap as Record<string, number[]> | undefined) ?? {}
        }
        resellerBotMap={(data.resellerBotMap as Record<string, { enabled?: boolean; brand?: string }>) || {}}
        pagination={pickPagination(data, "resellers")}
        canManageResellerControls={!isReseller}
        canCreateSubReseller={isReseller && actorPermissions["users.manage"] === true}
        canViewResellerControls={!isReseller}
        canManagePanelPrices={!isReseller || Boolean(actorPermissions?.["users.manage"])}
        actorIsReseller={isReseller}
        actorUserId={dashActorSvpUserId(data)}
        onPageChange={(p) => setPage("resellers", p)}
        onPerPageChange={(n) => setPer("resellers", n)}
        onOpenUserDetail={onOpenUserDetail}
        onOpenWorkspace={onOpenResellerWorkspace}
        onMutateSuccess={onAdminMutateSuccess}
        onImpersonateReseller={onImpersonateReseller}
      />
    )
  }

  if (activeTab === "reseller_workspace") {
    const ctxId = Number((data as Record<string, unknown>).resellerContextId ?? 0)
    if (ctxId > 0) {
      const canReviewReceipts = !isReseller || actorPermissions["receipts.review"] === true
      return (
        <DashboardUserDetailAdmin
          userId={ctxId}
          plans={plans}
          planCategories={planCategories}
          settings={settings}
          isReseller={isReseller}
          actorPermissions={actorPermissions}
          canReviewReceipts={canReviewReceipts}
          onBack={() => onSelectTab("resellers")}
          onMutateSuccess={onAdminMutateSuccess}
          onOpenUserDetail={onOpenUserDetail}
          enabledPlatforms={enabledPlatforms}
        />
      )
    }
  }

  if (activeTab === "audit") {
    return <DashboardAuditAdmin
        />
  }

  if (activeTab === "backup") {
    return <DashboardBackupAdmin settings={settings}
        onMutateSuccess={onAdminMutateSuccess} />
  }

  if (activeTab === "unit_economics") {
    return (
      <DashboardUnitEconomicsAdmin
        unitEconomics={data.unitEconomics}
        panelEconomicsMap={
          (data.panelEconomicsMap as Record<string, import("@/components/dashboard-panel-economics-sheet").PanelEconomicsEntry>) ??
          undefined
        }
        panels={panels}
        dashboardBaseUrl={dashboardBaseUrl}
        onSelectTab={onSelectTab}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "referral") {
    return (
      <DashboardReferralAdmin
        mode="settings"
        settings={settings}
        referralStats={null}
        referralEvents={[]}
        eventsPagination={null}
        readOnlySettings={isReseller}
        onMutateSuccess={onAdminMutateSuccess}
      />
    )
  }

  if (activeTab === "referral_reports") {
    return (
      <DashboardReferralAdmin
        mode="reports"
        settings={settings}
        referralStats={data.referralStats}
        referralEvents={asRecordArray(data.referralEvents)}
        eventsPagination={pickPagination(data, "referralEvents")}
        onEventsPageChange={(p) => setPage("referralEvents", p)}
        onEventsPerPageChange={(n) => setPer("referralEvents", n)}
        onOpenUserDetail={onOpenUserDetail}
      />
    )
  }

  if (activeTab === "marketing_lifecycle") {
    const mktStats = data.marketingLifecycleStats as MarketingLifecycleStats | null | undefined
    const mktRules = (Array.isArray(data.marketingRules) ? data.marketingRules : []) as MarketingRuleRow[]
    const mktRuleStats = (Array.isArray(data.marketingRuleStats) ? data.marketingRuleStats : []) as MarketingRuleStatRow[]
    const mktOffers = (Array.isArray(data.marketingOffers) ? data.marketingOffers : []) as MarketingOfferRow[]
    const mktFunnel = (Array.isArray(data.marketingLifecycleFunnel)
      ? data.marketingLifecycleFunnel
      : []) as MarketingFunnelDay[]
    return (
      <DashboardMarketingLifecycleAdmin
        stats={mktStats ?? null}
        funnel={mktFunnel}
        rules={mktRules}
        ruleStats={mktRuleStats}
        offers={mktOffers}
        pagination={pickPagination(data, "marketingOffers")}
        dashboardBaseUrl={dashboardBaseUrl}
        windowDays={marketingLifecycleWindowDays}
        offerStatusFilter={marketingOffersStatus}
        onWindowDaysChange={(d) => onMarketingLifecycleWindowDaysChange?.(d)}
        onOfferStatusChange={(s) => onMarketingOffersStatusChange?.(s)}
        onPageChange={(p) => setPage("marketingOffers", p)}
        onPerPageChange={(n) => setPer("marketingOffers", n)}
        onMutateSuccess={onAdminMutateSuccess}
        onOpenUserDetail={onOpenUserDetail}
        onViewSegmentUsers={onViewMarketingSegmentUsers}
        isReseller={isReseller}
        readOnlySettings={isReseller}
      />
    )
  }

  if (activeTab === "reseller_reports") {
    const repStats = data.resellerReportsStats as ResellerReportsStats | null | undefined
    const repRows = (Array.isArray(data.resellerReportsRows)
      ? data.resellerReportsRows
      : []) as ResellerReportRow[]
    const repDaily = (Array.isArray(data.resellerReportsDaily)
      ? data.resellerReportsDaily
      : []) as ResellerReportDaily[]
    return (
      <DashboardResellerReportsAdmin
        stats={repStats ?? null}
        rows={repRows}
        daily={repDaily}
        pagination={pickPagination(data, "resellerReports")}
        dashboardBaseUrl={dashboardBaseUrl}
        searchQuery={resellerReportsSearchQuery}
        windowDays={resellerReportsWindowDays}
        sortKey={resellerReportsSort}
        readOnlyAdminActions={isReseller}
        onSearchChange={(q) => onResellerReportsFiltersChange?.({ q })}
        onWindowDaysChange={(days) => onResellerReportsFiltersChange?.({ days })}
        onSortChange={(sort) => onResellerReportsFiltersChange?.({ sort })}
        onPageChange={(p) => setPage("resellerReports", p)}
        onPerPageChange={(n) => setPer("resellerReports", n)}
        onOpenUserDetail={onOpenUserDetail}
        onImpersonateReseller={!isReseller ? onImpersonateReseller : undefined}
      />
    )
  }

  if (activeTab === "discounts") {
    return (
      <DashboardDiscountsAdmin
        discountCodes={disc}
        discountUsageSummary={discountUsageSummary}
        plans={plans}
        usersList={users}
        pagination={pickPagination(data, "discountCodes")}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("discountCodes", p)}
        onPerPageChange={(n) => setPer("discountCodes", n)}
        readOnlySettings={isReseller}
        portalAdminUrl={typeof data.portalAdminUrl === "string" ? data.portalAdminUrl : ""}
      />
    )
  }

  if (activeTab === "l2tp_servers" && l2tpEnabled) {
    return (
      <DashboardL2tpAdmin
        servers={l2tp}
        pagination={pickPagination(data, "l2tpServers")}
        onMutateSuccess={onAdminMutateSuccess}
        onPageChange={(p) => setPage("l2tpServers", p)}
        onPerPageChange={(n) => setPer("l2tpServers", n)}
      />
    )
  }

  return <>{notFound}</>
}
