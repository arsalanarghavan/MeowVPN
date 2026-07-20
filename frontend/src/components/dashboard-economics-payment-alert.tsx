"use client"

import { useCallback, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { AlertTriangle } from "lucide-react"
import { Button } from "@/components/ui/button"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { buildDashboardTabUrl } from "@/lib/dash-tab"

export type UpcomingPayment = {
  line_id: number
  panel_id: number
  panel_label: string
  label: string
  cost_amount: number
  expires_at: string
  days_left: number
  payment_method?: string
}

export function DashboardEconomicsPaymentAlert({
  items,
  dashboardBaseUrl,
  onDismissRefresh,
}: {
  items: UpcomingPayment[]
  dashboardBaseUrl: string
  onDismissRefresh?: () => void
}) {
  const t = useTranslations("economicsOverview.alert")
  const locale = useLocale()
  const isFa = locale === "fa"
  const [busyId, setBusyId] = useState(0)

  const markPaid = useCallback(
    async (panelId: number) => {
      setBusyId(panelId)
      try {
        const res = await postAdminMutate("panel_economics_mark_paid", { panel_id: panelId })
        if (res.ok) onDismissRefresh?.()
      } finally {
        setBusyId(0)
      }
    },
    [onDismissRefresh]
  )

  if (!items.length) return null

  const settingsUrl = `${buildDashboardTabUrl(dashboardBaseUrl, "site_settings")}?site_subtab=finance`

  return (
    <div role="alert" className="rounded-lg border border-amber-500/50 bg-amber-500/10 px-4 py-3">
      <div className="flex gap-2">
        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-300" />
        <div className="min-w-0 flex-1 space-y-2">
          <p className="text-sm font-medium">{t("title")}</p>
          <ul className="space-y-2 text-sm">
            {items.slice(0, 8).map((row) => (
              <li
                key={row.panel_id}
                className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-amber-500/30 bg-background/60 px-2 py-1.5"
              >
                <span className="min-w-0">
                  <span className="font-medium">{row.panel_label}</span>
                  <span className="text-muted-foreground"> — {row.label}</span>
                  <span className="ms-2 text-xs text-muted-foreground">
                    {row.expires_at} ({t("daysLeft", { n: row.days_left })})
                  </span>
                </span>
                <span className="flex shrink-0 items-center gap-2">
                  <span className="tabular-nums">
                    {formatNumber(row.cost_amount, isFa)} {t("currency")}
                  </span>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={busyId === row.panel_id}
                    onClick={() => void markPaid(row.panel_id)}
                  >
                    {t("markPaid")}
                  </Button>
                </span>
              </li>
            ))}
          </ul>
          {items.length > 8 ? <p className="text-xs text-muted-foreground">{t("more", { n: items.length - 8 })}</p> : null}
          <a href={settingsUrl} className="text-xs text-primary underline-offset-2 hover:underline">
            {t("settingsLink")}
          </a>
        </div>
      </div>
    </div>
  )
}
