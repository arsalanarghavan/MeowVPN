"use client"

import { useMemo } from "react"
import { useLocale, useTranslations } from "next-intl"
import { PolarAngleAxis, RadialBar, RadialBarChart, ResponsiveContainer } from "recharts"

import { Badge } from "@/components/ui/badge"
import { Progress } from "@/components/ui/progress"
import { useChartPrimaryColor } from "@/lib/chart-accent"
import {
  formatBytes,
  formatNumber,
  formatNumericString,
  formatUptimeSeconds,
} from "@/lib/format-locale"
import {
  clampPct,
  hasAnyStructuredMetric,
  parsePanelLiveStatus,
} from "@/lib/panel-live-status-metrics"
import { cn } from "@/lib/utils"

type Props = {
  status: Record<string, number | string> | null | undefined
  /** Hide the title line when nested under a card that already has a heading. */
  hideTitle?: boolean
  className?: string
}

function ResourceRow({
  label,
  used,
  total,
}: {
  label: string
  used: number | null
  total: number | null
}) {
  const locale = useLocale()
  const isFa = locale === "fa"
  const pct = clampPct(used, total)
  const line =
    used != null && total != null && total > 0
      ? `${formatBytes(used, isFa)} / ${formatBytes(total, isFa)}`
      : used != null
        ? formatBytes(used, isFa)
        : "—"
  return (
    <div className="space-y-1">
      <div className="flex items-baseline justify-between gap-2 text-xs">
        <span className="font-medium text-muted-foreground">{label}</span>
        <span className="tabular-nums text-[11px] text-foreground/90">{line}</span>
      </div>
      {total != null && total > 0 ? <Progress className="h-2" value={pct} /> : null}
    </div>
  )
}

export function PanelServerStatusViz({ status, hideTitle = false, className }: Props) {
  const t = useTranslations("monitoringPage")
  const locale = useLocale()
  const isFa = locale === "fa"
  const chartPrimary = useChartPrimaryColor()
  const parsed = useMemo(() => parsePanelLiveStatus(status), [status])

  const show =
    hasAnyStructuredMetric(parsed) ||
    (status && typeof status === "object" && Object.keys(status).length > 0)
  if (!show) return null

  const cpuRaw = parsed.cpuPercentRaw
  const cpuGauge = parsed.cpuPercent ?? 0
  const cpuAnomaly = cpuRaw != null && (cpuRaw > 100 || cpuRaw < 0)
  const gaugeChartData = [{ name: "cpu", value: cpuGauge, fill: chartPrimary }]

  const kpiItems: { label: string; value: string }[] = []
  if (parsed.uptimeSeconds != null) {
    kpiItems.push({ label: t("metricUptime"), value: formatUptimeSeconds(parsed.uptimeSeconds, isFa) })
  }
  if (parsed.tcpCount != null) {
    kpiItems.push({ label: t("metricTcp"), value: formatNumber(parsed.tcpCount, isFa) })
  }
  if (parsed.cpuCores != null) {
    kpiItems.push({ label: t("metricCores"), value: formatNumber(parsed.cpuCores, isFa) })
  }
  if (parsed.logicalProcessors != null) {
    kpiItems.push({ label: t("metricLogical"), value: formatNumber(parsed.logicalProcessors, isFa) })
  }
  if (parsed.cpuSpeedMhz != null) {
    kpiItems.push({
      label: t("metricCpuMhz"),
      value: `${formatNumber(parsed.cpuSpeedMhz, isFa)} MHz`,
    })
  }

  const remainder = parsed.remaining.slice(0, 24)

  return (
    <div className={cn("mt-2 rounded border border-dashed border-border/80 p-3", className)}>
      {hideTitle ? null : <p className="mb-2 text-xs font-medium text-muted-foreground">{t("statusSummary")}</p>}

      <div className="grid gap-4 lg:grid-cols-[minmax(0,150px)_1fr]">
        <div className="flex min-h-[112px] flex-col items-center justify-start gap-1">
          {cpuRaw != null ? (
            <>
              <div className="h-[100px] w-full min-w-[120px] max-w-[160px]">
                <ResponsiveContainer width="100%" height="100%">
                  <RadialBarChart
                    data={gaugeChartData}
                    startAngle={180}
                    endAngle={0}
                    innerRadius="68%"
                    outerRadius="100%"
                    barSize={10}
                    margin={{ top: 0, right: 0, bottom: 0, left: 0 }}
                  >
                    <PolarAngleAxis type="number" domain={[0, 100]} angleAxisId={0} tick={false} />
                    <RadialBar
                      dataKey="value"
                      cornerRadius={4}
                      background={{ fill: "hsl(var(--muted))" }}
                      isAnimationActive={false}
                    />
                  </RadialBarChart>
                </ResponsiveContainer>
              </div>
              <div className="flex flex-wrap items-center justify-center gap-1">
                <span className="text-xs text-muted-foreground">{t("metricCpu")}</span>
                <span className="font-mono text-sm font-medium tabular-nums">
                  {formatNumber(cpuRaw, isFa)}
                  <span className="text-muted-foreground">%</span>
                </span>
                {cpuAnomaly ? (
                  <Badge variant="outline" className="text-[10px]">
                    {t("cpuOutOfRange")}
                  </Badge>
                ) : null}
              </div>
            </>
          ) : (
            <div className="flex flex-1 flex-col items-center justify-center gap-1 text-center text-xs text-muted-foreground">
              <span>{t("metricCpu")}</span>
              <span>—</span>
            </div>
          )}
        </div>

        <div className="min-w-0 space-y-3">
          <ResourceRow label={t("metricMem")} used={parsed.memUsed} total={parsed.memTotal} />
          <ResourceRow label={t("metricDisk")} used={parsed.diskUsed} total={parsed.diskTotal} />
          {parsed.swapTotal != null && parsed.swapTotal > 0 ? (
            <ResourceRow label={t("metricSwap")} used={parsed.swapUsed} total={parsed.swapTotal} />
          ) : null}

          {kpiItems.length > 0 ? (
            <div
              className={cn(
                "grid gap-2 border-t border-border/60 pt-3 sm:grid-cols-2",
                kpiItems.length >= 4 && "lg:grid-cols-2 xl:grid-cols-3"
              )}
            >
              {kpiItems.map((row) => (
                <div key={row.label} className="rounded-md border border-border/50 bg-card/30 px-2 py-1.5">
                  <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{row.label}</p>
                  <p className="mt-0.5 text-sm tabular-nums">{row.value}</p>
                </div>
              ))}
            </div>
          ) : null}
        </div>
      </div>

      {remainder.length > 0 ? (
        <details className="mt-3 rounded border border-border/60 bg-muted/20 p-2">
          <summary className="cursor-pointer text-xs font-medium text-muted-foreground">{t("rawDetails")}</summary>
          <div className="mt-2 grid max-h-48 gap-1 overflow-y-auto font-mono text-[11px] sm:grid-cols-2">
            {remainder.map(([k, v]) => (
              <div key={k} className="flex justify-between gap-2">
                <span className="truncate text-muted-foreground">{k}</span>
                <span className="shrink-0 tabular-nums">{formatNumericString(v, isFa)}</span>
              </div>
            ))}
          </div>
        </details>
      ) : null}
    </div>
  )
}
