import {
  mkdirSync,
  readFileSync,
  writeFileSync,
  existsSync,
  readdirSync,
  unlinkSync,
  renameSync,
} from "node:fs"
import { join, dirname } from "node:path"
import type { LegacySiteConfig, RelayMainBot, RelayResellerBot, TenantConfig, TenantConfigPayload } from "./types.js"
import { env } from "./env.js"
import { collectDomainsFromPayload, normalizePublicBase } from "./util/webhook-url.js"
import { newTenantId, secretFingerprint } from "./util/crypto.js"

const emptyMain = (): RelayMainBot => ({
  telegram_token: "",
  telegram_webhook_secret: "",
  telegram_secret_header: "",
  telegram_enabled: true,
  enabled: true,
  admin_telegram_ids: [],
})

export function tenantsDir(): string {
  return env.tenantsDir
}

function tenantPath(tenantId: string): string {
  return join(tenantsDir(), `${tenantId}.json`)
}

function readTenantFile(tenantId: string): TenantConfig | null {
  const p = tenantPath(tenantId)
  if (!existsSync(p)) return null
  try {
    const raw = JSON.parse(readFileSync(p, "utf8")) as TenantConfig
    const laravelBase = String(raw.laravel_base_url || "").replace(/\/$/, "")
    return {
      ...raw,
      laravel_base_url: laravelBase,
      main: { ...emptyMain(), ...raw.main },
      resellers: Array.isArray(raw.resellers) ? raw.resellers : [],
      domains: Array.isArray(raw.domains) ? raw.domains : [],
    }
  } catch {
    return null
  }
}

function writeTenantFile(tenant: TenantConfig): void {
  mkdirSync(tenantsDir(), { recursive: true })
  const next: TenantConfig = { ...tenant, updated_at: new Date().toISOString() }
  writeFileSync(tenantPath(tenant.tenant_id), JSON.stringify(next, null, 2), { mode: 0o600 })
}

let tenantIndex: Map<string, TenantConfig> | null = null
let fingerprintIndex: Map<string, string> | null = null

function rebuildIndex(): void {
  tenantIndex = new Map()
  fingerprintIndex = new Map()
  mkdirSync(tenantsDir(), { recursive: true })
  for (const f of readdirSync(tenantsDir())) {
    if (!f.endsWith(".json")) continue
    const id = f.replace(/\.json$/, "")
    const t = readTenantFile(id)
    if (!t) continue
    tenantIndex.set(t.tenant_id, t)
    if (t.shared_secret_fingerprint) {
      fingerprintIndex.set(t.shared_secret_fingerprint, t.tenant_id)
    }
  }
}

function ensureIndex(): void {
  if (!tenantIndex) rebuildIndex()
}

export function clearConfigCache(): void {
  tenantIndex = null
  fingerprintIndex = null
}

export function migrateLegacyConfigIfNeeded(): boolean {
  mkdirSync(tenantsDir(), { recursive: true })
  const legacyPath = env.configPath
  if (!existsSync(legacyPath)) return false
  const already = readdirSync(tenantsDir()).some((f) => f.endsWith(".json"))
  if (already) return false
  try {
    const parsed = JSON.parse(readFileSync(legacyPath, "utf8")) as LegacySiteConfig & { wp_base_url?: string }
    const secret = env.sharedSecret || "legacy-migrated"
    const tenant: TenantConfig = {
      tenant_id: "default",
      shared_secret: secret,
      shared_secret_fingerprint: secretFingerprint(secret),
      laravel_base_url: String(parsed.laravel_base_url || parsed.wp_base_url || "").replace(/\/$/, ""),
      default_public_url: normalizePublicBase(parsed.relay_public_url || ""),
      domains: collectDomainsFromPayload(parsed.relay_public_url || "", parsed.resellers || []),
      main: { ...emptyMain(), ...parsed.main },
      resellers: parsed.resellers || [],
      config_version: parsed.config_version || "",
      updated_at: parsed.updated_at,
    }
    writeTenantFile(tenant)
    renameSync(legacyPath, `${legacyPath}.migrated`)
    clearConfigCache()
    return true
  } catch {
    return false
  }
}

export function listTenants(): TenantConfig[] {
  ensureIndex()
  return [...(tenantIndex?.values() || [])]
}

export function getTenantById(tenantId: string): TenantConfig | null {
  ensureIndex()
  return tenantIndex?.get(tenantId) || readTenantFile(tenantId)
}

export function findTenantBySecret(secret: string): TenantConfig | null {
  if (!secret) return null
  ensureIndex()
  const fp = secretFingerprint(secret)
  const id = fingerprintIndex?.get(fp)
  if (id) {
    const t = tenantIndex?.get(id)
    if (t && t.shared_secret === secret) return t
  }
  for (const t of listTenants()) {
    if (t.shared_secret === secret) return t
  }
  return null
}

