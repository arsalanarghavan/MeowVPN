import { apiBase, apiHeaders, ensureCsrfCookie, normalizeAdminApiPath } from "@/lib/api"

export type UiLang = "fa" | "en"
export type UiTheme = "light" | "dark" | "system"
export type UiSidebar = "expanded" | "collapsed"

export type UiPreferencesPatch = {
  ui_accent?: string
  ui_lang?: UiLang
  ui_theme?: UiTheme
  ui_sidebar?: UiSidebar
}

export async function saveUiPreferences(patch: UiPreferencesPatch): Promise<void> {
  await ensureCsrfCookie()
  await fetch(`${apiBase()}${normalizeAdminApiPath("/dashboard/ui-preferences")}`, {
    method: "POST",
    headers: apiHeaders(),
    credentials: "include",
    body: JSON.stringify(patch),
  })
}
