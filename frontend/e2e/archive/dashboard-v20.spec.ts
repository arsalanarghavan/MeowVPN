import { test, expect } from "@playwright/test"
import { SITE_SETTINGS_SUBTABS } from "../src/lib/site-settings-subtab"
import {
  ALL_ADMIN_TAB_KEYS,
  RESELLER_FORBIDDEN_TABS,
  RESELLER_ONLY_TABS,
  tabMarkerFor,
} from "../src/config/admin-tab-markers"
import { ADMIN_ONLY_TAB_KEYS } from "../src/config/admin-nav"

async function loginAdmin(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "admin", pwd: "changeme" } })
}

async function loginReseller(request: import("@playwright/test").APIRequestContext) {
  await request.post("/api/v1/auth/login", { data: { log: "reseller", pwd: "changeme" } })
}

async function expectTabShell(page: import("@playwright/test").Page, tab: string) {
  await expect(page.locator(`[data-testid=dash-tab-${tab}]`)).toBeVisible({ timeout: 15_000 })
  const marker = tabMarkerFor(tab)
  await expect(page.locator(`[data-testid=dash-tab-${tab}]`)).toContainText(marker)
}

test.describe("dashboard v20 — Group A overview", () => {
  test("overview cards show numeric stats", async ({ page, request }) => {
    await loginAdmin(request)
    const state = await (await request.get("/api/v1/admin/state?activeTab=dashboard")).json()
    await page.goto("/dashboard/dashboard/")
    await expectTabShell(page, "dashboard")
    const total = Number(state.overview?.users_total ?? 0)
    if (total > 0) {
      await expect(page.getByText(String(total)).first()).toBeVisible()
    }
  })

  test("reseller overview scoped strict", async ({ request }) => {
    await loginAdmin(request)
    const adminTotal = Number(
      (await (await request.get("/api/v1/admin/state?activeTab=dashboard")).json()).overview?.users_total ?? 0
    )
    await loginReseller(request)
    const resellerTotal = Number(
      (await (await request.get("/api/v1/admin/state?activeTab=dashboard")).json()).overview?.users_total ?? 0
    )
    expect(resellerTotal).toBeLessThanOrEqual(adminTotal)
    if (adminTotal > 0) expect(resellerTotal).toBeGreaterThan(0)
  })

  test("economics link navigates (no fallback)", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    const link = page.getByRole("link", { name: /economics|اقتصاد|unit/i }).first()
    await expect(link).toBeVisible({ timeout: 10_000 })
    await link.click()
    await expect(page).toHaveURL(/unit_economics/)
  })

  test("panel health refresh click", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    const refresh = page.getByRole("button", { name: /panel health|سلامت پنل|refresh health/i }).first()
    if (await refresh.count()) {
      await refresh.click()
      await expect(page.locator("[data-testid=dash-tab-dashboard]")).toBeVisible()
    }
  })
})

test.describe("dashboard v20 — A.2 monitoring", () => {
  test("refresh live metrics click", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/")
    await expectTabShell(page, "monitoring")
    const refresh = page.getByRole("button", { name: /refresh|بروز|reload|live/i }).first()
    await expect(refresh).toBeVisible({ timeout: 10_000 })
    await refresh.click()
  })
})

test.describe("dashboard v20 — reseller nav matrix", () => {
  test("reseller forbidden tabs redirect or deny", async ({ page, request }) => {
    await loginReseller(request)
    for (const tab of RESELLER_FORBIDDEN_TABS) {
      await page.goto(`/dashboard/${tab}/`)
      const onForbidden = page.locator(`[data-testid=dash-tab-${tab}]`)
      await expect(onForbidden).toHaveCount(0)
    }
  })

  test("admin cannot load reseller-only tabs", async ({ page, request }) => {
    await loginAdmin(request)
    for (const tab of RESELLER_ONLY_TABS) {
      await page.goto(`/dashboard/${tab}/`)
      await expect(page.locator(`[data-testid=dash-tab-${tab}]`)).toHaveCount(0)
    }
  })

  test("reseller charge and settings", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/reseller_charge/")
    await expectTabShell(page, "reseller_charge")
    await page.goto("/dashboard/reseller_settings/")
    await expectTabShell(page, "reseller_settings")
  })
})

