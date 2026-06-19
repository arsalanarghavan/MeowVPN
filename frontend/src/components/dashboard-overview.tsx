"use client"

import { useMemo, type ComponentType } from "react"
import { useTranslation } from "react-i18next"
import {
  Bot,
  CreditCard,
  Layers,
  Percent,
  Radio,
  Receipt,
  Server,
  Tags,
  TrendingUp,
  UsersRound,
} from "lucide-react"
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from "recharts"

import { Badge } from "@/components/ui/badge"
import { DashSelect } from "@/components/dash-select"
import { Label } from "@/components/ui/label"
import {
  DashboardEconomicsOverviewCard,
  type EconomicsOverviewPayload,
} from "@/components/dashboard-economics-overview-card"
import {
  DashboardEconomicsPaymentAlert,
  type UpcomingPayment,
} from "@/components/dashboard-economics-payment-alert"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashPage } from "@/components/dash-page"
import { Button } from "@/components/ui/button"
import { DataPagination } from "@/components/data-pagination"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import {
  formatBytes,
  formatChartDayLabel,
  formatChartTooltipDate,
  formatDateOnly,
  formatDateTime,
  formatNumber,
  formatNumericString,
} from "@/lib/format-locale"
import { OverviewPreviewGrid } from "@/components/dashboard-overview-sections"
import { overviewAccentOutlineBtn, useChartPrimaryColor } from "@/lib/chart-accent"
import { cn } from "@/lib/utils"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { buildDashboardTabUrl } from "@/lib/dash-tab"
import type { DashRecord } from "@/lib/overview-rows"
import { overviewPlatformEnabled } from "@/lib/enabled-platforms"
import { useDashLocale } from "@/lib/dash-locale-context"
type OverviewUsers = {
  users_approved?: number
  users_pending?: number
  users_rejected?: number
  users_blocked?: number
  users_total?: number
  users_with_telegram?: number
  users_with_bale?: number
  users_today?: number
  services_total?: number
  services_l2tp?: number
}

type StatsPanelLine = {
  panel_id: number
  label: string
  xray_active: number
  xray_inactive: number
  max_online_day: number
}

