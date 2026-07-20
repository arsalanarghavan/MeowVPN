/** Client-side price estimates aligned with PHP Admin_User_Ops / Service_Renew. */

type DashRecord = Record<string, unknown>

function n(v: unknown): number {
  const x = Number(v)
  return Number.isFinite(x) ? x : 0
}

export function isPerGbPricing(svc: DashRecord): boolean {
  return String(svc.plan_pricing_type ?? "") === "per_gb"
}

export function previewRenewPriceToman(svc: DashRecord): number | null {
  const quotaGb = n(svc.quota_gb)
  if (isPerGbPricing(svc)) {
    if (quotaGb < 1) return null
    return Math.round(n(svc.plan_price_per_gb) * quotaGb * 100) / 100
  }
  const p = n(svc.plan_price)
  return p > 0 ? Math.round(p * 100) / 100 : 0
}

export function previewAddVolumePriceToman(svc: DashRecord, extraGb: number, plan?: DashRecord): number | null {
  const g = Math.max(1, Math.floor(extraGb))
  if (isPerGbPricing(svc)) {
    return Math.round(n(svc.plan_price_per_gb) * g * 100) / 100
  }
  const planPrice = n(svc.plan_price) || n(plan?.price)
  const tb = n(plan?.traffic_gb)
  if (tb < 1) return planPrice > 0 ? Math.round(planPrice * 100) / 100 : null
  return Math.round((planPrice * g) / tb * 100) / 100
}

export function previewAddSlotsPriceToman(extraUsers: number, pricePerExtraUser: number): number {
  const u = Math.max(0, pricePerExtraUser)
  const count = Math.max(1, Math.floor(extraUsers))
  return Math.round(count * u * 100) / 100
}

export function planForService(svc: DashRecord, plans: DashRecord[]): DashRecord | undefined {
  const pid = n(svc.plan_id)
  return plans.find((p) => n(p.id) === pid)
}
