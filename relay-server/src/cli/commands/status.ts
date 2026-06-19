import { existsSync } from "node:fs"
import { spawnSync } from "node:child_process"
import {
  collectAllDomains,
  listTenants,
  migrateLegacyConfigIfNeeded,
  clearConfigCache,
  tenantSummary,
} from "../../store.js"
import { env } from "../../env.js"
import { resolveInstallRoot } from "../paths.js"
import { systemdActiveState } from "./service.js"

export type StatusSnapshot = {
  ok: boolean
  port: number
  service_up: boolean
  uptime_sec: number | null
  forward_queue_depth: number | null
  tenant_count: number
  domains: string[]
  tenants: ReturnType<typeof tenantSummary>[]
  systemd: string
  nginx_ok: boolean | null
  install_root: string
}

async function fetchJson(url: string, headers?: Record<string, string>): Promise<Record<string, unknown> | null> {
  try {
    const res = await fetch(url, { headers, signal: AbortSignal.timeout(3000) })
    if (!res.ok) return null
    return (await res.json()) as Record<string, unknown>
  } catch {
    return null
  }
}

export async function getStatusSnapshot(): Promise<StatusSnapshot> {
  migrateLegacyConfigIfNeeded()
  clearConfigCache()
  const tenants = listTenants()
  const domains = collectAllDomains()

  const health = await fetchJson(`http://127.0.0.1:${env.port}/health`)
  const serviceUp = Boolean(health?.ok)

  let uptime: number | null = null
  let queueDepth: number | null = null
  let tenantSummaries = tenants.map(tenantSummary)

  if (serviceUp && env.masterSecret) {
    const internal = await fetchJson(`http://127.0.0.1:${env.port}/internal/status`, {
      "X-SVP-Relay-Secret": env.masterSecret,
    })
    if (internal?.ok) {
      uptime = typeof internal.uptime_sec === "number" ? internal.uptime_sec : null
      queueDepth = typeof internal.forward_queue_depth === "number" ? internal.forward_queue_depth : null
      if (Array.isArray(internal.tenants)) {
        tenantSummaries = internal.tenants as ReturnType<typeof tenantSummary>[]
      }
    }
  }

  let nginxOk: boolean | null = null
  try {
    const r = spawnSync("nginx", ["-t"], { stdio: "pipe" })
    nginxOk = r.status === 0
  } catch {
    nginxOk = null
  }

  return {
    ok: true,
    port: env.port,
    service_up: serviceUp,
    uptime_sec: uptime,
    forward_queue_depth: queueDepth,
    tenant_count: tenants.length,
    domains,
    tenants: tenantSummaries,
    systemd: systemdActiveState(),
    nginx_ok: nginxOk,
    install_root: resolveInstallRoot(),
  }
}

export function formatStatusSnapshot(s: StatusSnapshot): string {
  const lines = [
    "── Dashboard ──",
    `Install root:     ${s.install_root}`,
    `Port:             ${s.port}`,
    `Relay process:    ${s.service_up ? "up" : "down"}`,
    `systemd:          ${s.systemd}`,
    `Uptime:           ${s.uptime_sec != null ? `${s.uptime_sec}s` : "n/a"}`,
    `Forward queue:    ${s.forward_queue_depth != null ? String(s.forward_queue_depth) : "n/a"}`,
    `Tenants:          ${s.tenant_count}`,
    `Domains:          ${s.domains.length ? s.domains.join(", ") : "(none)"}`,
    `nginx -t:         ${s.nginx_ok === null ? "n/a" : s.nginx_ok ? "ok" : "failed"}`,
  ]
  if (s.tenants.length) {
    lines.push("", "Tenants:")
    for (const t of s.tenants) {
      lines.push(`  ${t.tenant_id}  ${t.laravel_base_url}  domains=${t.domains.join(",") || "-"}`)
    }
  }
  return lines.join("\n")
}

export function statusJson(): Record<string, unknown> {
  migrateLegacyConfigIfNeeded()
  clearConfigCache()
  const tenants = listTenants()
  return {
    ok: true,
    port: env.port,
    tenant_count: tenants.length,
    domains: collectAllDomains(),
    tenants: tenants.map(tenantSummary),
  }
}

export function runDoctor(): Record<string, boolean | string> {
  const checks: Record<string, boolean | string> = {}
  checks.node = process.version
  checks.port = String(env.port)
  checks.master_secret = Boolean(env.masterSecret)
  checks.tenants_dir = existsSync(env.tenantsDir)
  migrateLegacyConfigIfNeeded()
  checks.tenant_count = String(listTenants().length)
  checks.domains = collectAllDomains().join(",") || "(none)"
  checks.systemd = systemdActiveState()
  try {
    const r = spawnSync("nginx", ["-t"], { stdio: "pipe" })
    checks.nginx = r.status === 0
  } catch {
    checks.nginx = false
  }
  return checks
}

export function formatDoctor(checks: Record<string, boolean | string>): string {
  const lines = ["── Doctor ──"]
  for (const [k, v] of Object.entries(checks)) {
    const mark = v === true || (typeof v === "string" && v !== "inactive" && v !== "unknown" && v !== "false") ? "ok" : "!"
    if (typeof v === "boolean") {
      lines.push(`[${v ? "ok" : "!!"}] ${k}`)
    } else {
      lines.push(`[${mark === "ok" ? "ok" : "--"}] ${k}: ${v}`)
    }
  }
  return lines.join("\n")
}
