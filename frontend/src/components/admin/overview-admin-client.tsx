"use client"

import Link from "next/link"
import { useCallback, useEffect, useMemo, useRef, useState, type ComponentType } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Activity, Bot, Layers, Radio, Receipt, Server, TrendingUp, UsersRound } from "lucide-react"
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from "recharts"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"
import { getAdminState } from "@/lib/dash-admin-mutate"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import {
  formatBytes,
  formatChartDayLabel,
  formatChartTooltipDate,
  formatDateOnly,
  formatDateTime,
  formatNumber,
  formatNumericString,
} from "@/lib/format-locale"
import { useChartPrimaryColor } from "@/lib/chart-accent"
import { buildAllowedResellerTabs } from "@/lib/safe-reseller-tab"
import { cn } from "@/lib/utils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Progress } from "@/components/ui/progress"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import { DashSelect } from "@/components/dash-select"
import { DataPagination } from "@/components/data-pagination"
import {
  DashboardEconomicsOverviewCard,
  type EconomicsOverviewPayload,
} from "@/components/dashboard-economics-overview-card"
import {
  DashboardEconomicsPaymentAlert,
  type UpcomingPayment,
} from "@/components/dashboard-economics-payment-alert"
import { OverviewPreviewGrid } from "@/components/dashboard-overview-sections"

type DashRecord = Record<string, unknown>

type OverviewEconomics = EconomicsOverviewPayload & {
  upcomingPayments?: UpcomingPayment[]
}

type PanelHealth = {
  panelId: number
  /** @deprecated use httpOk */
  ok: boolean
  httpOk?: boolean
  networkReachable?: boolean
  httpStatus: number
  latencyMs: number | null
  checkedAt: string
  error?: string
  authProbeUrl?: string
  authProbeStatus?: number
}

type StatsPanelLine = {
  panel_id: number
  label: string
  xray_active: number
  xray_inactive: number
  max_online_day: number
}

type HostMetrics = {
  loadAvg?: [number, number, number] | null
  memoryBytes?: number | null
  memoryLimitBytes?: number | null
  diskFreeBytes?: number | null
  diskTotalBytes?: number | null
  checkedAt?: string
}

type ResellerOverviewMetrics = {
  window_days?: number
  sales_toman?: number
  sales_count?: number
  wholesale_toman?: number
  margin_est?: number
  downline_users?: number
  active_services?: number
  receipts_toman?: number
}

