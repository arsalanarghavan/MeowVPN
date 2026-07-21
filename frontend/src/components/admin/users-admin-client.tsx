"use client"

import { useCallback, useEffect, useMemo } from "react"
import { useLocale, useTranslations } from "next-intl"
import { useRouter, useSearchParams } from "next/navigation"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import {
  DEFAULT_USERS_LIST_FILTERS,
  UsersAdminCore,
  type UsersListFilters,
} from "@/components/admin/users/users-admin-core"

type DashRecord = Record<string, unknown>

function rows(v: unknown): DashRecord[] {
  return Array.isArray(v) ? (v.filter((x) => x && typeof x === "object") as DashRecord[]) : []
}

function readFiltersFromSearch(sp: URLSearchParams): UsersListFilters {
  return {
    status: sp.get("users_status") || DEFAULT_USERS_LIST_FILTERS.status,
    role: sp.get("users_role") || DEFAULT_USERS_LIST_FILTERS.role,
    platform: sp.get("users_platform") || DEFAULT_USERS_LIST_FILTERS.platform,
    segment: sp.get("users_segment") || DEFAULT_USERS_LIST_FILTERS.segment,
    sort: sp.get("users_sort") || DEFAULT_USERS_LIST_FILTERS.sort,
    dateFrom: sp.get("users_date_from") || "",
    dateTo: sp.get("users_date_to") || "",
    minSvc: sp.get("users_min_svc") || "",
    maxSvc: sp.get("users_max_svc") || "",
  }
}

function filtersToQuery(filters: UsersListFilters, q: string): Record<string, string> {
  const out: Record<string, string> = {
    users_q: q,
    users_status: filters.status,
    users_role: filters.role,
    users_platform: filters.platform,
    users_segment: filters.segment,
    users_sort: filters.sort,
    users_date_from: filters.dateFrom,
    users_date_to: filters.dateTo,
    users_min_svc: filters.minSvc,
    users_max_svc: filters.maxSvc,
  }
  return out
}

function writeUsersUrl(locale: string, filters: UsersListFilters, q: string) {
  if (typeof window === "undefined") return
  const url = new URL(window.location.href)
  const base = `/${locale}/dashboard/users`
  url.pathname = base
  const keep = new Set([
    "users_q",
    "users_status",
    "users_role",
    "users_platform",
    "users_segment",
    "users_sort",
    "users_date_from",
    "users_date_to",
    "users_min_svc",
    "users_max_svc",
  ])
  for (const key of [...url.searchParams.keys()]) {
    if (keep.has(key)) url.searchParams.delete(key)
  }
  const query = filtersToQuery(filters, q)
  for (const [k, v] of Object.entries(query)) {
    const def =
      k === "users_q"
        ? ""
        : k === "users_sort"
          ? DEFAULT_USERS_LIST_FILTERS.sort
          : k.startsWith("users_date") || k.includes("svc")
            ? ""
            : "all"
    if (!v || v === def) continue
    url.searchParams.set(k, v)
  }
  window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`)
}

export function UsersAdminClient() {
  const t = useTranslations("usersAdmin")
  const locale = useLocale()
  const router = useRouter()
  const searchParams = useSearchParams()

  const initialFilters = useMemo(() => readFiltersFromSearch(searchParams), [searchParams])
  const initialQ = searchParams.get("users_q") || ""

  const {
    data,
    loading,
    error,
    reload,
    listQuery,
    patchQuery,
    setPage,
    setPer,
    pickPagination,
    isReseller,
    actorPermissions,
    enabledPlatforms,
  } = useAdminTabState("users", filtersToQuery(initialFilters, initialQ))

  const listFilters: UsersListFilters = {
    status: listQuery.users_status || DEFAULT_USERS_LIST_FILTERS.status,
    role: listQuery.users_role || DEFAULT_USERS_LIST_FILTERS.role,
    platform: listQuery.users_platform || DEFAULT_USERS_LIST_FILTERS.platform,
    segment: listQuery.users_segment || DEFAULT_USERS_LIST_FILTERS.segment,
    sort: listQuery.users_sort || DEFAULT_USERS_LIST_FILTERS.sort,
    dateFrom: listQuery.users_date_from || "",
    dateTo: listQuery.users_date_to || "",
    minSvc: listQuery.users_min_svc || "",
    maxSvc: listQuery.users_max_svc || "",
  }
  const usersSearchQuery = listQuery.users_q || ""

  useEffect(() => {
    writeUsersUrl(locale, listFilters, usersSearchQuery)
    // eslint-disable-next-line react-hooks/exhaustive-deps -- sync URL from listQuery only
  }, [locale, listQuery])

  // Honor marketing deep-link when URL segment changes after mount.
  useEffect(() => {
    const seg = searchParams.get("users_segment")
    if (seg && seg !== (listQuery.users_segment || "all")) {
      patchQuery({ users_segment: seg, users_page: "1" })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams])

  const onListFiltersChange = useCallback(
    (patch: Partial<UsersListFilters>) => {
      const next: UsersListFilters = { ...listFilters, ...patch }
      const q = filtersToQuery(next, usersSearchQuery)
      q.users_page = "1"
      patchQuery(q)
    },
    [listFilters, patchQuery, usersSearchQuery]
  )

  const onUsersSearchQueryChange = useCallback(
    (q: string) => {
      patchQuery({ users_q: q, users_page: "1" })
    },
    [patchQuery]
  )

  const openDetail = useCallback(
    (id: number) => {
      router.push(`/${locale}/dashboard/users/u/${id}`)
    },
    [locale, router]
  )

  if (loading && rows(data.usersList ?? data.users).length === 0) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) {
    return <p className="text-sm text-destructive">{t("loadError")}</p>
  }

  return (
    <UsersAdminCore
      users={rows(data.usersList ?? data.users)}
      pending={rows(data.pendingUsers)}
      usersPagination={pickPagination("usersList")}
      pendingPagination={pickPagination("pendingUsers")}
      usersSearchQuery={usersSearchQuery}
      onUsersSearchQueryChange={onUsersSearchQueryChange}
      listFilters={listFilters}
      onListFiltersChange={onListFiltersChange}
      onMutateSuccess={reload}
      onUsersPageChange={(p) => setPage("usersList", p)}
      onUsersPerPageChange={(n) => setPer("usersList", n)}
      onPendingPageChange={(p) => setPage("pendingUsers", p)}
      onPendingPerPageChange={(n) => setPer("pendingUsers", n)}
      onOpenUserDetail={openDetail}
      isReseller={isReseller}
      actorPermissions={actorPermissions}
      enabledPlatforms={enabledPlatforms as import("@/config/bot-platforms").BotPlatformId[]}
    />
  )
}
