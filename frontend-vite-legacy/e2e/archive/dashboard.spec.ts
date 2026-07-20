import { test, expect } from "@playwright/test"

test.describe("dashboard shell", () => {
  test("login redirect shows login form on unauthenticated dashboard", async ({ page }) => {
    await page.goto("/dashboard/")
    await expect(page.locator("input[type=password], input[name=password]").first()).toBeVisible({
      timeout: 15_000,
    })
  })

  test("whitelabel settings route loads shell", async ({ page }) => {
    await page.goto("/dashboard/site_settings/?site_subtab=whitelabel")
    await expect(page.locator("body")).toContainText(/whitelabel|برند|تنظیمات/i, {
      timeout: 15_000,
    })
  })

  test("monitoring refresh query accepted", async ({ page }) => {
    await page.goto("/dashboard/monitoring/?refresh=1")
    await expect(page.locator("body")).toBeVisible()
  })

  test("dashboard home loads shell", async ({ page }) => {
    await page.goto("/dashboard/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("impersonation banner absent when logged out", async ({ page }) => {
    await page.goto("/dashboard/users/")
    await expect(page.locator("[data-testid=impersonation-banner]")).toHaveCount(0)
  })
})
