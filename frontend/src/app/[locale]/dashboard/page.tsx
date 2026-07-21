import { setRequestLocale } from "next-intl/server"
import { OverviewAdminClient } from "@/components/admin/overview-admin-client"
import { SafeResellerTabGuard } from "@/components/safe-reseller-tab-guard"

export default async function DashboardHomePage({
  params,
}: {
  params: Promise<{ locale: string }> | { locale: string }
}) {
  const { locale } = await Promise.resolve(params)
  setRequestLocale(locale)

  return (
    <SafeResellerTabGuard tab="dashboard">
      <OverviewAdminClient />
    </SafeResellerTabGuard>
  )
}
