"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"

type DashRecord = Record<string, unknown>

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

export function SiteSettingsLandingTab({
  settings,
  onMutateSuccess,
}: {
  settings?: DashRecord
  onMutateSuccess?: () => void
}) {
  const t = useTranslations("siteSettings.landing")
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      landing_enabled: bool(s.landing_enabled),
      landing_hero_title: String(s.landing_hero_title ?? ""),
      landing_hero_subtitle: String(s.landing_hero_subtitle ?? ""),
      landing_promo_title: String(s.landing_promo_title ?? ""),
      landing_promo_code: String(s.landing_promo_code ?? ""),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])

  const { saving, error, okMsg, saveSettingsTab } = useSiteSettingsSave(onMutateSuccess)

  const onSave = useCallback(async () => {
    await saveSettingsTab("landing", {
      landing_enabled: form.landing_enabled,
      landing_hero_title: form.landing_hero_title,
      landing_hero_subtitle: form.landing_hero_subtitle,
      landing_promo_title: form.landing_promo_title,
      landing_promo_code: form.landing_promo_code,
    })
  }, [form, saveSettingsTab])

  const homeUrl = typeof window !== "undefined" ? `${window.location.origin}/` : "/"

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("title")}</CardTitle>
          <CardDescription>{t("desc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
            <div className="space-y-1">
              <Label htmlFor="landing_enabled">{t("enabled")}</Label>
              <p className="text-sm text-muted-foreground">{t("enabledHint")}</p>
            </div>
            <Switch
              id="landing_enabled"
              checked={form.landing_enabled}
              onCheckedChange={(v) => setForm((f) => ({ ...f, landing_enabled: v }))}
            />
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="landing_hero_title">{t("heroTitle")}</Label>
              <Input
                id="landing_hero_title"
                value={form.landing_hero_title}
                onChange={(e) => setForm((f) => ({ ...f, landing_hero_title: e.target.value }))}
                placeholder={t("heroTitlePlaceholder")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="landing_promo_code">{t("promoCode")}</Label>
              <Input
                id="landing_promo_code"
                value={form.landing_promo_code}
                onChange={(e) => setForm((f) => ({ ...f, landing_promo_code: e.target.value }))}
                placeholder={t("promoCodePlaceholder")}
              />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="landing_hero_subtitle">{t("heroSubtitle")}</Label>
            <Textarea
              id="landing_hero_subtitle"
              value={form.landing_hero_subtitle}
              onChange={(e) => setForm((f) => ({ ...f, landing_hero_subtitle: e.target.value }))}
              placeholder={t("heroSubtitlePlaceholder")}
              rows={3}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="landing_promo_title">{t("promoTitle")}</Label>
            <Input
              id="landing_promo_title"
              value={form.landing_promo_title}
              onChange={(e) => setForm((f) => ({ ...f, landing_promo_title: e.target.value }))}
              placeholder={t("promoTitlePlaceholder")}
            />
          </div>

          <p className="text-sm text-muted-foreground">{t("contactHint")}</p>

          <div className="flex flex-wrap items-center gap-3">
            <Button type="button" onClick={() => void onSave()} disabled={saving}>
              {saving ? t("saving") : t("save")}
            </Button>
            {form.landing_enabled ? (
              <a
                href={homeUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex h-8 items-center justify-center rounded-lg border border-border bg-background px-2.5 text-sm font-medium hover:bg-muted"
              >
                {t("preview")}
              </a>
            ) : null}
          </div>

          <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
        </CardContent>
      </Card>
    </div>
  )
}
