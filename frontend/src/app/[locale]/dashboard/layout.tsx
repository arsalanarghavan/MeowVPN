import { setRequestLocale } from "next-intl/server"
import { DashboardShell } from "@/components/dashboard-shell"

export default async function DashboardLayout({
  children,
  params,
}: {
  children: React.ReactNode
  params: Promise<{ locale: string }> | { locale: string }
}) {
  const { locale } = await Promise.resolve(params)
  setRequestLocale(locale)

  return <DashboardShell>{children}</DashboardShell>
}
