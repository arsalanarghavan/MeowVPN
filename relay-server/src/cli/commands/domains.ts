import {
  addDomainToTenant,
  collectAllDomains,
  listTenants,
  migrateLegacyConfigIfNeeded,
  removeDomainFromTenant,
} from "../../store.js"

export function domainsList(): string[] {
  migrateLegacyConfigIfNeeded()
  return collectAllDomains()
}

export function domainsListText(): string {
  const d = domainsList()
  return d.length ? d.join("\n") : "(no domains)"
}

export function domainAdd(domain: string, tenantId?: string): { ok: boolean; tenant_id?: string; domains?: string[]; error?: string } {
  migrateLegacyConfigIfNeeded()
  const tid = tenantId || listTenants()[0]?.tenant_id
  if (!tid) {
    return { ok: false, error: "no tenant — sync config from Laravel first" }
  }
  const t = addDomainToTenant(tid, domain)
  return { ok: true, tenant_id: tid, domains: t?.domains }
}

export function domainRemove(domain: string, tenantId?: string): { ok: boolean; domains?: string[]; error?: string } {
  migrateLegacyConfigIfNeeded()
  const tid = tenantId || listTenants()[0]?.tenant_id
  if (!tid) return { ok: false, error: "no tenant" }
  const t = removeDomainFromTenant(tid, domain)
  return { ok: true, domains: t?.domains }
}
