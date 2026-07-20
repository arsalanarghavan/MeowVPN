import { useCallback, useEffect, useMemo, useState } from "react"
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts"
import { formatBytes, rangeLabel, t, type UsageRange, USAGE_RANGES } from "@/components/portal/lib"
import type { TrafficChartSlice } from "@/components/portal/lib/traffic-stats"
import type { UsageChartResponse, UsagePoint } from "@/components/portal/types"

type Props = {
  usageEndpoint?: string
  authQs?: string
  serviceId?: number
  refreshKey?: number
  onRefreshed?: () => void
  snapshot?: TrafficChartSlice | null
}

function buildUrl(endpoint: string, authQs: string, range: UsageRange, serviceId?: number): string {
  const sep = endpoint.includes("?") ? "&" : "?"
  const sid = serviceId && serviceId > 0 ? `&service_id=${serviceId}` : ""
  const qs = authQs ? `${authQs}&` : ""
  return `${endpoint}${sep}${qs}range=${encodeURIComponent(range)}${sid}`
}

function UsageSnapshotSummary({ snapshot }: { snapshot: TrafficChartSlice }) {
  const used = Math.max(0, snapshot.used ?? 0)
  const total = Math.max(0, snapshot.total ?? 0)
  const down = Math.max(0, snapshot.down ?? 0)
  const up = Math.max(0, snapshot.up ?? 0)
  const pct = total > 0 ? Math.min(100, (used / total) * 100) : 0

  return (
    <div className="usage-snapshot-summary">
      {total > 0 ? (
        <div className="usage-bar-wrap">
          <div className="usage-bar-label">
            <span className="ltr">{formatBytes(used)}</span>
            <span className="muted ltr">/ {formatBytes(total)}</span>
          </div>
          <div className="usage-bar">
            <div className="usage-bar-fill" style={{ width: `${pct}%` }} />
          </div>
        </div>
      ) : null}
      <div className="usage-snapshot-rows">
        {down > 0 ? (
          <div className="usage-snapshot-row">
            <span className="muted">{t("downloadTraffic")}</span>
            <strong className="ltr">{formatBytes(down)}</strong>
          </div>
        ) : null}
        {up > 0 ? (
          <div className="usage-snapshot-row">
            <span className="muted">{t("upload")}</span>
            <strong className="ltr">{formatBytes(up)}</strong>
          </div>
        ) : null}
        <div className="usage-snapshot-row">
          <span className="muted">{t("usedTraffic")}</span>
          <strong className="ltr">{formatBytes(used)}</strong>
        </div>
      </div>
    </div>
  )
}

export function UsageChart({
  usageEndpoint,
  authQs,
  serviceId,
  refreshKey = 0,
  onRefreshed,
  snapshot,
}: Props) {
  const [range, setRange] = useState<UsageRange>("7d")
  const [points, setPoints] = useState<UsagePoint[]>([])
  const [totalInRange, setTotalInRange] = useState(0)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(false)

  const snapshotUsed = Math.max(0, snapshot?.used ?? 0)

  const fetchData = useCallback(async () => {
    if (!usageEndpoint || !authQs) {
      setPoints([])
      setTotalInRange(0)
      return
    }
    setLoading(true)
    setError(false)
    try {
      const url = buildUrl(usageEndpoint, authQs, range, serviceId)
      const res = await fetch(url, { credentials: "same-origin" })
      if (!res.ok) throw new Error("fetch failed")
      const data = (await res.json()) as UsageChartResponse
      setPoints(Array.isArray(data.points) ? data.points : [])
      setTotalInRange(typeof data.total_in_range === "number" ? data.total_in_range : 0)
      onRefreshed?.()
    } catch {
      setError(true)
      setPoints([])
      setTotalInRange(0)
    } finally {
      setLoading(false)
    }
  }, [usageEndpoint, authQs, range, serviceId, onRefreshed])

  useEffect(() => {
    void fetchData()
  }, [fetchData, refreshKey])

  const chartData = useMemo(
    () =>
      points.map((p) => ({
        name: p.label || p.t,
        value: p.value,
      })),
    [points]
  )

  const showSnapshotFallback =
    !loading && !error && chartData.length === 0 && snapshotUsed > 0 && snapshot != null

  return (
    <section className="card usage-chart-card">
      <div className="usage-chart-head">
        <h2 className="card-title">{t("trafficStats")}</h2>
        {totalInRange > 0 ? (
          <span className="usage-chart-rate ltr">{formatBytes(totalInRange)}</span>
        ) : null}
      </div>

      <div className="range-chips" role="tablist">
        {USAGE_RANGES.map((r) => (
          <button
            key={r}
            type="button"
            role="tab"
            className={range === r ? "range-chip active" : "range-chip"}
            onClick={() => setRange(r)}
            aria-selected={range === r}
          >
            {rangeLabel(r)}
          </button>
        ))}
      </div>

      {loading ? <p className="muted chart-empty">{t("loading")}</p> : null}
      {!loading && error ? <p className="muted chart-empty">{t("chartError")}</p> : null}
      {!loading && !error && chartData.length === 0 && !showSnapshotFallback ? (
        <p className="muted chart-empty">{t("chartNoData")}</p>
      ) : null}

      {showSnapshotFallback ? <UsageSnapshotSummary snapshot={snapshot} /> : null}

      {!loading && !error && chartData.length > 0 ? (
        <div className="chart-wrap" dir="ltr">
          <ResponsiveContainer width="100%" height={220}>
            <AreaChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <defs>
                <linearGradient id="usageGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="var(--accent-blue)" stopOpacity={0.35} />
                  <stop offset="100%" stopColor="var(--accent-blue)" stopOpacity={0.02} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
              <XAxis dataKey="name" tick={{ fill: "var(--muted)", fontSize: 10 }} interval="preserveStartEnd" />
              <YAxis
                tickFormatter={(v) => formatBytes(Number(v))}
                tick={{ fill: "var(--muted)", fontSize: 10 }}
                width={64}
              />
              <Tooltip formatter={(v) => formatBytes(Number(v ?? 0))} />
              <Area
                type="monotone"
                dataKey="value"
                stroke="var(--accent-blue)"
                fill="url(#usageGrad)"
                strokeWidth={2}
              />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      ) : null}
    </section>
  )
}
