"use client"

import { useEffect, useMemo } from "react"
import { useTranslation } from "react-i18next"
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from "recharts"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import {
  resolvePanelHealthFlags,
  type OverviewPayload,
  type PanelHealth,
  type StatsPayload,
} from "@/components/dashboard-overview"
import { PanelServerStatusViz } from "@/components/panel-server-status-viz"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashPage } from "@/components/dash-page"
import {
  formatBytes,
  formatChartDayLabel,
  formatChartTooltipDate,
  formatDateTime,
  formatNumber,
  formatNumericString,
} from "@/lib/format-locale"
import { useChartPrimaryColor } from "@/lib/chart-accent"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"

type PanelStatLine = NonNullable<StatsPayload["panels"]>[number]

type LivePanelSnap = {
  panelId: number
  ok: boolean
  error?: string
  onlineNow: number | null
  status?: Record<string, number | string> | null
  checkedAt?: string
}

type ExternalHostSnap = {
  hostId: number
  label?: string
  ok: boolean
  error?: string
  metrics?: Record<string, number | string> | null
  checkedAt?: string
}

type OnlineDailyPoint = { date: string; totalMaxOnline: number }
type MonitorHostRow = { id: number; label: string; metricsUrl: string; bearerConfigured: boolean }

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function clampPct(p: number): number {
  if (!Number.isFinite(p)) return 0
  return Math.min(100, Math.max(0, p))
}

function truncateUrl(url: string, max = 40): string {
  const u = url.trim()
  if (!u) return "—"
  if (u.length <= max) return u
  return `${u.slice(0, max - 1)}…`
}

