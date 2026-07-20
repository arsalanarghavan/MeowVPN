"use client"

import { useEffect, useState } from "react"
import { useTheme } from "next-themes"
import { useLocale, useTranslations } from "next-intl"
import { usePathname, useRouter } from "next/navigation"
import { CheckIcon, LanguagesIcon, MoonIcon, SunIcon, PaletteIcon } from "lucide-react"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  ACCENT_MENU_ITEMS,
  ACCENT_SWATCH,
  normalizeAccent,
  type AccentPreset,
} from "@/lib/accent"
import { saveUiPreferences, type UiTheme } from "@/lib/dash-ui-preferences"

export function DashboardToolbar() {
  const t = useTranslations()
  const { theme, setTheme } = useTheme()
  const locale = useLocale()
  const router = useRouter()
  const pathname = usePathname()
  const [accent, setAccent] = useState<AccentPreset>("default")

  useEffect(() => {
    try {
      const stored = normalizeAccent(localStorage.getItem("svp-ui-accent"))
      setAccent(stored)
      if (stored !== "default") {
        document.documentElement.setAttribute("data-accent", stored)
      }
    } catch {
      /* ignore */
    }
  }, [])

  const applyAccent = (value: AccentPreset) => {
    setAccent(value)
    if (value === "default") {
      document.documentElement.removeAttribute("data-accent")
    } else {
      document.documentElement.setAttribute("data-accent", value)
    }
    try {
      localStorage.setItem("svp-ui-accent", value)
    } catch {
      /* ignore */
    }
    void saveUiPreferences({ ui_accent: value })
  }

  const applyTheme = (value: UiTheme) => {
    setTheme(value)
    void saveUiPreferences({ ui_theme: value })
  }

  const switchLocale = (next: string) => {
    const segments = pathname.split("/")
    if (segments.length > 1) {
      segments[1] = next
      router.replace(segments.join("/") || `/${next}/dashboard`)
    }
    if (next === "fa" || next === "en") {
      void saveUiPreferences({ ui_lang: next })
    }
  }

  return (
    <div className="ms-auto flex items-center gap-1">
      <DropdownMenu>
        <DropdownMenuTrigger className="inline-flex size-8 items-center justify-center rounded-lg hover:bg-muted">
          <LanguagesIcon className="size-4" />
          <span className="sr-only">{t("layout.language")}</span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuGroup>
            <DropdownMenuItem onClick={() => switchLocale("fa")}>
              فارسی {locale === "fa" ? <CheckIcon className="ms-auto size-4" /> : null}
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => switchLocale("en")}>
              English {locale === "en" ? <CheckIcon className="ms-auto size-4" /> : null}
            </DropdownMenuItem>
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>

      <DropdownMenu>
        <DropdownMenuTrigger className="inline-flex size-8 items-center justify-center rounded-lg hover:bg-muted">
          <SunIcon className="size-4 dark:hidden" />
          <MoonIcon className="hidden size-4 dark:block" />
          <span className="sr-only">{t("layout.theme")}</span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={() => applyTheme("light")}>
            {t("layout.themeLight")}
            {theme === "light" ? <CheckIcon className="ms-auto size-4" /> : null}
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => applyTheme("dark")}>
            {t("layout.themeDark")}
            {theme === "dark" ? <CheckIcon className="ms-auto size-4" /> : null}
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => applyTheme("system")}>
            {t("layout.themeSystem")}
            {theme === "system" ? <CheckIcon className="ms-auto size-4" /> : null}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <DropdownMenu>
        <DropdownMenuTrigger className="inline-flex size-8 items-center justify-center rounded-lg hover:bg-muted">
          <PaletteIcon className="size-4" />
          <span className="sr-only">{t("layout.accent")}</span>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-48">
          <DropdownMenuLabel>{t("layout.accent")}</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuGroup>
            {ACCENT_MENU_ITEMS.map((item) => (
              <DropdownMenuItem key={item.value} onClick={() => applyAccent(item.value)}>
                <span
                  className="me-2 size-3 rounded-full border"
                  style={{ background: ACCENT_SWATCH[item.value] }}
                />
                {t(item.labelKey)}
                {accent === item.value ? <CheckIcon className="ms-auto size-4" /> : null}
              </DropdownMenuItem>
            ))}
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  )
}
