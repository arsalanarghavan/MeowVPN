"use client"

import type { ComponentType } from "react"
import { ModernLayout } from "./modern/ModernLayout"
import { XuiLayout } from "./xui/XuiLayout"
import { PasarguardBuiltinLayout } from "./pasarguard_builtin/BuiltinLayout"
import { PasarguardV1Layout } from "./pasarguard_v1/PasarguardV1Layout"
import { PasarguardV2Layout } from "./pasarguard_v2/PasarguardV2Layout"

export type ThemeVariant =
  | "modern"
  | "pasarguard_builtin"
  | "pasarguard_v1"
  | "pasarguard_v2"
  | "xui"

export const themeRegistry: Record<ThemeVariant, ComponentType> = {
  modern: ModernLayout,
  pasarguard_builtin: PasarguardBuiltinLayout,
  pasarguard_v1: PasarguardV1Layout,
  pasarguard_v2: PasarguardV2Layout,
  xui: XuiLayout,
}

export function resolveThemeVariant(raw: string | undefined): ThemeVariant {
  if (
    raw === "pasarguard_builtin" ||
    raw === "pasarguard_v1" ||
    raw === "pasarguard_v2" ||
    raw === "xui" ||
    raw === "modern"
  ) {
    return raw
  }
  return "modern"
}
