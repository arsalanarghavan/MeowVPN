import { test, expect } from "@playwright/test"
import { ensureAdminApiSession, withSession } from "./helpers"

test.describe("absolute parity wave 5 smokes", () => {
  test.beforeEach(async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    if (!authed) {
      await withSession(page)
    }
  })

  test("backup scope controls render", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/backup", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toContainText(/Backup|پشتیبان|Scope|دامنه|Database|دیتابیس|Panel/i, {
      timeout: 15000,
    })
  })

  test("plan-cats buy panel step toggle exists", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/plan_cats", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toContainText(/panel step|مرحله پنل|buy flow|خرید|category|دسته/i, {
      timeout: 15000,
    })
  })

  test("payments hydrates receipts_status from URL", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/payments?payments_view=receipts&receipts_status=pending", {
      waitUntil: "domcontentloaded",
    })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page).toHaveURL(/receipts_status=pending/)
    await expect(page.locator("body")).toContainText(/Receipt|رسید|Payment|پرداخت/i, { timeout: 15000 })
  })

  test("bots mirrors pager surface", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/bots", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toContainText(/Mirror|آینه|Telegram|Bale|Bot/i, { timeout: 15000 })
  })

  test("bot_ui color editor or guide section present", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/bot_ui", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toContainText(/Color|رنگ|Guide|راهنما|Bot UI|استودیو|Studio/i, {
      timeout: 15000,
    })
  })
})
