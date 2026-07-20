import type { LucideIcon } from "lucide-react"
import {
  Activity,
  Bot,
  LayoutDashboard,
  Megaphone,
  Server,
  Settings2,
  Store,
  Users,
  Wallet,
} from "lucide-react"

/** Single top-level link in the sidebar. */
export type AdminNavLeaf = {
  kind: "leaf"
  tabKey: string
  icon: LucideIcon
}

/** Collapsible group of tabs (same pattern as legacy NavMain). */
export type AdminNavCollapsible = {
  kind: "collapsible"
  id: string
  icon: LucideIcon
  /** i18n key under translation, e.g. sidebar.groups.users */
  labelKey: string
  children: { tabKey: string; icon?: LucideIcon }[]
}

export type AdminNavEntry = AdminNavLeaf | AdminNavCollapsible

export type AdminNavSection = {
  id: string
  /** i18n: sidebar.sections.* */
  hintKey: string
  entries: AdminNavEntry[]
}

/** Tabs never shown to resellers (infra / global settings). */
export const ADMIN_ONLY_TAB_KEYS = new Set<string>([
  "audit",
  "site_settings",
  "backup",
  "configs",
  "texts",
  "notifications",
  "logs",
  "reseller_bots",
  "reseller_settings",
  "unit_economics",
  "bots",
  "xui_panels",
  "vpn_server",
])

/**
 * Full admin nav minus forbidden tabs, intersected with server/permission allow-list.
 * Injects `reseller_bots` under Bot settings when allowed (not present on super-admin nav).
 */
export function filterAdminNavForReseller(
  sections: AdminNavSection[],
  allowedTabs: Set<string>
): AdminNavSection[] {
  const out: AdminNavSection[] = []
  for (const sec of sections) {
    const entries: AdminNavEntry[] = []
    for (const ent of sec.entries) {
      if (ent.kind === "leaf") {
        if (ADMIN_ONLY_TAB_KEYS.has(ent.tabKey)) continue
        if (!allowedTabs.has(ent.tabKey)) continue
        entries.push(ent)
      } else {
        const children = ent.children.filter(
          (ch) => !ADMIN_ONLY_TAB_KEYS.has(ch.tabKey) && allowedTabs.has(ch.tabKey)
        )
        if (children.length === 0) continue
        entries.push({ ...ent, children })
      }
    }
    if (entries.length === 0) continue
    out.push({ ...sec, entries })
  }

  let patched = out
  if (allowedTabs.has("reseller_charge")) {
    patched = patched.map((sec) => {
      if (sec.id !== "finance") return sec
      return {
        ...sec,
        entries: sec.entries.map((ent) => {
          if (ent.kind !== "collapsible" || ent.id !== "finance_menu") return ent
          if (ent.children.some((c) => c.tabKey === "reseller_charge")) return ent
          return {
            ...ent,
            children: [{ tabKey: "reseller_charge", icon: Wallet }, ...ent.children],
          }
        }),
      }
    })
  }

  if (allowedTabs.has("reseller_settings")) {
    patched = patched.map((sec) => {
      if (sec.id !== "settings") return sec
      const has = sec.entries.some(
        (ent) => ent.kind === "leaf" && ent.tabKey === "reseller_settings"
      )
      if (has) return sec
      return {
        ...sec,
        entries: [
          { kind: "leaf", tabKey: "reseller_settings", icon: Settings2 },
          ...sec.entries,
        ],
      }
    })
  }

  if (allowedTabs.has("reseller_reports")) {
    patched = patched.map((sec) => {
      if (sec.id !== "users") return sec
      return {
        ...sec,
        entries: sec.entries.map((ent) => {
          if (ent.kind !== "collapsible" || ent.id !== "resellers_menu") return ent
          if (ent.children.some((c) => c.tabKey === "reseller_reports")) return ent
          return {
            ...ent,
            children: [...ent.children, { tabKey: "reseller_reports" }],
          }
        }),
      }
    })
  }

  if (!allowedTabs.has("reseller_bots")) {
    return reorderResellerNavSections(patched, false)
  }

  const botIdx = patched.findIndex((s) => s.id === "bot")
  if (botIdx < 0) return reorderResellerNavSections(patched, false)

  const botSec = patched[botIdx]!
  const newEntries = botSec.entries.map((ent) => {
    if (ent.kind === "collapsible" && ent.id === "bot_menu") {
      if (ent.children.some((c) => c.tabKey === "reseller_bots")) return ent
      return {
        ...ent,
        children: [...ent.children, { tabKey: "reseller_bots" }],
      }
    }
    return ent
  })
  patched[botIdx] = { ...botSec, entries: newEntries }
  return reorderResellerNavSections(patched, true)
}

