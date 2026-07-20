"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { getAdminState } from "@/lib/dash-admin-mutate"
import {
  listQuerySetPage,
  parsePaginationMeta,
  type ListQueryKey,
  type PaginationMeta,
} from "@/lib/dash-pagination"

type DashRecord = Record<string, unknown>

function rows(v: unknown): DashRecord[] {
  return Array.isArray(v) ? (v.filter((x) => x && typeof x === "object") as DashRecord[]) : []
}

export function useAdminTabState(activeTab: string, initialQuery: Record<string, string> = {}) {
  const [data, setData] = useState<DashRecord>({})
  const [listQuery, setListQuery] = useState<Record<string, string>>(initialQuery)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [reloadToken, setReloadToken] = useState(0)

  const reload = useCallback(() => setReloadToken((n) => n + 1), [])

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    void getAdminState(activeTab, listQuery)
      .then((state) => {
        if (!cancelled) setData(state)
      })
      .catch(() => {
        if (!cancelled) setError("load_failed")
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [activeTab, listQuery, reloadToken])

  const setPage = useCallback((key: ListQueryKey, page: number, perPage?: number) => {
    setListQuery((prev) => listQuerySetPage(prev, key, page, perPage))
  }, [])

  const setPer = useCallback((key: ListQueryKey, perPage: number) => {
    setListQuery((prev) => {
      const prefix = key === "usersList" ? "users" : key === "planCategories" ? "planCategories" : key
      const pageKey = `${prefix}_page`
      const page = Number(prev[pageKey] ?? 1)
      return listQuerySetPage(prev, key, page, perPage)
    })
  }, [])

  const pickPagination = useCallback(
    (key: ListQueryKey): PaginationMeta | null => {
      const pagination = data.pagination
      if (pagination && typeof pagination === "object") {
        const block = (pagination as DashRecord)[key]
        const parsed = parsePaginationMeta(block)
        if (parsed) return parsed
      }
      return parsePaginationMeta(data[`${key}Pagination`])
    },
    [data]
  )

  const patchQuery = useCallback((patch: Record<string, string>) => {
    setListQuery((prev) => ({ ...prev, ...patch }))
  }, [])

  const enabledPlatforms = useMemo(() => {
    const raw = data.enabledPlatforms
    return Array.isArray(raw) ? raw.map(String) : ["telegram", "bale"]
  }, [data.enabledPlatforms])

  const isReseller = data.isReseller === true || data.actorRole === "reseller"
  const actorPermissions =
    data.actorPermissions && typeof data.actorPermissions === "object"
      ? (data.actorPermissions as Record<string, boolean>)
      : undefined

  return {
    data,
    loading,
    error,
    reload,
    listQuery,
    patchQuery,
    setPage,
    setPer,
    pickPagination,
    rows,
    enabledPlatforms,
    isReseller,
    actorPermissions,
  }
}
