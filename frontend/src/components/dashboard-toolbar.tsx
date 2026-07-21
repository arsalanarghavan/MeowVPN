"use client"

import { useEffect, useMemo, useState } from "react"
import { useTheme } from "next-themes"
import { useLocale, useTranslations } from "next-intl"
import { usePathname, useRouter } from "next/navigation"
import {
  CheckIcon,
  LanguagesIcon,
  MaximizeIcon,
  MinimizeIcon,
  MoonIcon,
  SunIcon,
  PaletteIcon,
} from "lucide-react"
import { BaleLogo } from "@/components/icons/bale-logo"
import { TelegramLogo } from "@/components/icons/telegram-logo"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"
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
import { botPlatformUrl } from "@/lib/bot-links"
import { saveUiPreferences, type UiTheme } from "@/lib/dash-ui-preferences"
import { cn } from "@/lib/utils"

export function DashboardToolbar({
  variant = "header",
  className,
}: {
  variant?: "header" | "sidebar"
  className?: string
} = {}) {
  const t = useTranslations()
  const { theme, setTheme } = useTheme()
  const locale = useLocale()
  const router = useRouter()
  const pathname = usePathname()
  const shell = useDashboardShellOptional()
  const me = shell?.me
  const [accent, setAccent] = useState<AccentPreset>("default")
  const [isFullscreen, setIsFullscreen] = useState(false)
  const isSidebar = variant === "sidebar"

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

  useEffect(() => {
    const fromMe = me?.uiAccent ?? me?.ui_accent
    if (fromMe == null || fromMe === "") return
    const next = normalizeAccent(String(fromMe))
    setAccent(next)
  }, [me?.uiAccent, me?.ui_accent])

  useEffect(() => {
    const onFsChange = () => setIsFullscreen(Boolean(document.fullscreenElement))
    document.addEventListener("fullscreenchange", onFsChange)
    return () => document.removeEventListener("fullscreenchange", onFsChange)
  }, [])

  const botLinks = useMemo(() => {
    const features = me?.features as Record<string, unknown> | undefined
    const tgUser = String(me?.telegramBotUsername ?? me?.telegram_bot_username ?? "").trim()
    const baleUser = String(me?.baleBotUsername ?? me?.bale_bot_username ?? "").trim()
    const tgOn = features?.telegram !== false
    const baleOn = features?.bale === true
    return {
      telegram: tgOn && tgUser ? botPlatformUrl("telegram", tgUser) : null,
      bale: baleOn && baleUser ? botPlatformUrl("bale", baleUser) : null,
    }
  }, [me])

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

  const toggleFullscreen = async () => {
    try {
      if (!document.fullscreenElement) {
        await document.documentElement.requestFullscreen()
      } else {
        await document.exitFullscreen()
      }
    } catch {
      /* ignore */
    }
  }

  const iconBtn = isSidebar
    ? "inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background hover:bg-muted"
    : "inline-flex size-8 items-center justify-center rounded-lg border border-transparent hover:bg-muted"

  return (
    <div
      className={cn(
        isSidebar
          ? "flex w-full flex-wrap items-center gap-2"
          : "ms-auto flex shrink-0 items-center gap-1",
        className
      )}
    >
      <button
        type="button"
        className={iconBtn}
        aria-label={t("layout.fullscreen")}
        onClick={() => void toggleFullscreen()}
      >
        {isFullscreen ? <MinimizeIcon className="size-4" /> : <MaximizeIcon className="size-4" />}
      </button>
      {botLinks.telegram ? (
        <a
          href={botLinks.telegram}
          target="_blank"
          rel="noopener noreferrer"
          className={iconBtn}
          aria-label={t("layout.openTelegramBot")}
        >
          <TelegramLogo className="size-4 text-muted-foreground" />
        </a>
      ) : null}
      {botLinks.bale ? (
        <a
          href={botLinks.bale}
          target="_blank"
          rel="noopener noreferrer"
          className={iconBtn}
          aria-label={t("layout.openBaleBot")}
        >
          <BaleLogo />
        </a>
      ) : null}

      <DropdownMenu>
        <DropdownMenuTrigger className={iconBtn}>
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
        <DropdownMenuTrigger className={iconBtn}>
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
        <DropdownMenuTrigger className={iconBtn}>
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