type StatsPayload = {
  stat_date?: string
  day_offset?: number
  users?: OverviewUsers
  panels?: StatsPanelLine[]
  l2tp_services?: number
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

type HostMetrics = {
  loadAvg?: [number, number, number] | null
  memoryBytes?: number | null
  memoryLimitBytes?: number | null
  diskFreeBytes?: number | null
  diskTotalBytes?: number | null
  checkedAt?: string
}

type OnlineDailyPoint = {
  date: string
  totalMaxOnline: number
}

type OverviewEconomics = EconomicsOverviewPayload & {
  upcomingPayments?: UpcomingPayment[]
}

type OverviewPayload = {
  stats?: StatsPayload
  counts?: Record<string, unknown>
  bot?: {
    enabled?: boolean
    telegram_enabled?: boolean
    bale_enabled?: boolean
    telegram_bot_username?: string
    bale_bot_username?: string
  }
  panelHealth?: PanelHealth[]
  host?: HostMetrics
  onlineDailySeries?: OnlineDailyPoint[]
  livePanelSnapshots?: unknown[]
  externalHostSnapshots?: unknown[]
  economics?: OverviewEconomics | null
}

export type { OverviewPayload, PanelHealth, StatsPayload }

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
    h.networkReachable ??
    (Number.isFinite(code) && code >= 100 && code <= 599)
  return { httpOk, networkReachable }
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
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

function receiptStatusLabelKey(status: string): string {
  const s = status.toLowerCase()
  if (s === "approved" || s === "pending" || s === "rejected") {
    return `dashboardOverview.receiptStatus_${s}`
  }
  return "dashboardOverview.receiptStatus_other"
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

function StatCard({
  title,
  value,
  sub,
  className,
  testId,
}: {
  title: string
  value: number
  sub?: string
className?: string
  testId?: string
}) {
  const { isFa } = useDashLocale()

  return (
    <div
      data-testid={testId}
      className={cn(
        "rounded-lg border border-border bg-card p-4 shadow-sm",
        className
      )}
    >
      <p className="text-xs font-medium text-muted-foreground">{title}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">
        {formatNumber(value, isFa)}
      </p>
      {sub ? <p className="mt-0.5 text-xs text-muted-foreground">{sub}</p> : null}
    </div>
  )
}

function QuickLink({
  tabKey,
  label,
  base,
  onSelectTab,
}: {
  tabKey: string
  label: string
  base: string
  onSelectTab: (k: string) => void
}) {
  const root = base.replace(/\/?$/, "")
  const href = buildDashboardTabUrl(root, tabKey)
  return (
    <Button variant="outline" size="sm" className={cn("h-8", overviewAccentOutlineBtn)} asChild>
      <a
        href={href}
        onClick={(e) => {
          e.preventDefault()
          onSelectTab(tabKey)
        }}
      >
        {label}
      </a>
    </Button>
  )
}

function DashTabLink({
  tabKey,
  label,
  base,
  onSelectTab,
  Icon,
}: {
  tabKey: string
  label: string
  base: string
  onSelectTab: (k: string) => void
  Icon: ComponentType<{ className?: string }>
}) {
  const root = base.replace(/\/?$/, "")
  const href = buildDashboardTabUrl(root, tabKey)
  return (
    <Button
      variant="outline"
      size="sm"
      className={cn("h-9 max-w-full gap-2 ps-3 pe-3 font-normal", overviewAccentOutlineBtn)}
      asChild
    >
      <a
        href={href}
        onClick={(e) => {
          e.preventDefault()
          onSelectTab(tabKey)
        }}
      >
        <Icon className="size-4 shrink-0 opacity-80" aria-hidden />
        <span className="min-w-0 truncate">{label}</span>
      </a>
    </Button>
  )
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

export function DashboardOverview({
  overview,
  panels,
  panelsPagination,
  dashboardBaseUrl,
  allowedNavTabs = null,
  onSelectTab,
  onRefreshPanelHealth,
  onPanelsPageChange,
  onPanelsPerPageChange,
  compactHealthOnly = false,
  prependResellerFinance = false,
  actorBalance = undefined,
  resellerOverviewMetrics = null,
  overviewMetricsWindowDays = 30,
  onOverviewMetricsWindowChange,
  statsDay = 0,
  onStatsDayChange,
  recentUsers = [],
  recentReceipts = [],
  pendingUsersPreview = [],
  recentResellers = [],
  recentBroadcasts = [],
  isReseller = false,
  onOpenUserDetail,
  onOpenResellerWorkspace,
  onReceiptsFilterNavigate,
  onEconomicsRefresh,
}: {
  overview: OverviewPayload | undefined
  panels: DashRecord[]
  panelsPagination: PaginationMeta | null
dashboardBaseUrl: string
  /** When set (reseller), only show links to tabs in this set. */
  allowedNavTabs?: Set<string> | null
  onSelectTab: (tabKey: string) => void
  onRefreshPanelHealth?: () => void
  onPanelsPageChange: (page: number) => void
  onPanelsPerPageChange: (perPage: number) => void
  /** Reseller / user persona: only server list, online/offline, ping. */
  compactHealthOnly?: boolean
  /** Reseller: wallet above charts (full overview). */
  prependResellerFinance?: boolean
  /** Reseller wallet balance (toman); shown when compactHealthOnly and defined. */
  actorBalance?: number
  /** Reseller self-service performance KPIs (dashboard tab only). */
  resellerOverviewMetrics?: ResellerOverviewMetrics | Record<string, unknown> | null
  overviewMetricsWindowDays?: number
  onOverviewMetricsWindowChange?: (days: number) => void
  /** Reseller stats day offset (0 = today, up to 7). */
  statsDay?: number
  onStatsDayChange?: (day: number) => void
  recentUsers?: DashRecord[]
  recentReceipts?: DashRecord[]
  pendingUsersPreview?: DashRecord[]
  recentResellers?: DashRecord[]
  recentBroadcasts?: DashRecord[]
  isReseller?: boolean
  onOpenUserDetail?: (svpUserId: number) => void
  onOpenResellerWorkspace?: (resellerId: number) => void
  onReceiptsFilterNavigate?: (status?: string) => void
  onEconomicsRefresh?: () => void
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const chartPrimary = useChartPrimaryColor()
  const allowTab = (tab: string) => !allowedNavTabs || allowedNavTabs.has(tab)
  const u = overview?.stats?.users ?? {}
  const counts = overview?.counts ?? {}
  const bot = overview?.bot ?? {}
  const host = overview?.host
  const series = overview?.onlineDailySeries ?? []

  const healthById = useMemo(() => {
    const m = new Map<number, PanelHealth>()
    for (const h of overview?.panelHealth ?? []) {
      m.set(h.panelId, h)
    }
    return m
  }, [overview?.panelHealth])

  const statsByPanelId = useMemo(() => {
    const m = new Map<number, StatsPanelLine>()
    for (const row of overview?.stats?.panels ?? []) {
      m.set(Number(row.panel_id), row)
    }
    return m
  }, [overview?.stats?.panels])

  const rows = useMemo(() => {
    return (panels || [])
      .filter((p): p is DashRecord => p != null && typeof p === "object")
      .map((p) => {
        const id = num(p.id)
        const st = statsByPanelId.get(id)
        const h = healthById.get(id)
        return { p, id, st, h }
      })
  }, [panels, statsByPanelId, healthById])

  const receiptByStatus = counts.receiptsByStatus as Record<string, number> | undefined

  const receiptsTotalCount = num(
    (counts as { receiptsTotal?: unknown }).receiptsTotal ?? counts.receiptsSample
  )

  const receiptRowsSorted = useMemo(() => {
    if (!receiptByStatus || typeof receiptByStatus !== "object") return [] as [string, number][]
    return sortReceiptEntries(Object.entries(receiptByStatus).map(([k, v]) => [k, num(v)]))
  }, [receiptByStatus])

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
    const used = total - free
    return clampPct((used / total) * 100)
  }, [host?.diskFreeBytes, host?.diskTotalBytes])

  const chartRows = useMemo(
    () =>
      series.map((pt) => ({
        ...pt,
        label: pt.date.length >= 10 ? formatChartDayLabel(pt.date, isFa) : formatNumericString(pt.date, isFa),
        totalMaxOnline: num(pt.totalMaxOnline),
      })),
    [series, isFa]
  )

  const perfMetrics = (resellerOverviewMetrics ?? null) as ResellerOverviewMetrics | null
  const perfWindow = [7, 30, 90].includes(overviewMetricsWindowDays) ? overviewMetricsWindowDays : 30
  const resellerFocused = prependResellerFinance && isReseller
  const statsDayClamped = [0, 1, 2, 3, 4, 5, 6, 7].includes(statsDay) ? statsDay : 0

  const loadLine =
    host?.loadAvg && host.loadAvg.length >= 3
      ? host.loadAvg.map((x) => formatNumber(x, isFa)).join(" / ")
      : "—"

  if (compactHealthOnly) {
    const showFinance = typeof actorBalance === "number"
    return (
      <DashPage className={"space-y-4"}>
        <DashboardPageHeader
          title={<h2 className="text-lg font-semibold">{t("dashboardOverview.compactTitle")}</h2>}
          description={t("dashboardOverview.compactSubtitle")}
          actions={
            onRefreshPanelHealth ? (
              <Button type="button" variant="secondary" size="sm" onClick={onRefreshPanelHealth}>
                {t("dashboardOverview.refreshPanelHealth")}
              </Button>
            ) : undefined
          }
        />
        {showFinance && allowTab("reseller_charge") ? (
          <Card>
            <CardContent className={cn("flex flex-wrap items-center justify-between gap-3 pt-6")}>
              <div>
                <p className="text-xs font-medium text-muted-foreground">{t("dashboardOverview.actorWalletLabel")}</p>
                <p className="text-2xl font-semibold tabular-nums">{formatNumber(actorBalance, isFa)}</p>
              </div>
              <Button type="button" variant="default" size="sm" onClick={() => onSelectTab("reseller_charge")}>
                {t("dashboardOverview.actorWalletTopUp")}
              </Button>
            </CardContent>
          </Card>
        ) : null}
        <Card>
          <CardContent className="pt-6">
            {rows.length === 0 ? (
              <p className="text-sm text-muted-foreground">—</p>
            ) : (
              <ul className="space-y-2">
                {rows.map(({ p, id, st, h }) => {
                  const label = String(
                    p.label ?? p.name ?? st?.label ?? `#${formatNumericString(String(id), isFa)}`
                  )
                  const urlRaw = String(p.panel_url ?? (p as { panelUrl?: unknown }).panelUrl ?? "")
                  const loc = truncateUrl(urlRaw)
                  const { httpOk, networkReachable } = resolvePanelHealthFlags(h)
                  const lat = h?.latencyMs
                  const urlEmpty = !urlRaw.trim()
                  const online = Boolean(h && !urlEmpty && networkReachable && httpOk)
                  return (
                    <li
                      key={id}
                      className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/80 px-3 py-2.5 text-sm"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="font-medium">{label}</p>
                        <p className="break-all font-mono text-xs text-muted-foreground">{loc}</p>
                      </div>
                      <div className={cn("flex flex-wrap items-center gap-2")}>
                        <span className="tabular-nums text-muted-foreground">
                          {lat != null ? `${formatNumber(lat, isFa)} ms` : "—"}
                        </span>
                        <Badge variant={online ? "secondary" : "destructive"}>
                          {online ? t("dashboardOverview.online") : t("dashboardOverview.offline")}
                        </Badge>
                      </div>
                    </li>
                  )
                })}
              </ul>
            )}
          </CardContent>
        </Card>
        <DataPagination
          meta={panelsPagination}
        onPageChange={onPanelsPageChange}
          onPerPageChange={onPanelsPerPageChange}
        />
      </DashPage>
    )
  }

  return (
    <DashPage className={"space-y-6"}>
      <DashboardPageHeader
        title={<h2 className="text-lg font-semibold">{t("dashboardOverview.title")}</h2>}
        description={
          <>
            <p className="text-sm text-muted-foreground">{t("dashboardOverview.subtitle")}</p>
            {overview?.stats?.stat_date ? (
              <p className="mt-1 text-xs text-muted-foreground">
                {t("dashboardOverview.statDate")}: {formatDateOnly(String(overview.stats.stat_date), isFa)}
              </p>
            ) : null}
            {resellerFocused && onStatsDayChange ? (
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <Label htmlFor="overview-stats-day" className="text-xs text-muted-foreground">
                  {t("dashboardOverview.statsDayLabel")}
                </Label>
                <DashSelect
                  id="overview-stats-day"
                  triggerClassName="h-8 w-[10rem]"
                  value={String(statsDayClamped)}
                  onValueChange={(v) => {
                    const d = Number(v)
                    if (Number.isFinite(d) && d >= 0 && d <= 7) onStatsDayChange(d)
                  }}
                  options={[0, 1, 2, 3, 4, 5, 6, 7].map((d) => ({
                    value: String(d),
                    label:
                      d === 0
                        ? t("dashboardOverview.statsDayToday")
                        : t("dashboardOverview.statsDayAgo", { days: formatNumber(d, isFa) }),
                  }))}
                />
              </div>
            ) : null}
          </>
        }
      />

      {!isReseller && !compactHealthOnly && overview?.economics ? (
        <>
          {(overview.economics.upcomingPayments?.length ?? 0) > 0 ? (
            <DashboardEconomicsPaymentAlert
              items={overview.economics.upcomingPayments ?? []}
        dashboardBaseUrl={dashboardBaseUrl}
              onDismissRefresh={onEconomicsRefresh}
            />
          ) : null}
          <DashboardEconomicsOverviewCard
            economics={overview.economics}
        dashboardBaseUrl={dashboardBaseUrl}
          />
        </>
      ) : null}

      {prependResellerFinance ? (
        <div className="space-y-4">
          {typeof actorBalance === "number" ? (
            <Card>
              <CardContent
                className={cn(
                  "flex flex-wrap items-center justify-between gap-3 pt-6"
                )}
              >
                <div>
                  <p className="text-xs font-medium text-muted-foreground">
                    {t("dashboardOverview.actorWalletLabel")}
                  </p>
                  <p className="text-2xl font-semibold tabular-nums">{formatNumber(actorBalance, isFa)}</p>
                </div>
                {allowTab("reseller_charge") ? (
                  <Button type="button" variant="default" size="sm" onClick={() => onSelectTab("reseller_charge")}>
                    {t("dashboardOverview.actorWalletTopUp")}
                  </Button>
                ) : null}
              </CardContent>
            </Card>
          ) : null}
        </div>
      ) : null}

      {prependResellerFinance && onOverviewMetricsWindowChange ? (
        <Card className="border-primary/20">
          <CardHeader className="pb-2">
            <div className={cn("flex flex-wrap items-start justify-between gap-3")}>
              <div className={cn("flex items-start gap-3")}>
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <TrendingUp className="size-5" aria-hidden />
                </div>
                <div className="space-y-1">
                  <CardTitle className="text-base">{t("dashboardOverview.perfTitle")}</CardTitle>
                  <CardDescription>
                    {t("dashboardOverview.perfSubtitle", { days: formatNumber(perfWindow, isFa) })}
                  </CardDescription>
                </div>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="overview-metrics-window">{t("dashboardOverview.perfWindowDays")}</Label>
                <DashSelect
                  id="overview-metrics-window"
                  triggerClassName="w-[8rem]"
                  value={String(perfWindow)}
                  onValueChange={(v) => {
                    const d = Number(v)
                    if ([7, 30, 90].includes(d)) onOverviewMetricsWindowChange(d)
                  }}
                  options={[
                    { value: "7", label: t("dashboardOverview.perfWindow7") },
                    { value: "30", label: t("dashboardOverview.perfWindow30") },
                    { value: "90", label: t("dashboardOverview.perfWindow90") },
                  ]}
                />
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <StatCard
                className="border-primary/15 bg-primary/[0.03]"
                title={t("dashboardOverview.perfSales")}
                value={num(perfMetrics?.sales_toman)}
                sub={t("dashboardOverview.perfSalesHint")}
              />
              <StatCard
                title={t("dashboardOverview.perfWholesale")}
                value={num(perfMetrics?.wholesale_toman)}
                sub={t("dashboardOverview.perfWholesaleHint")}
              />
              <StatCard
                title={t("dashboardOverview.perfMargin")}
                value={num(perfMetrics?.margin_est)}
                sub={t("dashboardOverview.perfMarginHint")}
              />
              <StatCard
                title={t("dashboardOverview.perfDownline")}
                value={num(perfMetrics?.downline_users)}
                sub={
                  [
                    t("dashboardOverview.perfDownlineLifetimeHint"),
                    num(perfMetrics?.active_services) > 0
                      ? t("dashboardOverview.perfActiveServices", {
                          count: formatNumber(num(perfMetrics?.active_services), isFa),
                        })
                      : null,
                  ]
                    .filter(Boolean)
                    .join(" · ")
                }
              />
              <StatCard
                title={t("dashboardOverview.perfReceipts")}
                value={num(perfMetrics?.receipts_toman)}
                sub={t("dashboardOverview.perfReceiptsHint")}
              />
            </div>
            {num(perfMetrics?.sales_count) > 0 ? (
              <p className="text-xs text-muted-foreground">
                {t("dashboardOverview.perfSalesCount", {
                  count: formatNumber(num(perfMetrics?.sales_count), isFa),
                })}
              </p>
            ) : null}
            <p className="text-xs text-muted-foreground">{t("dashboardOverview.perfMarginDisclaimer")}</p>
          </CardContent>
        </Card>
      ) : null}

      {host != null && !resellerFocused ? (
        <Card className="border-primary/20">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{t("dashboardOverview.hostThisServer")}</CardTitle>
            <CardDescription>
              {t("dashboardOverview.hostLoad")}: {loadLine}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <div className="flex justify-between text-xs text-muted-foreground">
                  <span>{t("dashboardOverview.hostMem")}</span>
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
                  <span>{t("dashboardOverview.hostDisk")}</span>
                  <span className="tabular-nums">
                    {host?.diskFreeBytes != null && host?.diskTotalBytes != null
                      ? `${t("dashboardOverview.diskFreeLabel")}: ${formatBytes(host.diskFreeBytes, isFa)} · ${formatBytes(host.diskTotalBytes, isFa)}`
                      : "—"}
                  </span>
                </div>
                <Progress value={diskPct ?? 0} className={diskPct == null ? "opacity-40" : ""} />
              </div>
            </div>
          </CardContent>
        </Card>
      ) : null}

      <Card className="border-primary/15">
        <CardHeader>
          <CardTitle className="text-base">{t("dashboardOverview.chartOnlineTitle")}</CardTitle>
          <CardDescription>{t("dashboardOverview.chartOnlineSubtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="h-[220px] w-full min-w-0 ps-0 pe-1">
          {chartRows.length === 0 ? (
            <p className="text-sm text-muted-foreground">—</p>
          ) : (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartRows} margin={{ top: 8, right: 8, left: isFa ? 8 : 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="fillOnline" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor={chartPrimary} stopOpacity={0.35} />
                    <stop offset="95%" stopColor={chartPrimary} stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                <XAxis dataKey="label" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
                <YAxis
                  width={40}
                  tick={{ fontSize: 11 }}
                  tickLine={false}
                  axisLine={false}
                  tickFormatter={(v) => formatNumber(Number(v), isFa)}
                />
                <RechartsTooltip
                  content={({ active, payload }) => {
                    if (!active || !payload?.length) return null
                    const row = payload[0]?.payload as OnlineDailyPoint & { label: string }
                    const val = num(row?.totalMaxOnline)
                    return (
                      <div className="rounded-md border bg-popover px-2 py-1.5 text-xs shadow-md">
                        <div className="text-muted-foreground">
                          {row?.date ? formatChartTooltipDate(String(row.date), isFa) : ""}
                        </div>
                        <div className="font-medium tabular-nums">{formatNumber(val, isFa)}</div>
                      </div>
                    )
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="totalMaxOnline"
                  stroke={chartPrimary}
                  fill="url(#fillOnline)"
                  strokeWidth={2}
                />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>

      {!resellerFocused ? (
      <section className="relative space-y-4 overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-b from-primary/[0.06] to-transparent p-4 sm:p-5">
        <div
          className={cn(
            "pointer-events-none absolute inset-y-0 w-1 bg-gradient-to-b from-primary/80 to-transparent",
            isFa ? "end-0" : "start-0"
          )}
        />
        <div className={cn("flex flex-wrap items-center gap-2")}>
          <div className="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <UsersRound className="size-5" aria-hidden />
          </div>
          <h3 className="text-base font-semibold tracking-tight">{t("sidebar.sections.users")}</h3>
        </div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            className="border-border/80 bg-card/90 shadow-sm"
            title={t("dashboardOverview.usersTotal")}
            value={num(u.users_total)}
            testId="dash-overview-stat-users"
        />
          <StatCard
            className="border-border/80 bg-card/90 shadow-sm"
            title={t("dashboardOverview.usersApproved")}
            value={num(u.users_approved)}
        />
          <StatCard
            className="border-border/80 bg-card/90 shadow-sm"
            title={t("dashboardOverview.usersPending")}
            value={num(u.users_pending)}
        />
          <StatCard
            className="border-border/80 bg-card/90 shadow-sm"
            title={t("dashboardOverview.usersToday")}
            value={num(u.users_today)}
        />
          {overviewPlatformEnabled(bot, "telegram") ? (
          <StatCard
            className="border-primary/15 bg-primary/[0.04]"
            title={t("dashboardOverview.usersTelegram")}
            value={num(u.users_with_telegram)}
        />
          ) : null}
          {overviewPlatformEnabled(bot, "bale") ? (
          <StatCard
            className="border-primary/15 bg-primary/[0.06]"
            title={t("dashboardOverview.usersBale")}
            value={num(u.users_with_bale)}
        />
          ) : null}
          <StatCard title={t("dashboardOverview.usersRejected")} value={num(u.users_rejected)}
        />
          <StatCard title={t("dashboardOverview.usersBlocked")} value={num(u.users_blocked)}
        />
          <StatCard title={t("dashboardOverview.servicesTotal")} value={num(u.services_total)}
        />
        </div>
      </section>
      ) : null}

      {!resellerFocused ? (
      <section className="grid gap-4 lg:grid-cols-2">
        <div
          className={cn(
            "relative overflow-hidden rounded-2xl border border-primary/15 bg-card p-5 shadow-sm"
          )}
        >
          <div
            className={cn(
              "pointer-events-none absolute inset-y-0 w-1 bg-gradient-to-b from-primary/80 to-transparent",
              isFa ? "end-0" : "start-0"
            )}
          />
          <div className={cn("flex items-start gap-3")}>
            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <Bot className="size-6" aria-hidden />
            </div>
            <div className="min-w-0 flex-1 space-y-2">
              <h3 className="text-sm font-semibold">{t("dashboardOverview.botCard")}</h3>
              <p className="text-sm font-medium">
                {bot.enabled ? t("dashboardOverview.botEnabled") : t("dashboardOverview.botDisabled")}
              </p>
              {overviewPlatformEnabled(bot, "telegram") ? (
              <p className="text-xs text-muted-foreground">
                {t("dashboardOverview.telegram")}: {String(bot.telegram_bot_username || "—")}
              </p>
              ) : null}
              {overviewPlatformEnabled(bot, "bale") ? (
              <p className="text-xs text-muted-foreground">
                {t("dashboardOverview.bale")}: {String(bot.bale_bot_username || "—")}
              </p>
              ) : null}
            </div>
          </div>
        </div>

        <div
          className={cn(
            "relative overflow-hidden rounded-2xl border border-primary/15 bg-card p-5 shadow-sm"
          )}
        >
          <div
            className={cn(
              "pointer-events-none absolute inset-y-0 w-1 bg-gradient-to-b from-primary/80 to-transparent",
              isFa ? "end-0" : "start-0"
            )}
          />
          <div className={cn("flex items-start gap-3")}>
            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <Server className="size-6" aria-hidden />
            </div>
            <div className="min-w-0 flex-1 space-y-2">
              <h3 className="text-sm font-semibold">{t("dashboardOverview.infraCard")}</h3>
              <ul className="space-y-1.5 text-sm text-muted-foreground">
                <li
                  data-testid="dash-overview-stat-panels"
                  className="flex flex-wrap justify-between gap-2 border-b border-border/60 pb-1.5"
                >
                  <span>{t("dashboardOverview.panelsCount")}</span>
                  <span className="tabular-nums font-medium text-foreground">
                    {formatNumber(num(counts.panels), isFa)}
                  </span>
                </li>
                <li className="flex flex-wrap justify-between gap-2">
                  <span>{t("sidebar.items.broadcast")}</span>
                  <span className="tabular-nums font-medium text-foreground">
                    {formatNumber(num(counts.broadcasts), isFa)}
                  </span>
                </li>
              </ul>
              <div className="pt-1">
                <QuickLink
                  tabKey="xui_panels"
                  label={t("sidebar.items.xui_panels")}
                  base={dashboardBaseUrl}
                  onSelectTab={onSelectTab}
                />
              </div>
            </div>
          </div>
        </div>
      </section>
      ) : null}

      {!resellerFocused ? (
      <Card className="overflow-hidden border-primary/20 shadow-md">
        <CardHeader className="border-b border-border/60 bg-primary/[0.03] pb-4">
          <div className={cn("flex flex-wrap items-start justify-between gap-3")}>
            <div className={cn("space-y-1")}>
              <CardTitle className="text-lg">{t("dashboardOverview.financeCard")}</CardTitle>
              <CardDescription className="max-w-2xl text-pretty">
                {t("dashboardOverview.financeCardHint")}
              </CardDescription>
            </div>
            <Radio className="size-8 shrink-0 text-primary" aria-hidden />
          </div>
        </CardHeader>
        <CardContent className="space-y-6 pt-6">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div
              className={cn(
                "rounded-xl border border-border/80 bg-gradient-to-b from-card to-muted/20 p-4 shadow-sm"
              )}
            >
              <Layers className={cn("mb-2 size-5 text-primary", isFa && "ms-auto")} aria-hidden />
              <p className="text-xs font-medium text-muted-foreground">{t("dashboardOverview.plansCount")}</p>
              <p className="mt-1 text-2xl font-semibold tabular-nums">{formatNumber(num(counts.plans), isFa)}</p>
            </div>
            <div
              className={cn(
                "rounded-xl border border-border/80 bg-gradient-to-b from-card to-muted/20 p-4 shadow-sm"
              )}
            >
              <Tags className={cn("mb-2 size-5 text-primary", isFa && "ms-auto")} aria-hidden />
              <p className="text-xs font-medium text-muted-foreground">{t("dashboardOverview.planCategories")}</p>
              <p className="mt-1 text-2xl font-semibold tabular-nums">
                {formatNumber(num(counts.planCategories), isFa)}
              </p>
            </div>
            <div
              className={cn(
                "rounded-xl border border-border/80 bg-gradient-to-b from-card to-muted/20 p-4 shadow-sm"
              )}
            >
              <CreditCard className={cn("mb-2 size-5 text-primary", isFa && "ms-auto")} aria-hidden />
              <p className="text-xs font-medium text-muted-foreground">{t("dashboardOverview.cardsCount")}</p>
              <p className="mt-1 text-2xl font-semibold tabular-nums">{formatNumber(num(counts.cards), isFa)}</p>
            </div>
            <div
              data-testid="dash-overview-stat-receipts"
              className={cn(
                "rounded-xl border border-primary/20 bg-gradient-to-b from-primary/[0.07] to-card p-4 shadow-sm"
              )}
            >
              <Receipt className={cn("mb-2 size-5 text-primary", isFa && "ms-auto")} aria-hidden />
              <p className="text-xs font-medium text-muted-foreground">{t("dashboardOverview.receiptsTotal")}</p>
              <p className="mt-1 text-2xl font-semibold tabular-nums">
                {formatNumber(receiptsTotalCount, isFa)}
              </p>
            </div>
            <div
              className={cn(
                "rounded-xl border border-border/80 bg-gradient-to-b from-card to-muted/20 p-4 shadow-sm"
              )}
            >
              <Percent className={cn("mb-2 size-5 text-primary", isFa && "ms-auto")} aria-hidden />
              <p className="text-xs font-medium text-muted-foreground">{t("dashboardOverview.discountCodes")}</p>
              <p className="mt-1 text-2xl font-semibold tabular-nums">
                {formatNumber(num(counts.discountCodes), isFa)}
              </p>
            </div>
          </div>

          {receiptRowsSorted.length > 0 && receiptBarTotal > 0 ? (
            <div className={cn("space-y-3")}>
              <p className="text-sm font-medium">{t("dashboardOverview.receiptsByStatus")}</p>
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
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
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
            </div>
          ) : null}

          <div className={cn("flex flex-wrap gap-2")}>
            {allowTab("plans") ? (
              <DashTabLink
                tabKey="plans"
                label={t("sidebar.items.plans")}
                base={dashboardBaseUrl}
                onSelectTab={onSelectTab}
                Icon={Layers}
              />
            ) : null}
            {allowTab("plan_cats") ? (
              <DashTabLink
                tabKey="plan_cats"
                label={t("sidebar.items.plan_cats")}
                base={dashboardBaseUrl}
                onSelectTab={onSelectTab}
                Icon={Tags}
              />
            ) : null}
            {allowTab("cards") ? (
              <DashTabLink
                tabKey="cards"
                label={t("sidebar.items.cards")}
                base={dashboardBaseUrl}
                onSelectTab={onSelectTab}
                Icon={CreditCard}
              />
            ) : null}
            {allowTab("receipts") ? (
              <DashTabLink
                tabKey="receipts"
                label={t("sidebar.items.receipts")}
                base={dashboardBaseUrl}
                onSelectTab={onSelectTab}
                Icon={Receipt}
              />
            ) : null}
            {allowTab("discounts") ? (
              <DashTabLink
                tabKey="discounts"
                label={t("sidebar.items.discounts")}
                base={dashboardBaseUrl}
                onSelectTab={onSelectTab}
                Icon={Percent}
              />
            ) : null}
          </div>
        </CardContent>
      </Card>
      ) : null}

      {!resellerFocused && onOpenUserDetail && onReceiptsFilterNavigate ? (
        <OverviewPreviewGrid
        isReseller={isReseller}
          allowTab={allowTab}
          recentUsers={recentUsers}
          recentReceipts={recentReceipts}
          pendingUsersPreview={pendingUsersPreview}
          recentResellers={recentResellers}
          recentBroadcasts={recentBroadcasts}
          onSelectTab={onSelectTab}
          onOpenUserDetail={onOpenUserDetail}
          onOpenResellerWorkspace={onOpenResellerWorkspace}
          onReceiptsFilterNavigate={onReceiptsFilterNavigate}
        />
      ) : null}

      <section className="space-y-4">
        <div
          className={cn(
            "flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/15 bg-primary/[0.03] px-4 py-3"
          )}
        >
          <div className={cn("flex items-center gap-2")}>
            <Radio className="size-5 text-primary" aria-hidden />
            <h3 className="text-base font-semibold">{t("dashboardOverview.panelCards")}</h3>
          </div>
          {onRefreshPanelHealth ? (
            <Button type="button" variant="secondary" size="sm" onClick={onRefreshPanelHealth}>
              {t("dashboardOverview.refreshPanelHealth")}
            </Button>
          ) : null}
        </div>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">—</p>
          ) : (
            rows.map(({ p, id, st, h }) => {
              const label = String(p.label ?? st?.label ?? `#${formatNumericString(String(id), isFa)}`)
              const url = String(p.panel_url ?? "")
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

              const httpLabel = h ? formatNumericString(String(h.httpStatus || 0), isFa) : t("dashboardOverview.unknown")

              const checkedShort = h?.checkedAt ? formatDateTime(h.checkedAt, isFa) : "—"

              return (
                <Tooltip key={id || label}>
                  <TooltipTrigger asChild>
                    <Card className={cn("transition-shadow hover:shadow-md", tone)}>
                      <CardHeader className="gap-2 pb-2">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                          <CardTitle className="text-base leading-snug">{label}</CardTitle>
                          <div className={cn("flex max-w-[min(100%,14rem)] flex-wrap gap-1", isFa && "justify-end")}>
                            <Badge variant={active ? "secondary" : "outline"} className="text-[10px]">
                              {active
                                ? t("dashboardOverview.badgeDbActive")
                                : t("dashboardOverview.badgeDbInactive")}
                            </Badge>
                            {hasHealth && networkReachable ? (
                              <Badge variant="outline" className="border-emerald-500/40 text-[10px] text-emerald-700 dark:text-emerald-400">
                                {t("dashboardOverview.badgeNetworkOk")}
                              </Badge>
                            ) : hasHealth && !urlEmpty ? (
                              <Badge variant="destructive" className="text-[10px]">
                                {t("dashboardOverview.badgeTransportDown")}
                              </Badge>
                            ) : null}
                            {httpOk ? (
                              <Badge variant="outline" className="text-[10px] text-muted-foreground">
                                {t("dashboardOverview.badgeHttpOk")}
                              </Badge>
                            ) : networkReachable && !urlEmpty ? (
                              <Badge variant="outline" className="border-amber-500/50 text-[10px] text-amber-800 dark:text-amber-300">
                                {t("dashboardOverview.badgeHttpNonStandard", { code: httpLabel })}
                              </Badge>
                            ) : null}
                          </div>
                        </div>
                        <CardDescription className="font-mono text-xs break-all">
                          {truncateUrl(url)}
                        </CardDescription>
                      </CardHeader>
                      <CardContent className="space-y-4 text-sm">
                        <div
                          className={cn(
                            "rounded-xl border border-border/80 bg-background/80 px-3 py-3"
                          )}
                        >
                          <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            {t("dashboardOverview.colLatency")}
                          </p>
                          <p className="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">
                            {h?.latencyMs != null ? formatNumber(h.latencyMs, isFa) : "—"}
                            <span className="ms-1 text-base font-normal text-muted-foreground">ms</span>
                          </p>
                          <p className="mt-1 text-[11px] text-muted-foreground">{t("dashboardOverview.colReachable")}</p>
                          <p className="tabular-nums font-mono text-sm text-foreground">HTTP {httpLabel}</p>
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                          <span>{t("dashboardOverview.colXrayActive")}</span>
                          <span className="tabular-nums font-medium text-foreground">
                            {formatNumber(xa, isFa)} / {formatNumber(xi, isFa)}
                          </span>
                        </div>
                        <div className="space-y-1">
                          <div className="flex justify-between text-xs text-muted-foreground">
                            <span>{t("dashboardOverview.xrayShare")}</span>
                            <span className="tabular-nums">{formatNumber(Math.round(xrayPct), isFa)}%</span>
                          </div>
                          <Progress value={xTotal > 0 ? xrayPct : 0} className={xTotal === 0 ? "opacity-40" : ""} />
                        </div>
                        <div className="flex flex-wrap justify-between gap-2 border-t border-border pt-2 text-xs text-muted-foreground">
                          <span>{t("dashboardOverview.colMaxOnline")}</span>
                          <span className="tabular-nums font-medium text-foreground">
                            {st?.max_online_day != null ? formatNumber(num(st.max_online_day), isFa) : "—"}
                          </span>
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                          {t("dashboardOverview.lastCheck")}: {checkedShort}
                        </p>
                      </CardContent>
                    </Card>
                  </TooltipTrigger>
                  <TooltipContent
                    className="max-w-xs text-xs leading-relaxed"
                  >
                    <p className="text-muted-foreground">{t("dashboardOverview.ttRttHint")}</p>
                    {networkReachable ? (
                      <p className="mt-2">{t("dashboardOverview.ttNetworkOk")}</p>
                    ) : null}
                    {httpOk ? (
                      <p className="mt-2">{t("dashboardOverview.ttHttpOk")}</p>
                    ) : networkReachable ? (
                      <>
                        <p className="mt-2">{t("dashboardOverview.ttHttpNonStandard")}</p>
                        {h?.authProbeUrl && (h.authProbeStatus ?? 0) > 0 ? (
                          <p className="mt-1 text-muted-foreground">
                            {t("dashboardOverview.ttAuthProbeOk", {
                              url: h.authProbeUrl,
                              code: formatNumericString(String(h.authProbeStatus ?? 0), isFa),
                            })}
                          </p>
                        ) : null}
                      </>
                    ) : (
                      <p className="mt-2">{t("dashboardOverview.ttHttpFail")}</p>
                    )}
                    <p className="mt-2">
                      {active ? t("dashboardOverview.ttDbActive") : t("dashboardOverview.ttDbInactive")}
                    </p>
                    {h?.error ? <p className="mt-2 text-destructive">{h.error}</p> : null}
                  </TooltipContent>
                </Tooltip>
              )
            })
          )}
        </div>
        <DataPagination
          meta={panelsPagination}
        onPageChange={onPanelsPageChange}
          onPerPageChange={onPanelsPerPageChange}
        />
      </section>

      <section className={cn("space-y-2")}>
        <h3 className="text-sm font-medium">{t("dashboardOverview.quickLinks")}</h3>
        <div className="flex flex-wrap gap-2">
          {allowTab("users") ? (
            <QuickLink tabKey="users" label={t("sidebar.items.users")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          ) : null}
          {allowTab("receipts") ? (
            <QuickLink tabKey="receipts" label={t("sidebar.items.receipts")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          ) : null}
          {allowTab("xui_panels") ? (
            <QuickLink tabKey="xui_panels" label={t("sidebar.items.xui_panels")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          ) : null}
          {allowTab("plans") ? (
            <QuickLink tabKey="plans" label={t("sidebar.items.plans")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          ) : null}
          {allowTab("bots") ? (
            <QuickLink tabKey="bots" label={t("sidebar.items.bots")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          ) : null}
          {allowTab("reseller_bots") ? (
            <QuickLink tabKey="reseller_bots" label={t("sidebar.items.reseller_bots")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          ) : null}
          {allowTab("monitoring") ? (
            <QuickLink tabKey="monitoring" label={t("sidebar.items.monitoring")} base={dashboardBaseUrl} onSelectTab={onSelectTab} />
          ) : null}
        </div>
      </section>
    </DashPage>
  )
}
