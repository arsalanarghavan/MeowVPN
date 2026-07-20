#!/usr/bin/env python3
"""Rename ported admin exports to *View and append self-loading *Client wrappers."""

from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ADMIN = ROOT / "frontend" / "src" / "components" / "admin"

WRAPPERS: dict[str, str] = {
    "plan-cats-admin-client.tsx": '''
export function PlanCatsAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows } = useAdminTabState("plan_cats")
  const t = useTranslations("planCatsAdmin")
  if (loading && rows(data.planCategories).length === 0) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <PlanCatsAdminView
      planCategories={rows(data.planCategories ?? data.plan_categories)}
      panels={rows(data.panels)}
      pagination={pickPagination("planCategories")}
      onMutateSuccess={reload}
      onPageChange={(p) => setPage("planCategories", p)}
      onPerPageChange={(n) => setPer("planCategories", n)}
    />
  )
}
''',
    "broadcast-admin-client.tsx": '''
export function BroadcastAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, enabledPlatforms, isReseller } =
    useAdminTabState("broadcast")
  const t = useTranslations("broadcastAdmin")
  if (loading && rows(data.broadcasts).length === 0) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <BroadcastAdminView
      broadcasts={rows(data.broadcasts ?? data.broadcastJobs)}
      broadcastQueueAggregates={data.broadcastQueueAggregates}
      pagination={pickPagination("broadcasts")}
      onMutateSuccess={reload}
      onPageChange={(p) => setPage("broadcasts", p)}
      onPerPageChange={(n) => setPer("broadcasts", n)}
      enabledPlatforms={enabledPlatforms as import("@/config/bot-platforms").BotPlatformId[]}
      isReseller={isReseller}
    />
  )
}
''',
    "users-bulk-admin-client.tsx": '''
export function UsersBulkAdminClient() {
  const { data, loading, error, reload, rows, isReseller } = useAdminTabState("users_bulk")
  const t = useTranslations("usersBulkAdmin")
  if (loading && rows(data.panels).length === 0) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <UsersBulkAdminView
      panels={rows(data.panels)}
      onMutateSuccess={reload}
      canRunBulkWorker={!isReseller}
    />
  )
}
''',
    "resellers-admin-client.tsx": '''
export function ResellersAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, patchQuery, listQuery, isReseller } =
    useAdminTabState("resellers", { resellers_status: "all" })
  const t = useTranslations("resellersAdmin")
  if (loading && rows(data.resellers).length === 0) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <ResellersAdminView
      rows={rows(data.resellers)}
      panels={rows(data.panels)}
      resellerPermissionsMap={(data.resellerPermissionsMap as Record<string, Record<string, boolean>>) ?? {}}
      resellerPanelPricesMap={(data.resellerPanelPricesMap as Record<string, Array<Record<string, unknown>>>) ?? {}}
      wholesaleCatalogByPanel={(data.wholesaleCatalogByPanel as Record<string, { price_per_gb?: number; wholesale_line_label?: string }>) ?? {}}
      wholesaleLinesCatalog={rows(data.wholesaleLinesCatalog)}
      resellerWholesaleLineIdsMap={(data.resellerWholesaleLineIdsMap as Record<string, number[]>) ?? {}}
      resellerBotMap={(data.resellerBotMap as Record<string, { enabled?: boolean; brand?: string }>) ?? {}}
      resellersSearchQuery={listQuery.resellers_q ?? ""}
      resellersStatusFilter={listQuery.resellers_status ?? "all"}
      onResellersFiltersChange={(patch) => {
        const next: Record<string, string> = {}
        if (patch.q !== undefined) next.resellers_q = patch.q
        if (patch.status !== undefined) next.resellers_status = patch.status
        patchQuery(next)
      }}
      pagination={pickPagination("resellers")}
      canManageResellerControls={!isReseller}
      canCreateSubReseller={false}
      actorIsReseller={isReseller}
      actorUserId={Number(data.actorSvpUserId ?? 0)}
      onPageChange={(p) => setPage("resellers", p)}
      onPerPageChange={(n) => setPer("resellers", n)}
      onOpenUserDetail={() => {}}
      onMutateSuccess={reload}
    />
  )
}
''',
    "discounts-admin-client.tsx": '''
export function DiscountsAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, isReseller } = useAdminTabState("discounts")
  const t = useTranslations("discountsAdmin")
  if (loading && rows(data.discountCodes).length === 0) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <DiscountsAdminView
      discountCodes={rows(data.discountCodes ?? data.discounts)}
      discountUsageSummary={data.discountUsageSummary as import("@/components/admin/discounts-admin-client").UsageSummary | null}
      plans={rows(data.plans)}
      usersList={rows(data.usersList ?? data.users)}
      pagination={pickPagination("discountCodes")}
      onMutateSuccess={reload}
      onPageChange={(p) => setPage("discountCodes", p)}
      onPerPageChange={(n) => setPer("discountCodes", n)}
      readOnlySettings={isReseller}
    />
  )
}
''',
    "referral-admin-client.tsx": '''
export function ReferralAdminClient({ reports = false }: { reports?: boolean }) {
  const tab = reports ? "referral_reports" : "referral"
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, isReseller } = useAdminTabState(tab)
  const t = useTranslations("referralAdmin")
  if (loading) return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <ReferralAdminView
      mode={reports ? "reports" : "settings"}
      settings={data.settings as Record<string, unknown> | undefined}
      referralStats={data.referralStats}
      referralEvents={rows(data.referralEvents)}
      eventsPagination={pickPagination("referralEvents")}
      readOnlySettings={isReseller}
      onMutateSuccess={reload}
      onEventsPageChange={(p) => setPage("referralEvents", p)}
      onEventsPerPageChange={(n) => setPer("referralEvents", n)}
    />
  )
}
''',
    "marketing-lifecycle-admin-client.tsx": '''
export function MarketingLifecycleAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, patchQuery, listQuery, isReseller } =
    useAdminTabState("marketing_lifecycle")
  const t = useTranslations("marketingLifecycleAdmin")
  if (loading) return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <MarketingLifecycleAdminView
      stats={(data.marketingLifecycleStats as import("@/components/admin/marketing-lifecycle-admin-client").MarketingLifecycleStats | null) ?? null}
      funnel={Array.isArray(data.marketingFunnel) ? data.marketingFunnel as import("@/components/admin/marketing-lifecycle-admin-client").MarketingFunnelDay[] : []}
      rules={Array.isArray(data.marketingRules) ? data.marketingRules as import("@/components/admin/marketing-lifecycle-admin-client").MarketingRuleRow[] : []}
      ruleStats={Array.isArray(data.marketingRuleStats) ? data.marketingRuleStats as import("@/components/admin/marketing-lifecycle-admin-client").MarketingRuleStatRow[] : []}
      offers={rows(data.marketingOffers ?? data.marketingOffersList)}
      pagination={pickPagination("marketingOffers")}
      dashboardBaseUrl=""
      windowDays={Number(listQuery.marketing_window_days ?? data.marketingWindowDays ?? 30)}
      offerStatusFilter={listQuery.marketing_offer_status ?? ""}
      onWindowDaysChange={(n) => patchQuery({ marketing_window_days: String(n) })}
      onOfferStatusChange={(s) => patchQuery({ marketing_offer_status: s })}
      onPageChange={(p) => setPage("marketingOffers", p)}
      onPerPageChange={(n) => setPer("marketingOffers", n)}
      onMutateSuccess={reload}
      isReseller={isReseller}
      readOnlySettings={isReseller}
    />
  )
}
''',
    "unit-economics-admin-client.tsx": '''
export function UnitEconomicsAdminClient() {
  const { data, loading, error, reload, rows } = useAdminTabState("unit_economics")
  const t = useTranslations("unitEconomicsAdmin")
  if (loading) return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <UnitEconomicsAdminView
      unitEconomics={data.unitEconomics}
      panelEconomicsMap={(data.panelEconomicsMap as Record<string, import("@/components/admin/unit-economics-admin-client").PanelEconomicsEntry>) ?? {}}
      panels={rows(data.panels)}
      dashboardBaseUrl=""
      onMutateSuccess={reload}
    />
  )
}
''',
    "reseller-reports-admin-client.tsx": '''
export function ResellerReportsAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, patchQuery, listQuery, isReseller } =
    useAdminTabState("reseller_reports", { reseller_reports_window_days: "30" })
  const t = useTranslations("resellerReportsAdmin")
  if (loading) return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <ResellerReportsAdminView
      stats={(data.resellerReportsStats as import("@/components/admin/reseller-reports-admin-client").ResellerReportsStats | null) ?? null}
      rows={Array.isArray(data.resellerReports) ? data.resellerReports as import("@/components/admin/reseller-reports-admin-client").ResellerReportRow[] : []}
      daily={Array.isArray(data.resellerReportsDaily) ? data.resellerReportsDaily as import("@/components/admin/reseller-reports-admin-client").ResellerReportDaily[] : []}
      pagination={pickPagination("resellerReports")}
      dashboardBaseUrl=""
      searchQuery={listQuery.reseller_reports_q ?? ""}
      windowDays={Number(listQuery.reseller_reports_window_days ?? 30)}
      sortKey={listQuery.reseller_reports_sort ?? "revenue_desc"}
      onSearchChange={(q) => patchQuery({ reseller_reports_q: q })}
      onWindowDaysChange={(n) => patchQuery({ reseller_reports_window_days: String(n) })}
      onSortChange={(k) => patchQuery({ reseller_reports_sort: k })}
      onPageChange={(p) => setPage("resellerReports", p)}
      onPerPageChange={(n) => setPer("resellerReports", n)}
      onOpenUserDetail={() => {}}
      readOnlyAdminActions={isReseller}
    />
  )
}
''',
}

