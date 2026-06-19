import { test, expect } from "@playwright/test"

test.describe("dashboard session paths v27", () => {
  test.beforeEach(async ({ page, context }) => {
    await page.goto("/dashboard/login/")
    await page.getByLabel(/username|نام کاربری|log/i).fill("admin")
    await page.getByLabel(/password|رمز/i).fill("changeme")
    await page.getByRole("button", { name: /login|ورود/i }).click()
    await expect(page).toHaveURL(/\/dashboard\//, { timeout: 15_000 })
    const cookies = await context.cookies()
    await context.addCookies(cookies)
  })

  test("me/state returns isLoggedIn", async ({ request, context }) => {
    const cookies = await context.cookies()
    const cookieHeader = cookies.map((c) => `${c.name}=${c.value}`).join("; ")
    const state = await (
      await request.get("/api/v1/me/state", { headers: { Cookie: cookieHeader } })
    ).json()
    expect(state.isLoggedIn).toBe(true)
  })

  test("dashboard/persona accepts POST with session", async ({ request, context }) => {
    const cookies = await context.cookies()
    const cookieHeader = cookies.map((c) => `${c.name}=${c.value}`).join("; ")
    const res = await request.post("/api/v1/dashboard/persona", {
      data: { persona: "admin" },
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        Cookie: cookieHeader,
      },
    })
    expect(res.ok()).toBeTruthy()
    const body = await res.json()
    expect(body.ok).toBe(true)
  })

  test("dashboard/ui-preferences accepts POST", async ({ request, context }) => {
    const cookies = await context.cookies()
    const cookieHeader = cookies.map((c) => `${c.name}=${c.value}`).join("; ")
    const res = await request.post("/api/v1/dashboard/ui-preferences", {
      data: { ui_theme: "dark", ui_accent: "blue" },
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        Cookie: cookieHeader,
      },
    })
    expect(res.ok()).toBeTruthy()
  })
})
