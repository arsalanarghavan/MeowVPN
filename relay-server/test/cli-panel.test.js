import { describe, it, before, after } from "node:test"
import assert from "node:assert/strict"
import { mkdirSync, writeFileSync, rmSync, mkdtempSync } from "node:fs"
import { join } from "node:path"
import { tmpdir } from "node:os"
import { spawnSync } from "node:child_process"

const tmp = join(tmpdir(), `svp-cli-test-${process.pid}`)
const tenantsDir = join(tmp, "tenants")

before(() => {
  mkdirSync(tenantsDir, { recursive: true })
  writeFileSync(
    join(tmp, ".env"),
    `PORT=8799
RELAY_MASTER_SECRET=test-secret-32-chars-minimum-ok!!
DATA_DIR=${tmp}
TENANTS_DIR=${tenantsDir}
`,
  )
  process.env.SVP_RELAY_DIR = tmp
  process.env.TENANTS_DIR = tenantsDir
  process.env.DATA_DIR = tmp
  process.env.RELAY_MASTER_SECRET = "test-secret-32-chars-minimum-ok!!"
})

after(() => {
  rmSync(tmp, { recursive: true, force: true })
  delete process.env.SVP_RELAY_DIR
  delete process.env.TENANTS_DIR
  delete process.env.DATA_DIR
})

describe("cli panel helpers", () => {
  it("help mentions panel", () => {
    const r = spawnSync("node", ["dist/cli/svp-relay.js", "help"], {
      cwd: join(import.meta.dirname, ".."),
      encoding: "utf8",
    })
    assert.equal(r.status, 0)
    assert.match(r.stdout, /panel/)
  })

  it("loadInstallEnv reads .env from install root when cwd differs", async () => {
    const other = mkdtempSync(join(tmpdir(), "svp-other-"))
    const { loadInstallEnv, resolveInstallRoot } = await import("../dist/cli/paths.js")
    delete process.env.PORT
    loadInstallEnv()
    assert.equal(resolveInstallRoot(), tmp)
    assert.equal(process.env.PORT, "8799")
    rmSync(other, { recursive: true, force: true })
  })

  it("getStatusSnapshot returns expected shape", async () => {
    const { clearConfigCache, upsertTenantFromPayload } = await import("../dist/store.js")
    clearConfigCache()
    upsertTenantFromPayload("test-secret-32-chars-minimum-ok!!", {
      tenant_id: "cli-test",
      config_version: "1",
      laravel_base_url: "https://laravel.example.com",
      relay_public_url: "https://relay.example.com",
      main: {
        telegram_token: "1:ABC",
        telegram_webhook_secret: "wh",
        telegram_secret_header: "",
        telegram_enabled: true,
        enabled: true,
        admin_telegram_ids: [],
      },
      resellers: [],
    })
    const { getStatusSnapshot } = await import("../dist/cli/commands/status.js")
    const snap = await getStatusSnapshot()
    assert.equal(snap.ok, true)
    assert.equal(snap.port, 8799)
    assert.equal(snap.tenant_count, 1)
    assert.ok(Array.isArray(snap.domains))
    assert.ok(Array.isArray(snap.tenants))
    assert.equal(snap.install_root, tmp)
  })
})
