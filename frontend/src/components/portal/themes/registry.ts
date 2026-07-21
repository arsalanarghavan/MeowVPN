"use client"

import type { ComponentType } from "react"
import { ClassicLayout } from "./classic/ClassicLayout"
import { ModernLayout } from "./modern/ModernLayout"
import { XuiLayout } from "./xui/XuiLayout"
import { PasarguardBuiltinLayout } from "./pasarguard_builtin/BuiltinLayout"
import { PasarguardV1Layout } from "./pasarguard_v1/PasarguardV1Layout"
import { PasarguardV2Layout } from "./pasarguard_v2/PasarguardV2Layout"

export type ThemeVariant =
  | "modern"
  | "classic"
  | "pasarguard_builtin"
  | "pasarguard_v1"
  | "pasarguard_v2"
  | "xui"

export const themeRegistry: Record<ThemeVariant, ComponentType> = {
  modern: ModernLayout,
  classic: ClassicLayout,
  pasarguard_builtin: PasarguardBuiltinLayout,
  pasarguard_v1: PasarguardV1Layout,
  pasarguard_v2: PasarguardV2Layout,
  xui: XuiLayout,
}

/** Explicit aliases for admin template ids that are not registry keys. */
const THEME_ALIASES: Record<string, ThemeVariant> = {
  pasarguard: "pasarguard_v2",
}

export function resolveThemeVariant(raw: string | undefined): ThemeVariant {
  const key = (raw ?? "").trim().toLowerCase()
  if (key in THEME_ALIASES) {
    return THEME_ALIASES[key]
  }
  if (
    key === "pasarguard_builtin" ||
    key === "pasarguard_v1" ||
    key === "pasarguard_v2" ||
    key === "xui" ||
    key === "modern" ||
    key === "classic"
  ) {
    return key
  }
  return "modern"
}
