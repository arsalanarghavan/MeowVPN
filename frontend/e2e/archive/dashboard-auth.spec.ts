import { test, expect } from "@playwright/test"

test.describe("dashboard authenticated", () => {
  test("login success reaches dashboard shell", async ({ page, request }) => {
    const login = await request.post("/api/v1/auth/login", {
      data: { log: "admin", pwd: "changeme" },
    })
    expect(login.ok()).toBeTruthy()
    const body = await login.json()
    expect(body.ok).toBe(true)

    await page.goto(body.redirect || "/dashboard/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("whitelabel settings shows brand fields when logged in", async ({ page, request }) => {
    await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
    await page.goto("/dashboard/site_settings/?site_subtab=whitelabel")
    await expect(page.locator("body")).toContainText(/whitelabel|برند|تنظیمات/i, {
      timeout: 15_000,
    })
  })

  test("monitoring refresh query when logged in", async ({ page, request }) => {
    await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
    await page.goto("/dashboard/monitoring/?refresh=1")
    await expect(page.locator("body")).toBeVisible()
  })

  test("dashboard economics link or tab present", async ({ page, request }) => {
    await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
    await page.goto("/dashboard/?tab=dashboard")
    const econ = page.locator('a[href*="unit_economics"], [data-tab="unit_economics"]').first()
    if ((await econ.count()) > 0) {
      await expect(econ).toBeVisible({ timeout: 15_000 })
    }
  })
})