const RESELLER_NAV_SECTION_ORDER = ["overview", "users", "marketing", "finance", "bot", "settings"]

const RESELLER_BOT_CHILD_ORDER = ["plan_cats", "reseller_bots", "bot_ui"]

function reorderResellerNavSections(sections: AdminNavSection[], sortBotChildren: boolean): AdminNavSection[] {
  const sorted: AdminNavSection[] = []
  for (const id of RESELLER_NAV_SECTION_ORDER) {
    const found = sections.find((s) => s.id === id)
    if (found) sorted.push(found)
  }
  for (const s of sections) {
    if (!sorted.some((x) => x.id === s.id)) sorted.push(s)
  }
  if (!sortBotChildren) return sorted
  const botIdx = sorted.findIndex((s) => s.id === "bot")
  if (botIdx < 0) return sorted
  const botSec = sorted[botIdx]!
  const orderMap = new Map(RESELLER_BOT_CHILD_ORDER.map((k, i) => [k, i]))
  sorted[botIdx] = {
    ...botSec,
    entries: botSec.entries.map((ent) => {
      if (ent.kind === "collapsible" && ent.id === "bot_menu") {
        const children = [...ent.children].sort(
          (a, b) => (orderMap.get(a.tabKey) ?? 99) - (orderMap.get(b.tabKey) ?? 99)
        )
        return { ...ent, children }
      }
      return ent
    }),
  }
  return sorted
}

export const ADMIN_NAV_SECTIONS: AdminNavSection[] = [
  {
    id: "overview",
    hintKey: "sidebar.sections.overview",
    entries: [
      { kind: "leaf", tabKey: "dashboard", icon: LayoutDashboard },
      { kind: "leaf", tabKey: "monitoring", icon: Activity },
    ],
  },
  {
    id: "users",
    hintKey: "sidebar.sections.users",
    entries: [
      {
        kind: "collapsible",
        id: "users_menu",
        icon: Users,
        labelKey: "sidebar.groups.users",
        children: [
          { tabKey: "users" },
          { tabKey: "users_bulk" },
          { tabKey: "broadcast" },
        ],
      },
      {
        kind: "collapsible",
        id: "resellers_menu",
        icon: Store,
        labelKey: "sidebar.groups.resellers",
        children: [
          { tabKey: "resellers" },
          { tabKey: "reseller_reports" },
          { tabKey: "reseller_bots" },
          { tabKey: "reseller_xui_panels" },
        ],
      },
    ],
  },
  {
    id: "marketing",
    hintKey: "sidebar.sections.marketing",
    entries: [
      {
        kind: "collapsible",
        id: "marketing_menu",
        icon: Megaphone,
        labelKey: "sidebar.groups.marketing",
        children: [
          { tabKey: "referral" },
          { tabKey: "marketing_lifecycle" },
          { tabKey: "discounts" },
        ],
      },
    ],
  },
  {
    id: "finance",
    hintKey: "sidebar.sections.finance",
    entries: [
      {
        kind: "collapsible",
        id: "finance_menu",
        icon: Wallet,
        labelKey: "sidebar.groups.finance",
        children: [
          { tabKey: "plans" },
          { tabKey: "unit_economics" },
          { tabKey: "cards" },
          { tabKey: "receipts" },
          { tabKey: "referral_reports" },
        ],
      },
    ],
  },
  {
    id: "bot",
    hintKey: "sidebar.sections.bot",
    entries: [
      {
        kind: "collapsible",
        id: "bot_menu",
        icon: Bot,
        labelKey: "sidebar.groups.botSettings",
        children: [
          { tabKey: "bots" },
          { tabKey: "plan_cats" },
          { tabKey: "texts" },
          { tabKey: "bot_ui" },
        ],
      },
    ],
  },
  {
    id: "settings",
    hintKey: "sidebar.sections.settings",
    entries: [
      {
        kind: "collapsible",
        id: "servers_menu",
        icon: Server,
        labelKey: "sidebar.groups.servers",
        children: [
          { tabKey: "xui_panels" },
          { tabKey: "vpn_server" },
          { tabKey: "configs" },
        ],
      },
      {
        kind: "collapsible",
        id: "system_prefs_menu",
        icon: Settings2,
        labelKey: "sidebar.groups.systemPreferences",
        children: [
          { tabKey: "site_settings" },
          { tabKey: "audit" },
          { tabKey: "backup" },
        ],
      },
    ],
  },
]

