export type BotPlatformId = "telegram" | "bale"

export function mainEnabledPlatforms(settings?: Record<string, unknown> | null): BotPlatformId[] {
  const features =
    settings?.features && typeof settings.features === "object"
      ? (settings.features as Record<string, unknown>)
      : {}
  const out: BotPlatformId[] = []
  if (features.telegram !== false) out.push("telegram")
  if (features.bale === true) out.push("bale")
  return out.length ? out : ["telegram"]
}

export function overviewPlatformEnabled(
  platform: BotPlatformId,
  settings?: Record<string, unknown> | null
): boolean {
  return mainEnabledPlatforms(settings).includes(platform)
}
