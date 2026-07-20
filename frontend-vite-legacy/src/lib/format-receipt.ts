type DashRecord = Record<string, unknown>

export function receiptSelectedService(r: DashRecord): string {
  return String(r.selected_service ?? "").trim() || "—"
}
