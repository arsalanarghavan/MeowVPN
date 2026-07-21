export const ACCENT_PRESETS = [
  "default",
  "red",
  "rose",
  "orange",
  "green",
  "blue",
  "yellow",
  "violet",
] as const

export type AccentPreset = (typeof ACCENT_PRESETS)[number]

export const ACCENT_MENU_ITEMS = [
  { value: "default", labelKey: "layout.accentDefault" },
  { value: "red", labelKey: "layout.accentRed" },
  { value: "rose", labelKey: "layout.accentRose" },
  { value: "orange", labelKey: "layout.accentOrange" },
  { value: "green", labelKey: "layout.accentGreen" },
  { value: "blue", labelKey: "layout.accentBlue" },
  { value: "yellow", labelKey: "layout.accentYellow" },
  { value: "violet", labelKey: "layout.accentViolet" },
] as const satisfies ReadonlyArray<{ value: AccentPreset; labelKey: string }>

export const ACCENT_SWATCH: Record<AccentPreset, string> = {
  default: "hsl(240 5.9% 10%)",
  red: "hsl(0 72% 51%)",
  rose: "hsl(347 77% 50%)",
  orange: "hsl(25 95% 53%)",
  green: "hsl(142 71% 45%)",
  blue: "hsl(221 83% 53%)",
  yellow: "hsl(48 96% 53%)",
  violet: "hsl(262 83% 58%)",
}

export function normalizeAccent(accent?: string | null): AccentPreset {
  if (!accent || accent === "default") return "default"
  if (accent === "amber") return "orange"
  if ((ACCENT_PRESETS as readonly string[]).includes(accent)) {
    return accent as AccentPreset
  }
  return "default"
}

/** CSS vars overridden by accent presets; skip whitelabel branding when accent is active. */
export const ACCENT_BRANDING_VAR_KEYS = new Set([
  "--primary",
  "--primary-foreground",
  "--ring",
  "--sidebar-primary",
  "--sidebar-primary-foreground",
  "--sidebar-ring",
])
