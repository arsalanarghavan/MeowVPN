"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import type { DashboardFeatures } from "@/config/admin-nav"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

export function SiteSettingsFinanceTab({
  settings,
  dashboardBaseUrl,
  features,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  dashboardBaseUrl: string
  features?: DashboardFeatures | null
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const { ltrCell } = useDashLocale()
  const tf = (k: string) => t(`siteSettings.finance.${k}`)

  const initial = useMemo(
    () => ({
      panel_cost_reminder_days: String(settings?.panel_cost_reminder_days ?? "7,1,0"),
      panel_cost_extend_days_on_paid: String(settings?.panel_cost_extend_days_on_paid ?? 30),
    }),
    [settings]
  )

  const [form, setForm] = useState(initial)
  const [cryptoForm, setCryptoForm] = useState({
    crypto_enabled: bool(settings?.crypto_enabled ?? false),
    crypto_nowpayments_api_key: "",
    crypto_nowpayments_ipn_secret: "",
    crypto_nowpayments_pay_currency: String(settings?.crypto_nowpayments_pay_currency ?? "usdttrc20"),
  })
  useEffect(() => setForm(initial), [initial])
  useEffect(() => {
    setCryptoForm((prev) => ({
      ...prev,
      crypto_enabled: bool(settings?.crypto_enabled ?? false),
      crypto_nowpayments_pay_currency: String(settings?.crypto_nowpayments_pay_currency ?? "usdttrc20"),
    }))
  }, [settings])
  const { saving, error, okMsg, saveSettingsTab } = useSiteSettingsSave(onMutateSuccess)
  const cryptoOn = features?.crypto === true
  const apiKeySet = bool(settings?.crypto_nowpayments_api_key_set)
  const ipnSecretSet = bool(settings?.crypto_nowpayments_ipn_secret_set)

  const onSave = useCallback(async () => {
    await saveSettingsTab("finance", {
        panel_cost_reminder_days: form.panel_cost_reminder_days.trim(),
        panel_cost_extend_days_on_paid: Math.max(1, Number(form.panel_cost_extend_days_on_paid) || 30),
      })
  }, [form, saveSettingsTab])

  const economicsUrl = `${dashboardBaseUrl.replace(/\/$/, "")}/unit_economics/`

  return (
    <div className={cn("w-full space-y-6 text-start")}>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tf("title")}</CardTitle>
          <CardDescription>{tf("desc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="pc_days">{tf("reminderDays")}</Label>
            <Input
              id="pc_days"
              value={form.panel_cost_reminder_days}
              onChange={(e) =>
                setForm((f) => ({ ...f, panel_cost_reminder_days: e.target.value }))
              }
              placeholder="7,1,0"
              dir="ltr"
              className={ltrCell("font-mono")}
            />
            <p className="text-xs text-muted-foreground">{tf("reminderDaysHint")}</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="pc_extend">{tf("extendDaysOnPaid")}</Label>
            <Input
              id="pc_extend"
              type="number"
              min={1}
              max={365}
              value={form.panel_cost_extend_days_on_paid}
              onChange={(e) =>
                setForm((f) => ({ ...f, panel_cost_extend_days_on_paid: e.target.value }))
              }
            />
          </div>
          <p className="text-sm">
            <a href={economicsUrl} className="text-primary underline-offset-2 hover:underline">
              {tf("openUnitEconomics")}
            </a>
          </p>
        </CardContent>
      </Card>
      <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
      <Button type="button" disabled={saving} onClick={() => void onSave()}>
        {tf("save")}
      </Button>

      {cryptoOn ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tf("cryptoTitle")}</CardTitle>
            <CardDescription>{tf("cryptoDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={cryptoForm.crypto_enabled}
                onChange={(e) =>
                  setCryptoForm((f) => ({ ...f, crypto_enabled: e.target.checked }))
                }
              />
              {tf("cryptoEnabled")}
            </label>
            <div className="space-y-2">
              <Label htmlFor="np_api">{tf("cryptoApiKey")}</Label>
              <Input
                id="np_api"
                type="password"
                value={cryptoForm.crypto_nowpayments_api_key}
                onChange={(e) =>
                  setCryptoForm((f) => ({ ...f, crypto_nowpayments_api_key: e.target.value }))
                }
                placeholder={apiKeySet ? "••••••••" : ""}
                dir="ltr"
                className={ltrCell("font-mono")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="np_ipn">{tf("cryptoIpnSecret")}</Label>
              <Input
                id="np_ipn"
                type="password"
                value={cryptoForm.crypto_nowpayments_ipn_secret}
                onChange={(e) =>
                  setCryptoForm((f) => ({ ...f, crypto_nowpayments_ipn_secret: e.target.value }))
                }
                placeholder={ipnSecretSet ? "••••••••" : ""}
                dir="ltr"
                className={ltrCell("font-mono")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="np_cur">{tf("cryptoPayCurrency")}</Label>
              <Input
                id="np_cur"
                value={cryptoForm.crypto_nowpayments_pay_currency}
                onChange={(e) =>
                  setCryptoForm((f) => ({ ...f, crypto_nowpayments_pay_currency: e.target.value }))
                }
                dir="ltr"
                className={ltrCell("font-mono")}
              />
            </div>
            <Button
              type="button"
              variant="secondary"
              disabled={saving}
              onClick={() => {
                void (async () => {
                  const payload: Record<string, unknown> = {
                    crypto_enabled: cryptoForm.crypto_enabled ? 1 : 0,
                    crypto_nowpayments_pay_currency: cryptoForm.crypto_nowpayments_pay_currency.trim(),
                  }
                  if (cryptoForm.crypto_nowpayments_api_key.trim()) {
                    payload.crypto_nowpayments_api_key = cryptoForm.crypto_nowpayments_api_key.trim()
                  }
                  if (cryptoForm.crypto_nowpayments_ipn_secret.trim()) {
                    payload.crypto_nowpayments_ipn_secret =
                      cryptoForm.crypto_nowpayments_ipn_secret.trim()
                  }
                  const res = await postAdminMutate("crypto_settings", payload)
                  if (res.ok) onMutateSuccess?.()
                })()
              }}
            >
              {tf("cryptoSave")}
            </Button>
          </CardContent>
        </Card>
      ) : null}
    </div>
  )
}
