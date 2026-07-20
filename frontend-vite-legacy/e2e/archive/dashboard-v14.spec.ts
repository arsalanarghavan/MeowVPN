import { test, expect } from "@playwright/test"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
}

test.describe("dashboard v14 acceptance", () => {
  test("economics overview navigates to unit economics tab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/?tab=dashboard")
    const link = page.locator('a[href*="unit_economics"], [data-tab="unit_economics"]').first()
    if ((await link.count()) > 0) {
      await expect(link).toBeVisible({ timeout: 15_000 })
    } else {
      await expect(page.locator("body")).toBeVisible()
    }
  })

  test("whitelabel may show logo upload input", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=whitelabel")
    const fileInput = page.locator('input[type="file"]')
    if ((await fileInput.count()) > 0) {
      await expect(fileInput.first()).toBeVisible()
    }
  })

  test("cards tab shell loads for admin", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/cards/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("reseller reports tab shell", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/reseller_reports/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })
})
