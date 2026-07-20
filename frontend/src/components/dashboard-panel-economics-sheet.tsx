"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import { AlertTriangle, Plus, Trash2 } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import {
  Sheet,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { Textarea } from "@/components/ui/textarea"
import { DashboardDatePicker } from "@/components/dashboard-datetime-picker"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import {
  calculatePanelEconomicsWithShared,
  calculateUnitEconomics,
  PAYMENT_METHODS,
  type BillingCycle,
  type CostCategory,
  type CostLine,
} from "@/lib/unit-economics-calc"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"

export type PanelEconomicsEntry = {
  lines?: CostLine[]
  metrics?: { metrics?: Record<string, unknown>; warnings?: string[]; inputs?: Record<string, unknown> }
  sales_volume_gb_30d?: number
}

type LineForm = CostLine & { key: string }

const CATEGORIES: CostCategory[] = [
  "internal_server",
  "external_server",
  "cdn",
  "outbound",
  "support",
]

function emptyLine(category: CostCategory): LineForm {
  const defaultCycle: BillingCycle =
    category === "cdn" || category === "outbound" ? "per_gb" : "monthly"
  return {
    key: `ln-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    category,
    label: "",
    provider: "",
    cost_amount: 0,
    billing_cycle: defaultCycle,
    payment_method: "",
    paid_at: "",
    expires_at: "",
    host_ip: "",
    tunnel_mode: "",
    notes: "",
    active: true,
  }
}

function linesFromEntry(lines: CostLine[] | undefined, panelId: number): LineForm[] {
  if (!lines?.length) return []
  return lines.map((l, i) => ({
    key: `ln-${i}-${l.label ?? ""}`,
    panel_id: panelId,
    category: (l.category as CostCategory) || "external_server",
    label: String(l.label ?? ""),
    provider: String(l.provider ?? ""),
    cost_amount: Number(l.cost_amount) || 0,
    billing_cycle: (l.billing_cycle as BillingCycle) || "monthly",
    payment_method: String(l.payment_method ?? ""),
    paid_at: String(l.paid_at ?? "").slice(0, 10),
    expires_at: String(l.expires_at ?? "").slice(0, 10),
    host_ip: String(l.host_ip ?? ""),
    tunnel_mode: String(l.tunnel_mode ?? ""),
    notes: String(l.notes ?? ""),
    active: l.active !== false,
  }))
}

function expiryBadge(expiresAt: string): "expired" | "soon" | null {
  if (!expiresAt) return null
  const exp = new Date(expiresAt).getTime()
  if (!Number.isFinite(exp)) return null
  const now = Date.now()
  const days = (exp - now) / (86400 * 1000)
  if (days < 0) return "expired"
  if (days <= 30) return "soon"
  return null
}

export function DashboardPanelEconomicsSheet({
  open,
  onOpenChange,
  panelId,
  panelLabel,
  entry,
  globalConfig,
  sharedLines = [],
  siteVolumeGb = 0,
  onSaved,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  panelId: number
  panelLabel: string
  entry: PanelEconomicsEntry | undefined
  globalConfig: {
    total_sold_volume_gb?: number
    selling_price_per_gb?: number
    volume_mode?: string
    volume_window_days?: number
  }
  sharedLines?: CostLine[]
  siteVolumeGb?: number
onSaved?: (patch: { panelId: number; entry: PanelEconomicsEntry }) => void
}) {
  const { isFa } = useDashLocale()
  const formatMoney = (value: number, suffix: string) =>
    `${formatNumber(value, isFa)} ${suffix}`

  const t = useTranslations("unitEconomicsAdmin")
  const tp = (k: string) => t(`panelEconomics.${k}`)
  const ts = (k: string) => t(`sharedEconomics.${k}`)
  const currency = tp("currencySuffix")

  const [lines, setLines] = useState<LineForm[]>([])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    const loaded = linesFromEntry(entry?.lines, panelId)
    setLines(loaded)
    setError(null)
  }, [open, entry?.lines, panelId])

  const activeLines: CostLine[] = useMemo(
    () =>
      lines
        .filter((l) => l.active !== false && String(l.label ?? "").trim() !== "")
        .map((l) => ({
          ...l,
          panel_id: panelId,
          cost_amount: Number(l.cost_amount) || 0,
        })),
    [lines, panelId]
  )

  const panelSalesGb = useMemo(() => {
    const fromEntry = Number(entry?.sales_volume_gb_30d)
    if (Number.isFinite(fromEntry) && fromEntry >= 0) return fromEntry
    const fromMetrics = Number(entry?.metrics?.inputs?.sales_volume_gb_30d)
    if (Number.isFinite(fromMetrics) && fromMetrics >= 0) return fromMetrics
    return 0
  }, [entry])

  const effectiveVolume = useMemo(() => {
    const mode = String(globalConfig.volume_mode ?? "auto_sales")
    if (mode === "auto_sales") {
      return panelSalesGb
    }
    return Math.max(0, Number(globalConfig.total_sold_volume_gb) || 0)
  }, [globalConfig, panelSalesGb])

  const live = useMemo(() => {
    const cfg = {
      ...globalConfig,
      effective_volume_gb: effectiveVolume,
      sales_volume_gb_30d: panelSalesGb,
    }
    if (panelId > 0 && sharedLines.length > 0) {
      return calculatePanelEconomicsWithShared(
        activeLines,
        sharedLines,
        cfg,
        effectiveVolume,
        Math.max(0, siteVolumeGb)
      )
    }
    return calculateUnitEconomics(activeLines, cfg, effectiveVolume)
  }, [activeLines, globalConfig, effectiveVolume, panelSalesGb, panelId, sharedLines, siteVolumeGb])

  const lossMaking = live.warnings.includes("loss_making_price")

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const payload = lines
        .filter((l) => String(l.label ?? "").trim() !== "")
        .map(({ key: _k, ...rest }) => ({
          ...rest,
          panel_id: panelId,
          cost_amount: Number(rest.cost_amount) || 0,
          active: rest.active !== false ? 1 : 0,
        }))
      const op = panelId < 1 ? "shared_economics_save" : "panel_economics_save"
      const res = await postAdminMutate(
        op,
        panelId < 1 ? { lines: payload } : { panel_id: panelId, lines: payload }
      )
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      if (res.panelEconomicsMap && typeof res.panelEconomicsMap === "object") {
        const map = res.panelEconomicsMap as Record<string, PanelEconomicsEntry>
        const key = panelId < 1 ? "0" : String(panelId)
        const pe = map[key]
        if (pe) onSaved?.({ panelId, entry: pe })
      } else {
        const pe = res.panelEconomics as PanelEconomicsEntry | undefined
        if (pe) onSaved?.({ panelId, entry: pe })
      }
      onOpenChange(false)
    } finally {
      setSaving(false)
    }
  }, [lines, panelId, onOpenChange, onSaved, tp])

  const linesByCategory = useMemo(() => {
    const map = new Map<CostCategory, LineForm[]>()
    for (const cat of CATEGORIES) {
      map.set(cat, lines.filter((l) => l.category === cat))
    }
    return map
  }, [lines])

  const addLine = (cat: CostCategory) => {
    setLines((prev) => [...prev, emptyLine(cat)])
  }

  const updateLine = (key: string, patch: Partial<LineForm>) => {
    setLines((prev) => prev.map((l) => (l.key === key ? { ...l, ...patch } : l)))
  }

  const removeLine = (key: string) => {
    setLines((prev) => prev.filter((l) => l.key !== key))
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <DashSheetContent
        className={cn("flex w-full min-w-0 flex-col sm:max-w-2xl")}
      >
        <SheetHeader>
          <SheetTitle>
            {panelId < 1 ? ts("sheetTitle") : `${tp("sheetTitle")} — ${panelLabel}`}
          </SheetTitle>
        </SheetHeader>

        <div className="flex-1 space-y-4 overflow-y-auto px-4">
          {panelId > 0 ? (
            <div className="rounded-lg border bg-muted/20 px-3 py-2 text-sm">
              <span className="text-muted-foreground">{tp("salesVolume30d")}: </span>
              <span className="font-semibold tabular-nums">
                {formatNumber(panelSalesGb, isFa)} GB
              </span>
            </div>
          ) : null}
          <div className="grid gap-2 rounded-lg border bg-muted/30 p-3 sm:grid-cols-2">
            <div>
              <p className="text-xs text-muted-foreground">{tp("kpiFixedMonthly")}</p>
              <p className="text-lg font-semibold tabular-nums">
                {formatMoney(live.metrics.total_fixed_monthly_costs, currency)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground">{tp("kpiCostPerGb")}</p>
              <p
                className={cn(
                  "text-lg font-semibold tabular-nums",
                  lossMaking && "text-destructive"
                )}
              >
                {formatMoney(live.metrics.cost_per_gb, currency)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground">{tp("kpiVariablePerGb")}</p>
              <p className="text-lg font-semibold tabular-nums">
                {formatMoney(live.metrics.total_variable_cost_per_gb, currency)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground">{tp("kpiPanelProfitMonthly")}</p>
              <p className="text-lg font-semibold tabular-nums">
                {formatMoney(live.metrics.total_net_profit_monthly, currency)}
              </p>
              <p className="text-xs text-muted-foreground">{tp("panelProfitHint")}</p>
            </div>
          </div>

          {CATEGORIES.map((cat) => (
            <Collapsible key={cat} defaultOpen={cat === "cdn" || cat === "external_server"}>
              <div className="flex items-center justify-between gap-2">
                <CollapsibleTrigger>
                  <Button type="button" variant="ghost" className="h-8 px-2 font-medium">
                    {tp(`cat_${cat}`)}
                    <Badge variant="secondary" className="ms-2">
                      {linesByCategory.get(cat)?.length ?? 0}
                    </Badge>
                  </Button>
                </CollapsibleTrigger>
                <Button type="button" variant="outline" size="sm" onClick={() => addLine(cat)}>
                  <Plus className="size-4" aria-hidden />
                  {tp("addLine")}
                </Button>
              </div>
              <CollapsibleContent className="mt-2 space-y-3">
                {(linesByCategory.get(cat) ?? []).length === 0 ? (
                  <p className="text-sm text-muted-foreground">{tp("noLines")}</p>
                ) : null}
                {(linesByCategory.get(cat) ?? []).map((line) => {
                  const exp = expiryBadge(String(line.expires_at ?? ""))
                  return (
                    <div
                      key={line.key}
                      className="space-y-2 rounded-lg border border-border p-3"
                    >
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <Label className="text-sm font-medium">{tp("lineLabel")}</Label>
                        <div className="flex items-center gap-2">
                          {exp === "expired" ? (
                            <Badge variant="destructive">{tp("expired")}</Badge>
                          ) : exp === "soon" ? (
                            <Badge className="border-amber-500/50 bg-amber-500/10 text-amber-950 dark:text-amber-100">
                              <AlertTriangle className="me-1 size-3" />
                              {tp("expiresSoon")}
                            </Badge>
                          ) : null}
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={tp("removeLine")}
                            onClick={() => removeLine(line.key)}
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                      </div>
                      <Input
                        value={line.label}
                        onChange={(e) => updateLine(line.key, { label: e.target.value })}
                        placeholder={tp("lineLabelPlaceholder")}
                      />
                      <div className="grid gap-2 sm:grid-cols-2">
                        <div className="space-y-1">
                          <Label className="text-xs">{tp("provider")}</Label>
                          <Input
                            value={line.provider}
                            onChange={(e) =>
                              updateLine(line.key, { provider: e.target.value })
                            }
                          />
                        </div>
                        <div className="space-y-1">
                          <Label className="text-xs">{tp("costAmount")}</Label>
                          <Input
                            inputMode="decimal"
                            value={line.cost_amount === 0 ? "" : String(line.cost_amount)}
                            onChange={(e) =>
                              updateLine(line.key, {
                                cost_amount: Number(e.target.value) || 0,
                              })
                            }
                          />
                        </div>
                        <div className="space-y-1">
                          <Label className="text-xs">{tp("billingCycle")}</Label>
                          <DashSelect
                            value={line.billing_cycle ?? "monthly"}
                            onValueChange={(v) =>
                              updateLine(line.key, { billing_cycle: v as BillingCycle })
                            }
                            options={[
                              { value: "hourly", label: tp("cycleHourly") },
                              { value: "daily", label: tp("cycleDaily") },
                              { value: "monthly", label: tp("cycleMonthly") },
                              { value: "per_gb", label: tp("cyclePerGb") },
                            ]}
                          />
                        </div>
                        <div className="space-y-1">
                          <Label className="text-xs">{tp("paymentMethod")}</Label>
                          <DashSelect
                            value={line.payment_method || "other"}
                            onValueChange={(v) => updateLine(line.key, { payment_method: v })}
                            options={PAYMENT_METHODS.map((m) => ({
                              value: m,
                              label: tp(`pay_${m}`),
                            }))}
                          />
                        </div>
                        <DashboardDatePicker
                          label={tp("paidAt")}
        value={line.paid_at ?? ""}
                          onChange={(v) => updateLine(line.key, { paid_at: v })}
                        />
                        <DashboardDatePicker
                          label={tp("expiresAt")}
        value={line.expires_at ?? ""}
                          onChange={(v) => updateLine(line.key, { expires_at: v })}
                        />
                        <div className="space-y-1">
                          <Label className="text-xs">{tp("hostIp")}</Label>
                          <Input
                            dir="ltr"
                            value={line.host_ip}
                            onChange={(e) =>
                              updateLine(line.key, { host_ip: e.target.value })
                            }
                          />
                        </div>
                        <div className="space-y-1">
                          <Label className="text-xs">{tp("tunnelMode")}</Label>
                          <Input
                            value={line.tunnel_mode}
                            onChange={(e) =>
                              updateLine(line.key, { tunnel_mode: e.target.value })
                            }
                          />
                        </div>
                      </div>
                      <div className="space-y-1">
                        <Label className="text-xs">{tp("notes")}</Label>
                        <Textarea
                          rows={2}
                          value={line.notes}
                          onChange={(e) => updateLine(line.key, { notes: e.target.value })}
                          placeholder={tp("notesPlaceholder")}
                        />
                      </div>
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={line.active !== false}
                          onChange={(e) =>
                            updateLine(line.key, { active: e.target.checked })
                          }
                        />
                        {tp("activeLine")}
                      </label>
                    </div>
                  )
                })}
              </CollapsibleContent>
            </Collapsible>
          ))}

          {error ? (
            <p className="text-sm text-destructive" role="alert">
              {error}
            </p>
          ) : null}
        </div>

        <SheetFooter className="border-t pt-4">
          <Button type="button" disabled={saving} onClick={() => void onSave()}>
            {tp("save")}
          </Button>
        </SheetFooter>
      </DashSheetContent>
    </Sheet>
  )
}
