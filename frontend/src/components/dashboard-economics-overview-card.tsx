"use client"

import Link from "next/link"
import { useLocale, useTranslations } from "next-intl"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { formatNumber } from "@/lib/format-locale"
import { buildDashboardTabUrl } from "@/lib/dash-tab"
import { cn } from "@/lib/utils"

export type EconomicsOverviewPayload = {
  window_days?: number
  site?: {
    sales_volume_gb?: number
    revenue_est?: number
    receipts_approved_sum?: number
    cost_monthly_total?: number
    profit_est?: number
  }
  panels?: Array<{
    panel_id: number
    label: string
    sales_volume_gb?: number
    revenue_est?: number
    receipts_approved_sum?: number
    cost_monthly_total?: number
    profit_est?: number
    profit_margin_pct?: number | null
  }>
}

export function DashboardEconomicsOverviewCard({
  economics,
  dashboardBaseUrl,
}: {
  economics: EconomicsOverviewPayload | null | undefined
  dashboardBaseUrl: string
}) {
  const t = useTranslations("economicsOverview")
  const locale = useLocale()
  const isFa = locale === "fa"
  if (!economics?.site) return null

  const site = economics.site
  const panels = economics.panels ?? []
  const unitUrl = buildDashboardTabUrl(dashboardBaseUrl, "unit_economics")

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-2">
        <div>
          <CardTitle className="text-base">{t("title")}</CardTitle>
          <CardDescription>{t("subtitle", { days: economics.window_days ?? 30 })}</CardDescription>
        </div>
        <Button render={<Link href={unitUrl} />} type="button" variant="outline" size="sm">
          {t("openCalculator")}
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Stat label={t("siteRevenueEst")} value={site.revenue_est} suffix={t("currency")} isFa={isFa} />
          <Stat label={t("siteReceiptsSum")} value={site.receipts_approved_sum} suffix={t("currency")} isFa={isFa} />
          <Stat label={t("siteCost")} value={site.cost_monthly_total} suffix={t("currency")} isFa={isFa} />
          <Stat
            label={t("siteProfit")}
            value={site.profit_est}
            suffix={t("currency")}
            isFa={isFa}
            destructive={Number(site.profit_est) < 0}
          />
        </div>
        {panels.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[40rem] border-collapse text-sm">
              <thead>
                <tr className="border-b text-muted-foreground">
                  <th className="py-2 text-start">{t("colPanel")}</th>
                  <th className="py-2 text-end">{t("colVolumeGb")}</th>
                  <th className="py-2 text-end">{t("colRevenue")}</th>
                  <th className="py-2 text-end">{t("colReceipts")}</th>
                  <th className="py-2 text-end">{t("colCost")}</th>
                  <th className="py-2 text-end">{t("colProfit")}</th>
                </tr>
              </thead>
              <tbody>
                {panels.map((p) => (
                  <tr key={p.panel_id} className="border-b border-border/60">
                    <td className="py-2">{p.label || `#${p.panel_id}`}</td>
                    <td className="py-2 text-end tabular-nums">{formatNumber(p.sales_volume_gb ?? 0, isFa)}</td>
                    <td className="py-2 text-end tabular-nums">{formatNumber(p.revenue_est ?? 0, isFa)}</td>
                    <td className="py-2 text-end tabular-nums">{formatNumber(p.receipts_approved_sum ?? 0, isFa)}</td>
                    <td className="py-2 text-end tabular-nums">{formatNumber(p.cost_monthly_total ?? 0, isFa)}</td>
                    <td
                      className={cn(
                        "py-2 text-end font-medium tabular-nums",
                        Number(p.profit_est) < 0 && "text-destructive"
                      )}
                    >
                      {formatNumber(p.profit_est ?? 0, isFa)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </CardContent>
    </Card>
  )
}

function Stat({
  label,
  value,
  suffix,
  isFa,
  destructive,
}: {
  label: string
  value?: number
  suffix: string
  isFa: boolean
  destructive?: boolean
}) {
  return (
    <div className="rounded-md border bg-muted/30 px-3 py-2">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className={cn("text-lg font-semibold tabular-nums", destructive && "text-destructive")}>
        {formatNumber(Number(value) || 0, isFa)} {suffix}
      </p>
    </div>
  )
}
