export type UiLang = "fa" | "en"
export type UiTheme = "light" | "dark" | "system"
export type UiSidebar = "expanded" | "collapsed"

export type UiPreferencesPatch = {
  ui_accent?: string
  ui_lang?: UiLang
  ui_theme?: UiTheme
  ui_sidebar?: UiSidebar
}

import { apiHeaders, ensureCsrfCookie, normalizeAdminApiPath } from "@/lib/api-base"

export async function saveUiPreferences(
  patch: UiPreferencesPatch,
  opts: { restUrl: string }
): Promise<void> {
  const base = String(opts.restUrl || "").replace(/\/$/, "")
  if (!base) return
  await ensureCsrfCookie()
  await fetch(`${base}${normalizeAdminApiPath("/dashboard/ui-preferences")}`, {
    method: "POST",
    headers: apiHeaders(),
    credentials: "include",
    body: JSON.stringify(patch),
  })
}
