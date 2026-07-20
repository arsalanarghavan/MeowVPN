"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { Switch } from "@/components/ui/switch"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

export function SiteSettingsProxyTab({
  settings,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  onMutateSuccess?: () => void
}) {
  const t = useTranslations("siteSettings.proxy")
  const { ltrCell } = useDashLocale()
  const tp = t
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      telegram_proxy_enabled: bool(s.telegram_proxy_enabled),
      telegram_proxy_type: String(s.telegram_proxy_type || "http"),
      telegram_proxy_host: String(s.telegram_proxy_host ?? ""),
      telegram_proxy_port: String(Number(s.telegram_proxy_port) || ""),
      telegram_proxy_username: String(s.telegram_proxy_username ?? ""),
      telegram_proxy_password: "",
      telegram_api_base_url: String(s.telegram_api_base_url ?? ""),
    }),
    [s])

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])
  const { saving, error, okMsg, saveSettingsTab, setError } = useSiteSettingsSave(onMutateSuccess)
  const [testing, setTesting] = useState(false)
  const [testMsg, setTestMsg] = useState<string | null>(null)
  const passwordSet = bool(s.telegram_proxy_password_set)

  const onSave = useCallback(async () => {
    const payload: Record<string, unknown> = {
      telegram_proxy_enabled: form.telegram_proxy_enabled ? 1 : 0,
      telegram_proxy_type: form.telegram_proxy_type,
      telegram_proxy_host: form.telegram_proxy_host,
      telegram_proxy_port: Number(form.telegram_proxy_port) || 0,
      telegram_proxy_username: form.telegram_proxy_username,
      telegram_api_base_url: form.telegram_api_base_url,
    }
    if (form.telegram_proxy_password.trim() !== "") {
      payload.telegram_proxy_password = form.telegram_proxy_password
    }
    await saveSettingsTab("proxy", payload)
  }, [form, saveSettingsTab])

  const onTest = useCallback(async () => {
    setTesting(true)
    setTestMsg(null)
    setError(null)
    try {
      const res = await postAdminMutate("telegram_proxy_test", {})
      const data = res.data as Record<string, unknown> | undefined
      if (res.ok) {
        const uname = typeof data?.username === "string" ? data.username : ""
        setTestMsg(uname ? tp("testOkUser").replace("{{user}}", uname) : tp("testOk"))
      } else {
        setTestMsg(res.message || tp("testFail"))
      }
    } finally {
      setTesting(false)
    }
  }, [tp])

  const row = cn("flex items-center justify-between gap-3")

  return (
    <div className={cn("w-full space-y-6 text-start")}>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("title")}</CardTitle>
          <CardDescription>{tp("desc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className={row}>
            <Label>{tp("enabled")}</Label>
            <Switch
              checked={form.telegram_proxy_enabled}
              onCheckedChange={(v) => setForm((f) => ({ ...f, telegram_proxy_enabled: v }))}
            />
          </div>
          <div className="space-y-2">
            <Label>{tp("type")}</Label>
            <DashSelect
              value={form.telegram_proxy_type}
              onValueChange={(v) => setForm((f) => ({ ...f, telegram_proxy_type: v }))}
              options={[
                { value: "http", label: "HTTP" },
                { value: "socks5", label: "SOCKS5" },
              ]}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="px_host">{tp("host")}</Label>
              <Input
                id="px_host"
                value={form.telegram_proxy_host}
                onChange={(e) => setForm((f) => ({ ...f, telegram_proxy_host: e.target.value }))}
                dir="ltr"
                className={ltrCell("font-mono")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="px_port">{tp("port")}</Label>
              <Input
                id="px_port"
                type="number"
                min={0}
                max={65535}
                value={form.telegram_proxy_port}
                onChange={(e) => setForm((f) => ({ ...f, telegram_proxy_port: e.target.value }))}
                dir="ltr"
                className={ltrCell("tabular-nums")}
              />
            </div>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="px_user">{tp("username")}</Label>
              <Input
                id="px_user"
                value={form.telegram_proxy_username}
                onChange={(e) => setForm((f) => ({ ...f, telegram_proxy_username: e.target.value }))}
                dir="ltr"
                className={ltrCell("font-mono")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="px_pass">{tp("password")}</Label>
              <Input
                id="px_pass"
                type="password"
                value={form.telegram_proxy_password}
                onChange={(e) => setForm((f) => ({ ...f, telegram_proxy_password: e.target.value }))}
                placeholder={passwordSet ? "••••••••" : ""}
                dir="ltr"
                className={ltrCell("font-mono")}
              />
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="api_base">{tp("apiBaseUrl")}</Label>
            <Input
              id="api_base"
              value={form.telegram_api_base_url}
              onChange={(e) => setForm((f) => ({ ...f, telegram_api_base_url: e.target.value }))}
              placeholder={tp("apiBasePlaceholder")}
              dir="ltr"
              className={ltrCell("font-mono")}
            />
            <p className="text-xs text-muted-foreground">{tp("apiBaseHint")}</p>
          </div>
        </CardContent>
      </Card>

      <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
      {testMsg ? <p className="text-sm text-muted-foreground">{testMsg}</p> : null}
      <div className={cn("flex flex-wrap gap-2")}>
        <Button type="button" disabled={saving} onClick={() => void onSave()}>
          {tp("save")}
        </Button>
        <Button type="button" variant="outline" disabled={testing} onClick={() => void onTest()}>
          {tp("testConnection")}
        </Button>
      </div>
    </div>
  )
}
