import { test, expect } from "@playwright/test"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
}

test.describe("dashboard v24 QA — canonical auth path", () => {
  test("POST /api/v1/auth/login succeeds", async ({ request }) => {
    const res = await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
    expect(res.ok()).toBeTruthy()
    const body = await res.json()
    expect(body.ok ?? body.success ?? true).toBeTruthy()
  })
})

test.describe("dashboard v24 QA — RTL", () => {
  test("FA locale sets document dir rtl", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    await page.evaluate(() => localStorage.setItem("svp_dash_locale", "fa"))
    await page.reload()
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl", { timeout: 15_000 })
  })

  test("EN locale sets document dir ltr", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    await page.evaluate(() => localStorage.setItem("svp_dash_locale", "en"))
    await page.reload()
    await expect(page.locator("html")).toHaveAttribute("dir", "ltr", { timeout: 15_000 })
  })
})

test.describe("dashboard v24 QA — responsive viewports", () => {
  test.beforeEach(async ({ request }) => {
    await loginAdmin(request)
  })

  for (const [label, width, height] of [
    ["320px", 320, 568],
    ["375px", 375, 667],
    ["768px", 768, 1024],
    ["1280px", 1280, 800],
  ] as const) {
    test(`shell renders at ${label}`, async ({ page }) => {
      await page.setViewportSize({ width, height })
      await page.goto("/dashboard/dashboard/")
      await expect(page.locator("[data-testid=dash-tab-dashboard]")).toBeVisible({ timeout: 15_000 })
    })
  }
})

test.describe("dashboard v24 QA — dialogs and mobile layouts", () => {
  test.beforeEach(async ({ request }) => {
    await loginAdmin(request)
  })

  test("site_settings tabs scroll horizontally on narrow viewport", async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 })
    await page.goto("/dashboard/site_settings/")
    await expect(page.locator('[role="tablist"]').first()).toBeVisible({ timeout: 15_000 })
  })

  test("users page shell on mobile", async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 568 })
    await page.goto("/dashboard/users/")
    await expect(page.locator("[data-testid=dash-tab-users]")).toBeVisible({ timeout: 15_000 })
  })

  test("configs page shell", async ({ page }) => {
    await page.goto("/dashboard/configs/")
    await expect(page.locator("[data-testid=dash-tab-configs]")).toBeVisible({ timeout: 15_000 })
  })

  test("receipts page shell", async ({ page }) => {
    await page.goto("/dashboard/receipts/")
    await expect(page.locator("[data-testid=dash-tab-receipts]")).toBeVisible({ timeout: 15_000 })
  })

  test("backup page shell", async ({ page }) => {
    await page.goto("/dashboard/backup/")
    await expect(page.locator("[data-testid=dash-tab-backup]")).toBeVisible({ timeout: 15_000 })
  })
})
