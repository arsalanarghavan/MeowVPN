import { test, expect } from "@playwright/test"
import { ensureAdminApiSession, waitForMutate, withSession } from "./helpers"

test.describe("admin depth (API session)", () => {
  test.beforeEach(async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    if (!authed) {
      await withSession(page)
    }
  })

  test("overview tab renders OverviewAdminClient shell", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    // Next `/en/dashboard` mounts OverviewAdminClient (not a static stub).
    await expect(page.getByRole("heading", { name: /^Overview$/i })).toBeVisible({ timeout: 15000 })
    await expect(page.getByText(/Business health at a glance/i)).toBeVisible()
  })

  test("backup tab shows settings and reset-stuck control when locked", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/backup", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.getByText(/Backup|Stored|Schedule/i).first()).toBeVisible({ timeout: 15000 })

    const resetBtn = page.getByRole("button", { name: /Release backup lock|آزادسازی|گیر/i })
    if ((await resetBtn.count()) === 0) {
      test.skip(true, "Backup not stuck — reset control hidden")
    }

    const result = await waitForMutate(page, "backup_reset_stuck", async () => {
      await resetBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("marketing lifecycle confirm banner or KPIs visible", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/marketing_lifecycle", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.getByRole("heading", { name: /Customer lifecycle|چرخه عمر مشتری/i })).toBeVisible({
      timeout: 15000,
    })

    const confirmBtn = page.getByRole("button", { name: /Enable automation|فعال‌سازی/i })
    if ((await confirmBtn.count()) === 0) {
      // Already confirmed — surface KPIs only.
      await expect(page.getByText(/retention|converted|sent/i).first()).toBeVisible()
      return
    }

    const result = await waitForMutate(page, "marketing_lifecycle_confirm_defaults", async () => {
      await confirmBtn.click()
    })
    expect(result.status).toBeLessThan(500)
  })

  test("bot_ui create-group surface is interactive", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/bot_ui", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.locator("body")).toContainText(/Bot UI|منو|surface|group/i, { timeout: 15000 })

    const createGroupHeading = page.getByText(/Create group|ایجاد گروه/i).first()
    if ((await createGroupHeading.count()) === 0) {
      // Custom group surface selected, or create card gated — still require shell.
      await expect(page.locator("body")).toBeVisible()
      return
    }
    await expect(createGroupHeading).toBeVisible()
    await expect(page.getByRole("button", { name: /^Create group$|^ایجاد گروه$/i })).toBeVisible()
  })

  test("plans inbound picker label present when editing a plan", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/plans", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)
    await expect(page.getByText(/Plans|پلن/i).first()).toBeVisible({ timeout: 15000 })

    const addOrEdit = page.getByRole("button", { name: /Add plan|Edit|افزودن|ویرایش/i }).first()
    if ((await addOrEdit.count()) === 0) {
      test.skip(true, "No plan add/edit control")
    }
    await addOrEdit.click()

    const inboundLabel = page.getByText(/Inbounds \(locations\)|Inboundها/i)
    await expect(inboundLabel.first()).toBeVisible({ timeout: 10000 })
  })

  test("bots mirror section renders add control", async ({ page, request }) => {
    const authed = await ensureAdminApiSession(page, request)
    test.skip(!authed, "Laravel API unavailable or admin login failed")

    await page.goto("/en/dashboard/bots", { waitUntil: "domcontentloaded" })
    await expect(page).not.toHaveURL(/\/login/)

    const mirrorAdd = page.getByRole("button", { name: /Add mirror bot|افزودن ربات آینه/i })
    await expect(mirrorAdd).toBeVisible({ timeout: 15000 })

    await mirrorAdd.click()
    await expect(page.getByRole("dialog")).toBeVisible({ timeout: 5000 })
    await expect(page.getByRole("dialog")).toContainText(/mirror|آینه|token|توکن/i)
  })
})
