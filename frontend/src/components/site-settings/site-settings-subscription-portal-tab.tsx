"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

const TEMPLATES = [
  { id: "classic", titleKey: "classicTitle", descKey: "classicDesc", preview: "classic.svg" },
  { id: "modern", titleKey: "modernTitle", descKey: "modernDesc", preview: "modern.svg" },
  {
    id: "pasarguard_builtin",
    titleKey: "pasarguardBuiltinTitle",
    descKey: "pasarguardBuiltinDesc",
    preview: "pasarguard_builtin.svg",
  },
  {
    id: "pasarguard_v1",
    titleKey: "pasarguardV1Title",
    descKey: "pasarguardV1Desc",
    preview: "pasarguard_v1.svg",
  },
  {
    id: "pasarguard_v2",
    titleKey: "pasarguardV2Title",
    descKey: "pasarguardV2Desc",
    preview: "pasarguard_v2.svg",
  },
  { id: "xui", titleKey: "xuiTitle", descKey: "xuiDesc", preview: "xui.svg" },
] as const

const PG_TEMPLATE_IDS = new Set(["pasarguard_builtin", "pasarguard_v1", "pasarguard_v2", "pasarguard"])

function normalizeTemplateId(raw: string): string {
  if (raw === "pasarguard") return "pasarguard_v2"
  return raw
}

function previewUrl(file: string): string {
  return `/portal-previews/${file}`
}

