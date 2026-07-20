/** Mirror of SimpleVPBot_Unit_Economics_Calculator for live dashboard KPIs. */

export type BillingCycle = "hourly" | "daily" | "monthly" | "per_gb"

export type CostCategory =
  | "internal_server"
  | "external_server"
  | "cdn"
  | "outbound"
  | "support"

export type CostLine = {
  panel_id?: number
  category?: CostCategory | string
  label?: string
  provider?: string
  cost_amount?: number
  billing_cycle?: BillingCycle | string
  payment_method?: string
  paid_at?: string
  expires_at?: string
  host_ip?: string
  tunnel_mode?: string
  notes?: string
  active?: boolean
}

export type GlobalConfig = {
  total_sold_volume_gb?: number
  selling_price_per_gb?: number
  volume_mode?: "manual" | "auto_sales" | string
  volume_window_days?: number
  effective_volume_gb?: number
  sales_volume_gb_30d?: number
}

export type UnitEconomicsMetrics = {
  total_fixed_monthly_costs: number
  fixed_cost_share_per_gb: number
  total_variable_cost_per_gb: number
  cost_per_gb: number
  profit_per_gb: number
  total_net_profit_monthly: number
  profit_margin_percentage: number | null
}

export type UnitEconomicsResult = {
  inputs: {
    total_sold_volume_gb: number
    effective_volume_gb?: number
    selling_price_per_gb: number
    volume_mode?: string
    volume_window_days?: number
    sales_volume_gb_30d?: number
    lines: Array<Record<string, unknown>>
  }
  metrics: UnitEconomicsMetrics
  warnings: string[]
  breakdownByCategory?: Record<string, { fixed_monthly: number; variable_per_gb: number }>
}

const HOURS_PER_MONTH = 730
const DAYS_PER_MONTH = 30

function sanitizeLines(lines: CostLine[]): CostLine[] {
  const out: CostLine[] = []
  for (const row of lines) {
    if (row.active === false) continue
    const label = String(row.label ?? "").trim()
    if (!label) continue
    let cycle = String(row.billing_cycle ?? "monthly")
    if (!["hourly", "daily", "monthly", "per_gb"].includes(cycle)) cycle = "monthly"
    out.push({
      panel_id: Math.max(0, Number(row.panel_id) || 0),
      category: (row.category as CostCategory) || "external_server",
      label,
      provider: String(row.provider ?? ""),
      cost_amount: Math.max(0, Number(row.cost_amount) || 0),
      billing_cycle: cycle as BillingCycle,
    })
  }
  return out
}

export const PAYMENT_METHODS = [
  "toman_card",
  "toman_wallet",
  "toman_transfer",
  "usdt",
  "usdt_trc20",
  "other",
] as const

export type PaymentMethod = (typeof PAYMENT_METHODS)[number]

function lineMonthlyFixed(cost: number, cycle: string): number {
  const c = Math.max(0, cost)
  if (cycle === "hourly") return c * HOURS_PER_MONTH
  if (cycle === "daily") return c * DAYS_PER_MONTH
  if (cycle === "per_gb") return 0
  return c
}

function costTotalsFromLines(lines: CostLine[]): {
  fixedMonthly: number
  variablePerGb: number
} {
  let fixedMonthly = 0
  let variablePerGb = 0
  for (const line of sanitizeLines(lines)) {
    const cycle = String(line.billing_cycle ?? "monthly")
    const cost = Number(line.cost_amount) || 0
    if (cycle === "per_gb") variablePerGb += cost
    else fixedMonthly += lineMonthlyFixed(cost, cycle)
  }
  return { fixedMonthly, variablePerGb }
}

/** Panel KPI including volume-weighted shared infrastructure allocation. */
export function calculatePanelEconomicsWithShared(
  panelLines: CostLine[],
  sharedLines: CostLine[],
  config: GlobalConfig,
  panelVolumeGb: number,
  siteVolumeGb: number
): UnitEconomicsResult & {
  costAllocation?: Record<string, number>
} {
  const panel = calculateUnitEconomics(panelLines, config, panelVolumeGb)
  const shared = costTotalsFromLines(sharedLines)
  const allocFixed =
    siteVolumeGb > 0 && panelVolumeGb > 0
      ? shared.fixedMonthly * (panelVolumeGb / siteVolumeGb)
      : 0
  const totalFixed = panel.metrics.total_fixed_monthly_costs + allocFixed
  const totalVar =
    panel.metrics.total_variable_cost_per_gb + shared.variablePerGb
  const selling = Math.max(0, Number(config.selling_price_per_gb) || 0)
  const volume = Math.max(0, panelVolumeGb)
  const warnings: string[] = [...panel.warnings]
  let fixedShare = 0
  if (volume <= 0) {
    if (!warnings.includes("volume_required")) warnings.push("volume_required")
  } else {
    fixedShare = totalFixed / volume
  }
  const costPerGb = fixedShare + totalVar
  const profitPerGb = selling - costPerGb
  const totalNet = profitPerGb * (volume > 0 ? volume : 0)
  if (selling > 0 && selling < costPerGb && !warnings.includes("loss_making_price")) {
    warnings.push("loss_making_price")
  }
  const margin = selling > 0 ? (profitPerGb / selling) * 100 : null
  return {
    ...panel,
    warnings,
    metrics: {
      total_fixed_monthly_costs: totalFixed,
      fixed_cost_share_per_gb: fixedShare,
      total_variable_cost_per_gb: totalVar,
      cost_per_gb: costPerGb,
      profit_per_gb: profitPerGb,
      total_net_profit_monthly: totalNet,
      profit_margin_percentage: margin,
    },
    costAllocation: {
      panel_fixed_monthly: panel.metrics.total_fixed_monthly_costs,
      panel_variable_per_gb: panel.metrics.total_variable_cost_per_gb,
      shared_fixed_alloc_monthly: allocFixed,
      shared_variable_per_gb: shared.variablePerGb,
    },
  }
}

