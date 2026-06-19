import { test, expect } from "@playwright/test"

test.describe("dashboard auth v22", () => {
  test("login success redirects to dashboard", async ({ page }) => {
    await page.goto("/dashboard/login/")
    await page.getByLabel(/username|نام کاربری|log/i).fill("admin")
    await page.getByLabel(/password|رمز/i).fill("changeme")
    await page.getByRole("button", { name: /login|ورود/i }).click()
    await expect(page).toHaveURL(/\/dashboard\//, { timeout: 15_000 })
  })

  test("bad credentials show error", async ({ page }) => {
    await page.goto("/dashboard/login/")
    await page.getByLabel(/username|نام کاربری|log/i).fill("admin")
    await page.getByLabel(/password|رمز/i).fill("wrong-password")
    await page.getByRole("button", { name: /login|ورود/i }).click()
    await expect(page.getByText(/invalid|نامعتبر|failed|خطا/i).first()).toBeVisible({ timeout: 10_000 })
  })

  test("real session isLoggedIn after login", async ({ page, request }) => {
    await page.goto("/dashboard/login/")
    await page.getByLabel(/username|نام کاربری|log/i).fill("admin")
    await page.getByLabel(/password|رمز/i).fill("changeme")
    await page.getByRole("button", { name: /login|ورود/i }).click()
    await expect(page).toHaveURL(/\/dashboard\//, { timeout: 15_000 })
    const state = await (await request.get("/api/v1/me/state")).json()
    expect(state.isLoggedIn).toBe(true)
    expect(state.user?.username ?? state.user?.log).toBeTruthy()
  })

  test("csrf cookie fetched before login API", async ({ page }) => {
    const csrfCalls: string[] = []
    await page.route("**/sanctum/csrf-cookie", async (route) => {
      csrfCalls.push(route.request().url())
      await route.continue()
    })
    await page.goto("/dashboard/login/")
    await page.getByLabel(/username|نام کاربری|log/i).fill("admin")
    await page.getByLabel(/password|رمز/i).fill("changeme")
    await page.getByRole("button", { name: /login|ورود/i }).click()
    await expect(page).toHaveURL(/\/dashboard\//, { timeout: 15_000 })
    expect(csrfCalls.length).toBeGreaterThan(0)
    expect(csrfCalls[0]).toMatch(/sanctum\/csrf-cookie/)
  })
})
