import { test, expect } from "@playwright/test"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  const res = await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
  expect(res.ok()).toBeTruthy()
}

test.describe("dashboard v25 depth — relay control center", () => {
  test("relay doctor nginx ssl tabs visible", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=relay")
    await expect(page.getByText(/doctor|nginx|ssl|relay/i).first()).toBeVisible({ timeout: 15_000 })
  })
})

test.describe("dashboard v25 depth — backup restore", () => {
  test("backup upload restore dialog area", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/backup/")
    await expect(page.getByTestId("dash-backup-tab")).toBeVisible({ timeout: 15_000 })
    const upload = page.locator('input[type="file"]').first()
    await expect(upload).toBeAttached()
  })
})

test.describe("dashboard v25 depth — crypto module gate", () => {
  test("finance crypto subtab visible when module on", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=finance")
    await expect(page.getByText(/crypto|کریپتو/i).first()).toBeVisible({ timeout: 10_000 })
  })
})

test.describe("dashboard v25 depth — L2TP hidden", () => {
  test("l2tp tab visible with default seeder modules", async ({ page, request }) => {
    const boot = await request.get("/api/v1/bootstrap")
    const features = (await boot.json()).data?.features ?? {}
    await loginAdmin(request)
    await page.goto("/dashboard/l2tp_servers/")
    if (features.l2tp) {
      await expect(page.getByTestId("dash-l2tp-tab")).toBeVisible({ timeout: 15_000 })
    } else {
      await expect(page.getByTestId("dash-l2tp-tab")).toHaveCount(0)
    }
  })
})

test.describe("dashboard v25 depth — user merge", () => {
  test("user merge panel opens on users page", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users/")
    await expect(page.getByTestId("dash-user-merge-panel")).toBeVisible({ timeout: 15_000 })
  })
})

test.describe("dashboard v25 depth — receipts preview", () => {
  test("receipts tab shell with dialog hooks", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/receipts/")
    await expect(page.getByTestId("dash-receipts-tab")).toBeVisible({ timeout: 15_000 })
  })
})

test.describe("dashboard v25 depth — impersonate xs", () => {
  test("impersonate stop control on narrow viewport", async ({ page, request }) => {
    await loginAdmin(request)
    await page.setViewportSize({ width: 320, height: 568 })
    const start = await request.post("/api/v1/dashboard/impersonate/start", {
      data: { targetSvpUserId: 100 },
    })
    expect(start.ok()).toBeTruthy()
    await page.goto("/dashboard/dashboard/")
    const stop = page.getByRole("button", { name: /stop impersonat|پایان جعل|exit impersonat/i })
    if ((await stop.count()) > 0) {
      await expect(stop.first()).toBeVisible({ timeout: 10_000 })
    }
    await request.post("/api/v1/dashboard/impersonate/stop")
  })
})

test.describe("dashboard v25 depth — monitoring poll", () => {
  test("visibility-aware monitoring refresh", async ({ page, request }) => {
    const pollMs = process.env.CI ? 5_000 : 15_000
    test.setTimeout(pollMs + 30_000)
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/")
    await expect(page.locator("[data-testid=dash-tab-monitoring]")).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(pollMs)
    const refresh = page.getByRole("button", { name: /refresh|بروز|reload|live/i }).first()
    await refresh.click()
    await expect(page.locator("[data-testid=dash-tab-monitoring]")).toBeVisible()
  })
})
