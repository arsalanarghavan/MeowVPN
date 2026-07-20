import { NextResponse } from "next/server"
import type { NextRequest } from "next/server"
import { defaultLocale, locales } from "./i18n/config"

const SESSION_COOKIE = "simplevpbot_session"
const ME_STATE_TIMEOUT_MS = 1500

function hasSessionCookie(request: NextRequest): boolean {
  return Boolean(request.cookies.get(SESSION_COOKIE)?.value)
}

function apiInternalBase(): string {
  return (
    process.env.API_INTERNAL_BASE ||
    process.env.NEXT_PUBLIC_API_BASE ||
    "http://web/api/v1"
  ).replace(/\/$/, "")
}

/**
 * Validate the Laravel session via /me/state (cookies forwarded).
 * On explicit auth failures (401/403), treat as logged out.
 * If the edge runtime cannot reach the API (timeout/network), fall back to the
 * simplevpbot_session cookie so SSR navigation still works; API routes enforce auth.
 */
async function sessionLooksLoggedIn(request: NextRequest): Promise<boolean> {
  if (!hasSessionCookie(request)) {
    return false
  }

  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), ME_STATE_TIMEOUT_MS)
  try {
    const res = await fetch(`${apiInternalBase()}/me/state`, {
      method: "GET",
      headers: {
        Accept: "application/json",
        Cookie: request.headers.get("cookie") ?? "",
      },
      cache: "no-store",
      signal: controller.signal,
    })
    if (res.status === 401 || res.status === 403) {
      return false
    }
    if (!res.ok) {
      return hasSessionCookie(request)
    }
    const json = (await res.json()) as { ok?: boolean; isLoggedIn?: boolean; loggedIn?: boolean }
    if (typeof json.isLoggedIn === "boolean") {
      return json.isLoggedIn
    }
    if (typeof json.loggedIn === "boolean") {
      return json.loggedIn
    }
    return Boolean(json.ok)
  } catch {
    return hasSessionCookie(request)
  } finally {
    clearTimeout(timer)
  }
}

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl

  if (pathname === "/") {
    const url = request.nextUrl.clone()
    url.pathname = `/${defaultLocale}`
    return NextResponse.redirect(url)
  }

  const segments = pathname.split("/").filter(Boolean)
  const maybeLocale = segments[0]
  if (maybeLocale && !(locales as readonly string[]).includes(maybeLocale)) {
    const url = request.nextUrl.clone()
    url.pathname = `/${defaultLocale}${pathname}`
    return NextResponse.redirect(url)
  }

  const locale = (locales as readonly string[]).includes(maybeLocale ?? "")
    ? (maybeLocale as string)
    : defaultLocale
  const rest = segments.slice(1)
  const isDashboard = rest[0] === "dashboard"
  const isLogin = rest[0] === "login"
  const isMagicAuth = isDashboard && rest[1] === "auth" && rest[2] === "magic"

  if (isDashboard && !isMagicAuth) {
    const loggedIn = await sessionLooksLoggedIn(request)
    if (!loggedIn) {
      const url = request.nextUrl.clone()
      url.pathname = `/${locale}/login`
      url.searchParams.set("next", pathname)
      return NextResponse.redirect(url)
    }
  }

  if (isLogin) {
    const loggedIn = await sessionLooksLoggedIn(request)
    if (loggedIn) {
      const next = request.nextUrl.searchParams.get("next")
      const url = request.nextUrl.clone()
      url.pathname =
        next && next.startsWith(`/${locale}/`) ? next : `/${locale}/dashboard`
      url.search = ""
      return NextResponse.redirect(url)
    }
  }

  return NextResponse.next()
}

export const config = {
  matcher: ["/", "/fa/:path*", "/en/:path*"],
}
