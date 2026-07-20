"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import { AlertTriangle } from "lucide-react"

import { Button } from "@/components/ui/button"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashPage } from "@/components/dash-page"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import {
  DashboardPanelEconomicsSheet,
  type PanelEconomicsEntry,
} from "@/components/dashboard-panel-economics-sheet"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import {
  allActiveLinesFromMap,
  calculatePanelEconomicsWithShared,
  calculateUnitEconomics,
  panelLinesFromMap,
  sharedLinesFromMap,
  type UnitEconomicsResult,
} from "@/lib/unit-economics-calc"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

type SalesVolumePayload = {
  total_gb?: number
  by_panel?: Record<string, number>
  window_days?: number
  receipt_stats?: { pending_count?: number; pending_gb_estimate?: number }
}

function parseUnitEconomics(v: unknown): UnitEconomicsResult | null {
  if (!v || typeof v !== "object") return null
  const r = v as UnitEconomicsResult
  if (!r.metrics) return null
  return r
}

function parseSalesVolume(v: unknown): SalesVolumePayload | null {
  if (!v || typeof v !== "object") return null
  const s = v as Record<string, unknown>
  if (!("total_gb" in s) && !("by_panel" in s)) return null
  return s as SalesVolumePayload
}

function formatMoney(value: number, suffix: string, isFa: boolean): string {
  return `${formatNumber(value, isFa)} ${suffix}`
}

function formatPct(value: number | null, isFa: boolean): string {
  if (value == null || !Number.isFinite(value)) return "—"
  return `${formatNumber(value, isFa)}%`
}

function KpiGrid({
  result,
  currency,
  tp,
  lossMaking,
}: {
  result: UnitEconomicsResult
  currency: string
  tp: (k: string) => string
  lossMaking: boolean
}) {
  const { isFa } = useDashLocale()
  const m = result.metrics
  return (
    <>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <StatCard
          label={tp("kpiTotalFixedMonthly")}
          value={formatMoney(m.total_fixed_monthly_costs, currency, isFa)}
        />
        <StatCard
          label={tp("kpiCostPerGb")}
          value={formatMoney(m.cost_per_gb, currency, isFa)}
          destructive={lossMaking}
        />
        <StatCard
          label={tp("kpiSellingPrice")}
          value={formatMoney(result.inputs.selling_price_per_gb, currency, isFa)}
        />
        <StatCard
          label={tp("kpiProfitPerGb")}
          value={formatMoney(m.profit_per_gb, currency, isFa)}
          destructive={lossMaking}
        />
        <StatCard
          label={tp("kpiMargin")}
          value={formatPct(m.profit_margin_percentage, isFa)}
          destructive={lossMaking}
        />
        <StatCard
          label={tp("kpiVariablePerGb")}
          value={formatMoney(m.total_variable_cost_per_gb, currency, isFa)}
        />
      </div>
      <Card className="border-primary/30 bg-primary/5">
        <CardHeader className="pb-2">
          <CardDescription>{tp("kpiMonthlyProfitHint")}</CardDescription>
          <CardTitle
            className={cn("text-3xl tabular-nums", lossMaking && "text-destructive")}
          >
            {formatMoney(m.total_net_profit_monthly, currency, isFa)}
          </CardTitle>
        </CardHeader>
      </Card>
    </>
  )
}

