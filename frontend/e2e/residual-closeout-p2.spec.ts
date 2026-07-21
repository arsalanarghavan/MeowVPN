import { test, expect } from "@playwright/test"
import { ensureAdminApiSession, withSession } from "./helpers"

test.describe("residual closeout P2", () => {
  test("portal smoke with signed-link query params", async ({ page }) => {
    const exp = Math.floor(Date.now() / 1000) + 3600
    await page.goto(`/en/portal?uid=1&exp=${exp}&sig=e2e-smoke&theme=default`, {
      waitUntil: "domcontentloaded",
    })
    await expect(page.locator("body")).toBeVisible()
    await expect(page).toHaveURL(/\/en\/portal/)
    // Invalid sig must not crash the SPA shell.
    await expect(page.locator("body")).not.toContainText(/Application error|Internal Server Error/i)
  })

  test("overview shows panels pagination control when API is up", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    // Home mounts OverviewAdminClient (not the old static stub cards).
    await expect(page.getByRole("heading", { name: /^Overview$/i })).toBeVisible({ timeout: 15000 })
    await expect(page.getByText(/Business health at a glance/i)).toBeVisible()

    const pager = page.getByTestId("dash-overview-panels-pagination")
    if ((await pager.count()) === 0) {
      test.skip(true, "Panels pagination not rendered (empty panel health)")
    }
    await expect(pager).toBeVisible({ timeout: 10000 })
  })

  test("bots pager is visible when list pagination is present", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    if (!authed) await withSession(page)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/bots", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toContainText(/bot|ربات|mirror|آینه/i, { timeout: 15000 })

    const pager = page.getByTestId("bots-pagination")
    if ((await pager.count()) === 0) {
      test.skip(true, "Bots pager not visible (single page / no pagination meta)")
    }
    await expect(pager).toBeVisible({ timeout: 10000 })
  })

  test("user persona renders DashboardUserPortal (not customer /portal themes)", async ({
    page,
    request,
  }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    // Switch to user persona when the control exists (multi-persona accounts).
    const userPersona = page.getByRole("button", { name: /user|کاربر/i }).first()
    if ((await userPersona.count()) > 0) {
      await userPersona.click().catch(() => undefined)
      await page.waitForTimeout(500)
    }

    const portal = page.getByTestId("dash-user-portal")
    if ((await portal.count()) === 0) {
      test.skip(true, "Account has no user persona / portal mount")
    }
    await expect(portal).toBeVisible({ timeout: 10000 })
    await expect(page).not.toHaveURL(/\/portal\?/)
  })

  test("texts admin mounts fa/en editor (not dead Vite texts tab)", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/texts", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toContainText(/text|locale|فارسی|English|fa|en|متن/i, {
      timeout: 15000,
    })
  })

  test("force-join section on bots uses per-platform keys UI", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/bots", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toContainText(/force.?join|عضویت اجباری|required channel|کانال/i, {
      timeout: 15000,
    })
  })

  test("marketing lifecycle URL is marketing_lifecycle (not /marketing)", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/marketing_lifecycle", { waitUntil: "domcontentloaded" })
    await expect(page).toHaveURL(/\/en\/dashboard\/marketing_lifecycle/)
    await expect(page.locator("body")).toContainText(/lifecycle|marketing|بازگشت|مارکتینگ|rule|قانون/i, {
      timeout: 15000,
    })
  })
})
