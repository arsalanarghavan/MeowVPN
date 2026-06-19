import { addDomainToTenant, collectAllDomains, listTenants, migrateLegacyConfigIfNeeded, removeDomainFromTenant, } from "../../store.js";
export function domainsList() {
    migrateLegacyConfigIfNeeded();
    return collectAllDomains();
}
export function domainsListText() {
    const d = domainsList();
    return d.length ? d.join("\n") : "(no domains)";
}
export function domainAdd(domain, tenantId) {
    migrateLegacyConfigIfNeeded();
    const tid = tenantId || listTenants()[0]?.tenant_id;
    if (!tid) {
        return { ok: false, error: "no tenant — sync config from Laravel first" };
    }
    const t = addDomainToTenant(tid, domain);
    return { ok: true, tenant_id: tid, domains: t?.domains };
}
export function domainRemove(domain, tenantId) {
    migrateLegacyConfigIfNeeded();
    const tid = tenantId || listTenants()[0]?.tenant_id;
    if (!tid)
        return { ok: false, error: "no tenant" };
    const t = removeDomainFromTenant(tid, domain);
    return { ok: true, domains: t?.domains };
}
