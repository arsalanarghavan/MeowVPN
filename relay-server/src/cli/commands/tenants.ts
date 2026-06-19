import { getTenantById, listTenants, migrateLegacyConfigIfNeeded, tenantSummary } from "../../store.js"

export function tenantsListText(): string {
  migrateLegacyConfigIfNeeded()
  const lines: string[] = ["tenant_id\tlaravel_base_url\tdomains"]
  for (const t of listTenants()) {
    const s = tenantSummary(t)
    lines.push(`${s.tenant_id}\t${s.laravel_base_url}\t${s.domains.join(",")}`)
  }
  return lines.join("\n")
}

export function tenantShowJson(id: string): Record<string, unknown> | null {
  const t = getTenantById(id)
  if (!t) return null
  const { shared_secret: _s, ...safe } = t
  return safe as Record<string, unknown>
}

export function tenantShowText(id: string): string | null {
  const data = tenantShowJson(id)
  if (!data) return null
  const lines = [
    `Tenant: ${data.tenant_id}`,
    `Laravel URL: ${data.laravel_base_url || "n/a"}`,
    `Public URL: ${data.default_public_url || "(default)"}`,
    `Config version: ${data.config_version}`,
    `Updated: ${data.updated_at || "n/a"}`,
    `Domains: ${Array.isArray(data.domains) ? data.domains.join(", ") : ""}`,
    `Resellers: ${Array.isArray(data.resellers) ? data.resellers.length : 0}`,
    `Main webhook configured: ${(data.main as { telegram_webhook_secret?: string })?.telegram_webhook_secret ? "yes" : "no"}`,
  ]
  return lines.join("\n")
}

export function tenantChoices(): { name: string; value: string }[] {
  migrateLegacyConfigIfNeeded()
  return listTenants().map((t) => {
    const s = tenantSummary(t)
    return { name: `${s.tenant_id} (${s.laravel_base_url})`, value: s.tenant_id }
  })
}
