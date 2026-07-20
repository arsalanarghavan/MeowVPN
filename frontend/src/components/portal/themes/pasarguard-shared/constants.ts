import type { PortalLocale } from "@/components/portal/locales/pg"

export const PG_LANGS: PortalLocale[] = ["fa", "en", "zh", "ru"]

export const PG_PLATFORMS = ["ios", "android", "windows", "linux"] as const

export type PgThemeClass = "theme-pg-v2" | "theme-pg-v1" | "theme-pg-builtin"
