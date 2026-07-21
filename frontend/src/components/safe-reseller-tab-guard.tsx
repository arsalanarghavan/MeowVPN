"use client"

import { useEffect, useMemo, type ReactNode } from "react"
import { useLocale } from "next-intl"
import { useRouter } from "next/navigation"
import { useDashboardShell } from "@/components/dashboard-shell-provider"
import { buildAllowedResellerTabs, safeResellerTab } from "@/lib/safe-reseller-tab"

/** Redirect resellers away from tabs they are not allowed to open. */
export function SafeResellerTabGuard({
  tab,
  children,
}: {
  tab: string
  children: ReactNode
}) {
  const locale = useLocale()
  const router = useRouter()
  const { isReseller, allowedResellerTabs, actorPermissions, loading } = useDashboardShell()

  const allowed = useMemo(
    () => buildAllowedResellerTabs(allowedResellerTabs, actorPermissions),
    [allowedResellerTabs, actorPermissions]
  )
  const blocked = !loading && isReseller && safeResellerTab(tab, true, allowed) !== tab

  useEffect(() => {
    if (!blocked) return
    router.replace(`/${locale}/dashboard`)
  }, [blocked, locale, router])

  if (blocked) return null

  return <>{children}</>
}
