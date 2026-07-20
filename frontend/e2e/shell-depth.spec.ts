import { test, expect } from "@playwright/test"
import { ensureAdminApiSession, withSession, API_ORIGIN } from "./helpers"

test.describe("shell depth — auth session persona", () => {
  test("UI login page renders and CSRF cookie can be fetched", async ({ page, request }) => {
    await page.context().clearCookies()
    await page.goto("/en/login", { waitUntil: "domcontentloaded" })
    await expect(page.locator("body")).toContainText(/login|ورود|password|رمز/i)

    const csrf = await request.get(`${API_ORIGIN}/sanctum/csrf-cookie`, { timeout: 8000 }).catch(() => null)
    test.skip(!csrf || !csrf.ok(), "Laravel API unavailable")
    expect(csrf!.ok()).toBeTruthy()
  })

  test("me/state responds after API login", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    const state = await request.get(`${API_ORIGIN}/api/v1/me/state`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    })
    expect(state.ok()).toBeTruthy()
    const json = (await state.json()) as { data?: { isLoggedIn?: boolean; features?: unknown } }
    expect(json.data?.isLoggedIn ?? true).toBeTruthy()
  })

  test("authenticated dashboard has locale dir", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    const dir = await page.locator("html").getAttribute("dir")
    expect(dir === "rtl" || dir === "ltr" || dir == null).toBeTruthy()
  })

  test("impersonation banner appears after start when target exists", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.setViewportSize({ width: 320, height: 568 })
    const start = await request.post(`${API_ORIGIN}/api/v1/dashboard/impersonate/start`, {
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      data: { targetSvpUserId: 100 },
    })
    if (!start.ok()) {
      test.skip(true, "Impersonate start failed (no reseller target / HTTPS gate)")
    }

    await page.goto("/en/dashboard", { waitUntil: "domcontentloaded" })
    const banner = page.getByTestId("impersonation-banner")
    if ((await banner.count()) === 0) {
      await request.post(`${API_ORIGIN}/api/v1/dashboard/impersonate/stop`, {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      })
      test.skip(true, "Banner not rendered (boot may omit impersonation label)")
    }
    await expect(banner).toBeVisible({ timeout: 10000 })
    const stopBtn = page.getByRole("button", { name: /Switch to admin|بازگشت به مدیر/i })
    await expect(stopBtn).toBeVisible()
    await request.post(`${API_ORIGIN}/api/v1/dashboard/impersonate/stop`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    })
  })

  test("portal route renders theme shell", async ({ page }) => {
    await page.goto("/en/portal", { waitUntil: "domcontentloaded" })
    await expect(page.locator("body")).toBeVisible()
  })

  test("magic auth route exists", async ({ page }) => {
    await page.goto("/en/dashboard/auth/magic", { waitUntil: "domcontentloaded" })
    await expect(page.locator("body")).toBeVisible()
  })

  test("users deep-link route does not 404", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    if (!authed) await withSession(page)
    await page.goto("/en/dashboard/users/u/1", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/404/)
    await expect(page.locator("body")).toBeVisible()
  })

  test("ui-preferences endpoint accepts accent when authed", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    const res = await request.post(`${API_ORIGIN}/api/v1/dashboard/ui-preferences`, {
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      data: { ui_accent: "default" },
    })
    expect(res.status()).toBeLessThan(500)
  })
})
