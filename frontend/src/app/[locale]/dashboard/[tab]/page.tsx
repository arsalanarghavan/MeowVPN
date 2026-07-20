import { setRequestLocale } from "next-intl/server"
import { getTranslations } from "next-intl/server"
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

  switch (tab) {
    case "dashboard":
      return <OverviewAdminClient />
    case "monitoring":
      return <MonitoringAdminClient />
    case "users":
      return <UsersAdminClient />
    case "users_bulk":
      return <UsersBulkAdminClient />
    case "broadcast":
      return <BroadcastAdminClient />
    case "resellers":
      return <ResellersAdminClient />
    case "reseller_reports":
      return <ResellerReportsAdminClient />
    case "reseller_bots":
      return <ResellerBotsAdminClient />
    case "reseller_charge":
      return <ResellerChargeAdminClient />
    case "reseller_settings":
      return <ResellerSettingsAdminClient />
    case "referral":
      return <ReferralAdminClient />
    case "referral_reports":
      return <ReferralAdminClient reports />
    case "marketing_lifecycle":
      return <MarketingLifecycleAdminClient />
    case "discounts":
      return <DiscountsAdminClient />
    case "plans":
      return <PlansAdminClient />
    case "plan_cats":
      return <PlanCatsAdminClient />
    case "unit_economics":
      return <UnitEconomicsAdminClient />
    case "panel_financial_reports":
      return <PanelFinancialReportsClient />
    case "cards":
      return <CardsAdminClient />
    case "payments":
    case "receipts":
      return <PaymentsAdminClient />
    case "bots":
      return <BotsAdminClient />
    case "texts":
      return <TextsAdminClient />
    case "bot_ui":
      return <BotUiAdminClient />
    case "xui_panels":
      return <PanelsAdminClient />
    case "reseller_xui_panels":
      return <ResellerPanelsAdminClient />
    case "vpn_server":
    case "xray_core":
    case "xray_inbounds":
    case "xray_hosts":
    case "tunnel_nodes":
      return <VpnServerAdminClient defaultTab={vpnServerDefaultTab(tab)} />
    case "configs":
      return <ConfigsAdminClient />
    case "l2tp_servers":
      return <L2tpServersAdminClient />
    case "backup":
      return <BackupAdminClient />
    case "audit":
      return <AuditAdminClient />
    case "site_settings":
      return <SiteSettingsAdminClient />
    default:
      break
  }

  const t = await getTranslations("sidebar.tabs")
  let title = tab
  try {
    title = t(tab)
  } catch {
    title = tab
  }

  return (
    <div className="rounded-xl border bg-card p-6 shadow-sm">
      <h1 className="text-xl font-semibold">{title}</h1>
    </div>
  )
}
