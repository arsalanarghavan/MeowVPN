import { Check, Palette } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  ACCENT_MENU_ITEMS,
  ACCENT_SWATCH,
  normalizeAccent,
  type AccentPreset,
} from "@/lib/accent"
import { saveUiPreferences } from "@/lib/dash-ui-preferences"

type AccentMenuProps = {
  initialAccent?: string | null
  restUrl?: string
}

export function AccentMenu({ initialAccent, restUrl }: AccentMenuProps) {
  const { t, i18n } = useTranslation()
  const dir = i18n.dir()

  const [accent, setAccent] = useState<AccentPreset>(() => normalizeAccent(initialAccent))

  useEffect(() => {
    setAccent(normalizeAccent(initialAccent))
  }, [initialAccent])

  const saveAccent = useCallback(
    (value: AccentPreset) => {
      setAccent(value)
      document.documentElement.setAttribute("data-accent", value)
      if (!restUrl) return
      void saveUiPreferences({ ui_accent: value }, { restUrl })
    },
    [restUrl]
  )

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="relative"
          aria-label={t("layout.accent")}
        >
          <Palette className="size-4" />
          <span
            className="absolute end-1.5 bottom-1.5 size-2 rounded-full ring-1 ring-border"
            style={{ background: ACCENT_SWATCH[accent] }}
            aria-hidden
          />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-44">
        <div dir={dir} className="text-start">
          {ACCENT_MENU_ITEMS.map((item) => (
            <DropdownMenuItem
              key={item.value}
              className="gap-2"
              onSelect={() => saveAccent(item.value)}
            >
              <span
                className="size-3 shrink-0 rounded-full ring-1 ring-border"
                style={{ background: ACCENT_SWATCH[item.value] }}
                aria-hidden
              />
              <span className="flex-1 text-start">{t(item.labelKey)}</span>
              {accent === item.value ? <Check className="ms-auto size-4 shrink-0" /> : null}
            </DropdownMenuItem>
          ))}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
