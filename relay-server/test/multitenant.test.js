import { describe, it, before, after } from "node:test"
import assert from "node:assert/strict"
import { mkdirSync, rmSync, writeFileSync } from "node:fs"
import { join } from "node:path"
import { tmpdir } from "node:os"

const tmp = join(tmpdir(), `svp-relay-mt-${process.pid}`)
const tenantsDir = join(tmp, "tenants")

before(() => {
  mkdirSync(tenantsDir, { recursive: true })
  process.env.TENANTS_DIR = tenantsDir
  process.env.DATA_DIR = tmp
  process.env.RELAY_SHARED_SECRET = "tenant-a-secret-32chars-minimum!!"
})

after(() => {
  rmSync(tmp, { recursive: true, force: true })
  delete process.env.TENANTS_DIR
  delete process.env.DATA_DIR
})

describe("multi-tenant store", () => {
  it("upserts two tenants with different secrets", async () => {
    const { clearConfigCache, upsertTenantFromPayload, findTenantBySecret, listTenants } =
      await import("../dist/store.js")
    clearConfigCache()

    upsertTenantFromPayload("tenant-a-secret-32chars-minimum!!", {
      tenant_id: "site-a",
      config_version: "1",
      laravel_base_url: "https://laravel-a.example.com",
      relay_public_url: "https://tg-a.example.com",
      main: {
        telegram_token: "1:A",
        telegram_webhook_secret: "sec-a",
        telegram_secret_header: "",
        telegram_enabled: true,
        enabled: true,
        admin_telegram_ids: [],
      },
      resellers: [],
    })

    upsertTenantFromPayload("tenant-b-secret-32chars-minimum!!", {
      tenant_id: "site-b",
      config_version: "1",
      laravel_base_url: "https://laravel-b.example.com",
      relay_public_url: "https://tg-b.example.com",
      main: {
        telegram_token: "1:B",
        telegram_webhook_secret: "sec-b",
        telegram_secret_header: "",
        telegram_enabled: true,
        enabled: true,
        admin_telegram_ids: [],
      },
      resellers: [],
    })

    assert.equal(listTenants().length, 2)
    const ta = findTenantBySecret("tenant-a-secret-32chars-minimum!!")
    const tb = findTenantBySecret("tenant-b-secret-32chars-minimum!!")
    assert.equal(ta?.tenant_id, "site-a")
    assert.equal(tb?.tenant_id, "site-b")
  })

  it("resolves webhook by secret across tenants", async () => {
    const { clearConfigCache, findMainWebhook } = await import("../dist/store.js")
    clearConfigCache()
    const m = findMainWebhook("sec-b")
    assert.ok(m)
    assert.equal(m.tenant.tenant_id, "site-b")
  })
})
