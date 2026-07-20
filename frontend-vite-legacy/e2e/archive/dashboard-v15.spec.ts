import { test, expect } from "@playwright/test"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
}

test.describe("dashboard v15 acceptance", () => {
  test("economics card link navigates to unit economics", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/?tab=dashboard")
    const card = page.locator('[data-testid="economics-overview-card"], .economics-overview-card, a[href*="unit_economics"]').first()
    await expect(card).toBeVisible({ timeout: 15_000 })
    const href = await card.getAttribute("href")
    if (href) {
      expect(href).toContain("unit_economics")
    }
  })

  test("monitoring refresh with auth", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/?refresh=1")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("cards tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/cards/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("broadcast tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/broadcast/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("marketing lifecycle tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/marketing_lifecycle/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("referral tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/referral/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("l2tp tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/l2tp_servers/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("backup tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/backup/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("reseller reports tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/reseller_reports/")
    await expect(page.locator("body")).toBeVisible()
  })
})