export function DashboardMonitoring({
  overview,
  panels,
  panelsPagination,
  monitorHosts,
  onRefreshPanelHealth,
  onRefreshLivePanelMetrics,
  compactHealthOnly = false,
  isReseller = false,
}: {
  overview: OverviewPayload | undefined
  panels: Record<string, unknown>[]
  panelsPagination: PaginationMeta | null
  monitorHosts: Record<string, unknown>[]
onRefreshPanelHealth?: () => void
  onRefreshLivePanelMetrics?: () => void
  compactHealthOnly?: boolean
  isReseller?: boolean
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const chartPrimary = useChartPrimaryColor()

  useEffect(() => {
    if (compactHealthOnly) return
    const intervalSec = 60
    const tick = () => {
      if (document.visibilityState !== "visible") return
      onRefreshLivePanelMetrics?.()
      onRefreshPanelHealth?.()
    }
    tick()
    const id = window.setInterval(tick, intervalSec * 1000)
    const onVis = () => {
      if (document.visibilityState === "visible") tick()
    }
    document.addEventListener("visibilitychange", onVis)
    return () => {
      window.clearInterval(id)
      document.removeEventListener("visibilitychange", onVis)
    }
  }, [compactHealthOnly, onRefreshLivePanelMetrics, onRefreshPanelHealth])

  const host = overview?.host
  const series: OnlineDailyPoint[] = overview?.onlineDailySeries ?? []
  const panelHealth: PanelHealth[] = overview?.panelHealth ?? []
  const stats: StatsPayload | undefined = overview?.stats
  const liveSnaps = (overview?.livePanelSnapshots ?? []) as LivePanelSnap[]
  const extSnaps = (overview?.externalHostSnapshots ?? []) as ExternalHostSnap[]
  const monitorHostRows = useMemo<MonitorHostRow[]>(
    () =>
      (monitorHosts ?? []).map((row) => ({
        id: num(row.id),
        label: String(row.label ?? "").trim(),
        metricsUrl: String(row.metrics_url ?? "").trim(),
        bearerConfigured: String(row.bearer_token ?? "").trim().length > 0,
      })),
    [monitorHosts]
  )

  const healthById = useMemo(() => {
    const m = new Map<number, PanelHealth>()
    for (const h of panelHealth) {
      m.set(h.panelId, h)
    }
    return m
  }, [panelHealth])

  const statsLineById = useMemo(() => {
    const m = new Map<number, PanelStatLine>()
    const lines = stats?.panels ?? []
    for (const row of lines) {
      m.set(row.panel_id, row)
    }
    return m
  }, [stats])

  const liveById = useMemo(() => {
    const m = new Map<number, LivePanelSnap>()
    for (const s of liveSnaps) {
      if (s && typeof s.panelId === "number") m.set(s.panelId, s)
    }
    return m
  }, [liveSnaps])

  const chartData = useMemo(
    () =>
      series.map((d) => ({
        ...d,
        day: formatChartDayLabel(d.date, isFa),
        tooltipDate: formatChartTooltipDate(d.date, isFa),
      })),
    [series, isFa]
  )

  const barOnline = useMemo(() => {
    const rows: { name: string; online: number; pid: number }[] = []
    for (const s of liveSnaps) {
      if (!s || !s.ok || s.onlineNow == null) continue
      const st = statsLineById.get(s.panelId)
      rows.push({
        pid: s.panelId,
        name: st?.label ? String(st.label) : `#${s.panelId}`,
        online: s.onlineNow,
      })
    }
    return rows
  }, [liveSnaps, statsLineById])
  const extSnapById = useMemo(() => {
    const m = new Map<number, ExternalHostSnap>()
    for (const ex of extSnaps) {
      if (ex && typeof ex.hostId === "number") m.set(ex.hostId, ex)
    }
    return m
  }, [extSnaps])
  const extHostRows = useMemo<MonitorHostRow[]>(() => {
    const out = [...monitorHostRows]
    const seen = new Set(out.map((x) => x.id))
    for (const ex of extSnaps) {
      if (!ex || seen.has(ex.hostId)) continue
      out.push({
        id: ex.hostId,
        label: String(ex.label ?? "").trim(),
        metricsUrl: "",
        bearerConfigured: false,
      })
    }
    return out
  }, [monitorHostRows, extSnaps])

  const memLimit = num(host?.memoryLimitBytes)
  const memUse = num(host?.memoryBytes)
  const memPct = memLimit > 0 ? clampPct((memUse / memLimit) * 100) : 0
  const diskTotal = num(host?.diskTotalBytes)
  const diskFree = num(host?.diskFreeBytes)
  const diskUsed = diskTotal > 0 ? diskTotal - diskFree : 0
  const diskPct = diskTotal > 0 ? clampPct((diskUsed / diskTotal) * 100) : 0

  if (compactHealthOnly) {
    return (
      <DashPage className={"space-y-4"}>
        <DashboardPageHeader
          title={t("monitoringPage.compactTitle")}
          description={t("monitoringPage.compactSubtitle")}
          actions={
            onRefreshPanelHealth ? (
              <Button type="button" variant="secondary" size="sm" onClick={() => onRefreshPanelHealth()}>
                {t("dashboardOverview.refreshPanelHealth")}
              </Button>
            ) : null
          }
        />
        <Card>
          <CardContent className="pt-6">
            {panels.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t("dashboardOverview.unknown")}</p>
            ) : (
              <ul className="space-y-2">
                {panels.map((row) => {
                  const pid = Number(row.id)
                  const h = healthById.get(pid)
                  const { httpOk, networkReachable } = resolvePanelHealthFlags(h)
                  const urlRaw = String(row.panel_url ?? "")
                  const urlEmpty = !urlRaw.trim()
                  const lat = h?.latencyMs ?? null
                  const online = Boolean(h && !urlEmpty && networkReachable && httpOk)
                  const label = String(row.label ?? row.name ?? `#${pid}`)
                  return (
                    <li
                      key={pid}
                      className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/80 px-3 py-2.5 text-sm"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="font-medium">{label}</p>
                        <p className="break-all font-mono text-xs text-muted-foreground">{truncateUrl(urlRaw)}</p>
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
      </DashPage>
    )
  }

  return (
    <DashPage>
      <DashboardPageHeader
        title={t("monitoringPage.title")}
        description={t("monitoringPage.subtitle")}
        actions={
          <>
            {onRefreshPanelHealth ? (
              <Button type="button" variant="outline" size="sm" onClick={() => onRefreshPanelHealth()}>
                {t("dashboardOverview.refreshPanelHealth")}
              </Button>
            ) : null}
            {onRefreshLivePanelMetrics ? (
              <Button type="button" variant="default" size="sm" onClick={() => onRefreshLivePanelMetrics()}>
                {t("dashboardOverview.refreshLiveMetrics")}
              </Button>
            ) : null}
          </>
        }
      />

      {isReseller ? (
        <p className="text-sm text-muted-foreground">{t("monitoringPage.scopedResellerHint")}</p>
      ) : null}

      {host != null ? (
        <Card>
          <CardHeader>
            <CardTitle>{t("monitoringPage.siteHost")}</CardTitle>
            <CardDescription>
              {host?.checkedAt ? formatDateTime(host.checkedAt, isFa) : "—"}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div className="rounded-lg border border-border bg-card/50 p-3">
                <p className="text-xs text-muted-foreground">{t("dashboardOverview.hostLoad")}</p>
                <p className="mt-1 font-mono text-sm tabular-nums">
                  {host?.loadAvg?.length === 3
                    ? `${formatNumber(host.loadAvg[0], isFa)} / ${formatNumber(host.loadAvg[1], isFa)} / ${formatNumber(host.loadAvg[2], isFa)}`
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-card/50 p-3">
                <p className="text-xs text-muted-foreground">{t("dashboardOverview.hostMem")}</p>
                <p className="mt-1 text-sm tabular-nums">
                  {formatBytes(memUse, isFa)} / {memLimit > 0 ? formatBytes(memLimit, isFa) : "—"}
                </p>
                {memLimit > 0 ? <Progress className="mt-2 h-2" value={memPct} /> : null}
              </div>
              <div className="rounded-lg border border-border bg-card/50 p-3">
                <p className="text-xs text-muted-foreground">{t("dashboardOverview.hostDisk")}</p>
                <p className="mt-1 text-sm tabular-nums">
                  {diskTotal > 0
                    ? `${formatBytes(diskUsed, isFa)} / ${formatBytes(diskTotal, isFa)}`
                    : "—"}
                </p>
                {diskTotal > 0 ? <Progress className="mt-2 h-2" value={diskPct} /> : null}
              </div>
            </div>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("dashboardOverview.chartOnlineTitle")}</CardTitle>
          <CardDescription>{t("dashboardOverview.chartOnlineSubtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="h-[260px] w-full min-w-0">
          {chartData.length > 0 ? (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="monFillOnline" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor={chartPrimary} stopOpacity={0.35} />
                    <stop offset="95%" stopColor={chartPrimary} stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                <XAxis dataKey="day" tick={{ fontSize: 11 }} />
                <YAxis
                  width={44}
                  tick={{ fontSize: 11 }}
                  allowDecimals={false}
                  tickFormatter={(v) => formatNumber(Number(v), isFa)}
                />
                <RechartsTooltip
                  content={({ active, payload }) => {
                    if (!active || !payload?.length) return null
                    const row = payload[0].payload as OnlineDailyPoint & {
                      tooltipDate?: string
                    }
                    return (
                      <div className="rounded-md border bg-background/95 px-2 py-1.5 text-xs shadow-sm">
                        <div className="font-medium">{row.tooltipDate ?? row.date}</div>
                        <div className="text-muted-foreground">
                          {formatNumber(row.totalMaxOnline ?? 0, isFa)}
                        </div>
                      </div>
                    )
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="totalMaxOnline"
                  name={t("dashboardOverview.colMaxOnline")}
                  stroke={chartPrimary}
                  fill="url(#monFillOnline)"
                  strokeWidth={2}
                  dot={false}
                  isAnimationActive={false}
                />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
              {t("monitoringPage.chartNoAggregateData")}
            </div>
          )}
        </CardContent>
      </Card>

      {barOnline.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle>{t("dashboardOverview.colOnlineNow")}</CardTitle>
            <CardDescription>{t("monitoringPage.panelLive")}</CardDescription>
          </CardHeader>
          <CardContent className="h-[280px] w-full min-w-0">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={barOnline} margin={{ top: 8, right: 8, left: 0, bottom: 40 }}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                <XAxis dataKey="name" tick={{ fontSize: 10 }} interval={0} angle={isFa ? 0 : -25} textAnchor={isFa ? "middle" : "end"} height={50} />
                <YAxis
                  width={36}
                  tick={{ fontSize: 11 }}
                  allowDecimals={false}
                  tickFormatter={(v) => formatNumber(Number(v), isFa)}
                />
                <RechartsTooltip
                  formatter={(value: number) => [formatNumber(value, isFa), t("dashboardOverview.colOnlineNow")]}
                />
                <Bar dataKey="online" fill={chartPrimary} radius={[4, 4, 0, 0]} maxBarSize={48} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("monitoringPage.panelLive")}</CardTitle>
          <CardDescription>{t("dashboardOverview.panelsTable")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {panels.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("dashboardOverview.unknown")}</p>
          ) : (
            panels.map((row) => {
              const pid = Number(row.id)
              const h = healthById.get(pid)
              const st = statsLineById.get(pid)
              const live = liveById.get(pid)
              const { httpOk, networkReachable } = resolvePanelHealthFlags(h)
              const httpLabel = h ? formatNumericString(String(h.httpStatus || 0), isFa) : "—"
              const lat = h?.latencyMs ?? null
              const warnLat = lat != null && lat > 2500
              const maxDay = st?.max_online_day ?? 0
              const now = live?.onlineNow
              const warnDrop =
                live?.ok &&
                now != null &&
                maxDay > 5 &&
                now < Math.max(0, Math.floor(maxDay * 0.25))
              return (
                <div
                  key={pid}
                  className="rounded-lg border border-border/80 bg-card/40 p-3 text-sm"
                >
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="font-medium">{String(row.label ?? `#${pid}`)}</p>
                      <p className="break-all font-mono text-xs text-muted-foreground">
                        {truncateUrl(String(row.panel_url ?? ""))}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-1">
                      {networkReachable && httpOk ? (
                        <Badge variant="secondary">{t("dashboardOverview.online")}</Badge>
                      ) : networkReachable ? (
                        <Badge variant="outline" className="border-amber-500/50 text-amber-800 dark:text-amber-300">
                          {t("dashboardOverview.badgeHttpNonStandard", { code: httpLabel })}
                        </Badge>
                      ) : (
                        <Badge variant="destructive">{t("dashboardOverview.offline")}</Badge>
                      )}
                      {Number(row.active) ? (
                        <Badge variant="outline">{t("dashboardOverview.badgeDbActive")}</Badge>
                      ) : (
                        <Badge variant="outline">{t("dashboardOverview.badgeDbInactive")}</Badge>
                      )}
                      {warnLat ? (
                        <Badge variant="destructive">{t("monitoringPage.warnLatency")}</Badge>
                      ) : null}
                      {warnDrop ? (
                        <Badge variant="destructive">{t("monitoringPage.warnOnlineDrop")}</Badge>
                      ) : null}
                    </div>
                  </div>
                  <div className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                      <span className="text-muted-foreground">{t("dashboardOverview.colLatency")}: </span>
                      <span className="tabular-nums">{lat != null ? formatNumber(lat, isFa) : "—"}</span>
                    </div>
                    <div>
                      <span className="text-muted-foreground">{t("dashboardOverview.colOnlineNow")}: </span>
                      <span className="tabular-nums">
                        {live?.ok && now != null ? formatNumber(now, isFa) : live?.error ? `(${live.error})` : "—"}
                      </span>
                    </div>
                    <div>
                      <span className="text-muted-foreground">{t("dashboardOverview.colMaxOnline")}: </span>
                      <span className="tabular-nums">{formatNumber(maxDay, isFa)}</span>
                    </div>
                    <div>
                      <span className="text-muted-foreground">{t("dashboardOverview.colXrayActive")}: </span>
                      <span className="tabular-nums">{formatNumber(st?.xray_active ?? 0, isFa)}</span>
                    </div>
                  </div>
                  {live?.ok && live.status && Object.keys(live.status).length > 0 ? (
                    <PanelServerStatusViz status={live.status}
        />
                  ) : null}
                </div>
              )
            })
          )}
          {panelsPagination && panelsPagination.total > panelsPagination.perPage ? (
            <p className="text-xs text-muted-foreground">
              {t("monitoringPage.panelsPaginationHint", {
                total: formatNumber(panelsPagination.total, isFa),
              })}
            </p>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("monitoringPage.externalHosts")}</CardTitle>
          <CardDescription>{t("monitoringPage.extHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {extHostRows.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("monitoringPage.externalEmpty")}</p>
          ) : null}
          {extHostRows.map((hostRow) => {
            const ex = extSnapById.get(hostRow.id)
            return (
              <div key={hostRow.id} className="rounded-lg border border-border/80 p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="min-w-0">
                    <p className="font-medium">{hostRow.label || ex?.label || `Host #${hostRow.id}`}</p>
                    <p className="break-all font-mono text-xs text-muted-foreground">
                      {hostRow.metricsUrl ? truncateUrl(hostRow.metricsUrl) : "—"}
                    </p>
                  </div>
                  {ex?.ok ? (
                    <Badge variant="secondary">{t("monitoringPage.badgeOk")}</Badge>
                  ) : ex ? (
                    <Badge variant="destructive">{ex.error || "—"}</Badge>
                  ) : (
                    <Badge variant="outline">{t("dashboardOverview.unknown")}</Badge>
                  )}
                </div>
                <div className="mt-1 text-xs text-muted-foreground">
                  {hostRow.bearerConfigured ? t("monitoringPage.bearerYes") : t("monitoringPage.bearerNo")}
                  {ex?.checkedAt ? ` · ${formatDateTime(ex.checkedAt, isFa)}` : ""}
                </div>
                {ex?.metrics && Object.keys(ex.metrics).length > 0 ? (
                  <PanelServerStatusViz status={ex.metrics}
        hideTitle />
                ) : null}
              </div>
            )
          })}
        </CardContent>
      </Card>
    </DashPage>
  )
}
