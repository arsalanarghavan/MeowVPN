"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { formatDateTime } from "@/lib/format-locale"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = typeof v === "number" ? v : Number(v)
  return Number.isFinite(n) ? n : 0
}

/**
 * Cron / live-metrics settings for Laravel (schedule:run + SSE).
 * Persists via settings_tab=cron mutate.
 */
export function SiteSettingsCronTab({
  settings,
  onMutateSuccess,
}: {
  settings?: DashRecord
  onMutateSuccess?: () => void
}) {
  const t = useTranslations("siteSettings.cron")
  const ts = useTranslations("siteSettings.subscriptionPortal")
  const locale = useLocale()
  const isFa = locale === "fa"
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      live_metrics_poll_seconds: String(Math.max(10, num(s.live_metrics_poll_seconds) || 15)),
      live_sse_push_seconds: String(Math.max(3, num(s.live_sse_push_seconds) || 5)),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])

  const { saving, error, okMsg, saveSettingsTab } = useSiteSettingsSave(onMutateSuccess)
  const [copyHint, setCopyHint] = useState<string | null>(null)

  const scheduleLine = "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1"

  const onCopy = useCallback(async () => {
    try {
      await navigator.clipboard?.writeText(scheduleLine)
      setCopyHint(t("copied"))
      window.setTimeout(() => setCopyHint(null), 2200)
    } catch {
      setCopyHint(null)
    }
  }, [scheduleLine, t])

  const onSave = useCallback(async () => {
    await saveSettingsTab("cron", {
      live_metrics_poll_seconds: Math.max(10, num(form.live_metrics_poll_seconds) || 15),
      live_sse_push_seconds: Math.max(3, num(form.live_sse_push_seconds) || 5),
    })
  }, [form, saveSettingsTab])

  const collectedAt = num(s.live_metrics_collected_at)

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("title")}</CardTitle>
          <CardDescription>{t("subtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="space-y-2">
            <p className="text-sm font-medium">{t("serverCronTitle")}</p>
            <pre
              className="overflow-x-auto rounded bg-muted/50 px-2 py-1 font-mono text-xs break-all whitespace-pre-wrap"
              dir="ltr"
            >
              {scheduleLine}
            </pre>
            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" variant="outline" size="sm" onClick={() => void onCopy()}>
                {t("copyLine")}
              </Button>
              {copyHint ? <span className="text-xs text-emerald-600">{copyHint}</span> : null}
            </div>
            <p className="text-xs text-muted-foreground">{t("serverCronHint")}</p>
          </div>

          <div className="space-y-2 border-t border-border/60 pt-4">
            <p className="text-sm font-medium">{t("liveMetricsTitle")}</p>
            <p className="text-xs text-muted-foreground">
              {t("liveMetricsDesc", {
                sse: form.live_sse_push_seconds,
                poll: form.live_metrics_poll_seconds,
              })}
            </p>
            {collectedAt > 0 ? (
              <p className="text-xs text-muted-foreground">
                {t("liveMetricsCollected", { at: formatDateTime(collectedAt * 1000, isFa) })}
              </p>
            ) : null}
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="live_metrics_poll_seconds">
                  {t("liveMetricsDesc", { sse: "—", poll: form.live_metrics_poll_seconds })}
                </Label>
                <Input
                  id="live_metrics_poll_seconds"
                  type="number"
                  min={10}
                  value={form.live_metrics_poll_seconds}
                  onChange={(e) =>
                    setForm((f) => ({ ...f, live_metrics_poll_seconds: e.target.value }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="live_sse_push_seconds">
                  {t("liveMetricsDesc", { sse: form.live_sse_push_seconds, poll: "—" })}
                </Label>
                <Input
                  id="live_sse_push_seconds"
                  type="number"
                  min={3}
                  value={form.live_sse_push_seconds}
                  onChange={(e) =>
                    setForm((f) => ({ ...f, live_sse_push_seconds: e.target.value }))
                  }
                />
              </div>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Button type="button" onClick={() => void onSave()} disabled={saving}>
              {saving ? t("loading") : ts("save")}
            </Button>
            <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
