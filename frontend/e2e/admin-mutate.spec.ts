import { test, expect } from "@playwright/test"
import { ensureAdminApiSession, waitForMutate, withSession } from "./helpers"

test.describe("admin API login", () => {
  test("real session reaches dashboard when Laravel API is up", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toBeVisible()
  })

  test("admin state responds after API login", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    const state = await request.get(
      `${process.env.PLAYWRIGHT_API_ORIGIN || "http://127.0.0.1:8080"}/api/v1/admin/state?activeTab=dashboard`,
      { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } }
    )
    expect(state.ok()).toBeTruthy()
  })
})

test.describe("admin mutate smokes", () => {
  test.beforeEach(async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    if (!authed) {
      await withSession(page)
    }
  })

  test("site_settings proxy tab fires telegram_proxy_test mutate", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/site_settings?site_subtab=proxy", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const testBtn = page.getByRole("button", { name: /تست اتصال|Test connection/i })
    await expect(testBtn).toBeVisible({ timeout: 10000 })

    const result = await waitForMutate(page, "telegram_proxy_test", async () => {
      await testBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("xui_panels orphan scan section is interactive", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/xui_panels", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.getByText(/orphan|بدون‌صاحب/i)).toBeVisible({ timeout: 10000 })

    const scanBtn = page.getByRole("button", { name: /^اسکن$|^Scan$/i })
    await expect(scanBtn).toBeVisible()
    await expect(scanBtn).toBeDisabled()

    const panelSelect = page.locator("#orphan_panel")
    if ((await panelSelect.locator("option").count()) > 1) {
      await panelSelect.selectOption({ index: 1 })
    }
    await page.locator("#orphan_user").fill("1")
    await expect(scanBtn).toBeEnabled()

    const scanResponse = page.waitForResponse(
      (res) => res.url().includes("/admin/panel/orphan-clients/scan") && res.request().method() === "POST",
      { timeout: 15000 }
    )
    await scanBtn.click()
    const res = await scanResponse
    expect(res.status()).toBeLessThan(500)
  })

  test("xui_panels panel-wide orphan purge fires configs_panel_del_orphans", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/xui_panels", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const panelSelect = page.locator("#orphan_panel")
    if ((await panelSelect.locator("option").count()) > 1) {
      await panelSelect.selectOption({ index: 1 })
    } else {
      test.skip(true, "No panel rows for orphan purge")
    }

    const purgeBtn = page.getByRole("button", { name: /orphan.*v3|بدون‌صاحب.*v3|del.*orphan/i })
    if ((await purgeBtn.count()) === 0) {
      test.skip(true, "Panel-wide orphan purge button not shown")
    }

    page.once("dialog", (dialog) => void dialog.accept())
    const result = await waitForMutate(page, "configs_panel_del_orphans", async () => {
      await purgeBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("xui_panels merge preview fires panel_merge_preview when two panels exist", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/xui_panels", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const mergeBtn = page.getByRole("button", { name: /merge|مرج|انتقال/i }).first()
    if ((await mergeBtn.count()) === 0) {
      test.skip(true, "No panel rows in admin state")
    }

    const previewPromise = page.waitForResponse(
      (res) => {
        if (!res.url().includes("/admin/mutate") || res.request().method() !== "POST") return false
        try {
          return (res.request().postDataJSON() as { op?: string })?.op === "panel_merge_preview"
        } catch {
          return false
        }
      },
      { timeout: 15000 }
    )

    await mergeBtn.click()
    const targetSelect = page.locator("select").filter({ has: page.locator("option") }).last()
    if ((await targetSelect.locator("option").count()) < 2) {
      test.skip(true, "Need at least two same-provider panels for merge preview")
    }
    await targetSelect.selectOption({ index: 1 })

    const previewRes = await previewPromise
    expect(previewRes.status()).toBeLessThan(500)
  })

  test("xui_panels panel test fires panel_test mutate when a row exists", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/xui_panels", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const testBtn = page.getByRole("button", { name: /تست اتصال|Test connection/i }).first()
    if ((await testBtn.count()) === 0) {
      test.skip(true, "No panel rows in admin state")
    }
    await expect(testBtn).toBeVisible({ timeout: 10000 })

    const result = await waitForMutate(page, "panel_test", async () => {
      await testBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("payments receipts tab loads and approve fires receipt_action when pending row exists", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/payments", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.getByText(/رسید|Receipts|پرداخت/i)).toBeVisible({ timeout: 10000 })

    const approveBtn = page.getByRole("button", { name: /تأیید|Approve/i }).first()
    if ((await approveBtn.count()) === 0) {
      test.skip(true, "No reviewable receipts in admin state")
    }

    const result = await waitForMutate(page, "receipt_action", async () => {
      await approveBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("cards rial_settings sandbox save fires mutate", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/cards", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const zarinpalBtn = page.getByRole("button", { name: /زرین‌پال|Zarinpal/i }).first()
    await expect(zarinpalBtn).toBeVisible({ timeout: 10000 })
    await zarinpalBtn.click()

    const merchantInput = page.getByLabel(/merchant|مرchant|شناسه/i).first()
    if ((await merchantInput.count()) > 0) {
      await merchantInput.fill("e2e-test-merchant")
    }

    const saveBtn = page.getByRole("button", { name: /ذخیره|Save/i }).last()
    const result = await waitForMutate(page, "rial_settings", async () => {
      await saveBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("cards crypto_settings save fires mutate", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/cards", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const nowBtn = page.getByRole("button", { name: /NOWPayments|nowpayments/i }).first()
    if ((await nowBtn.count()) === 0) {
      test.skip(true, "NOWPayments gateway tile not visible")
    }
    await nowBtn.click()

    const saveBtn = page.getByRole("button", { name: /ذخیره|Save/i }).last()
    const result = await waitForMutate(page, "crypto_settings", async () => {
      await saveBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("configs tab sync posts configs-sync endpoint", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/configs", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    // Auto-sync on tab open hits REST (not a mutate op).
    const syncResponse = page.waitForResponse(
      (res) => res.url().includes("/dashboard/admin/configs-sync") && res.request().method() === "POST",
      { timeout: 15000 }
    ).catch(() => null)

    await page.reload({ waitUntil: "domcontentloaded" })
    const res = await syncResponse
    if (!res) {
      test.skip(true, "configs-sync not observed (no panels / sync gated)")
    }
    expect(res!.status()).toBeLessThan(500)
  })

  test("configs bulk reset fires configs_bulk_reset_traffic when rows selected", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/fa/dashboard/configs", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const rowCheckbox = page.locator('input[type="checkbox"]').nth(1)
    if ((await rowCheckbox.count()) === 0) {
      test.skip(true, "No config client rows")
    }
    await rowCheckbox.check()

    const resetBtn = page.getByRole("button", { name: /reset.*traffic|ریست.*ترافیک/i }).first()
    if ((await resetBtn.count()) === 0 || (await resetBtn.isDisabled())) {
      test.skip(true, "Bulk reset not available for current selection")
    }

    page.once("dialog", (dialog) => void dialog.accept())
    const result = await waitForMutate(page, "configs_bulk_reset_traffic", async () => {
      await resetBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })
})