export function SiteSettingsSubscriptionPortalTab({
  settings,
  portalBaseUrl,
  onMutateSuccess,
}: {
  settings?: DashRecord
  portalBaseUrl?: string
  onMutateSuccess?: () => void
}) {
  const t = useTranslations("siteSettings.subscriptionPortal")
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      portal_subscription_template: normalizeTemplateId(
        String(s.portal_subscription_template || "classic")
      ),
      portal_theme_brand_name: String(s.portal_theme_brand_name ?? s.portal_modern_brand_name ?? ""),
      portal_theme_brand_tagline: String(
        s.portal_theme_brand_tagline ?? s.portal_modern_brand_tagline ?? ""
      ),
      portal_datepicker: String(s.portal_datepicker || "jalali"),
      portal_theme_primary_light: String(s.portal_theme_primary_light ?? ""),
      portal_theme_primary_dark: String(s.portal_theme_primary_dark ?? ""),
      portal_theme_radius: String(s.portal_theme_radius ?? ""),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])

  const { saving, error, okMsg, saveSettingsTab } = useSiteSettingsSave(onMutateSuccess)

  const onSave = useCallback(async () => {
    await saveSettingsTab("subscription_portal", {
      portal_subscription_template: form.portal_subscription_template,
      portal_theme_brand_name: form.portal_theme_brand_name,
      portal_theme_brand_tagline: form.portal_theme_brand_tagline,
      portal_modern_brand_name: form.portal_theme_brand_name,
      portal_modern_brand_tagline: form.portal_theme_brand_tagline,
      portal_datepicker: form.portal_datepicker,
      portal_theme_primary_light: form.portal_theme_primary_light,
      portal_theme_primary_dark: form.portal_theme_primary_dark,
      portal_theme_radius: form.portal_theme_radius,
    })
  }, [form, saveSettingsTab])

  const preview = String(portalBaseUrl ?? "").trim()
  const showAppearance = PG_TEMPLATE_IDS.has(form.portal_subscription_template)
  const isSpa =
    form.portal_subscription_template === "modern" ||
    PG_TEMPLATE_IDS.has(form.portal_subscription_template) ||
    form.portal_subscription_template === "xui"

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("title")}</CardTitle>
          <CardDescription>{t("desc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            {TEMPLATES.map((tpl) => {
              const active = form.portal_subscription_template === tpl.id
              return (
                <div
                  key={tpl.id}
                  className={cn(
                    "rounded-lg border p-4 transition-colors",
                    active ? "border-primary bg-primary/5" : "border-border"
                  )}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={previewUrl(tpl.preview)}
                    alt=""
                    className="mb-3 h-24 w-full rounded-md border border-border/60 object-cover"
                  />
                  <div className="mb-2 flex items-center justify-between gap-2">
                    <h3 className="font-semibold">{t(tpl.titleKey)}</h3>
                    {active ? (
                      <span className="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
                        {t("active")}
                      </span>
                    ) : null}
                  </div>
                  <p className="mb-3 text-sm text-muted-foreground">{t(tpl.descKey)}</p>
                  <Button
                    type="button"
                    size="sm"
                    variant={active ? "secondary" : "outline"}
                    disabled={active || saving}
                    onClick={() =>
                      setForm((f) => ({ ...f, portal_subscription_template: tpl.id }))
                    }
                  >
                    {t("select")}
                  </Button>
                </div>
              )
            })}
          </div>
          <p className="text-xs text-muted-foreground">{t("l2tpNote")}</p>
        </CardContent>
      </Card>

      {isSpa ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("brandingTitle")}</CardTitle>
            <CardDescription>{t("brandingDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="portal_theme_brand_name">{t("brandName")}</Label>
              <Input
                id="portal_theme_brand_name"
                value={form.portal_theme_brand_name}
                onChange={(e) => setForm((f) => ({ ...f, portal_theme_brand_name: e.target.value }))}
              />
              <p className="text-xs text-muted-foreground">{t("brandNameHint")}</p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="portal_theme_brand_tagline">{t("brandTagline")}</Label>
              <Input
                id="portal_theme_brand_tagline"
                value={form.portal_theme_brand_tagline}
                onChange={(e) =>
                  setForm((f) => ({ ...f, portal_theme_brand_tagline: e.target.value }))
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="portal_datepicker">{t("datepickerTitle")}</Label>
              <select
                id="portal_datepicker"
                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                value={form.portal_datepicker}
                onChange={(e) => setForm((f) => ({ ...f, portal_datepicker: e.target.value }))}
              >
                <option value="jalali">{t("datepickerJalali")}</option>
                <option value="gregorian">{t("datepickerGregorian")}</option>
              </select>
              <p className="text-xs text-muted-foreground">{t("datepickerDesc")}</p>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {showAppearance ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("appearanceTitle")}</CardTitle>
            <CardDescription>{t("appearanceDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="portal_theme_primary_light">{t("primaryLight")}</Label>
              <Input
                id="portal_theme_primary_light"
                value={form.portal_theme_primary_light}
                onChange={(e) =>
                  setForm((f) => ({ ...f, portal_theme_primary_light: e.target.value }))
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="portal_theme_primary_dark">{t("primaryDark")}</Label>
              <Input
                id="portal_theme_primary_dark"
                value={form.portal_theme_primary_dark}
                onChange={(e) =>
                  setForm((f) => ({ ...f, portal_theme_primary_dark: e.target.value }))
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="portal_theme_radius">{t("borderRadius")}</Label>
              <Input
                id="portal_theme_radius"
                value={form.portal_theme_radius}
                onChange={(e) => setForm((f) => ({ ...f, portal_theme_radius: e.target.value }))}
              />
            </div>
          </CardContent>
        </Card>
      ) : null}

      {preview ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("previewLink")}</CardTitle>
            <CardDescription>{t("previewHint")}</CardDescription>
          </CardHeader>
          <CardContent>
            <a
              href={preview}
              className="text-sm text-primary underline-offset-4 hover:underline"
              target="_blank"
              rel="noreferrer"
            >
              {preview}
            </a>
          </CardContent>
        </Card>
      ) : null}

      <div className="flex flex-wrap items-center gap-3">
        <Button type="button" onClick={() => void onSave()} disabled={saving}>
          {t("save")}
        </Button>
        <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
      </div>
    </div>
  )
}