export function calculateUnitEconomics(
  lines: CostLine[],
  config: GlobalConfig,
  volumeGbOverride?: number
): UnitEconomicsResult {
  const clean = sanitizeLines(lines)
  const volume =
    volumeGbOverride !== undefined && Number.isFinite(volumeGbOverride)
      ? Math.max(0, volumeGbOverride)
      : Math.max(
          0,
          Number(config.effective_volume_gb ?? config.total_sold_volume_gb) || 0
        )
  const selling = Math.max(0, Number(config.selling_price_per_gb) || 0)

  let fixedMonthly = 0
  let variablePerGb = 0
  const linesNormalized: Array<Record<string, unknown>> = []
  const byCategory: Record<string, { fixed_monthly: number; variable_per_gb: number }> = {}

  for (const line of clean) {
    const cat = String(line.category ?? "external_server")
    const cycle = String(line.billing_cycle ?? "monthly")
    const cost = Number(line.cost_amount) || 0

    if (!byCategory[cat]) {
      byCategory[cat] = { fixed_monthly: 0, variable_per_gb: 0 }
    }

    if (cycle === "per_gb") {
      variablePerGb += cost
      byCategory[cat].variable_per_gb += cost
      linesNormalized.push({
        panel_id: line.panel_id,
        category: cat,
        label: line.label,
        cost_amount: cost,
        billing_cycle: cycle,
        monthly_cost: 0,
        per_gb_cost: cost,
      })
    } else {
      const monthly = lineMonthlyFixed(cost, cycle)
      fixedMonthly += monthly
      byCategory[cat].fixed_monthly += monthly
      linesNormalized.push({
        panel_id: line.panel_id,
        category: cat,
        label: line.label,
        cost_amount: cost,
        billing_cycle: cycle,
        monthly_cost: monthly,
        per_gb_cost: 0,
      })
    }
  }

  const warnings: string[] = []
  let fixedShare = 0
  if (volume <= 0) {
    warnings.push("volume_required")
  } else {
    fixedShare = fixedMonthly / volume
  }

  const costPerGb = fixedShare + variablePerGb
  const profitPerGb = selling - costPerGb
  const totalNet = profitPerGb * (volume > 0 ? volume : 0)

  if (selling > 0 && selling < costPerGb) {
    warnings.push("loss_making_price")
  }

  const margin = selling > 0 ? (profitPerGb / selling) * 100 : null

  return {
    inputs: {
      total_sold_volume_gb: volume,
      effective_volume_gb: volume,
      selling_price_per_gb: selling,
      volume_mode: config.volume_mode,
      volume_window_days: config.volume_window_days,
      sales_volume_gb_30d: config.sales_volume_gb_30d,
      lines: linesNormalized,
    },
    metrics: {
      total_fixed_monthly_costs: fixedMonthly,
      fixed_cost_share_per_gb: fixedShare,
      total_variable_cost_per_gb: variablePerGb,
      cost_per_gb: costPerGb,
      profit_per_gb: profitPerGb,
      total_net_profit_monthly: totalNet,
      profit_margin_percentage: margin,
    },
    warnings,
    breakdownByCategory: byCategory,
  }
}

export function linesForPanel(allLines: CostLine[], panelId: number): CostLine[] {
  if (panelId < 1) return []
  return allLines.filter((l) => (l.active !== false) && Number(l.panel_id) === panelId)
}

export function sharedLinesFromMap(
  map: Record<string, { lines?: CostLine[] } | undefined> | undefined
): CostLine[] {
  return map?.["0"]?.lines ?? []
}

export function panelLinesFromMap(
  map: Record<string, { lines?: CostLine[] } | undefined> | undefined,
  panelId: number
): CostLine[] {
  if (panelId < 1) return []
  return (map?.[String(panelId)]?.lines ?? []).filter((l) => l.active !== false)
}

export function allActiveLinesFromMap(
  map: Record<string, { lines?: CostLine[] } | undefined> | undefined
): CostLine[] {
  if (!map) return []
  const out: CostLine[] = []
  for (const entry of Object.values(map)) {
    if (!entry?.lines) continue
    for (const line of entry.lines) {
      if (line.active === false) continue
      out.push(line)
    }
  }
  return out
}
