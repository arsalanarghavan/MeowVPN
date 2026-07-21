"use client"

import { useEffect } from "react"
import { useLocale } from "next-intl"
import { useRouter } from "next/navigation"
import { useDashboardShell } from "@/components/dashboard-shell-provider"

/** Bare `/dashboard/reseller_workspace` without id → resellers (admin) or dashboard (reseller). */
export default function ResellerWorkspaceIndexPage() {
  const locale = useLocale()
  const router = useRouter()
  const { isReseller, isAdmin, loading } = useDashboardShell()

  useEffect(() => {
    if (loading) return
    const fallback = isAdmin && !isReseller ? "resellers" : "dashboard"
    if (fallback === "dashboard") {
      router.replace(`/${locale}/dashboard`)
    } else {
      router.replace(`/${locale}/dashboard/resellers`)
    }
  }, [loading, isReseller, isAdmin, locale, router])

  return <p className="p-4 text-sm text-muted-foreground">Redirecting…</p>
}
