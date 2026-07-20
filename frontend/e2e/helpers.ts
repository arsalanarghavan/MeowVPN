import type { APIRequestContext, Page } from "@playwright/test"

export const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || "http://127.0.0.1:3520"
export const API_ORIGIN = (process.env.PLAYWRIGHT_API_ORIGIN || "http://127.0.0.1:8080").replace(/\/$/, "")
export const SESSION_COOKIE = "simplevpbot_session"

export async function withSession(page: Page) {
  const base = new URL(BASE_URL)
  await page.context().addCookies([
    {
      name: SESSION_COOKIE,
      value: "e2e-session",
      domain: base.hostname,
      path: "/",
      httpOnly: true,
    },
  ])
}

function decodeXsrf(value: string | undefined): string | null {
  if (!value) return null
  try {
    return decodeURIComponent(value)
  } catch {
    return value
  }
}

async function copyApiCookiesToBrowser(page: Page, request: APIRequestContext) {
  const apiHost = new URL(API_ORIGIN).hostname
  const browserHost = new URL(BASE_URL).hostname
  const state = await request.storageState()
  const cookies = state.cookies
    .filter((c) => c.domain.includes(apiHost) || c.domain.includes("localhost") || c.domain.includes("127.0.0.1"))
    .map((c) => ({
      name: c.name,
      value: c.value,
      domain: browserHost,
      path: c.path || "/",
      httpOnly: c.httpOnly,
      secure: c.secure,
      sameSite: c.sameSite as "Strict" | "Lax" | "None" | undefined,
    }))
  if (cookies.length > 0) {
    await page.context().addCookies(cookies)
  }
  // API calls from the browser may target the API host directly.
  const apiCookies = state.cookies.map((c) => ({
    name: c.name,
    value: c.value,
    domain: c.domain.replace(/^\./, ""),
    path: c.path || "/",
    httpOnly: c.httpOnly,
    secure: c.secure,
    sameSite: c.sameSite as "Strict" | "Lax" | "None" | undefined,
  }))
  await page.context().addCookies(apiCookies)
}

/** Returns true when Laravel API is reachable and admin login succeeds. */
export async function ensureAdminApiSession(page: Page, request: APIRequestContext): Promise<boolean> {
  if (process.env.PLAYWRIGHT_SKIP_BACKEND === "1") {
    return false
  }

  try {
    const ready = await request.get(`${API_ORIGIN}/health/ready`, { timeout: 5000 })
    if (!ready.ok()) {
      return false
    }
  } catch {
    return false
  }

  const user = process.env.PLAYWRIGHT_ADMIN_USER || "admin"
  const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD || "changeme"

  try {
    const csrfRes = await request.get(`${API_ORIGIN}/sanctum/csrf-cookie`, { timeout: 8000 })
    if (!csrfRes.ok()) return false

    const stateAfterCsrf = await request.storageState()
    const xsrf = decodeXsrf(stateAfterCsrf.cookies.find((c) => c.name === "XSRF-TOKEN")?.value)
    if (!xsrf) return false

    const loginRes = await request.post(`${API_ORIGIN}/api/v1/auth/login`, {
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-XSRF-TOKEN": xsrf,
        "X-Requested-With": "XMLHttpRequest",
      },
      data: { log: user, pwd: password, remember: true },
      timeout: 8000,
    })
    if (!loginRes.ok()) return false

    const stateRes = await request.get(`${API_ORIGIN}/api/v1/admin/state?activeTab=payments`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      timeout: 8000,
    })
    if (!stateRes.ok()) return false

    await copyApiCookiesToBrowser(page, request)
    return true
  } catch {
    return false
  }
}

export async function waitForMutate(
  page: Page,
  op: string,
  action: () => Promise<void>
): Promise<{ ok: boolean; status: number }> {
  const responsePromise = page.waitForResponse(
    (res) => {
      if (!res.url().includes("/admin/mutate") || res.request().method() !== "POST") {
        return false
      }
      try {
        const body = res.request().postDataJSON() as { op?: string } | null
        return body?.op === op
      } catch {
        return true
      }
    },
    { timeout: 15000 }
  )
  await action()
  const response = await responsePromise
  let ok = response.ok()
  try {
    const json = (await response.json()) as { ok?: boolean }
    if (typeof json.ok === "boolean") ok = json.ok
  } catch {
    /* ignore */
  }
  return { ok, status: response.status() }
}
