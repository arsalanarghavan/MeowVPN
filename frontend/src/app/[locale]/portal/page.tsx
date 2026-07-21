import { setRequestLocale } from "next-intl/server"
import { fetchPortalBootstrap } from "@/components/portal/fetch-portal-bootstrap"
import { PortalHomeClient } from "@/components/portal/portal-home-client"

export default async function PortalPage({
  params,
  searchParams,
}: {
  params: Promise<{ locale: string }> | { locale: string }
  searchParams?:
    | Promise<Record<string, string | string[] | undefined>>
    | Record<string, string | string[] | undefined>
}) {
  const { locale } = await Promise.resolve(params)
  const sp = await Promise.resolve(searchParams ?? {})
  setRequestLocale(locale)

  const theme = Array.isArray(sp.theme) ? sp.theme[0] : sp.theme
  const initialData = await fetchPortalBootstrap(sp)

  return <PortalHomeClient theme={theme} initialData={initialData} />
}
