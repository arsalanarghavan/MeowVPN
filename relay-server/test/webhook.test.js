import { describe, it, before, after } from "node:test"
import assert from "node:assert/strict"
import express from "express"
import { mkdirSync, rmSync } from "node:fs"
import { join } from "node:path"
import { tmpdir } from "node:os"

const tmp = join(tmpdir(), `svp-relay-test-${process.pid}`)
const tenantsDir = join(tmp, "tenants")

before(() => {
  mkdirSync(tenantsDir, { recursive: true })
  process.env.TENANTS_DIR = tenantsDir
  process.env.DATA_DIR = tmp
  process.env.RELAY_MASTER_SECRET = "test-secret-32-chars-minimum-ok!!"
  process.env.RELAY_SHARED_SECRET = "test-secret-32-chars-minimum-ok!!"
})

after(() => {
  rmSync(tmp, { recursive: true, force: true })
  delete process.env.TENANTS_DIR
  delete process.env.DATA_DIR
})

async function seedTenant() {
  const { clearConfigCache, upsertTenantFromPayload } = await import("../dist/store.js")
  clearConfigCache()
  upsertTenantFromPayload("test-secret-32-chars-minimum-ok!!", {
    tenant_id: "test",
    config_version: "1",
    laravel_base_url: "https://laravel.example.com",
    relay_public_url: "https://relay.example.com",
    main: {
      telegram_token: "1:ABC",
      telegram_webhook_secret: "wh-sec",
      telegram_secret_header: "",
      telegram_enabled: true,
      enabled: true,
      admin_telegram_ids: [],
    },
    resellers: [],
  })
}

describe("laravelForwardBase", () => {
  it("uses laravel_base_url for forward base", async () => {
    await seedTenant()
    const { getTenantById } = await import("../dist/store.js")
    const { laravelForwardBase } = await import("../dist/util/webhook-url.js")
    const t = getTenantById("test")
    assert.ok(t)
    assert.equal(laravelForwardBase(t), "https://laravel.example.com")
  })
})

describe("webhook ingress", () => {
  it("acks quickly with ok:true for valid secret", async () => {
    await seedTenant()
    const { webhookRouter } = await import("../dist/routes/webhook.js")

    const app = express()
    app.use(express.json())
    app.use(webhookRouter)

    const server = await new Promise((resolve) => {
      const s = app.listen(0, () => resolve(s))
    })
    const addr = server.address()
    const port = typeof addr === "object" && addr ? addr.port : 0

    const res = await fetch(`http://127.0.0.1:${port}/webhook/telegram/wh-sec`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ update_id: 1 }),
    })
    const body = await res.json()
    assert.equal(res.status, 200)
    assert.equal(body.ok, true)

    await new Promise((r) => server.close(r))
  })

  it("rejects invalid webhook secret", async () => {
    await seedTenant()
    const { webhookRouter } = await import("../dist/routes/webhook.js")

    const app = express()
    app.use(express.json())
    app.use(webhookRouter)

    const server = await new Promise((resolve) => {
      const s = app.listen(0, () => resolve(s))
    })
    const addr = server.address()
    const port = typeof addr === "object" && addr ? addr.port : 0

    const res = await fetch(`http://127.0.0.1:${port}/webhook/telegram/wrong`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: "{}",
    })
    assert.equal(res.status, 403)

    await new Promise((r) => server.close(r))
  })
})

describe("internal auth", () => {
  it("requires X-SVP-Relay-Secret header", async () => {
    await seedTenant()
    const { internalRouter } = await import("../dist/routes/internal.js")

    const app = express()
    app.use(express.json())
    app.use(internalRouter)

    const server = await new Promise((resolve) => {
      const s = app.listen(0, () => resolve(s))
    })
    const addr = server.address()
    const port = typeof addr === "object" && addr ? addr.port : 0

    const bad = await fetch(`http://127.0.0.1:${port}/internal/health`)
    assert.equal(bad.status, 403)

    const ok = await fetch(`http://127.0.0.1:${port}/internal/health`, {
      headers: { "X-SVP-Relay-Secret": "test-secret-32-chars-minimum-ok!!" },
    })
    const body = await ok.json()
    assert.equal(ok.status, 200)
    assert.equal(body.ok, true)

    await new Promise((r) => server.close(r))
  })
})
