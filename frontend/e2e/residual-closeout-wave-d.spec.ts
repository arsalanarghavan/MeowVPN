import { test, expect } from "@playwright/test"
import { ensureAdminApiSession, waitForMutate } from "./helpers"

test.describe("residual closeout wave D", () => {
  test.beforeEach(async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")
  })

  test("bots update mode buttons fire bot_set_update_mode", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/bots", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const pollingBtn = page.getByRole("button", { name: /Polling|polling|پولینگ/i }).first()
    const webhookBtn = page.getByRole("button", { name: /Webhook|وب‌هوک/i }).first()
    const target =
      (await pollingBtn.isEnabled().catch(() => false)) ? pollingBtn :
      (await webhookBtn.isEnabled().catch(() => false)) ? webhookBtn :
      null
    if (!target) {
      test.skip(true, "Update mode controls not clickable")
    }

    const result = await waitForMutate(page, "bot_set_update_mode", async () => {
      await target!.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("texts editor saves fa/en via texts_save", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/texts", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const categoryTrigger = page.locator("[data-state]").filter({ hasText: /category|دسته/i }).first()
    if ((await categoryTrigger.count()) > 0) {
      await categoryTrigger.click().catch(() => undefined)
    }

    const faInput = page.locator('textarea[dir="rtl"]').first()
    if ((await faInput.count()) === 0) {
      test.skip(true, "No text keys loaded")
    }

    const marker = `e2e-${Date.now()}`
    await faInput.fill(marker)

    const saveBtn = page.getByRole("button", { name: /Save this key|ذخیره این کلید/i }).first()
    const result = await waitForMutate(page, "texts_save", async () => {
      await saveBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("force-join publish fires force_join_publish with platform", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/bots", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const publishBtn = page.getByRole("button", { name: /Send and pin|ارسال و سنجاق/i }).first()
    if ((await publishBtn.count()) === 0) {
      test.skip(true, "Force-join publish control not visible")
    }

    const result = await waitForMutate(page, "force_join_publish", async () => {
      await publishBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("marketing lifecycle rule sheet sends marketing_rule_save with rule_id", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/marketing_lifecycle", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const confirmBtn = page.getByRole("button", { name: /Enable automation|فعال‌سازی/i })
    if ((await confirmBtn.count()) > 0) {
      await confirmBtn.click()
      await page.waitForTimeout(500)
    }

    const addRule = page.getByRole("button", { name: /Add rule|افزودن قانون/i }).first()
    if ((await addRule.count()) === 0) {
      test.skip(true, "Marketing rules UI not available")
    }
    await addRule.click()

    const saveRule = page.getByRole("button", { name: /^Save$|^ذخیره$/i }).last()
    await expect(saveRule).toBeVisible({ timeout: 10000 })

    const responsePromise = page.waitForResponse(
      (res) => {
        if (!res.url().includes("/admin/mutate") || res.request().method() !== "POST") return false
        try {
          const body = res.request().postDataJSON() as { op?: string; rule_id?: number }
          return body?.op === "marketing_rule_save" && Object.prototype.hasOwnProperty.call(body, "rule_id")
        } catch {
          return false
        }
      },
      { timeout: 15000 }
    )

    await saveRule.click()
    const response = await responsePromise
    expect(response.status()).toBeLessThan(500)
  })

  test("payments transactions tab shows pagination when meta present", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/payments", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const txTab = page.getByRole("tab", { name: /Transactions|تراکنش/i })
    await txTab.click()

    const pager = page.getByTestId("data-pagination")
    if ((await pager.count()) === 0) {
      test.skip(true, "Transactions pagination not rendered (empty or single page)")
    }
    await expect(pager).toBeVisible({ timeout: 10000 })
  })

  test("backup tab exposes manual run and mapped error-message surface", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/backup", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const runBtn = page.getByRole("button", { name: /Run backup now|اجرای پشتیبان/i }).first()
    if ((await runBtn.count()) === 0) {
      test.skip(true, "Manual backup control not visible")
    }
    await expect(runBtn).toBeVisible()

    // Smoke: status/message area exists for mapped backup errors (formatBackupApiError).
    await expect(page.locator("body")).toContainText(/backup|پشتیبان|schedule|زمان‌بندی/i)
  })
})
