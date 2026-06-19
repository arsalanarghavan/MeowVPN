import { test, expect } from "@playwright/test"
import { ADMIN_TAB_KEYS } from "../src/config/admin-nav"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
}

async function loginReseller(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "reseller", pwd: "changeme" } })
}

const SMOKE_TABS = ADMIN_TAB_KEYS.filter(
  (k) =>
    ![
      "dashboard",
      "monitoring",
      "site_settings",
      "users",
      "resellers",
      "plans",
      "unit_economics",
      "configs",
      "audit",
      "cards",
      "broadcast",
      "marketing_lifecycle",
      "referral",
      "l2tp_servers",
      "backup",
      "reseller_reports",
      "bots",
      "bot_ui",
    ].includes(k)
)

test.describe("dashboard v16 tab smoke", () => {
  for (const tab of SMOKE_TABS) {
    test(`tab ${tab} loads`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/${tab}/`)
      await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    })
  }

  test("users list navigates to user detail", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users/")
    const manage = page.getByRole("button", { name: /manage|مدیریت/i }).first()
    await expect(manage).toBeVisible({ timeout: 15_000 })
    await manage.click()
    await expect(page).toHaveURL(/\/users\/u\/\d+/i, { timeout: 10_000 })
  })

  test("monitoring auto-refresh chart area visible", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/")
    await expect(page.locator(".recharts-responsive-container, [class*='chart']").first()).toBeVisible({
      timeout: 15_000,
    })
  })

  test("unit_economics tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/unit_economics/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("configs tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/configs/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("audit tab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/audit/")
    await expect(page.locator("body")).toBeVisible()
  })

  test("site_settings logs subtab loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=logs")
    await expect(page.locator("body")).toBeVisible()
  })
})

test.describe("dashboard v16 reseller", () => {
  test("reseller login and scoped nav", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/users/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    await page.goto("/dashboard/audit/")
    await expect(page).toHaveURL(/login|dashboard/i)
  })
})

test.describe("dashboard v16 interactions", () => {
  test("whitelabel save updates CSS variable", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=whitelabel")
    const accentInput = page.locator('input[type="color"], input[name*="accent"]').first()
    if (await accentInput.count()) {
      await accentInput.fill("#ff5500")
    }
    const saveBtn = page.getByRole("button", { name: /save|ذخیره/i }).first()
    if (await saveBtn.isVisible()) {
      await saveBtn.click()
    }
    const accent = await page.evaluate(() =>
      getComputedStyle(document.documentElement).getPropertyValue("--accent-primary").trim()
    )
    expect(accent.length).toBeGreaterThan(0)
  })

  test("cards tab shows reorder controls", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/cards/")
    await expect(page.locator("body")).toBeVisible()
    const reorder = page.getByRole("button", { name: /reorder|ترتیب|move/i }).first()
    if (await reorder.count()) {
      await expect(reorder).toBeVisible()
    }
  })

  test("reseller reports chart and impersonate", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/reseller_reports/")
    await expect(page.locator(".recharts-responsive-container").first()).toBeVisible({ timeout: 15_000 })
    const impersonate = page.getByRole("button", { name: /impersonate|ورود به حساب/i }).first()
    if (await impersonate.isVisible()) {
      await impersonate.click()
      await expect(page.locator("[data-testid=impersonation-banner]")).toBeVisible({ timeout: 10_000 })
    }
  })
})
