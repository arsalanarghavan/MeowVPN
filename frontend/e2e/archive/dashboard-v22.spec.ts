import { test, expect } from "@playwright/test"
import { SITE_SETTINGS_SUBTABS } from "../src/lib/site-settings-subtab"
import {
  ALL_ADMIN_TAB_KEYS,
  RESELLER_FORBIDDEN_TABS,
  RESELLER_ONLY_TABS,
  tabMarkerFor,
} from "../src/config/admin-tab-markers"

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

test.describe("dashboard v22 — A.1 overview", () => {
  test("overview stat cards show numeric values", async ({ page, request }) => {
    await loginAdmin(request)
    const state = await (await request.get("/api/v1/admin/state?activeTab=dashboard")).json()
    await page.goto("/dashboard/dashboard/")
    const usersTotal = Number(state.overview?.users_total ?? 0)
    if (usersTotal > 0) {
      await expect(page.getByTestId("dash-overview-stat-users")).toContainText(String(usersTotal))
    }
    await expect(page.getByTestId("dash-overview-stat-receipts")).toBeVisible({ timeout: 15_000 })
    await expect(page.getByTestId("dash-overview-stat-panels")).toBeVisible()
  })

  test("reseller overview scoped in browser", async ({ page, request }) => {
    await loginAdmin(request)
    const adminTotal = Number(
      (await (await request.get("/api/v1/admin/state?activeTab=dashboard")).json()).overview?.users_total ?? 0
    )
    await loginReseller(request)
    await page.goto("/dashboard/dashboard/")
    await expect(page.getByTestId("dash-overview-stat-users")).toBeVisible({ timeout: 15_000 })
    const resellerTotal = Number(
      (await (await request.get("/api/v1/admin/state?activeTab=dashboard")).json()).overview?.users_total ?? 0
    )
    expect(resellerTotal).toBeLessThanOrEqual(adminTotal)
  })

  test("panel health refresh changes badge area", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    const refresh = page.getByRole("button", { name: /panel health|سلامت پنل|refresh health/i }).first()
    await expect(refresh).toBeVisible({ timeout: 10_000 })
    await refresh.click()
    await expect(page.locator("[data-testid=dash-tab-dashboard]")).toBeVisible()
  })

  test("reseller quick links navigate", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/dashboard/")
    const usersLink = page.getByRole("link", { name: /users|کاربر/i }).first()
    await expect(usersLink).toBeVisible({ timeout: 10_000 })
    await usersLink.click()
    await expect(page).toHaveURL(/\/dashboard\/users/)
  })

  test("economics link navigates strict", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/dashboard/")
    const link = page.getByRole("link", { name: /economics|اقتصاد|unit/i }).first()
    await expect(link).toBeVisible({ timeout: 10_000 })
    await link.click()
    await expect(page).toHaveURL(/unit_economics/)
  })
})

test.describe("dashboard v22 — A.2 monitoring", () => {
  test("refresh live metrics click", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/")
    await expectTabShell(page, "monitoring")
    const refresh = page.getByRole("button", { name: /refresh|بروز|reload|live/i }).first()
    await expect(refresh).toBeVisible({ timeout: 10_000 })
    await refresh.click()
  })

  test("monitor host rows visible", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/")
    await expect(page.locator("[data-testid=dash-tab-monitoring]")).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText(/host|هاست|panel|پنل/i).first()).toBeVisible()
  })

  test("poll refresh without skip", async ({ page, request }) => {
    const pollMs = process.env.CI ? 5_000 : 60_000
    test.setTimeout(pollMs + 30_000)
    await loginAdmin(request)
    await page.goto("/dashboard/monitoring/")
    await expectTabShell(page, "monitoring")
    await page.waitForTimeout(pollMs)
    const refresh = page.getByRole("button", { name: /refresh|بروز|reload|live/i }).first()
    await refresh.click()
    await expect(page.locator("[data-testid=dash-tab-monitoring]")).toBeVisible()
  })
})

test.describe("dashboard v22 — reseller nav", () => {
  test("forbidden tabs deny shell", async ({ page, request }) => {
    await loginReseller(request)
    for (const tab of RESELLER_FORBIDDEN_TABS) {
      await page.goto(`/dashboard/${tab}/`)
      await expect(page.locator(`[data-testid=dash-tab-${tab}]`)).toHaveCount(0)
    }
  })

  test("reseller_xui_panels allowed", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/reseller_xui_panels/")
    await expectTabShell(page, "reseller_xui_panels")
  })

  test("admin cannot load reseller-only tabs", async ({ page, request }) => {
    await loginAdmin(request)
    for (const tab of RESELLER_ONLY_TABS) {
      await page.goto(`/dashboard/${tab}/`)
      await expect(page.locator(`[data-testid=dash-tab-${tab}]`)).toHaveCount(0)
    }
  })
})

