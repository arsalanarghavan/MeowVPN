import { Maximize, Minimize, Moon, Sun } from "lucide-react"
import { useTranslation } from "react-i18next"

import { AccentMenu } from "@/components/accent-menu"
import { BaleLogo } from "@/components/icons/bale-logo"
import { TelegramLogo } from "@/components/icons/telegram-logo"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

export type DashboardHeaderToolbarProps = {
  variant?: "header" | "sidebar"
  botLinks: { telegram: string | null; bale: string | null }
  langLabel: string
  /** Compact label for sidebar icon button (e.g. EN / FA). */
  langShortLabel?: string
  onToggleLang: () => void
  onToggleFullscreen: () => void
  isFullscreen: boolean
  theme?: string
  onToggleTheme: () => void
  uiAccent?: string | null
  restUrl?: string
  className?: string
}

export function DashboardHeaderToolbar({
  variant = "header",
  botLinks,
  langLabel,
  langShortLabel,
  onToggleLang,
  onToggleFullscreen,
  isFullscreen,
  theme,
  onToggleTheme,
  uiAccent,
  restUrl,
  className,
}: DashboardHeaderToolbarProps) {
  const { t } = useTranslation()
  const isSidebar = variant === "sidebar"

  return (
    <div
      className={cn(
        isSidebar
          ? "flex w-full flex-wrap items-center gap-2"
          : "flex shrink-0 items-center gap-2",
        className
      )}
    >
      <Button
        type="button"
        variant="outline"
        size="icon"
        className={isSidebar ? "size-9 shrink-0" : undefined}
        aria-label={t("layout.fullscreen")}
        onClick={() => void onToggleFullscreen()}
      >
        {isFullscreen ? <Minimize className="size-4" /> : <Maximize className="size-4" />}
      </Button>
      {botLinks.telegram ? (
        <Button
          variant="outline"
          size="icon"
          className={isSidebar ? "size-9 shrink-0" : undefined}
          asChild
        >
          <a
            href={botLinks.telegram}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={t("layout.openTelegramBot")}
          >
            <TelegramLogo className="size-4 text-muted-foreground" />
          </a>
        </Button>
      ) : null}
      {botLinks.bale ? (
        <Button
          variant="outline"
          size="icon"
          className={isSidebar ? "size-9 shrink-0" : undefined}
          asChild
        >
          <a
            href={botLinks.bale}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={t("layout.openBaleBot")}
          >
            <BaleLogo />
          </a>
        </Button>
      ) : null}
      {isSidebar ? (
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="size-9 shrink-0"
          aria-label={langLabel}
          onClick={onToggleLang}
        >
          <span className="text-xs font-medium uppercase">
            {langShortLabel ?? (langLabel.length <= 3 ? langLabel : langLabel.slice(0, 2))}
          </span>
        </Button>
      ) : (
        <Button variant="outline" onClick={onToggleLang}>
          {langLabel}
        </Button>
      )}
      <AccentMenu initialAccent={uiAccent} restUrl={restUrl} />
      <Button
        variant="outline"
        size="icon"
        className={isSidebar ? "size-9 shrink-0" : undefined}
        onClick={onToggleTheme}
      >
        {theme === "dark" ? <Sun className="size-4" /> : <Moon className="size-4" />}
      </Button>
    </div>
  )
}
