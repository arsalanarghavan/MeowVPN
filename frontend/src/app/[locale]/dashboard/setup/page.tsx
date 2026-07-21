import { setRequestLocale } from "next-intl/server"

/** Install wizard under /{locale}/dashboard/setup — DashboardShell detects path and renders InstallWizard. */
export default async function DashboardSetupPage({
  params,
}: {
  params: Promise<{ locale: string }> | { locale: string }
}) {
  const { locale } = await Promise.resolve(params)
  setRequestLocale(locale)
  return null
}