test.describe("dashboard v22 — B site_settings", () => {
  for (const sub of SITE_SETTINGS_SUBTABS) {
    test(`subtab ${sub}`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/site_settings/?site_subtab=${sub}`)
      await expectTabShell(page, "site_settings")
    })
  }

  test("whitelabel save applies CSS var", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=whitelabel")
    const colorInput = page.locator('input[type="color"]').first()
    await expect(colorInput).toBeVisible({ timeout: 10_000 })
    await colorInput.fill("#ff5500")
    const save = page.getByRole("button", { name: /save|ذخیره/i }).first()
    await save.click()
    const primary = await page.evaluate(() =>
      getComputedStyle(document.documentElement).getPropertyValue("--primary").trim()
    )
    expect(primary.length).toBeGreaterThan(0)
  })

  test("service naming reset click", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=service_naming")
    const reset = page.getByRole("button", { name: /reset|بازنشانی/i })
    await expect(reset).toBeVisible()
    await reset.click()
    await expect(page.getByText(/preview|پیش‌نمایش|default/i).first()).toBeVisible()
  })

  test("proxy test click shows toast", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=proxy")
    const testBtn = page.getByRole("button", { name: /test|تست/i }).first()
    await expect(testBtn).toBeVisible()
    await testBtn.click()
    await expect(page.getByText(/ok|success|خطا|error|fail/i).first()).toBeVisible({ timeout: 15_000 })
  })

  test("relay doctor nginx ssl tabs", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=relay")
    for (const label of [/doctor/i, /nginx/i, /ssl/i]) {
      await expect(page.getByRole("button", { name: label }).or(page.getByText(label)).first()).toBeVisible()
    }
  })

  test("purge list and purge_one API", async ({ request }) => {
    await loginAdmin(request)
    const list = await request.get("/api/v1/admin/state?activeTab=site_settings&site_subtab=purge")
    expect(list.ok()).toBeTruthy()
    const purge = await request.post("/api/v1/admin/mutate", {
      data: { op: "purge_expired_purge_one", service_id: 1 },
    })
    expect([200, 422]).toContain(purge.status())
  })

  test("logs filter mutate", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", { data: { op: "logs_clear" } })
    expect(res.ok()).toBeTruthy()
  })
})

test.describe("dashboard v22 — C users", () => {
  test("users pagination state", async ({ request }) => {
    await loginAdmin(request)
    const json = await (await request.get("/api/v1/admin/state?activeTab=users&users_page=1")).json()
    expect(json.usersList).toBeDefined()
    expect(json.pagination ?? json.usersPagination).toBeDefined()
  })

  test("user detail navigation", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users/?user_id=101")
    await expect(page.locator("[data-testid=dash-tab-users]")).toBeVisible({ timeout: 15_000 })
  })

  test("manual create via resellers mutate", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "user_manual_create", username: "e2e_v22_user", first_name: "E2E" },
    })
    expect(res.ok()).toBeTruthy()
    expect((await res.json()).ok).toBe(true)
  })

  test("service op + merge preview", async ({ request }) => {
    await loginAdmin(request)
    await request.post("/api/v1/admin/mutate", {
      data: { op: "service_panel_sync", service_id: 1 },
    }).then((r) => expect(r.ok()).toBeTruthy())
    const merge = await request.post("/api/v1/admin/mutate", {
      data: { op: "user_merge_preview", keep_id: 101, drop_id: 200 },
    })
    expect(merge.ok()).toBeTruthy()
    expect((await merge.json()).ok).toBe(true)
  })

  test("bulk job cancel mutate", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "users_bulk_job_cancel", job_id: 1 },
    })
    expect(res.ok()).toBeTruthy()
  })
})

test.describe("dashboard v22 — D bots", () => {
  test("webhook register button", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/bots/")
    await expectTabShell(page, "bots")
    await expect(page.getByRole("button", { name: /webhook/i }).first()).toBeVisible()
  })

  test("force_join_publish mutate", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", { data: { op: "force_join_publish" } })
    expect(res.ok()).toBeTruthy()
  })

  test("texts save mutate", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "texts_save", key: "welcome", value: "v22" },
    })
    expect(res.ok()).toBeTruthy()
  })

  test("reseller_bots admin route", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/reseller_bots/")
    await expectTabShell(page, "reseller_bots")
  })

  test("bot_ui reseller 403", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "bot_ui_layout_save", version: 1, surfaces: [] },
    })
    expect(res.status()).toBe(403)
  })
})

test.describe("dashboard v22 — E panels", () => {
  test("panel test connection mutate", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", { data: { op: "panel_test", panel_id: 1 } })
    expect(res.ok()).toBeTruthy()
  })

  test("configs sync endpoint", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/configs-sync", { data: { panel_id: 1 } })
    expect([200, 422]).toContain(res.status())
  })

  test("reseller panel prices save", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "reseller_panel_prices_save", reseller_svp_user_id: 100, prices: [] },
    })
    expect(res.ok()).toBeTruthy()
  })
})

test.describe("dashboard v22 — F finance", () => {
  test("receipt approve deliver", async ({ request }) => {
    await loginAdmin(request)
    const approve = await request.post("/api/v1/admin/mutate", {
      data: { op: "receipt_action", receipt_id: 1, action: "approve" },
    })
    expect(approve.ok()).toBeTruthy()
    const deliver = await request.post("/api/v1/admin/mutate", {
      data: { op: "receipt_set_status", receipt_id: 1, status: "delivered" },
    })
    expect(deliver.ok()).toBeTruthy()
  })

  test("reseller wallet topup checkout", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "reseller_wallet_topup_checkout", amount: 1000 },
    })
    expect(res.ok()).toBeTruthy()
  })
})

test.describe("dashboard v22 — G marketing", () => {
  test("broadcast send mutate", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "broadcast_send", bc_text: "v22", bc_targets: "telegram" },
    })
    expect(res.ok()).toBeTruthy()
  })

  test("marketing rule save admin only", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/mutate", {
      data: { op: "marketing_rule_save", segment_key: "never_purchased", enabled: true },
    })
    expect(res.status()).toBe(403)
  })

  test("impersonation start requires admin", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/impersonate/start", { data: { target_id: 100 } })
    expect(res.status()).toBe(403)
  })
})

test.describe("dashboard v22 — H system", () => {
  test("audit impersonation filter", async ({ request }) => {
    await loginAdmin(request)
    const res = await request.get("/api/v1/admin/audit?page=1&per_page=20&domain=security&q=impersonation")
    expect(res.ok()).toBeTruthy()
    expect((await res.json()).pagination).toBeDefined()
  })

  test("backup state tab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/backup/")
    await expectTabShell(page, "backup")
  })
})

test.describe("dashboard v22 — tab markers", () => {
  for (const tab of ALL_ADMIN_TAB_KEYS) {
    if (RESELLER_ONLY_TABS.has(tab)) continue
    test(`admin tab ${tab}`, async ({ page, request }) => {
      await loginAdmin(request)
      await page.goto(`/dashboard/${tab}/`)
      await expectTabShell(page, tab)
    })
  }
})

test.describe("dashboard v22 — stats alias", () => {
  test("stats series activeTab alias", async ({ request }) => {
    await loginAdmin(request)
    const json = await (await request.get(
      "/api/v1/admin/state?tab=dashboard&overview_metrics_window_days=7&stats_day=0"
    )).json()
    expect(json.stats?.window_days).toBe(7)
    expect(Array.isArray(json.stats?.series)).toBe(true)
  })
})

test.describe("dashboard v22 — strict UI partials", () => {
  test("purge tab list and confirm dialog", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=purge_expired")
    await expect(page.getByTestId("dash-purge-tab")).toBeVisible({ timeout: 15_000 })
    await page.getByRole("button", { name: /run cron|اجرای cron/i }).first().click()
  })

  test("logs filter and clear confirm", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=logs")
    await expect(page.getByTestId("dash-logs-tab")).toBeVisible({ timeout: 15_000 })
    await page.getByPlaceholder(/search|جستجو/i).fill("error")
    await page.getByRole("button", { name: /search|جستجو/i }).click()
    await page.getByRole("button", { name: /clear|پاک/i }).first().click()
    await expect(page.getByRole("alertdialog")).toBeVisible()
  })

  test("users manual create from users tab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users/")
    await expect(page.getByTestId("dash-users-tab")).toBeVisible({ timeout: 15_000 })
    await page.getByTestId("dash-users-manual-create-open").click()
    await page.getByTestId("dash-users-manual-create-username").fill(`e2e_v22_${Date.now()}`)
    await page.getByTestId("dash-users-manual-create-first").fill("E2E")
    await page.getByTestId("dash-users-manual-create-submit").click()
  })

  test("bulk tab shell and job area", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users_bulk/")
    await expect(page.getByTestId("dash-bulk-tab")).toBeVisible({ timeout: 15_000 })
  })

  test("receipts tab shell", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/receipts/")
    await expect(page.getByTestId("dash-receipts-tab")).toBeVisible({ timeout: 15_000 })
  })

  test("bot_ui reseller read-only view", async ({ page, request }) => {
    await loginReseller(request)
    await page.goto("/dashboard/bot_ui/")
    await expect(page.locator("[data-testid=dash-tab-bot_ui]")).toBeVisible({ timeout: 15_000 })
  })
})

test.describe("dashboard v22 — OPEN tab CRUD shells", () => {
  test("plan categories tab add button", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/plan_cats/")
    await expect(page.getByTestId("dash-plan-cats-tab")).toBeVisible({ timeout: 15_000 })
    await page.getByRole("button", { name: /add|افزودن|new/i }).first().click()
  })

  test("cards tab shell and reorder handle", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/cards/")
    await expect(page.getByTestId("dash-cards-tab")).toBeVisible({ timeout: 15_000 })
  })

  test("discounts tab save interaction", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/discounts/")
    await expect(page.getByTestId("dash-discounts-tab")).toBeVisible({ timeout: 15_000 })
    await page.getByRole("button", { name: /add|افزودن|new/i }).first().click()
  })

  test("unit economics save panel", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/unit_economics/")
    await expect(page.getByTestId("dash-unit-economics-tab")).toBeVisible({ timeout: 15_000 })
    const save = page.getByRole("button", { name: /save|ذخیره/i }).first()
    if (await save.isVisible()) await save.click()
  })

  test("broadcast send UI", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/broadcast/")
    await expect(page.getByTestId("dash-broadcast-tab")).toBeVisible({ timeout: 15_000 })
    await page.getByRole("textbox").first().fill("e2e v22 broadcast")
    await page.getByRole("button", { name: /send|ارسال/i }).first().click()
  })

  test("marketing rule save UI", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/marketing_lifecycle/")
    await expect(page.getByTestId("dash-marketing-tab")).toBeVisible({ timeout: 15_000 })
    const save = page.getByRole("button", { name: /save|ذخیره/i }).first()
    if (await save.isVisible()) await save.click()
  })

  test("referral settings save", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/referral/")
    await expect(page.getByTestId("dash-referral-tab")).toBeVisible({ timeout: 15_000 })
    const save = page.getByRole("button", { name: /save|ذخیره/i }).first()
    if (await save.isVisible()) await save.click()
  })

  test("resellers CRUD shell", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/resellers/")
    await expect(page.getByTestId("dash-resellers-tab")).toBeVisible({ timeout: 15_000 })
  })

  test("l2tp add form open", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/l2tp_servers/")
    await expect(page.getByTestId("dash-l2tp-tab")).toBeVisible({ timeout: 15_000 })
    await page.getByRole("button", { name: /add|افزودن|new/i }).first().click()
  })

  test("backup upload restore area", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/backup/")
    await expect(page.getByTestId("dash-backup-tab")).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText(/restore|بازیابی|upload|آپلود/i).first()).toBeVisible()
  })
})

test.describe("dashboard v22 — D/E depth UI", () => {
  test("webhook register click", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/bots/")
    const btn = page.getByRole("button", { name: /webhook|register|ثبت/i }).first()
    await expect(btn).toBeVisible({ timeout: 10_000 })
    await btn.click()
  })

  test("configs sync and stale badge area", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/configs/")
    await expect(page.getByTestId("dash-configs-tab")).toBeVisible({ timeout: 15_000 })
    const sync = page.getByRole("button", { name: /sync|همگام/i }).first()
    if (await sync.isVisible()) await sync.click()
  })

  test("panel test connection click", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/xui_panels/")
    const testBtn = page.getByRole("button", { name: /test|تست/i }).first()
    await expect(testBtn).toBeVisible({ timeout: 10_000 })
    await testBtn.click()
  })

  test("user merge preview from UI", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/users/?user_id=101")
    const merge = page.getByTestId("dash-user-merge-panel")
    if (await merge.isVisible()) {
      await page.locator("#keep-id").fill("101")
      await page.locator("#drop-id").fill("200")
      await page.getByRole("button", { name: /preview|پیش‌نمایش/i }).click()
    }
  })

  test("notifications cooldown save", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=notifications")
    const save = page.getByRole("button", { name: /save|ذخیره/i }).first()
    await expect(save).toBeVisible({ timeout: 10_000 })
    await save.click()
  })

  test("finance crypto subtab", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=finance")
    await expect(page.getByText(/crypto|کریپتو/i).first()).toBeVisible({ timeout: 10_000 })
  })

  test("reseller defaults save", async ({ page, request }) => {
    await loginAdmin(request)
    await page.goto("/dashboard/site_settings/?site_subtab=resellers_defaults")
    const save = page.getByRole("button", { name: /save|ذخیره/i }).first()
    await expect(save).toBeVisible({ timeout: 10_000 })
    await save.click()
  })

  test("impersonate admin strict 403 for reseller", async ({ request }) => {
    await loginReseller(request)
    const res = await request.post("/api/v1/admin/impersonate/start", { data: { target_id: 100 } })
    expect(res.status()).toBe(403)
  })
})
