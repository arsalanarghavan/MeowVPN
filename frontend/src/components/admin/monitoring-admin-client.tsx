"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
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
import { resolvePanelHealthFlags } from "@/components/admin/overview-admin-client"
import { PanelServerStatusViz } from "@/components/panel-server-status-viz"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { useLiveMetricsSse, type LiveMetricsSsePayload } from "@/hooks/use-live-metrics-sse"
import { getAdminState } from "@/lib/dash-admin-mutate"
import { apiBase } from "@/lib/api"
import { useChartPrimaryColor } from "@/lib/chart-accent"
import {
  formatBytes,
  formatChartDayLabel,
  formatChartTooltipDate,
  formatDateTime,
  formatNumber,
  formatNumericString,
} from "@/lib/format-locale"

type DashRecord = Record<string, unknown>

type PanelHealth = {
  panelId: number
  ok: boolean
  httpOk?: boolean
  networkReachable?: boolean
  httpStatus: number
  latencyMs: number | null
  checkedAt: string
  error?: string
}

type LiveSnapshot = DashRecord & {
  panelId?: number
  ok?: boolean
  error?: string
  onlineNow?: number | null
  status?: DashRecord | null
  checkedAt?: string | number | null
}

type ExternalHostSnap = {
  hostId: number
  label?: string
  ok: boolean
  error?: string
  metrics?: Record<string, number | string> | null
  checkedAt?: string
}

type MonitorHostRow = { id: number; label: string; metricsUrl: string; bearerConfigured: boolean }

