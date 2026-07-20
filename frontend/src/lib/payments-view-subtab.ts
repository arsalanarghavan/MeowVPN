export const PAYMENTS_VIEWS = ["receipts", "transactions", "orders"] as const

export type PaymentsView = (typeof PAYMENTS_VIEWS)[number]

export function isPaymentsView(v: string): v is PaymentsView {
  return (PAYMENTS_VIEWS as readonly string[]).includes(v)
}

export function readPaymentsViewFromUrl(): PaymentsView {
  if (typeof window === "undefined") return "receipts"
  const raw = new URLSearchParams(window.location.search).get("payments_view") || "receipts"
  return isPaymentsView(raw) ? raw : "receipts"
}

export function writePaymentsViewToUrl(view: PaymentsView) {
  if (typeof window === "undefined") return
  const url = new URL(window.location.href)
  url.searchParams.set("payments_view", view)
  window.history.replaceState(window.history.state, "", url.toString())
}
