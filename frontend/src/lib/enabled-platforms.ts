import { BOT_PLATFORMS, type BotPlatformId } from "@/config/bot-platforms"

type DashRecord = Record<string, unknown>

export function platformFlagIsOn(v: unknown): boolean {
  if (v === undefined || v === null) return true
  if (v === true || v === 1 || v === "1") return true
  if (v === false || v === 0 || v === "0") return false
  const s = String(v).trim().toLowerCase()
  if (s === "") return true
  return !["0", "false", "off", "no"].includes(s)
}

export function mainPlatformEnabled(
  settings: DashRecord | undefined,
  platform: BotPlatformId
): boolean {
  const s = settings ?? {}
  if (!platformFlagIsOn(s.enabled)) return false
  const key = platform === "telegram" ? "telegram_enabled" : "bale_enabled"
  return platformFlagIsOn(s[key])
}

export function resellerPlatformEnabled(
  row: DashRecord | undefined,
  platform: BotPlatformId
): boolean {
  const r = row ?? {}
  if (!platformFlagIsOn(r.enabled)) return false
  const key = platform === "telegram" ? "telegram_enabled" : "bale_enabled"
  return platformFlagIsOn(r[key])
}

/** Site admin UI: main bot platform flags only. */
export function mainEnabledPlatforms(settings: DashRecord | undefined): BotPlatformId[] {
  return BOT_PLATFORMS.filter((p) => mainPlatformEnabled(settings, p.id)).map((p) => p.id)
}

/** Reseller dashboard: main site + reseller profile must both allow the platform. */
export function effectiveEnabledPlatforms(
  settings: DashRecord | undefined,
  resellerRow: DashRecord | undefined
): BotPlatformId[] {
  return BOT_PLATFORMS.filter(
    (p) => mainPlatformEnabled(settings, p.id) && resellerPlatformEnabled(resellerRow, p.id)
  ).map((p) => p.id)
}

export function overviewPlatformEnabled(
  overviewBot: DashRecord | undefined,
  platform: BotPlatformId
): boolean {
  const b = overviewBot ?? {}
  const key = platform === "telegram" ? "telegram_enabled" : "bale_enabled"
  return platformFlagIsOn(b[key])
}

export function overviewEnabledPlatforms(overviewBot: DashRecord | undefined): BotPlatformId[] {
  return BOT_PLATFORMS.filter((p) => overviewPlatformEnabled(overviewBot, p.id)).map((p) => p.id)
}
