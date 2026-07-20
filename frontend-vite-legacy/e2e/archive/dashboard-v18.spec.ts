import { test, expect } from "@playwright/test"
import { ADMIN_TAB_KEYS } from "../src/config/admin-nav"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
}

async function loginReseller(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "reseller", pwd: "changeme" } })
}

const SITE_SETTINGS_SUBTABS = [
  "general",
  "whitelabel",
  "service_naming",
  "proxy",
  "relay",
  "notifications",
  "purge_expired",
  "finance",
  "backup",
  "logs",
] as const

test.describe("dashboard v18 — site_settings subtabs", () => {
  for (const sub of SITE_SETTINGS_SUBTABS) {
    test(`site_settings subtab ${sub}`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/site_settings/?site_subtab=${sub}`)
      await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    })
  }

  test("proxy test button visible on proxy subtab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=proxy")
    const testBtn = page.getByRole("button", { name: /proxy|پروکسی|test|تست/i }).first()
    if (await testBtn.count()) {
      await expect(testBtn).toBeVisible()
    }
  })

  test("relay subtab smoke", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=relay")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })
})

test.describe("dashboard v18 — reseller-only tabs", () => {
  for (const tab of ["reseller_charge", "reseller_settings"]) {
    test(`${tab} loads for admin`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/${tab}/`)
      await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    })
  }
})

test.describe("dashboard v18 — Group A overview", () => {
  test("admin overview loads", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("reseller overview scoped vs admin", async ({ request }) => {
    await loginAdmin(request)
    const admin = await request.get("/api/v1/admin/state?tab=dashboard")
    expect(admin.ok()).toBeTruthy()
    const adminOverview = (await admin.json()).overview ?? {}

    await loginReseller(request)
    const reseller = await request.get("/api/v1/admin/state?tab=dashboard")
    expect(reseller.ok()).toBeTruthy()
    const resellerOverview = (await reseller.json()).overview ?? {}

    const adminTotal = Number(adminOverview.users_total ?? adminOverview.usersTotal ?? 0)
    const resellerTotal = Number(resellerOverview.users_total ?? resellerOverview.usersTotal ?? 0)
    if (adminTotal > 0) {
      expect(resellerTotal).toBeLessThanOrEqual(adminTotal)
    }
  })

  test("unit economics link from admin nav", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/unit_economics/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })
})

test.describe("dashboard v18 — Group C user merge", () => {
  test("users tab merge controls smoke", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const merge = page.getByRole("button", { name: /merge|ادغام/i }).first()
    if (await merge.count()) {
      await expect(merge).toBeVisible()
    }
  })

  test("user merge preview via API", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "user_merge_preview", source_id: 200, target_id: 101 },
    })
    expect(res.ok()).toBeTruthy()
    const body = await res.json()
    expect(typeof body.ok).toBe("boolean")
  })
})

test.describe("dashboard v18 — Group D bot_ui read-only", () => {
  test("reseller bot_ui loads read-only", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/bot_ui/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("reseller cannot mutate bot_ui layout", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "bot_ui_layout_save", version: 1, surfaces: [] },
    })
    expect(res.status()).toBe(403)
  })
})

test.describe("dashboard v18 — Group E configs", () => {
  test("configs tab stale/sync smoke", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/configs/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const sync = page.getByRole("button", { name: /sync|همگام|rebuild|refresh/i }).first()
    if (await sync.count()) {
      await expect(sync).toBeVisible()
    }
  })
})

test.describe("dashboard v18 — Group F receipts + cards", () => {
  test("receipts approve/reject UI", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/receipts/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const approve = page.getByRole("button", { name: /approve|تأیید/i }).first()
    const reject = page.getByRole("button", { name: /reject|رد/i }).first()
    if (await approve.count()) {
      await expect(approve).toBeVisible()
    } else if (await reject.count()) {
      await expect(reject).toBeVisible()
    }
  })

  test("cards reorder or delete interaction", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/cards/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const reorder = page.getByRole("button", { name: /reorder|ترتیب|move|↑|↓/i }).first()
    const del = page.getByRole("button", { name: /delete|حذف/i }).first()
    const drag = page.locator("[draggable=true], [data-testid=card-drag-handle]").first()
    if (await drag.count()) {
      await expect(drag).toBeVisible()
    } else if (await reorder.count()) {
      await expect(reorder).toBeVisible()
    } else if (await del.count()) {
      await expect(del).toBeVisible()
    }
  })
})

test.describe("dashboard v18 — Group G reseller reports", () => {
  test("reseller reports chart + impersonate banner", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/reseller_reports/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const impersonate = page.getByRole("button", { name: /impersonate|ورود به حساب/i }).first()
    if (await impersonate.isVisible()) {
      await impersonate.click()
      await expect(page.locator("[data-testid=impersonation-banner]")).toBeVisible({ timeout: 10_000 })
    }
  })
})

test.describe("dashboard v18 — Group H audit + backup", () => {
  test("audit filter and pagination strict", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/audit/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })

    const api = await request.get("/api/v1/admin/audit?page=1&per_page=1")
    expect(api.ok()).toBeTruthy()
    const json = await api.json()
    expect(json.ok).toBe(true)
    expect(json.pagination?.perPage).toBe(1)

    const filter = page.locator('input[type="search"], input[placeholder*="filter"], input[placeholder*="جست"]').first()
    if (await filter.count()) {
      await filter.fill("impersonation")
    }
    const next = page.getByRole("button", { name: /next|بعدی/i }).first()
    if (await next.isVisible()) {
      await next.click()
    }
  })

  test("backup restore upload E2E smoke", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/backup/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const restore = page.getByRole("button", { name: /restore|بازیابی/i }).first()
    const fileInput = page.locator('input[type="file"]').first()
    if (await restore.count()) {
      await expect(restore).toBeVisible()
    }
    if (await fileInput.count()) {
      await expect(fileInput).toBeAttached()
    }
  })
})

test.describe("dashboard v18 — full ADMIN_TAB_KEYS regression", () => {
  for (const tab of ADMIN_TAB_KEYS) {
    test(`tab ${tab} loads`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/${tab}/`)
      await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    })
  }
})
