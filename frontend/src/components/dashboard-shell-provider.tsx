"use client"

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react"
import { useLocale, useTranslations } from "next-intl"
import { useRouter } from "next/navigation"
import { ImpersonationBanner } from "@/components/impersonation-banner"
import { type DashboardFeatures } from "@/config/admin-nav"
import { apiBase, apiHeaders, ensureCsrfCookie } from "@/lib/api"
import { startImpersonation } from "@/lib/impersonation"

export type DashboardMeState = {
  isLoggedIn?: boolean
  isAdmin?: boolean
  isReseller?: boolean
  activePersona?: string
  availablePersonas?: string[]
  impersonating?: boolean
  impersonationTargetId?: number
  impersonationTargetLabel?: string
  features?: DashboardFeatures
  actorPermissions?: Record<string, boolean>
  allowedResellerTabs?: string[]
  user?: { name?: string; email?: string; username?: string; display_name?: string }
  portalAdminUrl?: string
  [key: string]: unknown
}

type DashboardShellContextValue = {
  me: DashboardMeState | null
  loading: boolean
  refreshMe: () => Promise<void>
  isAdmin: boolean
  isReseller: boolean
  impersonating: boolean
  features: DashboardFeatures | null
  actorPermissions: Record<string, boolean>
  allowedResellerTabs: string[] | null
  portalAdminUrl: string
  onImpersonateReseller?: (id: number) => void
  openUserDetail: (id: number) => void
  openResellerWorkspace: (id: number) => void
  openUsersSegment: (segment: string) => void
}

const DashboardShellContext = createContext<DashboardShellContextValue | null>(null)

export function useDashboardShell(): DashboardShellContextValue {
  const ctx = useContext(DashboardShellContext)
  if (!ctx) {
    throw new Error("useDashboardShell must be used within DashboardShellProvider")
  }
  return ctx
}

/** Safe optional access when outside provider (e.g. isolated story). */
export function useDashboardShellOptional(): DashboardShellContextValue | null {
  return useContext(DashboardShellContext)
}

async function fetchMeState(): Promise<DashboardMeState | null> {
  try {
    await ensureCsrfCookie()
    const res = await fetch(`${apiBase()}/me/state`, {
      credentials: "include",
      headers: apiHeaders(),
    })
    if (!res.ok) return null
    const json = (await res.json()) as { ok?: boolean; data?: DashboardMeState }
    const data = json.data ?? (json as DashboardMeState)
    if (!data || typeof data !== "object") return null
    return data
  } catch {
    return null
  }
}

export function DashboardShellProvider({ children }: { children: ReactNode }) {
  const locale = useLocale()
  const t = useTranslations("layout")
  const router = useRouter()
  const [me, setMe] = useState<DashboardMeState | null>(null)
  const [loading, setLoading] = useState(true)
  const [impersonateErr, setImpersonateErr] = useState<string | null>(null)

  const refreshMe = useCallback(async () => {
    const next = await fetchMeState()
    setMe(next)
    setLoading(false)
  }, [])

  useEffect(() => {
    void refreshMe()
  }, [refreshMe])

  const impersonating = Boolean(me?.impersonating)
  const isAdmin = Boolean(me?.isAdmin) || me?.activePersona === "admin"
  const isReseller =
    Boolean(me?.isReseller) || me?.activePersona === "reseller" || impersonating

  const onImpersonateReseller = useCallback(
    async (id: number) => {
      if (!isAdmin || impersonating || id < 1) return
      setImpersonateErr(null)
      try {
        const r = await startImpersonation(id)
        if (r.ok) {
          window.location.reload()
          return
        }
        setImpersonateErr(t("impersonateFailed"))
      } catch {
        setImpersonateErr(t("impersonateFailed"))
      }
    },
    [isAdmin, impersonating, t]
  )

  const openUserDetail = useCallback(
    (id: number) => {
      if (id < 1) return
      router.push(`/${locale}/dashboard/users/u/${id}`)
    },
    [locale, router]
  )

  const openResellerWorkspace = useCallback(
    (id: number) => {
      if (id < 1) return
      router.push(`/${locale}/dashboard/reseller_workspace/${id}`)
    },
    [locale, router]
  )

  const openUsersSegment = useCallback(
    (segment: string) => {
      const q = encodeURIComponent(segment)
      router.push(`/${locale}/dashboard/users?users_segment=${q}`)
    },
    [locale, router]
  )

  const allowedFromMe = useMemo(() => {
    const map = me?.resellerAllowedTabs
    if (map && typeof map === "object" && !Array.isArray(map)) {
      return Object.entries(map as Record<string, unknown>)
        .filter(([, v]) => v === true)
        .map(([k]) => k)
    }
    if (Array.isArray(me?.allowedResellerTabs)) {
      return me!.allowedResellerTabs as string[]
    }
    return null
  }, [me])

  const value = useMemo<DashboardShellContextValue>(
    () => ({
      me,
      loading,
      refreshMe,
      isAdmin: isAdmin && !impersonating,
      isReseller,
      impersonating,
      features: (me?.features as DashboardFeatures) ?? null,
      actorPermissions:
        me?.actorPermissions && typeof me.actorPermissions === "object"
          ? (me.actorPermissions as Record<string, boolean>)
          : {},
      allowedResellerTabs: allowedFromMe,
      portalAdminUrl: String(me?.portalAdminUrl ?? me?.portal_admin_url ?? ""),
      onImpersonateReseller:
        isAdmin && !impersonating
          ? (id: number) => {
              void onImpersonateReseller(id)
            }
          : undefined,
      openUserDetail,
      openResellerWorkspace,
      openUsersSegment,
    }),
    [
      me,
      loading,
      refreshMe,
      isAdmin,
      isReseller,
      impersonating,
      allowedFromMe,
      onImpersonateReseller,
      openUserDetail,
      openResellerWorkspace,
      openUsersSegment,
    ]
  )

  const targetLabel = String(me?.impersonationTargetLabel ?? "").trim()

  return (
    <DashboardShellContext.Provider value={value}>
      {impersonating && targetLabel ? <ImpersonationBanner targetLabel={targetLabel} /> : null}
      {impersonateErr ? (
        <p className="border-b border-destructive/30 bg-destructive/10 px-4 py-1 text-xs text-destructive">
          {impersonateErr}
        </p>
      ) : null}
      {children}
    </DashboardShellContext.Provider>
  )
}
