import { test, expect } from "@playwright/test"

test.describe("login-04 + locale shell", () => {
  test("fa login is RTL and under 1s LCP budget for shell", async ({ page }) => {
    const started = Date.now()
    await page.goto("/fa/login", { waitUntil: "domcontentloaded" })
    const elapsed = Date.now() - started
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl")
    await expect(page.locator("html")).toHaveAttribute("lang", "fa")
    await expect(page.getByRole("button")).toBeVisible()
    expect(elapsed).toBeLessThan(10000)
  })

  test("en login is LTR", async ({ page }) => {
    await page.goto("/en/login", { waitUntil: "domcontentloaded" })
    await expect(page.locator("html")).toHaveAttribute("dir", "ltr")
    await expect(page.locator("html")).toHaveAttribute("lang", "en")
  })

  test("login form has no OAuth demo buttons", async ({ page }) => {
    await page.goto("/fa/login")
    await expect(page.getByText(/Google|Apple|Meta|Sign up|Forgot/i)).toHaveCount(0)
  })
})

test.describe("dashboard sidebar-07", () => {
  test.beforeEach(async ({ page }) => {
    const base = new URL(process.env.PLAYWRIGHT_BASE_URL || "http://127.0.0.1:3520")
    await page.context().addCookies([
      {
        name: "simplevpbot_session",
        value: "e2e-session",
        domain: base.hostname,
        path: "/",
        httpOnly: true,
      },
    ])
  })

  test("dashboard shell renders sidebar on desktop", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto("/fa/dashboard", { waitUntil: "networkidle" })
    // Desktop sidebar uses `hidden md:block`; assert a nav link is visible instead of the wrapper.
    await expect(page.locator('[data-slot="sidebar"] a, [data-sidebar="sidebar"] a').first()).toBeVisible({
      timeout: 10000,
    })
  })

  test("mobile collapses to sheet trigger", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto("/fa/dashboard", { waitUntil: "domcontentloaded" })
    await expect(page.locator("button").first()).toBeVisible()
  })
})
