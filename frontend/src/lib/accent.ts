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

/** Light-mode preview swatches (matches index.css accent presets). */
export const ACCENT_SWATCH: Record<AccentPreset, string> = {
  default: "oklch(20.5% 0 0)",
  red: "oklch(57% 0.22 27)",
  rose: "oklch(52% 0.2 12)",
  orange: "oklch(65% 0.2 45)",
  green: "oklch(48% 0.16 155)",
  blue: "oklch(52% 0.2 252)",
  yellow: "oklch(75% 0.15 85)",
  violet: "oklch(48% 0.22 292)",
}

export function normalizeAccent(accent?: string | null): AccentPreset {
  if (!accent || accent === "default") {
    return "default"
  }
  if (accent === "amber") {
    return "orange"
  }
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