export function DashboardUnitEconomicsAdmin({
  unitEconomics,
  panelEconomicsMap,
  panels,
  dashboardBaseUrl,
  onSelectTab,
  onMutateSuccess,
}: {
  unitEconomics: unknown
  panelEconomicsMap?: Record<string, PanelEconomicsEntry>
  panels: DashRecord[]
dashboardBaseUrl: string
  onSelectTab?: (tab: string) => void
  onMutateSuccess?: () => void
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string) => t(`unitEconomicsAdmin.${k}`)
  const ts = (k: string) => t(`sharedEconomics.${k}`)
  const currency = tp("currencySuffix")

  const initialGlobal = useMemo(() => {
    const u = parseUnitEconomics(unitEconomics)
    const raw = unitEconomics as Record<string, unknown> | null
    const inputs = (raw?.inputs ?? u?.inputs) as Record<string, unknown> | undefined
    return {
      total_sold_volume_gb: String(inputs?.total_sold_volume_gb ?? ""),
      selling_price_per_gb: String(inputs?.selling_price_per_gb ?? ""),
      volume_mode: String(inputs?.volume_mode ?? "auto_sales"),
      volume_window_days: String(inputs?.volume_window_days ?? 30),
    }
  }, [unitEconomics])

  const salesVolume = useMemo(
    () =>
      parseSalesVolume(
        (unitEconomics as Record<string, unknown> | null)?.salesVolume
      ),
    [unitEconomics]
  )

  const [globalForm, setGlobalForm] = useState(initialGlobal)
  const [localMap, setLocalMap] = useState<Record<string, PanelEconomicsEntry>>(
    panelEconomicsMap ?? {}
  )
  const [selectedPanelId, setSelectedPanelId] = useState<string>("0")
  const [sharedSheetOpen, setSharedSheetOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setGlobalForm(initialGlobal)
  }, [initialGlobal])

  useEffect(() => {
    if (panelEconomicsMap) setLocalMap(panelEconomicsMap)
  }, [panelEconomicsMap])

  const isAutoVolume = globalForm.volume_mode === "auto_sales"

  const siteVolumeGb = useMemo(() => {
    if (isAutoVolume) {
      return num(salesVolume?.total_gb)
    }
    return num(globalForm.total_sold_volume_gb)
  }, [isAutoVolume, salesVolume, globalForm.total_sold_volume_gb])

  const globalConfig = useMemo(
    () => ({
      total_sold_volume_gb: siteVolumeGb,
      effective_volume_gb: siteVolumeGb,
      selling_price_per_gb: num(globalForm.selling_price_per_gb),
      volume_mode: globalForm.volume_mode,
      volume_window_days: Math.max(1, num(globalForm.volume_window_days) || 30),
      sales_volume_gb_30d: num(salesVolume?.total_gb),
    }),
    [globalForm, siteVolumeGb, salesVolume]
  )

  const allLines = useMemo(() => allActiveLinesFromMap(localMap), [localMap])
  const sharedLines = useMemo(() => sharedLinesFromMap(localMap), [localMap])

  const sharedOnlyResult = useMemo(
    () => calculateUnitEconomics(sharedLines, globalConfig, 0),
    [sharedLines, globalConfig]
  )

  const siteResult = useMemo(
    () => calculateUnitEconomics(allLines, globalConfig, siteVolumeGb),
    [allLines, globalConfig, siteVolumeGb]
  )

  const panelVolumeGb = useMemo(() => {
    const pid = Number(selectedPanelId)
    if (!pid) return 0
    if (isAutoVolume) {
      const fromMap = num(localMap[String(pid)]?.sales_volume_gb_30d)
      if (fromMap > 0) return fromMap
      return num(salesVolume?.by_panel?.[String(pid)] ?? salesVolume?.by_panel?.[pid])
    }
    return num(globalForm.total_sold_volume_gb)
  }, [selectedPanelId, isAutoVolume, localMap, salesVolume, globalForm.total_sold_volume_gb])

  const panelResult = useMemo(() => {
    const pid = Number(selectedPanelId)
    if (!pid) return null
    const panelLines = panelLinesFromMap(localMap, pid)
    const cfg = {
      ...globalConfig,
      effective_volume_gb: panelVolumeGb,
      sales_volume_gb_30d: panelVolumeGb,
    }
    if (sharedLines.length > 0) {
      return calculatePanelEconomicsWithShared(
        panelLines,
        sharedLines,
        cfg,
        panelVolumeGb,
        siteVolumeGb
      )
    }
    return calculateUnitEconomics(panelLines, cfg, panelVolumeGb)
  }, [localMap, sharedLines, globalConfig, selectedPanelId, panelVolumeGb, siteVolumeGb])

  const warnings = siteResult.warnings
  const lossMaking = warnings.includes("loss_making_price")
  const volumeRequired = warnings.includes("volume_required")

  const normalizedLines = useMemo(() => {
    const pid = Number(selectedPanelId)
    if (!pid) return siteResult.inputs.lines
    return panelResult?.inputs.lines ?? []
  }, [selectedPanelId, siteResult, panelResult])

  const onSaveGlobal = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("unit_economics_config_save", {
        total_sold_volume_gb: num(globalForm.total_sold_volume_gb),
        selling_price_per_gb: num(globalForm.selling_price_per_gb),
        volume_mode: globalForm.volume_mode,
        volume_window_days: Math.max(1, num(globalForm.volume_window_days) || 30),
      })
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      if (res.panelEconomicsMap && typeof res.panelEconomicsMap === "object") {
        setLocalMap(res.panelEconomicsMap as Record<string, PanelEconomicsEntry>)
      }
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [globalForm, onMutateSuccess, tp])

  const openPanelCosts = () => {
    const pid = Number(selectedPanelId)
    if (pid < 1) return
    const base = dashboardBaseUrl.replace(/\/$/, "")
    window.location.href = `${base}/xui_panels/?panel_costs=${pid}`
    onSelectTab?.("xui_panels")
  }

  return (
    <DashPage className={"w-full space-y-6"} data-testid="dash-unit-economics-tab">
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />

      {volumeRequired ? (
        <div
          role="alert"
          className="mb-4 flex gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-950 dark:text-amber-100"
        >
          <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
          <span>{tp("warnVolumeRequired")}</span>
        </div>
      ) : null}

      {lossMaking ? (
        <div
          role="alert"
          className="mb-4 flex gap-2 rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
          <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
          <span>{tp("warnLossMaking")}</span>
        </div>
      ) : null}

      <section className="mb-8 space-y-4">
        <h2 className="text-lg font-semibold">{tp("siteWideTitle")}</h2>
        <p className="text-sm text-muted-foreground">{tp("liveCalcHint")}</p>
        <KpiGrid
          result={siteResult}
        currency={currency}
          tp={tp}
          lossMaking={lossMaking}
        />
      </section>

      <section className="mb-8 space-y-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold">{ts("sectionTitle")}</h2>
            <p className="text-sm text-muted-foreground">{ts("sectionDesc")}</p>
          </div>
          <Button type="button" variant="outline" onClick={() => setSharedSheetOpen(true)}>
            {ts("editShared")}
          </Button>
        </div>
        <KpiGrid
          result={sharedOnlyResult}
        currency={currency}
          tp={tp}
          lossMaking={sharedOnlyResult.warnings.includes("loss_making_price")}
        />
      </section>

      <section className="mb-8 space-y-4">
        <div className="flex flex-wrap items-end gap-4">
          <div className="min-w-[12rem] flex-1 space-y-1">
            <Label>{tp("selectPanel")}</Label>
            <DashSelect
              value={selectedPanelId}
              onValueChange={setSelectedPanelId}
              placeholder={tp("selectPanelPlaceholder")}
              options={[
                { value: "0", label: tp("allPanelsAggregate") },
                ...panels.map((p) => {
                  const id = String(num(p.id))
                  return { value: id, label: String(p.label ?? id) }
                }),
              ]}
            />
          </div>
          {Number(selectedPanelId) > 0 ? (
            <Button type="button" variant="outline" onClick={openPanelCosts}>
              {tp("editPanelCosts")}
            </Button>
          ) : null}
        </div>

        {Number(selectedPanelId) > 0 && panelResult ? (
          <>
            {sharedLines.length > 0 ? (
              <p className="text-sm text-muted-foreground">{tp("sharedAllocHint")}</p>
            ) : null}
            <KpiGrid
              result={panelResult}
        currency={currency}
              tp={tp}
              lossMaking={panelResult.warnings.includes("loss_making_price")}
            />
          </>
        ) : null}

        {Number(selectedPanelId) > 0 && normalizedLines.length > 0 ? (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{tp("normalizedLinesTitle")}</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="w-full min-w-[32rem] border-collapse text-sm">
                <thead>
                  <tr className="border-b text-muted-foreground">
                    <th className="py-2 text-start">{tp("colLabel")}</th>
                    <th className="py-2 text-start">{tp("colCategory")}</th>
                    <th className="py-2 text-start">{tp("colCycle")}</th>
                    <th className="py-2 text-end">{tp("colMonthly")}</th>
                    <th className="py-2 text-end">{tp("colPerGb")}</th>
                  </tr>
                </thead>
                <tbody>
                  {normalizedLines.map((row, i) => (
                    <tr key={i} className="border-b border-border/60">
                      <td className="py-2">{String(row.label ?? "")}</td>
                      <td className="py-2">{String(row.category ?? "")}</td>
                      <td className="py-2">{String(row.billing_cycle ?? "")}</td>
                      <td className="py-2 text-end tabular-nums">
                        {formatNumber(num(row.monthly_cost), isFa)}
                      </td>
                      <td className="py-2 text-end tabular-nums">
                        {num(row.per_gb_cost) > 0
                          ? formatNumber(num(row.per_gb_cost), isFa)
                          : "—"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>
        ) : null}
      </section>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("globalInputsTitle")}</CardTitle>
          <CardDescription>{tp("globalInputsDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>{tp("volumeSource")}</Label>
            <DashSelect
              value={globalForm.volume_mode}
              onValueChange={(v) => setGlobalForm((f) => ({ ...f, volume_mode: v }))}
              options={[
                { value: "auto_sales", label: tp("volumeModeAuto") },
                { value: "manual", label: tp("volumeModeManual") },
              ]}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1">
              <Label htmlFor="window">{tp("volumeWindowDays")}</Label>
              <Input
                id="window"
                type="number"
                min={1}
                max={365}
                value={globalForm.volume_window_days}
                onChange={(e) =>
                  setGlobalForm((f) => ({ ...f, volume_window_days: e.target.value }))
                }
              />
            </div>
            <div className="space-y-1">
              <Label htmlFor="selling">{tp("sellingPricePerGb")}</Label>
              <Input
                id="selling"
                inputMode="decimal"
                value={globalForm.selling_price_per_gb}
                onChange={(e) =>
                  setGlobalForm((f) => ({ ...f, selling_price_per_gb: e.target.value }))
                }
              />
            </div>
          </div>
          {isAutoVolume ? (
            <div className="space-y-1 rounded-md border bg-muted/30 px-3 py-2">
              <p className="text-sm font-medium">{tp("salesVolumeAutoTotal")}</p>
              <p className="text-2xl font-semibold tabular-nums">
                {formatNumber(siteVolumeGb, isFa)} GB
              </p>
              {salesVolume?.receipt_stats &&
              (num(salesVolume.receipt_stats.pending_count) > 0 ||
                num(salesVolume.receipt_stats.pending_gb_estimate) > 0) ? (
                <p className="text-xs text-muted-foreground">
                  {t("unitEconomicsAdmin.receiptPendingHint", {
                    count: num(salesVolume.receipt_stats.pending_count),
                    gb: formatNumber(
                      num(salesVolume.receipt_stats.pending_gb_estimate),
                      isFa
                    ),
                  })}
                </p>
              ) : null}
            </div>
          ) : (
            <div className="space-y-1">
              <Label htmlFor="volume">{tp("totalVolumeGb")}</Label>
              <Input
                id="volume"
                inputMode="decimal"
                value={globalForm.total_sold_volume_gb}
                onChange={(e) =>
                  setGlobalForm((f) => ({ ...f, total_sold_volume_gb: e.target.value }))
                }
              />
            </div>
          )}
          {isAutoVolume && panels.length > 0 ? (
            <div className="space-y-2">
              <p className="text-sm font-medium">{tp("salesVolumeByPanel")}</p>
              <ul className="space-y-1 text-sm">
                {panels.map((p) => {
                  const id = num(p.id)
                  const gb = num(
                    localMap[String(id)]?.sales_volume_gb_30d ??
                      salesVolume?.by_panel?.[String(id)] ??
                      salesVolume?.by_panel?.[id]
                  )
                  return (
                    <li key={id} className="flex justify-between gap-2 tabular-nums">
                      <span>{String(p.label ?? id)}</span>
                      <span>{formatNumber(gb, isFa)} GB</span>
                    </li>
                  )
                })}
                {num(salesVolume?.by_panel?.["0"] ?? salesVolume?.by_panel?.[0]) > 0 ? (
                  <li className="flex justify-between gap-2 tabular-nums text-muted-foreground">
                    <span>{tp("panelUnassigned")}</span>
                    <span>
                      {formatNumber(
                        num(salesVolume?.by_panel?.["0"] ?? salesVolume?.by_panel?.[0]),
                        isFa
                      )}{" "}
                      GB
                    </span>
                  </li>
                ) : null}
              </ul>
            </div>
          ) : null}
          {error ? (
            <p className="text-sm text-destructive" role="alert">
              {error}
            </p>
          ) : null}
          <Button type="button" disabled={saving} onClick={() => void onSaveGlobal()}>
            {tp("saveGlobal")}
          </Button>
        </CardContent>
      </Card>
      <DashboardPanelEconomicsSheet
        open={sharedSheetOpen}
        onOpenChange={setSharedSheetOpen}
        panelId={0}
        panelLabel={ts("sheetTitle")}
        entry={localMap["0"]}
        globalConfig={globalConfig}
        onSaved={({ panelId, entry }) => {
          setLocalMap((m) => ({ ...m, [String(panelId)]: entry }))
          onMutateSuccess?.()
        }}
      />
    </DashPage>
  )
}

function StatCard({
  label,
  value,
  destructive = false,
}: {
  label: string
  value: string
  destructive?: boolean
}) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardDescription>{label}</CardDescription>
        <CardTitle
          className={cn("text-xl tabular-nums", destructive && "text-destructive")}
        >
          {value}
        </CardTitle>
      </CardHeader>
    </Card>
  )
}