test.describe("dashboard v20 — site_settings", () => {
  for (const sub of SITE_SETTINGS_SUBTABS) {
    test(`subtab ${sub}`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/site_settings/?site_subtab=${sub}`)
      await expectTabShell(page, "site_settings")
    })
  }

  test("service naming reset button", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=service_naming")
    const reset = page.getByRole("button", { name: /reset|بازنشانی/i })
    await expect(reset).toBeVisible()
  })

  test("proxy test button", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=proxy")
    const testBtn = page.getByRole("button", { name: /test|تست/i }).first()
    await expect(testBtn).toBeVisible()
  })

  test("relay control center tabs", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=relay")
    await expect(page.getByText(/doctor|nginx|ssl|relay|واسط/i).first()).toBeVisible()
  })
})

test.describe("dashboard v20 — users", () => {
  test("user detail route", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users/?user_id=101")
    await expect(page.locator("[data-testid=dash-tab-users]")).toBeVisible()
  })

  test("merge preview API keep_id/drop_id", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "user_merge_preview", keep_id: 101, drop_id: 200 },
    })
    expect(res.ok()).toBeTruthy()
    expect((await res.json()).ok).toBe(true)
  })
})

test.describe("dashboard v20 — bots", () => {
  test("webhook button visible", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/bots/")
    await expectTabShell(page, "bots")
    await expect(page.getByRole("button", { name: /webhook/i }).first()).toBeVisible()
  })

  test("bot_ui reseller 403", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "bot_ui_layout_save", version: 1, surfaces: [] },
    })
    expect(res.status()).toBe(403)
  })

  test("reseller_bots as reseller", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/bots/")
    await expect(page.locator("[data-testid=dash-tab-bots]")).toBeVisible({ timeout: 15_000 })
  })
})

test.describe("dashboard v20 — finance + system", () => {
  test("configs tab marker", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/configs/")
    await expectTabShell(page, "configs")
  })

  test("receipts tab marker", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/receipts/")
    await expectTabShell(page, "receipts")
  })

  test("broadcast + marketing", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/broadcast/")
    await expectTabShell(page, "broadcast")
    await page.goto("/dashboard/marketing_lifecycle/")
    await expectTabShell(page, "marketing_lifecycle")
  })

  test("audit API pagination", async ({ request }) => {
    await loginAdmin(request)
    const api = await request.get("/api/v1/admin/audit?page=1&per_page=1&domain=security")
    expect(api.ok()).toBeTruthy()
    expect((await api.json()).pagination?.perPage).toBe(1)
  })

  test("reseller_xui_panels tab", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/reseller_xui_panels/")
    await expectTabShell(page, "reseller_xui_panels")
  })

  test("l2tp tab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/l2tp_servers/")
    await expectTabShell(page, "l2tp_servers")
  })
})

test.describe("dashboard v20 — tab content markers", () => {
  for (const tab of ALL_ADMIN_TAB_KEYS) {
    if (RESELLER_ONLY_TABS.has(tab)) continue
    test(`admin tab ${tab} content marker`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/${tab}/`)
      await expectTabShell(page, tab)
    })
  }
})

test.describe("dashboard v20 — legacy redirects", () => {
  test("notifications → site_settings", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/notifications/")
    await expect(page).toHaveURL(/site_settings.*site_subtab=notifications/)
  })
})

test.describe("dashboard v20 — stats in admin state", () => {
  test("stats series with activeTab alias", async ({ request }) => {
    await loginAdmin(request)
    const json = await (await request.get(
      "/api/v1/admin/state?tab=dashboard&overview_metrics_window_days=7&stats_day=0"
    )).json()
    expect(json.stats?.window_days).toBe(7)
    expect(Array.isArray(json.stats?.series)).toBe(true)
  })
})
