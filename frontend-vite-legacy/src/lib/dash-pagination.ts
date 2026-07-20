/** Matches PHP `dash_list_pagination` param prefixes in `route_admin_state`. */
export const LIST_QUERY_PREFIX = {
  usersList: "users",
  resellers: "resellers",
  botsList: "bots",
  pendingUsers: "pendingUsers",
  receipts: "receipts",
  broadcasts: "broadcasts",
  discountCodes: "discounts",
  plans: "plans",
  cards: "cards",
  panels: "panels",
  planCategories: "planCategories",
  l2tpServers: "l2tp",
  texts: "texts",
  referralEvents: "referralEvents",
  resellerReports: "resellerReports",
  marketingOffers: "marketingOffers",
} as const

export type ListQueryKey = keyof typeof LIST_QUERY_PREFIX

export type PaginationMeta = {
  page: number
  perPage: number
  total: number
}

export function parsePaginationMeta(raw: unknown): PaginationMeta | null {
  if (!raw || typeof raw !== "object") return null
  const o = raw as Record<string, unknown>
  const page = Number(o.page)
  const perPage = Number(o.perPage)
  const total = Number(o.total)
  if (!Number.isFinite(page) || !Number.isFinite(perPage) || !Number.isFinite(total)) return null
  return { page: Math.max(1, page), perPage: Math.max(1, perPage), total: Math.max(0, total) }
}

export function listQuerySetPage(
  prev: Record<string, string>,
  key: ListQueryKey,
  page: number,
  perPage?: number
): Record<string, string> {
  const prefix = LIST_QUERY_PREFIX[key]
  const next = { ...prev, [`${prefix}_page`]: String(Math.max(1, page)) }
  if (perPage != null && Number.isFinite(perPage)) {
    next[`${prefix}_per_page`] = String(Math.max(1, Math.min(100, perPage)))
  }
  return next
}

/**
 * Build query string for GET dashboard/admin/state.
 * Merges list pagination from `listQuery`, optional health refresh, and tab-specific
 * overrides so catalog tabs still receive enough rows for dropdowns (plans, plan_cats).
 */
export function buildAdminStateQuery(
  listQuery: Record<string, string>,
  opts?: {
    refreshPanelHealth?: boolean
    refreshLivePanelMetrics?: boolean
    activeTab?: string
    /** Reseller operator: avoid stale admin panels_page leaving panel list empty. */
    resellerOperator?: boolean
    /** When false, skip L2TP server list prefetch on state load. */
    l2tpEnabled?: boolean
    /** Users tab user-detail: request plan catalog without full admin state payload. */
    includePlansForUserDetail?: boolean
  }
): string {
  const sp = new URLSearchParams()
  if (opts?.refreshPanelHealth) sp.set("refreshPanelHealth", "1")
  if (opts?.refreshLivePanelMetrics) sp.set("refreshLivePanelMetrics", "1")
  for (const [k, v] of Object.entries(listQuery)) {
    if (v !== "" && v != null) sp.set(k, String(v))
  }
  const tab = opts?.activeTab || ""
  if (tab !== "") sp.set("activeTab", tab)
  if (tab === "monitoring") {
    sp.set("panels_page", "1")
    sp.set("panels_per_page", "100")
  }
  if (tab === "texts") {
    sp.set("texts_page", "1")
    sp.set("texts_per_page", "500")
  }
  if (tab === "l2tp_servers" && opts?.l2tpEnabled !== false) {
    sp.set("l2tp_page", "1")
    sp.set("l2tp_per_page", "100")
  }
  if (tab === "plan_cats") {
    sp.set("panels_page", "1")
    sp.set("panels_per_page", "100")
  }
  if (tab === "plans") {
    sp.set("panels_page", "1")
    sp.set("panels_per_page", "100")
    sp.set("planCategories_page", "1")
    sp.set("planCategories_per_page", "100")
    if (opts?.l2tpEnabled !== false) {
      sp.set("l2tp_page", "1")
      sp.set("l2tp_per_page", "100")
    }
  }
  if (tab === "resellers" && opts?.l2tpEnabled !== false) {
    sp.set("l2tp_page", "1")
    sp.set("l2tp_per_page", "100")
  }
  if (opts?.resellerOperator) {
    sp.set("panels_page", "1")
    sp.set("panels_per_page", "100")
  }
  if (opts?.includePlansForUserDetail) {
    sp.set("includePlansForUserDetail", "1")
  }
  const s = sp.toString()
  return s ? `?${s}` : ""
}
