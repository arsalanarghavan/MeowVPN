"use client"

import { useCallback, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { useRouter } from "next/navigation"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import {
  DEFAULT_USERS_LIST_FILTERS,
  UsersAdminCore,
  type UsersListFilters,
} from "@/components/admin/users/users-admin-core"
import { UserDetailAdmin } from "@/components/admin/users/user-detail-admin"

type DashRecord = Record<string, unknown>

function rows(v: unknown): DashRecord[] {
  return Array.isArray(v) ? (v.filter((x) => x && typeof x === "object") as DashRecord[]) : []
}

export function UsersAdminClient() {
  const t = useTranslations("usersAdmin")
  const locale = useLocale()
  const router = useRouter()
  const [usersSearchQuery, setUsersSearchQuery] = useState("")
  const [listFilters, setListFilters] = useState<UsersListFilters>(DEFAULT_USERS_LIST_FILTERS)

  const query = {
    users_q: usersSearchQuery,
    users_status: listFilters.status,
    users_role: listFilters.role,
    users_platform: listFilters.platform,
    users_segment: listFilters.segment,
    users_sort: listFilters.sort,
    users_date_from: listFilters.dateFrom,
    users_date_to: listFilters.dateTo,
    users_min_svc: listFilters.minSvc,
    users_max_svc: listFilters.maxSvc,
  }

  const { data, loading, error, reload, setPage, setPer, pickPagination, isReseller, actorPermissions, enabledPlatforms } =
    useAdminTabState("users", query)

  const onListFiltersChange = useCallback((patch: Partial<UsersListFilters>) => {
    setListFilters((cur) => ({ ...cur, ...patch }))
  }, [])

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
      onUsersSearchQueryChange={setUsersSearchQuery}
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
