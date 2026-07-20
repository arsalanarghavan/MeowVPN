/** Laravel API base URL — thin helper for legacy dashboard components. */
export function apiBase(boot?: Record<string, unknown>): string {
  const fromEnv = process.env.NEXT_PUBLIC_API_URL
  if (typeof fromEnv === "string" && fromEnv.trim() !== "") {
    return fromEnv.replace(/\/$/, "")
  }
  const b = boot ?? (typeof window !== "undefined" ? window.__SIMPLEVPBOT_DASH__ : undefined) ?? {}
  return String((b as { restUrl?: string }).restUrl || "/api/v1").replace(/\/$/, "")
}

export function normalizeAdminApiPath(path: string): string {
  const p = path.startsWith("/") ? path : `/${path}`
  if (p.startsWith("/dashboard/admin/")) {
    return p.replace("/dashboard/admin/", "/admin/")
  }
  return p
}
