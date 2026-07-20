/** Per-tab content markers for Playwright v21 (prefer data-testid over broad regex). */
import { ADMIN_TAB_KEYS } from "./admin-nav"

/** Overview stat testids (replace regex false-positives on dashboard tab). */
export const OVERVIEW_STAT_TESTIDS = [
  "dash-overview-stat-users",
  "dash-overview-stat-receipts",
  "dash-overview-stat-panels",
] as const

export const ADMIN_TAB_MARKERS: Record<string, RegExp> = {
  dashboard: /dash-overview-stat-users|overview|پیشخوان/i,
  monitoring: /monitor|مانیتور|panel|پنل/i,
  site_settings: /settings|تنظیم|whitelabel|برند/i,
  users: /users|کاربر/i,
  users_bulk: /bulk|گروهی|job/i,
  bots: /bot|ربات|webhook/i,
  bot_ui: /layout|چیدمان|studio/i,
  texts: /text|متن/i,
  xui_panels: /panel|پنل/i,
  configs: /config|کلاینت|client/i,
  plans: /plan|پلن/i,
  plan_cats: /categor|دسته/i,
  cards: /card|کارت|payment/i,
  receipts: /receipt|رسید/i,
  discounts: /discount|تخفیف/i,
  unit_economics: /economics|اقتصاد/i,
  broadcast: /broadcast|پخش/i,
  marketing_lifecycle: /marketing|مارکتینگ|lifecycle/i,
  referral: /referral|معرف/i,
  referral_reports: /referral|معرف/i,
  resellers: /reseller|نماینده/i,
  reseller_reports: /report|گزارش|reseller/i,
  reseller_bots: /bot|ربات/i,
  reseller_xui_panels: /panel|پنل|price|قیمت/i,
  reseller_charge: /charge|شارژ|wallet/i,
  reseller_settings: /settings|تنظیم/i,
  l2tp_servers: /l2tp|L2TP/i,
  backup: /backup|پشتیبان/i,
  audit: /audit|ممیزی|log/i,
}

/** Tabs that must not render for admin actor. */
export const RESELLER_ONLY_TABS = new Set(["reseller_charge", "reseller_settings"])

/** Tabs forbidden for reseller sidebar (subset of ADMIN_ONLY_TAB_KEYS). */
export const RESELLER_FORBIDDEN_TABS = new Set([
  "audit",
  "site_settings",
  "backup",
  "configs",
  "texts",
  "reseller_bots",
  "unit_economics",
  "bots",
  "xui_panels",
])

export function tabMarkerFor(key: string): RegExp {
  return ADMIN_TAB_MARKERS[key] ?? /./
}

export const ALL_ADMIN_TAB_KEYS = ADMIN_TAB_KEYS
