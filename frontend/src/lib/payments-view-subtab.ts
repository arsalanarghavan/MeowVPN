export const PAYMENTS_VIEWS = ["receipts", "transactions", "orders"] as const

export type PaymentsView = (typeof PAYMENTS_VIEWS)[number]

export type PaymentsFiltersFromUrl = {
  q: string
  status: string
  type: string
  method: string
  sort: string
  dateFrom: string
  dateTo: string
  amountMin: string
  amountMax: string
}

export function isPaymentsView(v: string): v is PaymentsView {
  return (PAYMENTS_VIEWS as readonly string[]).includes(v)
}

export function readPaymentsViewFromUrl(): PaymentsView {
  if (typeof window === "undefined") return "receipts"
  const raw = new URLSearchParams(window.location.search).get("payments_view") || "receipts"
  return isPaymentsView(raw) ? raw : "receipts"
}

function readDualParam(sp: URLSearchParams, payKey: string, rcptKey: string, fallback = ""): string {
  return sp.get(payKey) ?? sp.get(rcptKey) ?? fallback
}

export function readPaymentsFiltersFromUrl(view?: PaymentsView): PaymentsFiltersFromUrl {
  if (typeof window === "undefined") {
    return {
      q: "",
      status: "all",
      type: "",
      method: "",
      sort: "created_desc",
      dateFrom: "",
      dateTo: "",
      amountMin: "",
      amountMax: "",
    }
  }
  const sp = new URLSearchParams(window.location.search)
  const statusRaw = readDualParam(sp, "payments_status", "receipts_status", "all")
  return {
    q: readDualParam(sp, "payments_q", "receipts_q"),
    status: statusRaw.trim() === "" ? "all" : statusRaw,
    type: readDualParam(sp, "payments_type", "payments_type"),
    method: readDualParam(sp, "payments_method", "payments_method"),
    sort: readDualParam(sp, "payments_sort", "receipts_sort", "created_desc") || "created_desc",
    dateFrom: readDualParam(sp, "payments_date_from", "receipts_date_from"),
    dateTo: readDualParam(sp, "payments_date_to", "receipts_date_to"),
    amountMin: readDualParam(sp, "payments_amount_min", "receipts_amount_min"),
    amountMax: readDualParam(sp, "payments_amount_max", "receipts_amount_max"),
  }
}

export function writePaymentsViewToUrl(view: PaymentsView) {
  if (typeof window === "undefined") return
  const url = new URL(window.location.href)
  url.searchParams.set("payments_view", view)
  window.history.replaceState(window.history.state, "", url.toString())
}