export function upsertTenantFromPayload(secret: string, body: TenantConfigPayload): TenantConfig {
  ensureIndex()
  let tenant = body.tenant_id ? getTenantById(body.tenant_id) : findTenantBySecret(secret)
  const tenantId = tenant?.tenant_id || body.tenant_id || newTenantId()
  const defaultPublic = normalizePublicBase(body.relay_public_url || "")
  const domains = collectDomainsFromPayload(
    defaultPublic,
    body.resellers || [],
    body.domains || tenant?.domains || [],
  )
  const laravelBase = String(body.laravel_base_url || "").replace(/\/$/, "")
  const next: TenantConfig = {
    tenant_id: tenantId,
    shared_secret: secret,
    shared_secret_fingerprint: secretFingerprint(secret),
    laravel_base_url: laravelBase,
    default_public_url: defaultPublic,
    domains,
    main: { ...emptyMain(), ...body.main },
    resellers: (body.resellers || []).map((r) => ({ ...r })),
    config_version: body.config_version || String(Date.now()),
    updated_at: new Date().toISOString(),
  }
  writeTenantFile(next)
  clearConfigCache()
  return next
}

export function syncTenantDomains(tenantId: string, domains: string[]): TenantConfig | null {
  const t = getTenantById(tenantId)
  if (!t) return null
  const hosts = new Set<string>([...t.domains, ...domains.map((d) => d.toLowerCase().trim())].filter(Boolean))
  const next = { ...t, domains: [...hosts].sort() }
  writeTenantFile(next)
  clearConfigCache()
  return next
}

export function addDomainToTenant(tenantId: string, domain: string): TenantConfig | null {
  const host = domain.replace(/^https?:\/\//, "").split("/")[0].toLowerCase().trim()
  if (!host) return null
  return syncTenantDomains(tenantId, [host])
}

export function removeDomainFromTenant(tenantId: string, domain: string): TenantConfig | null {
  const t = getTenantById(tenantId)
  if (!t) return null
  const host = domain.replace(/^https?:\/\//, "").split("/")[0].toLowerCase().trim()
  const next = { ...t, domains: t.domains.filter((d) => d !== host) }
  writeTenantFile(next)
  clearConfigCache()
  return next
}

export function collectAllDomains(): string[] {
  const hosts = new Set<string>()
  for (const t of listTenants()) {
    for (const d of t.domains) hosts.add(d)
    const h = normalizePublicBase(t.default_public_url)
    if (h) {
      try {
        hosts.add(new URL(h.startsWith("http") ? h : `https://${h}`).host)
      } catch {
        /* ignore */
      }
    }
  }
  return [...hosts].sort()
}

export type MainWebhookMatch = { tenant: TenantConfig }
export type ResellerWebhookMatch = { tenant: TenantConfig; prof: RelayResellerBot }

export function findMainWebhook(secret: string): MainWebhookMatch | null {
  for (const tenant of listTenants()) {
    if (tenant.main.telegram_webhook_secret && tenant.main.telegram_webhook_secret === secret) {
      return { tenant }
    }
  }
  return null
}

export function findResellerWebhook(rid: number, secret: string): ResellerWebhookMatch | null {
  for (const tenant of listTenants()) {
    const prof = tenant.resellers.find((r) => r.reseller_svp_user_id === rid)
    if (prof && prof.webhook_secret && prof.webhook_secret === secret) {
      return { tenant, prof }
    }
  }
  return null
}

/** @deprecated Use getTenantById / findTenantBySecret */
export function getConfig(): TenantConfig {
  const tenants = listTenants()
  return tenants[0] || {
    tenant_id: "",
    shared_secret: "",
    shared_secret_fingerprint: "",
    laravel_base_url: "",
    default_public_url: "",
    domains: [],
    main: emptyMain(),
    resellers: [],
    config_version: "",
  }
}

/** @deprecated */
export function saveConfig(cfg: TenantConfigPayload & { tenant_id?: string }): void {
  upsertTenantFromPayload(env.sharedSecret || "local", cfg)
}

export function deleteTenant(tenantId: string): boolean {
  const p = tenantPath(tenantId)
  if (!existsSync(p)) return false
  unlinkSync(p)
  clearConfigCache()
  return true
}

export function tenantSummary(t: TenantConfig) {
  return {
    tenant_id: t.tenant_id,
    laravel_base_url: t.laravel_base_url,
    default_public_url: t.default_public_url,
    domains: t.domains,
    config_version: t.config_version,
    updated_at: t.updated_at || null,
    reseller_count: t.resellers.length,
    main_webhook_configured: Boolean(t.main.telegram_webhook_secret),
  }
}
