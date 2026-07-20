"use client"

import { useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { ChevronsUpDown } from "lucide-react"
import { Button } from "@/components/ui/button"
import { stopImpersonation } from "@/lib/impersonation"

export function ImpersonationBanner({ targetLabel }: { targetLabel: string }) {
  const t = useTranslations("layout")
  const locale = useLocale()
  const [busy, setBusy] = useState(false)

  async function stop() {
    if (busy) return
    setBusy(true)
    try {
      const r = await stopImpersonation()
      if (r.ok) {
        window.location.href = `/${locale}/dashboard`
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <div
      data-testid="impersonation-banner"
      className="flex w-full shrink-0 flex-wrap items-center justify-between gap-3 border-b border-amber-200/80 bg-amber-50 px-4 py-2 text-sm dark:border-amber-900/50 dark:bg-amber-950/40"
    >
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <span className="shrink-0 text-muted-foreground">{t("impersonationBarPrefix")}</span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-8 max-w-[min(100%,24rem)] gap-1 font-normal"
          disabled
          aria-label={targetLabel}
        >
          <span className="truncate">{targetLabel}</span>
          <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden />
        </Button>
      </div>
      <Button
        type="button"
        variant="secondary"
        size="sm"
        className="w-full shrink-0 sm:w-auto"
        onClick={() => void stop()}
        disabled={busy}
      >
        {t("impersonationSwitchToAdmin")}
      </Button>
    </div>
  )
}
