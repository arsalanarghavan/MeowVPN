"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function daysToString(raw: unknown): string {
  if (Array.isArray(raw)) {
    return (raw as unknown[]).map((x) => String(Number(x))).filter((x) => x !== "NaN").join(",")
  }
  return String(raw ?? "3,1")
}

export function SiteSettingsNotificationsTab({
  settings,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const { iconGapClass, ltrCell } = useDashLocale()
  const tp = (k: string) => t(`siteSettings.notifications.${k}`)
  const tn = (k: string, opts?: Record<string, string | number>) =>
    t(`notificationsAdmin.${k}`, opts)
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      notify_low_traffic_percent: String(num(s.notify_low_traffic_percent) || 10),
      notify_expiry_days: daysToString(s.notify_expiry_days),
      notify_user_volume: bool(s.notify_user_volume ?? true),
      notify_user_expiry: bool(s.notify_user_expiry ?? true),
      notify_user_users: bool(s.notify_user_users ?? true),
      notify_user_after_expire: bool(s.notify_user_after_expire ?? true),
      notify_idle_enabled: bool(s.notify_idle_enabled),
      notify_idle_after_days: String(Math.max(7, num(s.notify_idle_after_days) || 45)),
      notify_idle_cooldown_days: String(Math.max(7, num(s.notify_idle_cooldown_days) || 90)),
      notify_admin_panel_down: bool(s.notify_admin_panel_down ?? true),
      notify_admin_panel_down_cooldown: String(Math.max(5, num(s.notify_admin_panel_down_cooldown) || 30)),
      notify_panel_cost_expiry: bool(s.notify_panel_cost_expiry ?? true),
      alert_ip_warn_min_distinct: String(Math.max(1, num(s.alert_ip_warn_min_distinct) || 3)),
      alert_ip_warn_hysteresis: bool(s.alert_ip_warn_hysteresis ?? true),
      alert_ip_warn_cooldown_minutes: String(Math.max(0, num(s.alert_ip_warn_cooldown_minutes) || 0)),
      traffic_stale_days: String(Math.max(1, num(s.traffic_stale_days) || 7)),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])
  const { saving, error, okMsg, saveSettingsTab } = useSiteSettingsSave(onMutateSuccess)

  const chk = (key: keyof typeof form, labelKey: string) => (
    <label className={iconGapClass("text-sm")}>
      <input
        type="checkbox"
        className="size-4 rounded border-input"
        checked={Boolean(form[key])}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.checked }))}
      />
      {tn(labelKey)}
    </label>
  )

  const onSave = useCallback(async () => {
    await saveSettingsTab("notifications", {
        notify_low_traffic_percent: num(form.notify_low_traffic_percent),
        notify_expiry_days: form.notify_expiry_days.trim(),
        notify_user_volume: form.notify_user_volume ? 1 : 0,
        notify_user_expiry: form.notify_user_expiry ? 1 : 0,
        notify_user_users: form.notify_user_users ? 1 : 0,
        notify_user_after_expire: form.notify_user_after_expire ? 1 : 0,
        notify_idle_enabled: form.notify_idle_enabled ? 1 : 0,
        notify_idle_after_days: Math.max(7, num(form.notify_idle_after_days)),
        notify_idle_cooldown_days: Math.max(7, num(form.notify_idle_cooldown_days)),
        notify_admin_panel_down: form.notify_admin_panel_down ? 1 : 0,
        notify_admin_panel_down_cooldown: Math.max(5, num(form.notify_admin_panel_down_cooldown)),
        notify_panel_cost_expiry: form.notify_panel_cost_expiry ? 1 : 0,
        alert_ip_warn_min_distinct: Math.max(1, num(form.alert_ip_warn_min_distinct)),
        alert_ip_warn_hysteresis: form.alert_ip_warn_hysteresis ? 1 : 0,
        alert_ip_warn_cooldown_minutes: Math.max(0, num(form.alert_ip_warn_cooldown_minutes)),
        traffic_stale_days: Math.max(1, num(form.traffic_stale_days)),
      })
  }, [form, saveSettingsTab])

  return (
    <div className={cn("w-full space-y-6 text-start")}>
      <div className="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tn("cardTitle")}</CardTitle>
          <CardDescription>{tn("cardDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="n_low">{tn("lowTrafficPercent")}</Label>
            <Input
              id="n_low"
              type="number"
              min={1}
              value={form.notify_low_traffic_percent}
              onChange={(e) => setForm((f) => ({ ...f, notify_low_traffic_percent: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="n_days">{tn("expiryDays")}</Label>
            <Input
              id="n_days"
              value={form.notify_expiry_days}
              onChange={(e) => setForm((f) => ({ ...f, notify_expiry_days: e.target.value }))}
              placeholder={tn("expiryDaysPlaceholder")}
              dir="ltr"
              className={ltrCell("font-mono")}
            />
            <p className="text-xs text-muted-foreground">{tn("expiryDaysHint")}</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tn("rulesTitle")}</CardTitle>
          <CardDescription>{tn("rulesDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {chk("notify_user_volume", "ruleVolume")}
          {chk("notify_user_expiry", "ruleExpiry")}
          {chk("notify_user_users", "ruleUsers")}
          {chk("notify_user_after_expire", "ruleAfterExpire")}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tn("idleTitle")}</CardTitle>
          <CardDescription>{tn("idleDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {chk("notify_idle_enabled", "idleEnabled")}
          <div className="space-y-2">
            <Label htmlFor="idle_after">{tn("idleAfterDays")}</Label>
            <Input
              id="idle_after"
              type="number"
              min={7}
              value={form.notify_idle_after_days}
              onChange={(e) => setForm((f) => ({ ...f, notify_idle_after_days: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="idle_cool">{tn("idleCooldownDays")}</Label>
            <Input
              id="idle_cool"
              type="number"
              min={7}
              value={form.notify_idle_cooldown_days}
              onChange={(e) => setForm((f) => ({ ...f, notify_idle_cooldown_days: e.target.value }))}
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tn("adminTitle")}</CardTitle>
          <CardDescription>{tn("adminDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {chk("notify_admin_panel_down", "adminPanelDown")}
          {chk("notify_panel_cost_expiry", "adminPanelCostExpiry")}
          <div className="space-y-2">
            <Label htmlFor="adm_cool">{tn("adminCooldown")}</Label>
            <Input
              id="adm_cool"
              type="number"
              min={5}
              value={form.notify_admin_panel_down_cooldown}
              onChange={(e) => setForm((f) => ({ ...f, notify_admin_panel_down_cooldown: e.target.value }))}
            />
          </div>
        </CardContent>
      </Card>

      <Card className="lg:col-span-2">
        <CardHeader>
          <CardTitle className="text-base">{tp("ipTitle")}</CardTitle>
          <CardDescription>{tp("ipDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="ip_min">{tp("ipMinDistinct")}</Label>
            <Input
              id="ip_min"
              type="number"
              min={1}
              value={form.alert_ip_warn_min_distinct}
              onChange={(e) => setForm((f) => ({ ...f, alert_ip_warn_min_distinct: e.target.value }))}
            />
          </div>
          <label className={iconGapClass("text-sm")}>
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={form.alert_ip_warn_hysteresis}
              onChange={(e) => setForm((f) => ({ ...f, alert_ip_warn_hysteresis: e.target.checked }))}
            />
            {tp("ipHysteresis")}
          </label>
          <div className="space-y-2">
            <Label htmlFor="ip_cool">{tp("ipCooldownMinutes")}</Label>
            <Input
              id="ip_cool"
              type="number"
              min={0}
              value={form.alert_ip_warn_cooldown_minutes}
              onChange={(e) => setForm((f) => ({ ...f, alert_ip_warn_cooldown_minutes: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="traffic_stale">{tn("trafficStaleDays")}</Label>
            <Input
              id="traffic_stale"
              type="number"
              min={1}
              value={form.traffic_stale_days}
              onChange={(e) => setForm((f) => ({ ...f, traffic_stale_days: e.target.value }))}
            />
          </div>
        </CardContent>
      </Card>
      </div>

      <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
      <Button type="button" disabled={saving} onClick={() => void onSave()}>
        {tn("save")}
      </Button>
    </div>
  )
}
