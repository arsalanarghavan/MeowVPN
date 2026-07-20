import { defineConfig, devices } from "@playwright/test"

const baseURL = process.env.PLAYWRIGHT_BASE_URL || "http://localhost:8080"

export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  testMatch: process.env.CI
    ? /dashboard-v23\.spec\.ts|dashboard-auth-v23\.spec\.ts|dashboard-v24-qa\.spec\.ts|dashboard-v25-depth\.spec\.ts|dashboard-session-v27\.spec\.ts/
    : /dashboard-v23\.spec\.ts|dashboard-auth-v23\.spec\.ts|dashboard-v24-qa\.spec\.ts|dashboard-v25-depth\.spec\.ts|dashboard-session-v27\.spec\.ts/,
  reporter: "list",
  use: {
    baseURL,
    trace: "on-first-retry",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
})
