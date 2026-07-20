import { setRequestLocale } from "next-intl/server"
import { PortalHomeClient } from "@/components/portal/portal-home-client"

export default async function PortalPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }> | { locale: string }
  searchParams?: Promise<{ theme?: string }> | { theme?: string }
}) {
  const { locale } = await Promise.resolve(params)
  const sp = await Promise.resolve(searchParams ?? {})
  setRequestLocale(locale)
  return <PortalHomeClient theme={sp.theme} />
}
