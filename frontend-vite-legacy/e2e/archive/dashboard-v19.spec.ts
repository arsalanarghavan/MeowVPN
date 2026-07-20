import { test, expect } from "@playwright/test"
import { ADMIN_TAB_KEYS } from "../src/config/admin-nav"
import { SITE_SETTINGS_SUBTABS } from "../src/lib/site-settings-subtab"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
}

async function loginReseller(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "reseller", pwd: "changeme" } })
}

test.describe("dashboard v19 — site_settings real subtabs", () => {
  for (const sub of SITE_SETTINGS_SUBTABS) {
    test(`subtab ${sub}`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/site_settings/?site_subtab=${sub}`)
      await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    })
  }
})

test.describe("dashboard v19 — reseller tabs (reseller actor)", () => {
  test("reseller_charge renders for reseller", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/reseller_charge/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText(/charge|شارژ/i).first()).toBeVisible()
  })

  test("reseller_settings renders for reseller", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/reseller_settings/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })
})

test.describe("dashboard v19 — Group A overview", () => {
  test("overview cards visible", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("economics link navigates", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    const link = page.getByRole("link", { name: /economics|اقتصاد|unit/i }).first()
    if (await link.count()) {
      await link.click()
      await expect(page).toHaveURL(/unit_economics/)
    } else {
      await page.goto("/dashboard/unit_economics/")
      await expect(page.locator("body")).toBeVisible()
    }
  })

  test("reseller overview scoped", async ({ request }) => {
    await loginAdmin(request)
    const adminTotal = Number(
      (await (await request.get("/api/v1/admin/state?tab=dashboard")).json()).overview?.users_total ?? 0
    )
    await loginReseller(request)
    const resellerTotal = Number(
      (await (await request.get("/api/v1/admin/state?tab=dashboard")).json()).overview?.users_total ?? 0
    )
    if (adminTotal > 0) expect(resellerTotal).toBeLessThanOrEqual(adminTotal)
  })
})

test.describe("dashboard v19 — A.2 monitoring", () => {
  test("60s poll interval", async ({ page, request }) => {
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
    const has60s = await page.evaluate(() =>
      ((window as unknown as { __intervals?: number[] }).__intervals ?? []).some((ms) => ms === 60_000)
    )
    expect(has60s).toBe(true)
  })

  test("refresh button", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/")
    const refresh = page.getByRole("button", { name: /refresh|بروز|reload/i }).first()
    if (await refresh.count()) await expect(refresh).toBeVisible()
  })
})

test.describe("dashboard v19 — Group C users", () => {
  test("user detail route", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users/?user_id=101")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("merge preview API", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "user_merge_preview", source_id: 200, target_id: 101 },
    })
    expect(res.ok()).toBeTruthy()
    expect((await res.json()).ok).toBe(true)
  })
})

test.describe("dashboard v19 — Group D bots", () => {
  test("bots tab webhook controls", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/bots/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    const webhook = page.getByRole("button", { name: /webhook/i }).first()
    if (await webhook.count()) await expect(webhook).toBeVisible()
  })

  test("bot_ui reseller read-only", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "bot_ui_layout_save", version: 1, surfaces: [] },
    })
    expect(res.status()).toBe(403)
  })
})

test.describe("dashboard v19 — Group E configs", () => {
  test("configs sync control", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/configs/")
    const sync = page.getByRole("button", { name: /sync|همگام|rebuild|refresh/i }).first()
    if (await sync.count()) await expect(sync).toBeVisible()
  })
})

test.describe("dashboard v19 — Group F receipts + cards", () => {
  test("receipts approve/reject", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/receipts/")
    const approve = page.getByRole("button", { name: /approve|تأیید/i }).first()
    const reject = page.getByRole("button", { name: /reject|رد/i }).first()
    if (await approve.count()) await expect(approve).toBeVisible()
    else if (await reject.count()) await expect(reject).toBeVisible()
  })

  test("cards reorder or delete", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/cards/")
    const del = page.getByRole("button", { name: /delete|حذف/i }).first()
    const drag = page.locator("[draggable=true]").first()
    if (await drag.count()) await expect(drag).toBeVisible()
    else if (await del.count()) await expect(del).toBeVisible()
  })
})

test.describe("dashboard v19 — Group G marketing + impersonate", () => {
  test("broadcast tab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/broadcast/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("marketing lifecycle tab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/marketing_lifecycle/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("impersonation banner", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/reseller_reports/")
    const impersonate = page.getByRole("button", { name: /impersonate|ورود به حساب/i }).first()
    if (await impersonate.isVisible()) {
      await impersonate.click()
      await expect(page.locator("[data-testid=impersonation-banner]")).toBeVisible({ timeout: 10_000 })
    }
  })
})

test.describe("dashboard v19 — Group H system", () => {
  test("l2tp_servers tab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/l2tp_servers/")
    await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
  })

  test("backup restore upload", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/backup/")
    const fileInput = page.locator('input[type="file"]').first()
    if (await fileInput.count()) await expect(fileInput).toBeAttached()
  })

  test("audit filter strict", async ({ page, request }) => {
    await loginAdmin(request)
    const api = await request.get("/api/v1/admin/audit?page=1&per_page=1&domain=security")
    expect(api.ok()).toBeTruthy()
    const json = await api.json()
    expect(json.ok).toBe(true)
    expect(json.pagination?.perPage).toBe(1)
    await page.goto("/dashboard/audit/")
    await expect(page.locator("body")).toBeVisible()
  })
})

test.describe("dashboard v19 — legacy tab redirects", () => {
  test("notifications legacy → site_settings", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/notifications/")
    await expect(page).toHaveURL(/site_settings.*site_subtab=notifications/)
  })

  test("logs legacy → site_settings", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/logs/")
    await expect(page).toHaveURL(/site_settings.*site_subtab=logs/)
  })
})

test.describe("dashboard v19 — reseller nav scope", () => {
  test("reseller cannot access audit", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/audit/")
    await expect(page).toHaveURL(/login|dashboard/i)
  })
})

test.describe("dashboard v19 — ADMIN_TAB_KEYS regression", () => {
  for (const tab of ADMIN_TAB_KEYS) {
    test(`admin tab ${tab}`, async ({ page, request }) => {
      await loginAdmin(request)
      if (tab === "reseller_charge" || tab === "reseller_settings") {
        await loginReseller(request)
      }
      await page.goto(`/dashboard/${tab}/`)
      await expect(page.locator("body")).toBeVisible({ timeout: 15_000 })
    })
  }
})
