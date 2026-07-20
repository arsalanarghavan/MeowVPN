/** Map legacy WP REST paths (`/dashboard/admin/...`) to Laravel `/admin/...`. */
export function normalizeAdminApiPath(path: string): string {
  const p = path.startsWith("/") ? path : `/${path}`
  if (p.startsWith("/dashboard/admin/")) {
    return p.replace("/dashboard/admin/", "/admin/")
  }
  return p
}

export function apiBase(): string {
  if (typeof window !== "undefined") {
    return process.env.NEXT_PUBLIC_API_BASE || "/api/v1"
  }
  return process.env.API_INTERNAL_BASE || process.env.NEXT_PUBLIC_API_BASE || "http://web/api/v1"
}

export function apiOrigin(): string {
  const base = apiBase()
  if (base.startsWith("http")) {
    return base.replace(/\/api\/v1\/?$/, "")
  }
  if (typeof window !== "undefined") {
    return window.location.origin
  }
  return process.env.API_ORIGIN || "http://web"
}

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))
  return match ? decodeURIComponent(match[1]) : null
}

export function apiHeaders(extra?: HeadersInit): Headers {
  const headers = new Headers(extra)
  if (!headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json")
  }
  headers.set("Accept", "application/json")
  headers.set("X-Requested-With", "XMLHttpRequest")
  const xsrf = readCookie("XSRF-TOKEN")
  if (xsrf) {
    headers.set("X-XSRF-TOKEN", xsrf)
  }
  return headers
}

export async function ensureCsrfCookie(): Promise<void> {
  const origin = apiOrigin()
  await fetch(`${origin}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  })
}