/** Derive HTTP / network flags (supports older API caches without httpOk / networkReachable). */
export function resolvePanelHealthFlags(h: PanelHealth | undefined): {
  httpOk: boolean
  networkReachable: boolean
} {
  if (!h) {
    return { httpOk: false, networkReachable: false }
  }
  const code = Number(h.httpStatus)
  const httpOk = h.httpOk ?? h.ok ?? false
  const networkReachable =
    h.networkReachable ?? (Number.isFinite(code) && code >= 100 && code <= 599)
  return { httpOk, networkReachable }
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function arr(v: unknown): DashRecord[] {
  return Array.isArray(v) ? (v.filter((x) => x && typeof x === "object") as DashRecord[]) : []
}

function pickPagination(data: DashRecord, key: string): PaginationMeta | null {
  const raw = data.pagination
  if (raw && typeof raw === "object") {
    const parsed = parsePaginationMeta((raw as DashRecord)[key])
    if (parsed) return parsed
  }
  return parsePaginationMeta(data[`${key}Pagination`])
}

function clampPct(p: number): number {
  if (!Number.isFinite(p)) return 0
  return Math.min(100, Math.max(0, p))
}

function truncateUrl(url: string, max = 36): string {
  const u = url.trim()
  if (!u) return "—"
  if (u.length <= max) return u
  return `${u.slice(0, max - 1)}…`
}

const RECEIPT_STATUS_ORDER = ["approved", "pending", "rejected"] as const

function receiptStatusLabelKey(status: string): "receiptStatus_approved" | "receiptStatus_pending" | "receiptStatus_rejected" | "receiptStatus_other" {
  const s = status.toLowerCase()
  if (s === "approved" || s === "pending" || s === "rejected") {
    return `receiptStatus_${s}`
  }
  return "receiptStatus_other"
}

function sortReceiptEntries(entries: [string, number][]): [string, number][] {
  const seen = new Set<string>()
  const out: [string, number][] = []
  for (const k of RECEIPT_STATUS_ORDER) {
    for (const [sk, v] of entries) {
      if (sk.toLowerCase() === k) {
        out.push([sk, v])
        seen.add(sk)
        break
      }
    }
  }
  for (const row of entries) {
    if (!seen.has(row[0])) out.push(row)
  }
  return out
}

function receiptSegmentClass(status: string): string {
  const s = status.toLowerCase()
  if (s === "approved") return "bg-emerald-500"
  if (s === "pending") return "bg-amber-400"
  if (s === "rejected") return "bg-rose-500"
  return "bg-muted-foreground/55"
}

function parsePanelHealth(raw: unknown): PanelHealth[] {
  if (!Array.isArray(raw)) return []
  return raw
    .filter((row): row is DashRecord => !!row && typeof row === "object")
    .map((row) => ({
      panelId: num(row.panelId ?? row.panel_id),
      ok: row.ok === true || row.ok === 1 || row.ok === "1",
      httpOk: row.httpOk == null ? undefined : Boolean(row.httpOk),
      networkReachable: row.networkReachable == null ? undefined : Boolean(row.networkReachable),
      httpStatus: num(row.httpStatus ?? row.http_status),
      latencyMs: row.latencyMs == null && row.latency_ms == null ? null : num(row.latencyMs ?? row.latency_ms),
      checkedAt: String(row.checkedAt ?? row.checked_at ?? ""),
      error: row.error != null ? String(row.error) : undefined,
      authProbeUrl: row.authProbeUrl != null ? String(row.authProbeUrl) : undefined,
      authProbeStatus:
        row.authProbeStatus == null && row.auth_probe_status == null
          ? undefined
          : num(row.authProbeStatus ?? row.auth_probe_status),
    }))
    .filter((h) => h.panelId > 0)
}

function parseHostMetrics(raw: unknown): HostMetrics | null {
  if (!raw || typeof raw !== "object") return null
  const h = raw as DashRecord
  const loadRaw = h.loadAvg ?? h.load_avg
  let loadAvg: [number, number, number] | null = null
  if (Array.isArray(loadRaw) && loadRaw.length >= 3) {
    loadAvg = [num(loadRaw[0]), num(loadRaw[1]), num(loadRaw[2])]
  }
  return {
    loadAvg,
    memoryBytes: h.memoryBytes == null && h.memory_bytes == null ? null : num(h.memoryBytes ?? h.memory_bytes),
    memoryLimitBytes:
      h.memoryLimitBytes == null && h.memory_limit_bytes == null
        ? null
        : num(h.memoryLimitBytes ?? h.memory_limit_bytes),
    diskFreeBytes:
      h.diskFreeBytes == null && h.disk_free_bytes == null ? null : num(h.diskFreeBytes ?? h.disk_free_bytes),
    diskTotalBytes:
      h.diskTotalBytes == null && h.disk_total_bytes == null ? null : num(h.diskTotalBytes ?? h.disk_total_bytes),
    checkedAt: h.checkedAt != null ? String(h.checkedAt) : undefined,
  }
}

function QuickLink({
  href,
  label,
  value,
  icon: Icon,
}: {
  href: string
  label: string
  value?: string
  icon: ComponentType<{ className?: string }>
}) {
  return (
    <Button render={<Link href={href} />} variant="outline" className="h-auto justify-start gap-3 p-3">
      <Icon className="size-4 text-muted-foreground" aria-hidden />
      <span className="min-w-0 text-start">
        <span className="block text-sm font-medium">{label}</span>
        {value ? <span className="block text-xs text-muted-foreground">{value}</span> : null}
      </span>
    </Button>
  )
}

function StatCard({
  title,
  value,
  hint,
  className,
}: {
  title: string
  value: string
  hint?: string
  className?: string
}) {
  return (
    <Card className={className}>
      <CardHeader className="pb-2">
        <CardDescription>{title}</CardDescription>
        <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
        {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      </CardHeader>
    </Card>
  )
}

function hasResellerPerfMetrics(raw: unknown): raw is ResellerOverviewMetrics {
  if (!raw || typeof raw !== "object") return false
  const m = raw as DashRecord
  return (
    m.sales_toman != null ||
    m.wholesale_toman != null ||
    m.margin_est != null ||
    m.receipts_toman != null ||
    m.downline_users != null
  )
}

export function OverviewAdminClient() {
  const t = useTranslations("dashboardOverview")
  const tNav = useTranslations("sidebar.items")
  const locale = useLocale()
  const isFa = locale === "fa"
  const chartPrimary = useChartPrimaryColor()
  const shell = useDashboardShellOptional()
  const [data, setData] = useState<DashRecord>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [metricsWindowDays, setMetricsWindowDays] = useState(30)
  const [statsDay, setStatsDay] = useState(0)
  const [refreshingHealth, setRefreshingHealth] = useState(false)
  const [panelsPage, setPanelsPage] = useState(1)
  const [panelsPerPage, setPanelsPerPage] = useState(20)
  const [panelsPagination, setPanelsPagination] = useState<PaginationMeta | null>(null)
  const metricsWindowRef = useRef(metricsWindowDays)
  const statsDayRef = useRef(statsDay)
  metricsWindowRef.current = metricsWindowDays
  statsDayRef.current = statsDay

  const load = useCallback(
    async (opts?: {
      refreshPanelHealth?: boolean
      overviewMetricsWindowDays?: number
      statsDay?: number
    }) => {
      setLoading(true)
      setError(null)
      try {
        const windowDays = opts?.overviewMetricsWindowDays ?? metricsWindowRef.current
        const dayRaw = opts?.statsDay ?? statsDayRef.current
        const day = [0, 1, 2, 3, 4, 5, 6, 7].includes(dayRaw) ? dayRaw : 0
        const query: Record<string, string | number> = {
          overview_metrics_window_days: [7, 30, 90].includes(windowDays) ? windowDays : 30,
          stats_day: day,
          panels_page: panelsPage,
          panels_per_page: panelsPerPage,
        }
        if (opts?.refreshPanelHealth) query.refreshPanelHealth = 1

        const dashboard = await getAdminState("dashboard", query)
        if (dashboard && Object.keys(dashboard).length > 0) {
          setData(dashboard)
          setPanelsPagination(pickPagination(dashboard, "panels"))
          return
        }
        const overview = await getAdminState("overview", query)
        setData(overview)
        setPanelsPagination(pickPagination(overview, "panels"))
      } catch {
        setError(t("loadError"))
      } finally {
        setLoading(false)
      }
    },
    [panelsPage, panelsPerPage, t]
  )

  useEffect(() => {
    void load()
  }, [load])

  const refreshPanelHealth = useCallback(async () => {
    setRefreshingHealth(true)
    try {
      await load({ refreshPanelHealth: true })
    } finally {
      setRefreshingHealth(false)
    }
  }, [load])

  const overviewPayload =
    data.overview && typeof data.overview === "object" ? (data.overview as DashRecord) : {}
  const overview = { ...overviewPayload, ...data }
  const stats =
    (overview.stats && typeof overview.stats === "object" ? (overview.stats as DashRecord) : null) ??
    (data.stats && typeof data.stats === "object" ? (data.stats as DashRecord) : {})
  const statsUsers = stats.users && typeof stats.users === "object" ? (stats.users as DashRecord) : overview
  const bot = overview.bot && typeof overview.bot === "object" ? (overview.bot as DashRecord) : {}
  const counts = overview.counts && typeof overview.counts === "object" ? (overview.counts as DashRecord) : {}
  const plans = arr(data.plans)
  const cards = arr(data.cards)
  const panels = arr(data.panels)
  const receipts = arr(data.receipts)
  const pendingUsers = arr(data.pendingUsers)
  const recentUsers = arr(data.usersList ?? data.users)
  const recentResellers = arr(data.resellers)
  const recentBroadcasts = arr(data.broadcasts)
  const isReseller = data.isReseller === true || data.actorRole === "reseller" || shell?.isReseller === true
  const allowedNavTabs = useMemo(() => {
    if (!isReseller) return null
    return buildAllowedResellerTabs(shell?.allowedResellerTabs, shell?.actorPermissions)
  }, [isReseller, shell?.actorPermissions, shell?.allowedResellerTabs])
  const allowTab = (tab: string) => !allowedNavTabs || allowedNavTabs.has(tab)
  const statsDayClamped = [0, 1, 2, 3, 4, 5, 6, 7].includes(statsDay) ? statsDay : 0
  const statDateRaw = String(stats.stat_date ?? overview.stat_date ?? "")

  const host = useMemo(() => {
    const nested = overviewPayload.host
    const top = data.host
    if (nested && typeof nested === "object") return parseHostMetrics(nested)
    if (top && typeof top === "object") return parseHostMetrics(top)
    return null
  }, [data.host, overviewPayload.host])

  const panelHealthList = useMemo(() => {
    const nested = overviewPayload.panelHealth
    const top = data.panelHealth
    if (Array.isArray(nested)) return parsePanelHealth(nested)
    if (Array.isArray(top)) return parsePanelHealth(top)
    return []
  }, [data.panelHealth, overviewPayload.panelHealth])

  const healthById = useMemo(() => {
    const m = new Map<number, PanelHealth>()
    for (const h of panelHealthList) m.set(h.panelId, h)
    return m
  }, [panelHealthList])

  const statsByPanelId = useMemo(() => {
    const m = new Map<number, StatsPanelLine>()
    const rows = Array.isArray(stats.panels) ? stats.panels : []
    for (const row of rows) {
      if (!row || typeof row !== "object") continue
      const r = row as DashRecord
      const id = num(r.panel_id ?? r.panelId)
      if (id < 1) continue
      m.set(id, {
        panel_id: id,
        label: String(r.label ?? ""),
        xray_active: num(r.xray_active),
        xray_inactive: num(r.xray_inactive),
        max_online_day: num(r.max_online_day),
      })
    }
    return m
  }, [stats.panels])

  const panelRows = useMemo(() => {
    return panels.map((p) => {
      const id = num(p.id)
      return { p, id, st: statsByPanelId.get(id), h: healthById.get(id) }
    })
  }, [panels, statsByPanelId, healthById])

  const onlineSeries = useMemo(() => {
    const raw = overview.onlineDailySeries
    if (!Array.isArray(raw)) return []
    return raw
      .filter((row): row is DashRecord => !!row && typeof row === "object")
      .map((row) => ({
        date: String(row.date ?? ""),
        totalMaxOnline: num(row.totalMaxOnline ?? row.total_max_online),
      }))
      .filter((row) => row.date)
  }, [overview.onlineDailySeries])

  const chartData = useMemo(
    () =>
      onlineSeries.map((d) => ({
        ...d,
        day: formatChartDayLabel(d.date, isFa),
        tooltipDate: formatChartTooltipDate(d.date, isFa),
      })),
    [onlineSeries, isFa]
  )

  const receiptRowsSorted = useMemo(() => {
    const byStatus = counts.receiptsByStatus
    if (byStatus && typeof byStatus === "object") {
      return sortReceiptEntries(
        Object.entries(byStatus as Record<string, unknown>).map(([k, v]) => [k, num(v)])
      )
    }
    const countsLocal: Record<string, number> = { approved: 0, pending: 0, rejected: 0 }
    let other = 0
    for (const row of receipts) {
      const st = String(row.status ?? "").toLowerCase()
      if (st === "approved" || st === "pending" || st === "rejected") countsLocal[st]++
      else other++
    }
    const entries: [string, number][] = Object.entries(countsLocal)
    if (other > 0) entries.push(["other", other])
    return sortReceiptEntries(entries)
  }, [counts.receiptsByStatus, receipts])

  const receiptBarTotal = useMemo(
    () => receiptRowsSorted.reduce((sum, [, v]) => sum + v, 0),
    [receiptRowsSorted]
  )

  const memPct = useMemo(() => {
    const used = host?.memoryBytes
    const lim = host?.memoryLimitBytes
    if (used == null || lim == null || lim <= 0) return null
    return clampPct((used / lim) * 100)
  }, [host?.memoryBytes, host?.memoryLimitBytes])

  const diskPct = useMemo(() => {
    const free = host?.diskFreeBytes
    const total = host?.diskTotalBytes
    if (free == null || total == null || total <= 0) return null
    return clampPct(((total - free) / total) * 100)
  }, [host?.diskFreeBytes, host?.diskTotalBytes])

  const loadLine =
    host?.loadAvg && host.loadAvg.length >= 3
      ? host.loadAvg.map((x) => formatNumber(x, isFa)).join(" / ")
      : "—"

  const economics = useMemo((): OverviewEconomics | null => {
    const raw =
      (overview.economics && typeof overview.economics === "object" ? overview.economics : null) ??
      (data.economics && typeof data.economics === "object" ? data.economics : null)
    if (!raw || typeof raw !== "object") return null
    return raw as OverviewEconomics
  }, [data.economics, overview.economics])

  const upcomingPayments = economics?.upcomingPayments ?? []

  const userObj = data.user && typeof data.user === "object" ? (data.user as DashRecord) : null
  const actorBalance =
    typeof userObj?.balance === "number"
      ? userObj.balance
      : typeof data.actorBalance === "number"
        ? (data.actorBalance as number)
        : undefined

  const perfMetrics = hasResellerPerfMetrics(data.resellerOverviewMetrics)
    ? (data.resellerOverviewMetrics as ResellerOverviewMetrics)
    : null
  const showResellerPerf = isReseller && perfMetrics != null
  const perfWindow = [7, 30, 90].includes(metricsWindowDays) ? metricsWindowDays : 30

  const statCards = useMemo(
    () => [
      { title: t("usersTotal"), value: num(statsUsers.users_total ?? overview.users_total), hint: t("usersApproved") },
      { title: t("usersPending"), value: num(statsUsers.users_pending ?? overview.users_pending) },
      { title: t("usersToday"), value: num(statsUsers.users_today ?? overview.users_today) },
      { title: t("servicesTotal"), value: num(statsUsers.services_total ?? overview.services_total) },
      { title: t("plansCount"), value: num(overview.plans_total) || plans.length },
      { title: t("cardsCount"), value: num(overview.cards_total) || cards.length },
      { title: t("receiptsTotal"), value: num(overview.receipts_total ?? counts.receiptsTotal) || receipts.length },
      { title: t("panelsCount"), value: num(overview.panels_total ?? counts.panels) || panels.length },
    ],
    [cards.length, counts.panels, counts.receiptsTotal, overview, panels.length, plans.length, receipts.length, statsUsers, t]
  )

  const base = `/${locale}/dashboard`
  const showHostCard = host != null && !showResellerPerf
  const showPanelHealth = panelRows.length > 0

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
          {statDateRaw ? (
            <p className="text-xs text-muted-foreground">
              {t("statDate")}: {formatDateOnly(statDateRaw, isFa)}
            </p>
          ) : null}
          {isReseller ? (
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <Label htmlFor="overview-stats-day" className="text-xs text-muted-foreground">
                {t("statsDayLabel")}
              </Label>
              <DashSelect
                id="overview-stats-day"
                triggerClassName="h-8 w-[10rem]"
                value={String(statsDayClamped)}
                onValueChange={(v) => {
                  const d = Number(v)
                  if (!Number.isFinite(d) || d < 0 || d > 7) return
                  setStatsDay(d)
                  void load({ statsDay: d })
                }}
                options={[0, 1, 2, 3, 4, 5, 6, 7].map((d) => ({
                  value: String(d),
                  label:
                    d === 0
                      ? t("statsDayToday")
                      : t("statsDayAgo", { days: formatNumber(d, isFa) }),
                }))}
              />
            </div>
          ) : null}
        </div>
        <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
          {t("refresh")}
        </Button>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}

      {upcomingPayments.length > 0 ? (
        <DashboardEconomicsPaymentAlert
          items={upcomingPayments}
          dashboardBaseUrl={base}
          onDismissRefresh={() => void load()}
        />
      ) : null}

      {economics?.site ? (
        <DashboardEconomicsOverviewCard economics={economics} dashboardBaseUrl={base} />
      ) : null}

      {isReseller && typeof actorBalance === "number" ? (
        <Card>
          <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
            <div>
              <p className="text-xs font-medium text-muted-foreground">{t("actorWalletLabel")}</p>
              <p className="text-2xl font-semibold tabular-nums">{formatNumber(actorBalance, isFa)}</p>
            </div>
            <Button type="button" variant="default" size="sm" render={<Link href={`${base}/reseller_charge`} />}>
              {t("actorWalletTopUp")}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {showResellerPerf ? (
        <Card className="border-primary/20">
          <CardHeader className="pb-2">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="flex items-start gap-3">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <TrendingUp className="size-5" aria-hidden />
                </div>
                <div className="space-y-1">
                  <CardTitle className="text-base">{t("perfTitle")}</CardTitle>
                  <CardDescription>
                    {t("perfSubtitle", { days: formatNumber(perfWindow, isFa) })}
                  </CardDescription>
                </div>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="overview-metrics-window">{t("perfWindowDays")}</Label>
                <DashSelect
                  id="overview-metrics-window"
                  triggerClassName="w-[8rem]"
                  value={String(perfWindow)}
                  onValueChange={(v) => {
                    const d = Number(v)
                    if (![7, 30, 90].includes(d)) return
                    setMetricsWindowDays(d)
                    void load({ overviewMetricsWindowDays: d })
                  }}
                  options={[
                    { value: "7", label: t("perfWindow7") },
                    { value: "30", label: t("perfWindow30") },
                    { value: "90", label: t("perfWindow90") },
                  ]}
                />
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <StatCard
                className="border-primary/15"
                title={t("perfSales")}
                value={formatNumber(num(perfMetrics?.sales_toman), isFa)}
                hint={t("perfSalesHint")}
              />
              <StatCard
                title={t("perfWholesale")}
                value={formatNumber(num(perfMetrics?.wholesale_toman), isFa)}
                hint={t("perfWholesaleHint")}
              />
              <StatCard
                title={t("perfMargin")}
                value={formatNumber(num(perfMetrics?.margin_est), isFa)}
                hint={t("perfMarginHint")}
              />
              <StatCard
                title={t("perfDownline")}
                value={formatNumber(num(perfMetrics?.downline_users), isFa)}
                hint={
                  [
                    t("perfDownlineLifetimeHint"),
                    num(perfMetrics?.active_services) > 0
                      ? t("perfActiveServices", {
                          count: formatNumber(num(perfMetrics?.active_services), isFa),
                        })
                      : null,
                  ]
                    .filter(Boolean)
                    .join(" · ")
                }
              />
              <StatCard
                title={t("perfReceipts")}
                value={formatNumber(num(perfMetrics?.receipts_toman), isFa)}
                hint={t("perfReceiptsHint")}
              />
            </div>
            {num(perfMetrics?.sales_count) > 0 ? (
              <p className="text-xs text-muted-foreground">
                {t("perfSalesCount", { count: formatNumber(num(perfMetrics?.sales_count), isFa) })}
              </p>
            ) : null}
            <p className="text-xs text-muted-foreground">{t("perfMarginDisclaimer")}</p>
          </CardContent>
        </Card>
      ) : null}

      {showHostCard ? (
        <Card className="border-primary/20">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{t("hostThisServer")}</CardTitle>
            <CardDescription>
              {t("hostLoad")}: {loadLine}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <div className="flex justify-between text-xs text-muted-foreground">
                  <span>{t("hostMem")}</span>
                  <span className="tabular-nums">
                    {host?.memoryBytes != null && host?.memoryLimitBytes != null
                      ? `${formatBytes(host.memoryBytes, isFa)} / ${formatBytes(host.memoryLimitBytes, isFa)}`
                      : "—"}
                  </span>
                </div>
                <Progress value={memPct ?? 0} className={memPct == null ? "opacity-40" : ""} />
              </div>
              <div className="space-y-2">
                <div className="flex justify-between text-xs text-muted-foreground">
                  <span>{t("hostDisk")}</span>
                  <span className="tabular-nums">
                    {host?.diskFreeBytes != null && host?.diskTotalBytes != null
                      ? `${t("diskFreeLabel")}: ${formatBytes(host.diskFreeBytes, isFa)} · ${formatBytes(host.diskTotalBytes, isFa)}`
                      : "—"}
                  </span>
                </div>
                <Progress value={diskPct ?? 0} className={diskPct == null ? "opacity-40" : ""} />
              </div>
            </div>
          </CardContent>
        </Card>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {statCards.map((s) => (
          <StatCard key={s.title} title={s.title} value={formatNumber(s.value, isFa)} hint={s.hint} />
        ))}
      </div>

      <div className="grid gap-4 xl:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("chartOnlineTitle")}</CardTitle>
            <CardDescription>{t("chartOnlineSubtitle")}</CardDescription>
          </CardHeader>
          <CardContent className="h-56">
            {chartData.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t("emptyPreview")}</p>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" />
                  <XAxis dataKey="day" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} width={36} />
                  <RechartsTooltip
                    labelFormatter={(_, payload) =>
                      String((payload?.[0]?.payload as { tooltipDate?: string } | undefined)?.tooltipDate ?? "")
                    }
                  />
                  <Area type="monotone" dataKey="totalMaxOnline" stroke={chartPrimary} fill={chartPrimary} fillOpacity={0.15} />
                </AreaChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("receiptsByStatus")}</CardTitle>
            <CardDescription>{t("financeCardHint")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {receiptRowsSorted.length > 0 && receiptBarTotal > 0 ? (
              <>
                <div className="flex h-2.5 w-full overflow-hidden rounded-full bg-muted">
                  {receiptRowsSorted.map(([status, val]) => {
                    const pct = receiptBarTotal > 0 ? clampPct((val / receiptBarTotal) * 100) : 0
                    if (pct <= 0) return null
                    return (
                      <div
                        key={status}
                        className={cn("h-full min-w-[2px] transition-[width]", receiptSegmentClass(status))}
                        style={{ width: `${pct}%` }}
                        title={`${t(receiptStatusLabelKey(status))}: ${formatNumber(val, isFa)}`}
                      />
                    )
                  })}
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                  {receiptRowsSorted.map(([status, val]) => (
                    <div
                      key={status}
                      className="flex items-center justify-between gap-2 rounded-lg border border-border/70 bg-muted/25 px-3 py-2 text-sm"
                    >
                      <span className="flex min-w-0 items-center gap-2">
                        <span
                          className={cn("size-2 shrink-0 rounded-full", receiptSegmentClass(status))}
                          aria-hidden
                        />
                        <span className="truncate font-medium">{t(receiptStatusLabelKey(status))}</span>
                      </span>
                      <span className="tabular-nums text-muted-foreground">{formatNumber(val, isFa)}</span>
                    </div>
                  ))}
                </div>
              </>
            ) : (
              <p className="text-sm text-muted-foreground">{t("emptyPreview")}</p>
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("botCard")}</CardTitle>
          <CardDescription>{t("financeCardHint")}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-2">
          <Badge variant={bot.enabled ? "default" : "secondary"}>{bot.enabled ? t("botEnabled") : t("botDisabled")}</Badge>
          <Badge variant={bot.telegram_enabled ? "default" : "secondary"}>{t("telegram")}</Badge>
          <Badge variant={bot.bale_enabled ? "default" : "secondary"}>{t("bale")}</Badge>
        </CardContent>
      </Card>

      {showPanelHealth ? (
        <section className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/15 bg-primary/[0.03] px-4 py-3">
            <div className="flex items-center gap-2">
              <Radio className="size-5 text-primary" aria-hidden />
              <h3 className="text-base font-semibold">{t("panelCards")}</h3>
            </div>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={loading || refreshingHealth}
              onClick={() => void refreshPanelHealth()}
            >
              {t("refreshPanelHealth")}
            </Button>
          </div>
          <TooltipProvider>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {panelRows.length === 0 ? (
                <p className="text-sm text-muted-foreground">—</p>
              ) : (
                panelRows.map(({ p, id, st, h }) => {
                  const label = String(
                    p.label ?? p.name ?? st?.label ?? `#${formatNumericString(String(id), isFa)}`
                  )
                  const url = String(p.panel_url ?? (p as { panelUrl?: unknown }).panelUrl ?? "")
                  const active = p.active === true || p.active === 1 || p.active === "1"
                  const xa = num(st?.xray_active)
                  const xi = num(st?.xray_inactive)
                  const xTotal = xa + xi
                  const xrayPct = xTotal > 0 ? clampPct((xa / xTotal) * 100) : 0
                  const urlEmpty = !url.trim()
                  const hasHealth = Boolean(h)
                  const { httpOk, networkReachable } = resolvePanelHealthFlags(h)
                  const transportDown = hasHealth && !networkReachable && !urlEmpty
                  const httpOdd = hasHealth && networkReachable && !httpOk && !urlEmpty
                  const healthyCore = hasHealth && httpOk && active && !urlEmpty
                  const tone = urlEmpty
                    ? "border-destructive/50 bg-destructive/[0.04]"
                    : !hasHealth
                      ? "border-amber-500/40 bg-amber-500/[0.05]"
                      : transportDown
                        ? "border-destructive/70 bg-destructive/[0.06]"
                        : httpOdd
                          ? "border-amber-500/60 bg-amber-500/[0.08]"
                          : healthyCore
                            ? "border-emerald-600/50 bg-emerald-600/[0.06]"
                            : !active
                              ? "border-border bg-muted/15"
                              : "border-border/80 bg-card"
                  const httpLabel = h
                    ? formatNumericString(String(h.httpStatus || 0), isFa)
                    : t("unknown")
                  const checkedShort = h?.checkedAt ? formatDateTime(h.checkedAt, isFa) : "—"

                  return (
                    <Tooltip key={id || label}>
                      <TooltipTrigger>
                        <Card className={cn("transition-shadow hover:shadow-md", tone)}>
                        <CardHeader className="gap-2 pb-2">
                          <div className="flex flex-wrap items-start justify-between gap-2">
                            <CardTitle className="text-base leading-snug">{label}</CardTitle>
                            <div className={cn("flex max-w-[min(100%,14rem)] flex-wrap gap-1", isFa && "justify-end")}>
                              <Badge variant={active ? "secondary" : "outline"} className="text-[10px]">
                                {active ? t("badgeDbActive") : t("badgeDbInactive")}
                              </Badge>
                              {hasHealth && networkReachable ? (
                                <Badge
                                  variant="outline"
                                  className="border-emerald-500/40 text-[10px] text-emerald-700 dark:text-emerald-400"
                                >
                                  {t("badgeNetworkOk")}
                                </Badge>
                              ) : hasHealth && !urlEmpty ? (
                                <Badge variant="destructive" className="text-[10px]">
                                  {t("badgeTransportDown")}
                                </Badge>
                              ) : null}
                              {httpOk ? (
                                <Badge variant="outline" className="text-[10px] text-muted-foreground">
                                  {t("badgeHttpOk")}
                                </Badge>
                              ) : networkReachable && !urlEmpty ? (
                                <Badge
                                  variant="outline"
                                  className="border-amber-500/50 text-[10px] text-amber-800 dark:text-amber-300"
                                >
                                  {t("badgeHttpNonStandard", { code: httpLabel })}
                                </Badge>
                              ) : null}
                            </div>
                          </div>
                          <CardDescription className="break-all font-mono text-xs">
                            {truncateUrl(url)}
                          </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                          <div className="rounded-xl border border-border/80 bg-background/80 px-3 py-3">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                              {t("colLatency")}
                            </p>
                            <p className="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">
                              {h?.latencyMs != null ? formatNumber(h.latencyMs, isFa) : "—"}
                              <span className="ms-1 text-base font-normal text-muted-foreground">ms</span>
                            </p>
                            <p className="mt-1 text-[11px] text-muted-foreground">{t("colReachable")}</p>
                            <p className="font-mono text-sm tabular-nums text-foreground">HTTP {httpLabel}</p>
                          </div>
                          <div className="flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                            <span>{t("colXrayActive")}</span>
                            <span className="font-medium tabular-nums text-foreground">
                              {formatNumber(xa, isFa)} / {formatNumber(xi, isFa)}
                            </span>
                          </div>
                          <div className="space-y-1">
                            <div className="flex justify-between text-xs text-muted-foreground">
                              <span>{t("xrayShare")}</span>
                              <span className="tabular-nums">{formatNumber(Math.round(xrayPct), isFa)}%</span>
                            </div>
                            <Progress value={xTotal > 0 ? xrayPct : 0} className={xTotal === 0 ? "opacity-40" : ""} />
                          </div>
                          <div className="flex flex-wrap justify-between gap-2 border-t border-border pt-2 text-xs text-muted-foreground">
                            <span>{t("colMaxOnline")}</span>
                            <span className="font-medium tabular-nums text-foreground">
                              {st?.max_online_day != null ? formatNumber(num(st.max_online_day), isFa) : "—"}
                            </span>
                          </div>
                          <p className="text-[11px] text-muted-foreground">
                            {t("lastCheck")}: {checkedShort}
                          </p>
                        </CardContent>
                        </Card>
                      </TooltipTrigger>
                      <TooltipContent className="max-w-xs text-xs leading-relaxed">
                        <p className="text-muted-foreground">{t("ttRttHint")}</p>
                        {networkReachable ? <p className="mt-2">{t("ttNetworkOk")}</p> : null}
                        {httpOk ? (
                          <p className="mt-2">{t("ttHttpOk")}</p>
                        ) : networkReachable ? (
                          <>
                            <p className="mt-2">{t("ttHttpNonStandard")}</p>
                            {h?.authProbeUrl && (h.authProbeStatus ?? 0) > 0 ? (
                              <p className="mt-1 text-muted-foreground">
                                {t("ttAuthProbeOk", {
                                  url: h.authProbeUrl,
                                  code: formatNumericString(String(h.authProbeStatus ?? 0), isFa),
                                })}
                              </p>
                            ) : null}
                          </>
                        ) : (
                          <p className="mt-2">{t("ttHttpFail")}</p>
                        )}
                        <p className="mt-2">{active ? t("ttDbActive") : t("ttDbInactive")}</p>
                        {h?.error ? <p className="mt-2 text-destructive">{h.error}</p> : null}
                      </TooltipContent>
                    </Tooltip>
                  )
                })
              )}
            </div>
          </TooltipProvider>
          <DataPagination
            data-testid="dash-overview-panels-pagination"
            meta={panelsPagination}
            onPageChange={setPanelsPage}
            onPerPageChange={(n) => {
              setPanelsPerPage(n)
              setPanelsPage(1)
            }}
          />
        </section>
      ) : null}

      <OverviewPreviewGrid
        dashboardBaseUrl={base}
        allowTab={allowTab}
        recentUsers={recentUsers}
        recentReceipts={receipts}
        pendingUsersPreview={pendingUsers}
        recentResellers={recentResellers}
        recentBroadcasts={recentBroadcasts}
        isReseller={isReseller}
      />

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("quickLinks")}</CardTitle>
          <CardDescription>{t("financeCardHint")}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
          {allowTab("users") ? (
            <QuickLink
              href={`${base}/users`}
              label={tNav("users")}
              value={formatNumber(pendingUsers.length, isFa)}
              icon={UsersRound}
            />
          ) : null}
          {allowTab("receipts") || allowTab("payments") ? (
            <QuickLink
              href={`${base}/payments`}
              label={tNav(allowTab("receipts") ? "receipts" : "payments")}
              value={formatNumber(receipts.length, isFa)}
              icon={Receipt}
            />
          ) : null}
          {allowTab("xui_panels") ? (
            <QuickLink
              href={`${base}/xui_panels`}
              label={tNav("xui_panels")}
              value={formatNumber(panels.length, isFa)}
              icon={Server}
            />
          ) : null}
          {allowTab("plans") ? (
            <QuickLink
              href={`${base}/plans`}
              label={tNav("plans")}
              value={formatNumber(plans.length, isFa)}
              icon={Layers}
            />
          ) : null}
          {allowTab("bots") ? (
            <QuickLink href={`${base}/bots`} label={tNav("bots")} icon={Bot} />
          ) : null}
          {allowTab("reseller_bots") ? (
            <QuickLink href={`${base}/reseller_bots`} label={tNav("reseller_bots")} icon={Bot} />
          ) : null}
          {allowTab("monitoring") ? (
            <QuickLink href={`${base}/monitoring`} label={tNav("monitoring")} icon={Activity} />
          ) : null}
        </CardContent>
      </Card>
    </div>
  )
}
