/** Bot settings keys grouped per platform (extend array for new messengers). */
export type BotPlatformId = "telegram" | "bale"

export type BotPlatformConfig = {
  id: BotPlatformId
  titleKey: string
  summaryUsernameKey: string
  enableSettingKey: "telegram_enabled" | "bale_enabled"
  fieldKeys: readonly (keyof BotPlatformForm)[]
}

/** Form fields sent to REST (path webhook secrets are server-only). */
export type BotPlatformForm = {
  telegram_token: string
  bale_token: string
  telegram_secret_header: string
  bale_wallet_provider_token: string
}

export const BOT_PLATFORMS: readonly BotPlatformConfig[] = [
  {
    id: "telegram",
    titleKey: "botsAdmin.platformTelegram",
    summaryUsernameKey: "botsAdmin.tgUser",
    enableSettingKey: "telegram_enabled",
    fieldKeys: ["telegram_token", "telegram_secret_header"],
  },
  {
    id: "bale",
    titleKey: "botsAdmin.platformBale",
    summaryUsernameKey: "botsAdmin.baleUser",
    enableSettingKey: "bale_enabled",
    fieldKeys: ["bale_token", "bale_wallet_provider_token"],
  },
] as const