type PanelStatLine = {
  panel_id: number
  label?: string
  xray_active?: number
  max_online_day?: number
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function snapshotId(row: LiveSnapshot): number {
  return num(row.panelId)
}

function snapshotOnline(row: LiveSnapshot): number {
  return row.onlineNow == null ? 0 : num(row.onlineNow)
}

function normalizeCheckedAt(v: unknown): string | number | null {
  if (v == null) return null
  if (typeof v === "string" || typeof v === "number") return v
  return String(v)
}

function normalizeSnapshots(raw: unknown): LiveSnapshot[] {
  if (!Array.isArray(raw)) return []
  return raw
    .filter((row): row is LiveSnapshot => !!row && typeof row === "object")
    .map((row) => ({
      ...row,
      panelId: num(row.panelId ?? row.panel_id),
      ok: bool(row.ok),
      error: row.error != null ? String(row.error) : "",
      onlineNow: row.onlineNow == null ? null : num(row.onlineNow),
      checkedAt: normalizeCheckedAt(row.checkedAt ?? row.checked_at),
      status: row.status && typeof row.status === "object" ? (row.status as DashRecord) : null,
    }))
    .sort((a, b) => snapshotId(a) - snapshotId(b))
}

function parsePanelHealth(raw: unknown): PanelHealth[] {
  if (!Array.isArray(raw)) return []
  return raw
    .filter((row): row is DashRecord => !!row && typeof row === "object")
    .map((row) => ({
      panelId: num(row.panelId ?? row.panel_id),
      ok: bool(row.ok),
      httpOk: row.httpOk == null ? undefined : Boolean(row.httpOk),
      networkReachable: row.networkReachable == null ? undefined : Boolean(row.networkReachable),
      httpStatus: num(row.httpStatus ?? row.http_status),
      latencyMs: row.latencyMs == null && row.latency_ms == null ? null : num(row.latencyMs ?? row.latency_ms),
      checkedAt: String(row.checkedAt ?? row.checked_at ?? ""),
      error: row.error != null ? String(row.error) : undefined,
    }))
    .filter((h) => h.panelId > 0)
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

export function MonitoringAdminClient() {
  const t = useTranslations("monitoringPage")
  const tOverview = useTranslations("dashboardOverview")
  const locale = useLocale()
  const isFa = locale === "fa"
  const chartPrimary = useChartPrimaryColor()

  const [overview, setOverview] = useState<DashRecord>({})
  const [panels, setPanels] = useState<DashRecord[]>([])
  const [monitorHosts, setMonitorHosts] = useState<DashRecord[]>([])
  const [snapshots, setSnapshots] = useState<LiveSnapshot[]>([])
  const [loading, setLoading] = useState(true)
  const [healthBusy, setHealthBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const applyState = useCallback((data: Record<string, unknown>) => {
    const ov = data.overview && typeof data.overview === "object" ? (data.overview as DashRecord) : {}
    setOverview(ov)
    setMonitorHosts(Array.isArray(data.monitorHosts) ? (data.monitorHosts as DashRecord[]) : [])
    const panelRows = Array.isArray(data.panels)
      ? (data.panels as DashRecord[])
      : Array.isArray(ov.panels)
        ? (ov.panels as DashRecord[])
        : []
    setPanels(panelRows)
    const liveFromOverview = ov.livePanelSnapshots
    const liveTop = data.livePanelSnapshots
    if (Array.isArray(liveTop)) {
      setSnapshots(normalizeSnapshots(liveTop))
    } else if (Array.isArray(liveFromOverview)) {
      setSnapshots(normalizeSnapshots(liveFromOverview))
    }
  }, [])

  const load = useCallback(
    async (opts?: { refreshLivePanelMetrics?: boolean; refreshPanelHealth?: boolean }) => {
      setLoading(true)
      setError(null)
      try {
        const query: Record<string, string | number> = {}
        if (opts?.refreshLivePanelMetrics) query.refreshLivePanelMetrics = 1
        if (opts?.refreshPanelHealth) query.refreshPanelHealth = 1
        const data = await getAdminState("monitoring", query)
        applyState(data)
      } catch {
        setError(t("loadError"))
      } finally {
        setLoading(false)
      }
    },
    [applyState, t]
  )

  useEffect(() => {
    void load()
  }, [load])

  const refreshPanelHealth = useCallback(async () => {
    setHealthBusy(true)
    try {
      await load({ refreshPanelHealth: true })
    } finally {
      setHealthBusy(false)
    }
  }, [load])

  const refreshLive = useCallback(async () => {
    await load({ refreshLivePanelMetrics: true })
  }, [load])

  const applyLivePayload = useCallback((payload: LiveMetricsSsePayload) => {
    if (Array.isArray(payload.livePanelSnapshots)) {
      setSnapshots(normalizeSnapshots(payload.livePanelSnapshots))
    }
  }, [])

  const { connected } = useLiveMetricsSse({
    enabled: true,
    restBase: apiBase(),
    onPayload: applyLivePayload,
  })

  useEffect(() => {
    const intervalSec = 60
    const tick = () => {
      if (document.visibilityState !== "visible") return
      void load({ refreshLivePanelMetrics: true, refreshPanelHealth: true })
    }
    const id = window.setInterval(tick, intervalSec * 1000)
    const onVis = () => {
      if (document.visibilityState === "visible") tick()
    }
    document.addEventListener("visibilitychange", onVis)
    return () => {
      window.clearInterval(id)
      document.removeEventListener("visibilitychange", onVis)
    }
  }, [load])

  const panelHealth = useMemo(() => {
    const nested = overview.panelHealth
    return parsePanelHealth(nested)
  }, [overview.panelHealth])

  const healthById = useMemo(() => {
    const m = new Map<number, PanelHealth>()
    for (const h of panelHealth) m.set(h.panelId, h)
    return m
  }, [panelHealth])

  const stats = useMemo(() => {
    const raw = overview.stats
    if (!raw || typeof raw !== "object") return null
    return raw as { panels?: PanelStatLine[] }
  }, [overview.stats])

  const statsLineById = useMemo(() => {
    const m = new Map<number, PanelStatLine>()
    for (const row of stats?.panels ?? []) {
      m.set(num(row.panel_id), row)
    }
    return m
  }, [stats])

  const liveById = useMemo(() => {
    const m = new Map<number, LiveSnapshot>()
    for (const s of snapshots) {
      const id = snapshotId(s)
      if (id > 0) m.set(id, s)
    }
    return m
  }, [snapshots])

  const extSnaps = useMemo(() => {
    const raw = overview.externalHostSnapshots
    if (!Array.isArray(raw)) return [] as ExternalHostSnap[]
    return raw
      .filter((row): row is DashRecord => !!row && typeof row === "object")
      .map((row) => ({
        hostId: num(row.hostId ?? row.host_id),
        label: row.label != null ? String(row.label) : undefined,
        ok: bool(row.ok),
        error: row.error != null ? String(row.error) : undefined,
        metrics:
          row.metrics && typeof row.metrics === "object"
            ? (row.metrics as Record<string, number | string>)
            : null,
        checkedAt: row.checkedAt != null ? String(row.checkedAt) : undefined,
      }))
      .filter((x) => x.hostId > 0)
  }, [overview.externalHostSnapshots])

  const monitorHostRows = useMemo<MonitorHostRow[]>(
    () =>
      monitorHosts.map((row) => ({
        id: num(row.id),
        label: String(row.label ?? "").trim(),
        metricsUrl: String(row.metrics_url ?? "").trim(),
        bearerConfigured: String(row.bearer_token ?? "").trim().length > 0,
      })),
    [monitorHosts]
  )

  const extSnapById = useMemo(() => {
    const m = new Map<number, ExternalHostSnap>()
    for (const ex of extSnaps) m.set(ex.hostId, ex)
    return m
  }, [extSnaps])

  const extHostRows = useMemo<MonitorHostRow[]>(() => {
    const out = [...monitorHostRows]
    const seen = new Set(out.map((x) => x.id))
    for (const ex of extSnaps) {
      if (seen.has(ex.hostId)) continue
      out.push({
        id: ex.hostId,
        label: String(ex.label ?? "").trim(),
        metricsUrl: "",
        bearerConfigured: false,
      })
    }
    return out
  }, [monitorHostRows, extSnaps])

  const summary = useMemo(() => {
    const count = snapshots.length
    const healthy = snapshots.filter((row) => row.ok).length
    const failed = Math.max(0, count - healthy)
    const online = snapshots.reduce((sum, row) => sum + snapshotOnline(row), 0)
    return { count, healthy, failed, online }
  }, [snapshots])

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

  const barOnline = useMemo(() => {
    return snapshots
      .filter((s) => s.ok && s.onlineNow != null)
      .map((s) => {
        const st = statsLineById.get(snapshotId(s))
        return {
          name: st?.label ? String(st.label) : `#${snapshotId(s)}`,
          online: snapshotOnline(s),
        }
      })
  }, [snapshots, statsLineById])

  const hostMetrics =
    overview.host && typeof overview.host === "object" ? (overview.host as DashRecord) : null
  const memLimit = num(hostMetrics?.memoryLimitBytes)
  const memUse = num(hostMetrics?.memoryBytes)
  const memPct = memLimit > 0 ? clampPct((memUse / memLimit) * 100) : 0
  const diskTotal = num(hostMetrics?.diskTotalBytes)
  const diskFree = num(hostMetrics?.diskFreeBytes)
  const diskUsed = diskTotal > 0 ? diskTotal - diskFree : 0
  const diskPct = diskTotal > 0 ? clampPct((diskUsed / diskTotal) * 100) : 0
  const loadAvg = Array.isArray(hostMetrics?.loadAvg) ? (hostMetrics.loadAvg as unknown[]) : null

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={connected ? "default" : "destructive"}>
            {connected ? t("sseConnected") : t("sseDisconnected")}
          </Badge>
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={loading || healthBusy}
            onClick={() => void refreshPanelHealth()}
          >
            {tOverview("refreshPanelHealth")}
          </Button>
          <Button type="button" size="sm" disabled={loading} onClick={() => void refreshLive()}>
            {tOverview("refreshLiveMetrics")}
          </Button>
        </div>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("summarySnapshots")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(summary.count, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("summaryOnlineNow")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(summary.online, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("summaryHealthy")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(summary.healthy, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t("summaryFailed")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(summary.failed, isFa)}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      {hostMetrics ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("siteHost")}</CardTitle>
            <CardDescription>
              {hostMetrics.checkedAt ? formatDateTime(String(hostMetrics.checkedAt), isFa) : "—"}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div className="rounded-lg border border-border bg-card/50 p-3">
                <p className="text-xs text-muted-foreground">{tOverview("hostLoad")}</p>
                <p className="mt-1 font-mono text-sm tabular-nums">
                  {loadAvg && loadAvg.length >= 3
                    ? `${formatNumber(num(loadAvg[0]), isFa)} / ${formatNumber(num(loadAvg[1]), isFa)} / ${formatNumber(num(loadAvg[2]), isFa)}`
                    : "—"}
                </p>
              </div>
              <div className="rounded-lg border border-border bg-card/50 p-3">
                <p className="text-xs text-muted-foreground">{tOverview("hostMem")}</p>
                <p className="mt-1 text-sm tabular-nums">
                  {formatBytes(memUse, isFa)} / {memLimit > 0 ? formatBytes(memLimit, isFa) : "—"}
                </p>
                {memLimit > 0 ? <Progress className="mt-2 h-2" value={memPct} /> : null}
              </div>
              <div className="rounded-lg border border-border bg-card/50 p-3">
                <p className="text-xs text-muted-foreground">{tOverview("hostDisk")}</p>
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

      <div className="grid gap-4 xl:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tOverview("chartOnlineTitle")}</CardTitle>
            <CardDescription>{tOverview("chartOnlineSubtitle")}</CardDescription>
          </CardHeader>
          <CardContent className="h-56">
            {chartData.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t("chartNoAggregateData")}</p>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" />
                  <XAxis dataKey="day" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} width={36} tickFormatter={(v) => formatNumber(Number(v), isFa)} />
                  <RechartsTooltip
                    labelFormatter={(_, payload) =>
                      String((payload?.[0]?.payload as { tooltipDate?: string } | undefined)?.tooltipDate ?? "")
                    }
                  />
                  <Area
                    type="monotone"
                    dataKey="totalMaxOnline"
                    stroke={chartPrimary}
                    fill={chartPrimary}
                    fillOpacity={0.15}
                  />
                </AreaChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tOverview("colOnlineNow")}</CardTitle>
            <CardDescription>{t("panelLive")}</CardDescription>
          </CardHeader>
          <CardContent className="h-56">
            {barOnline.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t("snapshotEmpty")}</p>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={barOnline} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-border/60" />
                  <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} width={36} tickFormatter={(v) => formatNumber(Number(v), isFa)} />
                  <RechartsTooltip formatter={(value: number) => [formatNumber(value, isFa), tOverview("colOnlineNow")]} />
                  <Bar dataKey="online" fill={chartPrimary} radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("panelLive")}</CardTitle>
          <CardDescription>{tOverview("panelsTable")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {panels.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tOverview("unknown")}</p>
          ) : (
            panels.map((row) => {
              const pid = num(row.id)
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
                live?.ok && now != null && maxDay > 5 && now < Math.max(0, Math.floor(maxDay * 0.25))
              return (
                <div key={pid} className="rounded-lg border border-border/80 bg-card/40 p-3 text-sm">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="font-medium">{String(row.label ?? `#${pid}`)}</p>
                      <p className="break-all font-mono text-xs text-muted-foreground">
                        {truncateUrl(String(row.panel_url ?? ""))}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-1">
                      {networkReachable && httpOk ? (
                        <Badge variant="secondary">{tOverview("online")}</Badge>
                      ) : networkReachable ? (
                        <Badge variant="outline" className="border-amber-500/50 text-amber-800 dark:text-amber-300">
                          {tOverview("badgeHttpNonStandard", { code: httpLabel })}
                        </Badge>
                      ) : (
                        <Badge variant="destructive">{tOverview("offline")}</Badge>
                      )}
                      {bool(row.active) ? (
                        <Badge variant="outline">{tOverview("badgeDbActive")}</Badge>
                      ) : (
                        <Badge variant="outline">{tOverview("badgeDbInactive")}</Badge>
                      )}
                      {warnLat ? <Badge variant="destructive">{t("warnLatency")}</Badge> : null}
                      {warnDrop ? <Badge variant="destructive">{t("warnOnlineDrop")}</Badge> : null}
                    </div>
                  </div>
                  <div className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                      <span className="text-muted-foreground">{tOverview("colLatency")}: </span>
                      <span className="tabular-nums">{lat != null ? formatNumber(lat, isFa) : "—"}</span>
                    </div>
                    <div>
                      <span className="text-muted-foreground">{tOverview("colOnlineNow")}: </span>
                      <span className="tabular-nums">
                        {live?.ok && now != null
                          ? formatNumber(now, isFa)
                          : live?.error
                            ? `(${live.error})`
                            : "—"}
                      </span>
                    </div>
                    <div>
                      <span className="text-muted-foreground">{tOverview("colMaxOnline")}: </span>
                      <span className="tabular-nums">{formatNumber(maxDay, isFa)}</span>
                    </div>
                    <div>
                      <span className="text-muted-foreground">{tOverview("colXrayActive")}: </span>
                      <span className="tabular-nums">{formatNumber(st?.xray_active ?? 0, isFa)}</span>
                    </div>
                  </div>
                  {live?.ok && live.status && Object.keys(live.status).length > 0 ? (
                    <PanelServerStatusViz status={live.status as Record<string, number | string>} />
                  ) : null}
                </div>
              )
            })
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("externalHosts")}</CardTitle>
          <CardDescription>{t("extHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {extHostRows.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("externalEmpty")}</p>
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
                    <Badge variant="secondary">{t("badgeOk")}</Badge>
                  ) : ex ? (
                    <Badge variant="destructive">{ex.error || "—"}</Badge>
                  ) : (
                    <Badge variant="outline">{tOverview("unknown")}</Badge>
                  )}
                </div>
                <div className="mt-1 text-xs text-muted-foreground">
                  {hostRow.bearerConfigured ? t("bearerYes") : t("bearerNo")}
                  {ex?.checkedAt ? ` · ${formatDateTime(ex.checkedAt, isFa)}` : ""}
                </div>
                {ex?.metrics && Object.keys(ex.metrics).length > 0 ? (
                  <PanelServerStatusViz status={ex.metrics} hideTitle />
                ) : null}
              </div>
            )
          })}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("summarySnapshots")}</CardTitle>
          <CardDescription>{t("compactSubtitle")}</CardDescription>
        </CardHeader>
        <CardContent>
          {snapshots.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("snapshotEmpty")}</p>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("snapshotPanel")}</TableHead>
                    <TableHead>{t("snapshotOnlineNow")}</TableHead>
                    <TableHead>{t("snapshotStatus")}</TableHead>
                    <TableHead>{t("snapshotCheckedAt")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {snapshots.map((row, index) => (
                    <TableRow key={`${snapshotId(row) || index}-${String(row.checkedAt ?? index)}`}>
                      <TableCell className="tabular-nums" dir="ltr">
                        #{snapshotId(row) || "—"}
                      </TableCell>
                      <TableCell className="tabular-nums" dir="ltr">
                        {formatNumber(snapshotOnline(row), isFa)}
                      </TableCell>
                      <TableCell>
                        <Badge variant={row.ok ? "default" : "destructive"}>
                          {row.ok ? t("snapshotOk") : t("snapshotError")}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground">
                        {formatDateTime(row.checkedAt, isFa)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
