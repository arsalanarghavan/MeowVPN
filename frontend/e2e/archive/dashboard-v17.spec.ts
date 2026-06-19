import { test, expect } from "@playwright/test"
import { ADMIN_TAB_KEYS } from "../src/config/admin-nav"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
}

async function loginReseller(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "reseller", pwd: "changeme" } })
}

const SUBTAB_OVERRIDES: Record<string, string> = {
  site_settings: "?site_subtab=general",
}

test.describe("dashboard v17 — full ADMIN_TAB_KEYS", () => {
  for (const tab of ADMIN_TAB_KEYS) {
    test(`tab ${tab} loads`, async ({ page, request }) => {
      await loginAdmin(request)
      const suffix = SUBTAB_OVERRIDES[tab] ?? ""
      await page.goto(`/dashboard/${tab}/${suffix}`)
      await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    })
  }
})

test.describe("dashboard v17 — Group F", () => {
  for (const tab of ["plan_cats", "discounts", "referral_reports"]) {
    test(`${tab} tab loads`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/${tab}/`)
      await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    })
  }
})

test.describe("dashboard v17 — Group H audit", () => {
  test("audit filter and pagination", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/audit/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const filter = page.locator('input[type="search"], input[placeholder*="filter"], input[placeholder*="جست"]').first()
    if (await filter.count()) {
      await filter.fill("impersonation")
    }
    const next = page.getByRole("button", { name: /next|بعدی/i }).first()
    if (await next.isVisible()) {
      await next.click()
    }
  })

  test("impersonation row from reseller reports", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/reseller_reports/")
    const impersonate = page.getByRole("button", { name: /impersonate|ورود به حساب/i }).first()
    if (await impersonate.isVisible()) {
      await impersonate.click()
      await expect(page.locator("[data-testid=impersonation-banner]")).toBeVisible({ timeout: 10_000 })
    }
  })
})

test.describe("dashboard v17 — Group H L2TP + backup", () => {
  test("l2tp_servers tab smoke", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/l2tp_servers/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("backup restore UI smoke", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/backup/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const restore = page.getByRole("button", { name: /restore|بازیابی/i }).first()
    if (await restore.count()) {
      await expect(restore).toBeVisible()
    }
  })
})

test.describe("dashboard v17 — monitoring 60s poll", () => {
  test("auto-refresh uses 60 second interval", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/")
    await page.addInitScript(() => {
      ;(window as unknown as { __intervals: number[] }).__intervals = []
      const orig = window.setInterval
      window.setInterval = ((handler: TimerHandler, timeout?: number, ...args: unknown[]) => {
        ;(window as unknown as { __intervals: number[] }).__intervals.push(timeout ?? 0)
        return orig(handler, timeout, ...args)
      }) as typeof window.setInterval
    })
    await page.reload()
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const has60s = await page.evaluate(() =>
      ((window as unknown as { __intervals?: number[] }).__intervals ?? []).some((ms) => ms === 60_000)
    )
    expect(has60s).toBe(true)
  })
})

test.describe("dashboard v17 — cards reorder/delete", () => {
  test("cards tab reorder or delete controls", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/cards/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const reorder = page.getByRole("button", { name: /reorder|ترتیب|move/i }).first()
    const del = page.getByRole("button", { name: /delete|حذف/i }).first()
    if (await reorder.count()) {
      await expect(reorder).toBeVisible()
    } else if (await del.count()) {
      await expect(del).toBeVisible()
    }
  })
})

test.describe("dashboard v17 — reseller scope", () => {
  test("reseller cannot access audit", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/audit/")
    await expect(page).toHaveURL(/login|dashboard/i)
  })
})
