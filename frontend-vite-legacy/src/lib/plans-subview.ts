export type PlansView = "plans" | "wholesale"

export function isPlansView(v: string): v is PlansView {
  return v === "plans" || v === "wholesale"
}

export function readPlansViewFromUrl(): PlansView {
  if (typeof window === "undefined") return "plans"
  const raw = new URLSearchParams(window.location.search).get("plans_view") || "plans"
  return isPlansView(raw) ? raw : "plans"
}

export function writePlansViewToUrl(view: PlansView) {
  if (typeof window === "undefined") return
  const url = new URL(window.location.href)
  if (view === "plans") {
    url.searchParams.delete("plans_view")
  } else {
    url.searchParams.set("plans_view", view)
  }
  window.history.replaceState(window.history.state, "", url.toString())
}

/** Legacy top-level tab folded into plans hub. */
export function resolveLegacyPlansTab(tab: string): { tab: string; view?: PlansView } {
  if (tab === "wholesale_lines") return { tab: "plans", view: "wholesale" }
  return { tab }
}
