"use client"

import { useMemo } from "react"
import { useTranslation } from "react-i18next"

import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { Badge } from "@/components/ui/badge"

const RESELLER_PANELS_TABLE_COLS = ["55%", "20%", "15%"]
import { DashPage } from "@/components/dash-page"
import { Card, CardContent } from "@/components/ui/card"
import { formatNumber } from "@/lib/format-locale"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { useDashLocale } from "@/lib/dash-locale-context"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function panelAllowed(row: Record<string, unknown> | undefined): boolean {
  if (!row) return false
  const acc = row.panel_access === true || row.panel_access === 1 || row.panel_access === "1"
  const price = Number(String(row.price_per_gb ?? "").replace(/,/g, ""))
  return acc || (Number.isFinite(price) && price > 0)
}

export function DashboardResellerPanelsAdmin({
  panels,
  resellerPanelPricesMap,
}: {
  panels: DashRecord[]
  resellerPanelPricesMap: Record<string, Array<Record<string, unknown>> | undefined>
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`resellerPanelsAdmin.${k}`, opts)

  const rows = useMemo(() => {
    const accessCount = new Map<number, number>()
    for (const list of Object.values(resellerPanelPricesMap)) {
      if (!Array.isArray(list)) continue
      for (const row of list) {
        if (!panelAllowed(row)) continue
        const pid = num(row.panel_id)
        if (pid < 1) continue
        accessCount.set(pid, (accessCount.get(pid) ?? 0) + 1)
      }
    }
    return panels
      .map((p) => {
        const id = num(p.id)
        return {
          id,
          label: String(p.label ?? p.name ?? `#${id}`),
          active: p.active === true || p.active === 1 || p.active === "1",
          resellerCount: accessCount.get(id) ?? 0,
        }
      })
      .sort((a, b) => a.label.localeCompare(b.label))
  }, [panels, resellerPanelPricesMap])

  return (
    <DashPage data-testid="dash-reseller-panels-tab">
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />
      <Card>
        <CardContent className="pt-6">
          <DashTableShell
        minWidth="28rem" colWidths={RESELLER_PANELS_TABLE_COLS}>
            <thead>
              <tr className="bg-muted/40">
                <DashTh>{tp("colPanel")}</DashTh>
                <DashTh>{tp("colStatus")}</DashTh>
                <DashTh>{tp("colResellers")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <DashTd colSpan={3} className="p-4 text-center text-muted-foreground">
                    {tp("empty")}
                  </DashTd>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.id}>
                    <DashTd className="truncate font-medium">{row.label}</DashTd>
                    <DashTd>
                      <Badge variant={row.active ? "default" : "secondary"}>
                        {row.active ? tp("statusActive") : tp("statusInactive")}
                      </Badge>
                    </DashTd>
                    <DashTd className="tabular-nums">{formatNumber(row.resellerCount, isFa)}</DashTd>
                  </tr>
                ))
              )}
            </tbody>
          </DashTableShell>
        </CardContent>
      </Card>
      <p className="text-xs text-muted-foreground">{tp("hint")}</p>
    </DashPage>
  )
}
