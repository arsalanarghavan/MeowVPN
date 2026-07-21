import { setRequestLocale } from "next-intl/server"
import { getTranslations } from "next-intl/server"
import { redirect } from "next/navigation"
import { SafeResellerTabGuard } from "@/components/safe-reseller-tab-guard"
import { AuditAdminClient } from "@/components/admin/audit-admin-client"
import { BackupAdminClient } from "@/components/admin/backup-admin-client"
import { BotsAdminClient } from "@/components/admin/bots-admin-client"
import { BotUiAdminClient } from "@/components/admin/bot-ui-admin-client"
import { BroadcastAdminClient } from "@/components/admin/broadcast-admin-client"
import { CardsAdminClient } from "@/components/admin/cards-admin-client"
import { ConfigsAdminClient } from "@/components/admin/configs-admin-client"
import { DiscountsAdminClient } from "@/components/admin/discounts-admin-client"
import { L2tpServersAdminClient } from "@/components/admin/l2tp-servers-admin-client"
import { MarketingLifecycleAdminClient } from "@/components/admin/marketing-lifecycle-admin-client"
import { MonitoringAdminClient } from "@/components/admin/monitoring-admin-client"
import { OverviewAdminClient } from "@/components/admin/overview-admin-client"
import { PanelFinancialReportsClient } from "@/components/admin/panel-financial-reports-client"
import { PanelsAdminClient } from "@/components/admin/panels-admin-client"
import { PaymentsAdminClient } from "@/components/admin/payments-admin-client"
import { PlanCatsAdminClient } from "@/components/admin/plan-cats-admin-client"
import { PlansAdminClient } from "@/components/admin/plans-admin-client"
import { ReferralAdminClient } from "@/components/admin/referral-admin-client"
import { ResellerBotsAdminClient } from "@/components/admin/reseller-bots-admin-client"
import { ResellerChargeAdminClient } from "@/components/admin/reseller-charge-admin-client"
import { ResellerPanelsAdminClient } from "@/components/admin/reseller-panels-admin-client"
import { ResellerReportsAdminClient } from "@/components/admin/reseller-reports-admin-client"
import { ResellerSettingsAdminClient } from "@/components/admin/reseller-settings-admin-client"
import { ResellersAdminClient } from "@/components/admin/resellers-admin-client"
import { SiteSettingsAdminClient } from "@/components/admin/site-settings-admin-client"
import { TextsAdminClient } from "@/components/admin/texts-admin-client"
import { UnitEconomicsAdminClient } from "@/components/admin/unit-economics-admin-client"
import { UsersAdminClient } from "@/components/admin/users-admin-client"
import { UsersBulkAdminClient } from "@/components/admin/users-bulk-admin-client"
import { VpnServerAdminClient } from "@/components/admin/vpn-server-admin-client"
import { resolveLegacySiteTab } from "@/lib/site-settings-subtab"
import { resolveLegacyPlansTab } from "@/lib/plans-subview"

function vpnServerDefaultTab(tab: string): "overview" | "inbounds" | "hosts" | "tunnels" | undefined {
  switch (tab) {
    case "xray_core":
      return "overview"
    case "xray_inbounds":
      return "inbounds"
    case "xray_hosts":
      return "hosts"
    case "tunnel_nodes":
      return "tunnels"
    default:
      return undefined
  }
}

export default async function DashboardTabPage({
  params,
}: {
  params: Promise<{ locale: string; tab: string }> | { locale: string; tab: string }
}) {
  const { locale, tab } = await Promise.resolve(params)
  setRequestLocale(locale)

  const legSite = resolveLegacySiteTab(tab)
  if (legSite.subtab) {
    redirect(`/${locale}/dashboard/site_settings?site_subtab=${encodeURIComponent(legSite.subtab)}`)
  }
  const legPlans = resolveLegacyPlansTab(legSite.tab)
  if (legPlans.view) {
    redirect(`/${locale}/dashboard/plans?plans_view=${encodeURIComponent(legPlans.view)}`)
  }
  const resolvedTab = legPlans.tab

  const wrap = (node: React.ReactNode) => (
    <SafeResellerTabGuard tab={resolvedTab}>{node}</SafeResellerTabGuard>
  )

  switch (resolvedTab) {
    case "dashboard":
      return wrap(<OverviewAdminClient />)
    case "monitoring":
      return wrap(<MonitoringAdminClient />)
    case "users":
      return wrap(<UsersAdminClient />)
    case "users_bulk":
      return wrap(<UsersBulkAdminClient />)
    case "broadcast":
      return wrap(<BroadcastAdminClient />)
    case "resellers":
      return wrap(<ResellersAdminClient />)
    case "reseller_reports":
      return wrap(<ResellerReportsAdminClient />)
    case "reseller_bots":
      return wrap(<ResellerBotsAdminClient />)
    case "reseller_charge":
      return wrap(<ResellerChargeAdminClient />)
    case "reseller_settings":
      return wrap(<ResellerSettingsAdminClient />)
    case "referral":
      return wrap(<ReferralAdminClient />)
    case "referral_reports":
      return wrap(<ReferralAdminClient reports />)
    case "marketing_lifecycle":
      return wrap(<MarketingLifecycleAdminClient />)
    case "discounts":
      return wrap(<DiscountsAdminClient />)
    case "plans":
      return wrap(<PlansAdminClient />)
    case "plan_cats":
      return wrap(<PlanCatsAdminClient />)
    case "unit_economics":
      return wrap(<UnitEconomicsAdminClient />)
    case "panel_financial_reports":
      return wrap(<PanelFinancialReportsClient />)
    case "cards":
      return wrap(<CardsAdminClient />)
    case "payments":
    case "receipts":
      return wrap(<PaymentsAdminClient />)
    case "bots":
      return wrap(<BotsAdminClient />)
    case "texts":
      return wrap(<TextsAdminClient />)
    case "bot_ui":
      return wrap(<BotUiAdminClient />)
    case "xui_panels":
      return wrap(<PanelsAdminClient />)
    case "reseller_xui_panels":
      return wrap(<ResellerPanelsAdminClient />)
    case "vpn_server":
    case "xray_core":
    case "xray_inbounds":
    case "xray_hosts":
    case "tunnel_nodes":
      return wrap(<VpnServerAdminClient defaultTab={vpnServerDefaultTab(tab)} />)
    case "configs":
      return wrap(<ConfigsAdminClient />)
    case "l2tp_servers":
      return wrap(<L2tpServersAdminClient />)
    case "backup":
      return wrap(<BackupAdminClient />)
    case "audit":
      return wrap(<AuditAdminClient />)
    case "site_settings":
      return wrap(<SiteSettingsAdminClient />)
    default:
      break
  }

  const t = await getTranslations("sidebar.tabs")
  let title = resolvedTab
  try {
    title = t(resolvedTab)
  } catch {
    title = resolvedTab
  }

  return wrap(
    <div className="rounded-xl border bg-card p-6 shadow-sm">
      <h1 className="text-xl font-semibold">{title}</h1>
    </div>
  )
}