/** Ordered tab keys for URL/boot compatibility (labels from i18n in SPA). */
export const ADMIN_TAB_KEYS: string[] = [
  "dashboard",
  "monitoring",
  "site_settings",
  "users",
  "resellers",
  "reseller_reports",
  "users_bulk",
  "broadcast",
  "plans",
  "unit_economics",
  "cards",
  "receipts",
  "referral",
  "referral_reports",
  "marketing_lifecycle",
  "discounts",
  "plan_cats",
  "reseller_bots",
  "texts",
  "bot_ui",
  "bots",
  "reseller_xui_panels",
  "xui_panels",
  "vpn_server",
  "configs",
  "l2tp_servers",
  "backup",
  "audit",
  "reseller_charge",
  "reseller_settings",
]

export type DashboardFeatures = {
  xui_panel?: boolean
  xray_core?: boolean
  tunnel?: boolean
  backup?: boolean
  marketing?: boolean
  reseller?: boolean
  l2tp?: boolean
  relay?: boolean
  telegram?: boolean
  bale?: boolean
  crypto?: boolean
}

const FEATURE_TAB_MAP: Record<string, keyof DashboardFeatures> = {
  xui_panels: "xui_panel",
  configs: "xui_panel",
  unit_economics: "xui_panel",
  backup: "backup",
  broadcast: "marketing",
  marketing_lifecycle: "marketing",
  resellers: "reseller",
  reseller_reports: "reseller",
  reseller_bots: "reseller",
  reseller_settings: "reseller",
  reseller_charge: "reseller",
  l2tp_servers: "l2tp",
  proxy: "telegram",
  cards: "crypto",
}

const BOT_PLATFORM_TABS = new Set(["bots", "bot_ui", "texts", "plan_cats"])

/** Hide nav entries when the corresponding backend module is disabled. */
export function filterAdminNavByFeatures(
  sections: AdminNavSection[],
  features: DashboardFeatures | null | undefined
): AdminNavSection[] {
  if (!features || typeof features !== "object") return sections
  const isOn = (tabKey: string): boolean => {
    if (tabKey === "reseller_xui_panels") {
      return features.reseller === true && features.xui_panel === true
    }
    if (BOT_PLATFORM_TABS.has(tabKey)) {
      return features.telegram === true || features.bale === true
    }
    if (tabKey === "vpn_server") {
      return features.xray_core === true || features.tunnel === true
    }
    const feat = FEATURE_TAB_MAP[tabKey]
    if (!feat) return true
    return (features as Record<string, unknown>)[feat] === true
  }
  const out: AdminNavSection[] = []
  for (const sec of sections) {
    const entries: AdminNavEntry[] = []
    for (const ent of sec.entries) {
      if (ent.kind === "leaf") {
        if (!isOn(ent.tabKey)) continue
        entries.push(ent)
      } else {
        const children = ent.children.filter((ch) => isOn(ch.tabKey))
        if (children.length === 0) continue
        entries.push({ ...ent, children })
      }
    }
    if (entries.length === 0) continue
    out.push({ ...sec, entries })
  }
  return out
}

/** Add L2TP servers tab under servers_menu when the feature flag is on. */
export function injectL2tpNavTab(
  sections: AdminNavSection[],
  enabled: boolean
): AdminNavSection[] {
  if (!enabled) return sections
  return sections.map((sec) => {
    if (sec.id !== "settings") return sec
    return {
      ...sec,
      entries: sec.entries.map((ent) => {
        if (ent.kind !== "collapsible" || ent.id !== "servers_menu") return ent
        if (ent.children.some((c) => c.tabKey === "l2tp_servers")) return ent
        return {
          ...ent,
          children: [...ent.children, { tabKey: "l2tp_servers" }],
        }
      }),
    }
  })
}

export type SearchNavRow = {
  tabKey: string
  sectionHintKey: string
  /** When inside a collapsible, i18n key for the parent row */
  parentLabelKey?: string
}

/** Flat list for command palette: every navigable tab once per menu row (duplicate tabKeys allowed for search). */
export function flattenNavForSearch(
  sections: AdminNavSection[] = ADMIN_NAV_SECTIONS
): SearchNavRow[] {
  const rows: SearchNavRow[] = []
  for (const sec of sections) {
    for (const ent of sec.entries) {
      if (ent.kind === "leaf") {
        rows.push({ tabKey: ent.tabKey, sectionHintKey: sec.hintKey })
      } else {
        for (const ch of ent.children) {
          rows.push({
            tabKey: ch.tabKey,
            sectionHintKey: sec.hintKey,
            parentLabelKey: ent.labelKey,
          })
        }
      }
    }
  }
  return rows
}