IMPORTS = '''
import { useTranslations } from "next-intl"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
'''

VIEW_RENAMES = {
    "plan-cats-admin-client.tsx": "PlanCatsAdminClient",
    "broadcast-admin-client.tsx": "BroadcastAdminClient",
    "users-bulk-admin-client.tsx": "UsersBulkAdminClient",
    "resellers-admin-client.tsx": "ResellersAdminClient",
    "discounts-admin-client.tsx": "DiscountsAdminClient",
    "referral-admin-client.tsx": "ReferralAdminClient",
    "marketing-lifecycle-admin-client.tsx": "MarketingLifecycleAdminClient",
    "unit-economics-admin-client.tsx": "UnitEconomicsAdminClient",
    "reseller-reports-admin-client.tsx": "ResellerReportsAdminClient",
}


def main() -> None:
    for filename, wrapper in WRAPPERS.items():
        path = ADMIN / filename
        text = path.read_text(encoding="utf-8")
        view_name = VIEW_RENAMES[filename]
        text = text.replace(f"export function {view_name}(", f"export function {view_name.replace('Client', 'View')}(")
        if "useAdminTabState" not in text:
            # insert imports after first use client block
            text = text.replace('"use client"\n\n', '"use client"\n' + IMPORTS + "\n", 1)
        if f"export function {view_name}(" not in text:
            text = text.rstrip() + "\n" + wrapper
            path.write_text(text, encoding="utf-8")
            print(f"wrapped {filename}")
        else:
            print(f"skip {filename} (wrapper exists?)")


if __name__ == "__main__":
    main()
