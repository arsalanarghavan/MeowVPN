import { formatDateTime, formatNumber } from "@/lib/format-locale"

export type DashRecord = Record<string, unknown>

export function overviewNum(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

export function userDisplayLabel(row: DashRecord): string {
  const fn = String(row.first_name ?? "").trim()
  const ln = String(row.last_name ?? "").trim()
  const combined = `${fn} ${ln}`.trim()
  if (combined) return combined
  const un = String(row.username ?? "").trim()
  if (un) return un.startsWith("@") ? un : `@${un}`
  const id = overviewNum(row.id)
  return id > 0 ? `#${id}` : "—"
}

export function userStatusBadgeVariant(
  st: string
): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved") return "default"
  if (st === "pending") return "secondary"
  if (st === "rejected") return "destructive"
  if (st === "blocked") return "outline"
  return "outline"
}

export function receiptStatusBadgeVariant(
  st: string
): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved") return "default"
  if (st === "rejected") return "destructive"
  return "secondary"
}

export function receiptAmount(row: DashRecord): number {
  const direct = overviewNum(row.amount)
  if (direct > 0 || row.amount === 0) return direct
  return overviewNum(row.transaction_amount)
}

export function formatOverviewDate(raw: unknown, isFa: boolean): string {
  const s = String(raw ?? "").trim()
  if (!s) return "—"
  return formatDateTime(s, isFa)
}

export function formatOverviewAmount(amount: number, isFa: boolean, freeLabel: string): string {
  if (Math.abs(amount) < 0.009) return freeLabel
  return formatNumber(amount, isFa)
}
