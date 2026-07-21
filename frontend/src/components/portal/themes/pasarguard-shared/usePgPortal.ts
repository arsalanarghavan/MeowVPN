"use client"

import { useMemo } from "react"

import { labelFromUri } from "@/components/portal/lib"
import { getAppsClients, getInitialData, type LinkItem, type PortalUser } from "@/components/portal/types"

export function usePgPortal() {
  const data = getInitialData()
  const user = data.user
  const meta = data.meta ?? {}
  const branding = meta.branding ?? {}
  const headers = meta.headers ?? {}
  const subUrl = meta.subscription_url ?? ""
  const links = data.links ?? []
  const linkItems = data.link_items ?? data.cards?.[0]?.link_items ?? []
  const apps = useMemo(() => getAppsClients(data), [data])
  const serviceId = data.service_id ?? data.cards?.[0]?.service_id

  const items = useMemo(() => {
    if (linkItems.length > 0) {
      return linkItems.map((item: LinkItem, i: number) => ({
        uri: item.uri,
        label: item.label || labelFromUri(item.uri),
        key: `item-${i}`,
      }))
    }
    return links.map((uri, i) => ({
      uri,
      label: labelFromUri(uri),
      key: `link-${i}`,
    }))
  }, [linkItems, links])

  return {
    data,
    user,
    meta,
    branding,
    headers,
    subUrl,
    links,
    items,
    apps,
    serviceId,
    chart: data.chart ?? data.cards?.[0]?.chart ?? null,
  }
}

export function pgUsagePercent(user: PortalUser): number {
  if ((user.quota_hidden_from_user ?? 0) === 1) return 0
  if (!user.data_limit || user.data_limit <= 0) {
    return 0
  }
  const pct = (user.used_traffic / user.data_limit) * 100
  return Math.min(Number.isFinite(pct) ? pct : 0, 100)
}

export function pgStatusClass(status: string): string {
  const s = status.toLowerCase()
  if (s === "active") return "active"
  if (s === "disabled") return "disabled"
  if (s === "expired") return "expired"
  if (s === "limited") return "limited"
  if (s === "on_hold") return "on_hold"
  return "disabled"
}
