import { describe, it, before, after } from "node:test"
import assert from "node:assert/strict"
import express from "express"
import { mkdirSync, rmSync, writeFileSync } from "node:fs"
import { join } from "node:path"
import { tmpdir } from "node:os"

const tmp = join(tmpdir(), `svp-admin-test-${process.pid}`)

before(() => {
  mkdirSync(join(tmp, "tenants"), { recursive: true })
  process.env.TENANTS_DIR = join(tmp, "tenants")
  process.env.DATA_DIR = tmp
  process.env.RELAY_MASTER_SECRET = "test-secret-32-chars-minimum-ok!!"
  process.env.RELAY_SHARED_SECRET = "test-secret-32-chars-minimum-ok!!"
})

after(() => {
  rmSync(tmp, { recursive: true, force: true })
})

describe("admin API", () => {
  it("dashboard requires master secret", async () => {
    const { adminRouter } = await import("../dist/routes/admin.js")
    const app = express()
    app.use(express.json())
    app.use(adminRouter)
    const port = 18787
    const server = app.listen(port)
    try {
      const bad = await fetch(`http://127.0.0.1:${port}/internal/admin/dashboard`)
      assert.equal(bad.status, 403)
      const ok = await fetch(`http://127.0.0.1:${port}/internal/admin/dashboard`, {
        headers: { "X-SVP-Relay-Secret": "test-secret-32-chars-minimum-ok!!" },
      })
      assert.equal(ok.status, 200)
      const body = await ok.json()
      assert.equal(body.ok, true)
      assert.ok("uptime_sec" in body)
    } finally {
      server.close()
    }
  })
})

describe("nginx dual render", () => {
  it("writes telegram and admin configs", async () => {
    const outTg = join(tmp, "nginx-tg.conf")
    const outAd = join(tmp, "nginx-ad.conf")
    process.env.NGINX_TELEGRAM_CONFIG_PATH = outTg
    process.env.NGINX_ADMIN_CONFIG_PATH = outAd
    process.env.ADMIN_SSL_CERT = join(tmp, "admin.crt")
    process.env.ADMIN_SSL_KEY = join(tmp, "admin.key")
    writeFileSync(process.env.ADMIN_SSL_CERT, "x")
    writeFileSync(process.env.ADMIN_SSL_KEY, "x")
    const { renderAllNginx } = await import("../dist/cli/nginx.js")
    const paths = renderAllNginx({
      domains: ["tg.example.com"],
      laravelIps: ["1.2.3.4"],
      telegramOut: outTg,
      adminOut: outAd,
    })
    assert.equal(paths.telegram, outTg)
    assert.equal(paths.admin, outAd)
    const tg = (await import("node:fs")).readFileSync(outTg, "utf8")
    assert.match(tg, /\/webhook\//)
    assert.match(tg, /location \/internal\//)
    const ad = (await import("node:fs")).readFileSync(outAd, "utf8")
    assert.match(ad, /default_server/)
    assert.match(ad, /allow 1\.2\.3\.4/)
  })
})
