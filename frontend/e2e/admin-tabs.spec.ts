import { test, expect } from "@playwright/test"

async function withSession(page: import("@playwright/test").Page) {
  const base = new URL(process.env.PLAYWRIGHT_BASE_URL || "http://127.0.0.1:3520")
  await page.context().addCookies([
    {
      name: "simplevpbot_session",
      value: "e2e-session",
      domain: base.hostname,
      path: "/",
      httpOnly: true,
    },
  ])
}

const TABS = [
  { path: "/fa/dashboard/payments", expectText: /پرداخت|Payments|رسید|receipts/i },
  { path: "/fa/dashboard/site_settings", expectText: /تنظیمات|Settings|Whitelabel|برند/i },
  { path: "/fa/dashboard/xui_panels", expectText: /پنل|panel|3x-ui|PasarGuard/i },
  { path: "/fa/dashboard/panel_financial_reports", expectText: /مالی|financial|گزارش/i },
  { path: "/fa/dashboard/users", expectText: /کاربر|Users/i },
  { path: "/fa/dashboard/plans", expectText: /پلن|Plans/i },
]

test.describe("admin tabs shell (session cookie)", () => {
  for (const tab of TABS) {
    test(`renders ${tab.path}`, async ({ page }) => {
      await withSession(page)
      await page.goto(tab.path, { waitUntil: "domcontentloaded" })
      await expect(page).not.toHaveURL(/\/login/)
      await expect(page.locator("body")).toBeVisible()
      await expect(page.locator("body")).toContainText(tab.expectText)
    })
  }
})

test.describe("auth guard", () => {
  test("dashboard redirects to login without session", async ({ page }) => {
    await page.context().clearCookies()
    const res = await page.goto("/fa/dashboard", { waitUntil: "domcontentloaded" })
    // Prefer checking final URL; allow redirect response as well.
    expect(res?.status() === 307 || res?.status() === 302 || page.url().includes("/login")).toBeTruthy()
    await expect(page).toHaveURL(/\/fa\/login/)
  })
})

test.describe("portal", () => {
  test("portal route renders", async ({ page }) => {
    await page.goto("/fa/portal", { waitUntil: "domcontentloaded" })
    await expect(page.locator("body")).toBeVisible()
  })
})
