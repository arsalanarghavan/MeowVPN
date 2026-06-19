/** Laravel API base URL for dashboard SPA. */
export function apiBase(boot?: Record<string, unknown>): string {
  const fromEnv = import.meta.env.VITE_API_URL
  if (typeof fromEnv === "string" && fromEnv.trim() !== "") {
    return fromEnv.replace(/\/$/, "")
  }
  const b = boot ?? window.__SIMPLEVPBOT_DASH__ ?? {}
  return String((b as { restUrl?: string }).restUrl || "/api/v1").replace(/\/$/, "")
}

/** Map legacy WP REST paths (`/dashboard/admin/...`) to Laravel `/admin/...`. */
export function normalizeAdminApiPath(path: string): string {
  const p = path.startsWith("/") ? path : `/${path}`
  if (p.startsWith("/dashboard/admin/")) {
    return p.replace("/dashboard/admin/", "/admin/")
  }
  // §7.1 session routes keep `/dashboard/` prefix (persona, ui-preferences, impersonate).
  return p
}

/** Read Sanctum CSRF token from cookie (decoded). */
export function readCsrfToken(): string {
  if (typeof document === "undefined") return ""
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  if (!match?.[1]) return ""
  try {
    return decodeURIComponent(match[1])
  } catch {
    return match[1]
  }
}

/** Fetch CSRF cookie before state-changing requests (Sanctum lives at app root, not under /api/v1). */
export async function ensureCsrfCookie(): Promise<void> {
  const url =
    typeof window !== "undefined" && window.location?.origin
      ? `${window.location.origin}/sanctum/csrf-cookie`
      : "/sanctum/csrf-cookie"
  await fetch(url, { credentials: "include" })
}

export function apiHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
  }
  const csrf = readCsrfToken()
  if (csrf) {
    headers["X-XSRF-TOKEN"] = csrf
  }
  return headers
}
