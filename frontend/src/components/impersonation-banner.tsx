import { useState } from "react"
import { useTranslation } from "react-i18next"
import { ChevronsUpDown } from "lucide-react"

import { Button } from "@/components/ui/button"
import { apiHeaders, ensureCsrfCookie, normalizeAdminApiPath } from "@/lib/api-base"

export function ImpersonationBanner({
  targetLabel,
  restBase,
  dashboardBaseUrl,
}: {
  targetLabel: string
  restBase: string
  dashboardBaseUrl: string
}) {
  const { t } = useTranslation()
  const [busy, setBusy] = useState(false)

  async function stop() {
    if (busy || !restBase) return
    setBusy(true)
    try {
      await ensureCsrfCookie()
      const base = restBase.replace(/\/$/, "")
      const r = await fetch(`${base}${normalizeAdminApiPath("/dashboard/impersonate/stop")}`, {
        method: "POST",
        credentials: "include",
        headers: apiHeaders(),
      })
      if (r.ok) {
        const base = dashboardBaseUrl.replace(/\/?$/, "")
        window.location.href = `${base}/`
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <div
      className="flex w-full shrink-0 flex-wrap items-center justify-between gap-3 border-b border-amber-200/80 bg-amber-50 px-4 py-2 text-sm dark:border-amber-900/50 dark:bg-amber-950/40"
    >
      <div className="flex min-w-0 flex-1 items-center gap-2">
        <span className="shrink-0 text-muted-foreground">{t("layout.impersonationBarPrefix")}</span>
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
        {t("layout.impersonationSwitchToAdmin")}
      </Button>
    </div>
  )
}
