/** Client fallback when me/state has no resellerAllowedTabs map. */
export const RESELLER_ALLOWED_BY_PERMISSION: Record<string, string | null> = {
  dashboard: null,
  monitoring: "services.manage",
  users: "users.manage",
  resellers: "users.manage",
  users_bulk: "users.bulk",
  plans: "plans.manage",
  plan_cats: "plans.manage",
  cards: "plans.manage",
  referral: "users.manage",
  referral_reports: "users.manage",
  reseller_reports: "users.manage",
  marketing_lifecycle: "marketing.lifecycle",
  discounts: "plans.manage",
  reseller_bots: "services.manage",
  bot_ui: "services.manage",
  broadcast: "broadcast.send",
  receipts: "receipts.review",
  payments: "receipts.review",
  reseller_charge: "plans.manage",
  reseller_settings: null,
  reseller_workspace: null,
  reseller_xui_panels: "services.manage",
}

export function buildAllowedResellerTabs(
  serverTabs: string[] | null | undefined,
  actorPermissions: Record<string, boolean> | null | undefined
): Set<string> {
  if (Array.isArray(serverTabs) && serverTabs.length > 0) {
    return new Set(serverTabs)
  }
  const perms = actorPermissions ?? {}
  const out = new Set<string>()
  for (const [tab, perm] of Object.entries(RESELLER_ALLOWED_BY_PERMISSION)) {
    if (perm == null || perms[perm] === true) {
      out.add(tab)
    }
  }
  return out
}

export function safeResellerTab(
  tab: string,
  isReseller: boolean,
  allowed: Set<string>
): string {
  if (!isReseller) return tab
  return allowed.has(tab) ? tab : "dashboard"
}
