import type { RelayResellerBot, TenantConfig } from "../types.js"

export function normalizePublicBase(url: string): string {
  return String(url || "").replace(/\/$/, "")
}

/** Laravel API base for webhook forward. */
export function laravelForwardBase(tenant: TenantConfig): string {
  return normalizePublicBase(tenant.laravel_base_url || "")
}

export function publicBaseForReseller(tenant: TenantConfig, prof: RelayResellerBot): string {
  const u = prof.relay_public_url || tenant.default_public_url
  return normalizePublicBase(u)
}

export function mainWebhookUrl(tenant: TenantConfig): string {
  const base = normalizePublicBase(tenant.default_public_url)
  const sec = tenant.main.telegram_webhook_secret
  if (!base || !sec) return ""
  return `${base}/webhook/telegram/${encodeURIComponent(sec)}`
}

export function resellerWebhookUrl(tenant: TenantConfig, rid: number): string {
  const prof = tenant.resellers.find((r) => r.reseller_svp_user_id === rid)
  if (!prof?.webhook_secret) return ""
  const base = publicBaseForReseller(tenant, prof)
  if (!base) return ""
  return `${base}/webhook/telegram/reseller/${rid}/${encodeURIComponent(prof.webhook_secret)}`
}

export function hostFromUrl(url: string): string {
  try {
    return new URL(url).host.toLowerCase()
  } catch {
    return ""
  }
}

export function collectDomainsFromPayload(
  defaultPublicUrl: string,
  resellers: RelayResellerBot[],
  extra?: string[]
): string[] {
  const hosts = new Set<string>()
  const add = (u: string) => {
    const h = hostFromUrl(u.startsWith("http") ? u : `https://${u}`)
    if (h) hosts.add(h)
  }
  add(defaultPublicUrl)
  for (const r of resellers) {
    if (r.relay_public_url) add(r.relay_public_url)
  }
  for (const d of extra || []) {
    add(d)
  }
  return [...hosts].sort()
}
