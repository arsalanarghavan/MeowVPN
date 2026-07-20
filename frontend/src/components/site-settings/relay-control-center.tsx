"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

const PURPLE = "border-violet-500/30 bg-violet-950/20"
const PURPLE_BTN = "bg-violet-600 hover:bg-violet-700 text-white"

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function asStringList(v: unknown): string[] {
  if (!Array.isArray(v)) return []
  return v.map((x) => String(x).trim()).filter(Boolean)
}

export function RelayControlCenter({
  settings,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  onMutateSuccess?: () => void
}) {
  const t = useTranslations("siteSettings.relay")
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      telegram_relay_enabled: bool(s.telegram_relay_enabled),
      telegram_relay_force: bool(s.telegram_relay_force),
      telegram_relay_vps_ip: String(s.telegram_relay_vps_ip ?? ""),
      telegram_relay_admin_url: String(s.telegram_relay_admin_url ?? s.telegram_relay_base_url ?? ""),
      telegram_relay_public_url: String(s.telegram_relay_public_url ?? ""),
      telegram_relay_laravel_forward_url: String(
        s.telegram_relay_laravel_forward_url ?? s.telegram_relay_wp_forward_url ?? ""
      ),
      telegram_relay_allowed_ips: String(s.telegram_relay_allowed_ips ?? ""),
      telegram_relay_admin_ssl_verify: bool(s.telegram_relay_admin_ssl_verify),
      telegram_relay_shared_secret: "",
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  const [dash, setDash] = useState<DashRecord | null>(null)
  const [logs, setLogs] = useState("")
  const [sslStatus, setSslStatus] = useState<DashRecord | null>(null)
  const [busy, setBusy] = useState("")
  const [actionMsg, setActionMsg] = useState<string | null>(null)
  const [sslDomain, setSslDomain] = useState("")
  const [sslEmail, setSslEmail] = useState("")

  useEffect(() => setForm(initial), [initial])

  const { saving, error, okMsg, saveSettingsTab, setError } = useSiteSettingsSave(onMutateSuccess)
  const secretSet = bool(s.telegram_relay_shared_secret_set)
  const tenantId = String(s.telegram_relay_tenant_id ?? dash?.tenant_id ?? "")

  const refreshDashboard = useCallback(async () => {
    if (!form.telegram_relay_enabled && !form.telegram_relay_force) return
    const res = await postAdminMutate("telegram_relay_admin_dashboard", {})
    if (res.ok && res.data && typeof res.data === "object") {
      setDash(res.data as DashRecord)
    }
  }, [form.telegram_relay_enabled, form.telegram_relay_force])

  useEffect(() => {
    void refreshDashboard()
    const id = window.setInterval(() => void refreshDashboard(), 30000)
    return () => window.clearInterval(id)
  }, [refreshDashboard])

  const runOp = useCallback(
    async (op: string, payload: Record<string, unknown> = {}) => {
      setBusy(op)
      setActionMsg(null)
      setError(null)
      try {
        const res = await postAdminMutate(op, payload)
        if (res.ok) {
          setActionMsg(t("actionOk"))
          if (op === "telegram_relay_admin_logs" && res.data && typeof res.data === "object") {
            setLogs(String((res.data as DashRecord).output ?? ""))
          }
          if (op === "telegram_relay_admin_ssl_status" && res.data) setSslStatus(res.data as DashRecord)
          await refreshDashboard()
          onMutateSuccess?.()
        } else {
          setActionMsg(res.message || t("actionFail"))
        }
        return res
      } finally {
        setBusy("")
      }
    },
    [onMutateSuccess, refreshDashboard, setError, t]
  )

  const onSave = useCallback(async () => {
    const ip = form.telegram_relay_vps_ip.trim()
    const adminUrl = form.telegram_relay_admin_url.trim() || (ip ? `https://${ip.replace(/^https?:\/\//, "")}` : "")
    const payload: Record<string, unknown> = {
      telegram_relay_enabled: form.telegram_relay_enabled ? 1 : 0,
      telegram_relay_force: form.telegram_relay_force ? 1 : 0,
      telegram_relay_vps_ip: ip,
      telegram_relay_admin_url: adminUrl,
      telegram_relay_base_url: adminUrl,
      telegram_relay_public_url: form.telegram_relay_public_url,
      telegram_relay_laravel_forward_url: form.telegram_relay_laravel_forward_url,
      telegram_relay_allowed_ips: form.telegram_relay_allowed_ips,
      telegram_relay_admin_ssl_verify: form.telegram_relay_admin_ssl_verify ? 1 : 0,
    }
    if (form.telegram_relay_shared_secret.trim()) {
      payload.telegram_relay_shared_secret = form.telegram_relay_shared_secret
    }
    const ok = await saveSettingsTab("relay", payload)
    if (ok) await runOp("telegram_relay_auto_sync")
  }, [form, runOp, saveSettingsTab])

  const domains = asStringList(dash?.domains).length ? asStringList(dash?.domains) : asStringList(s.telegram_relay_domains)

  return (
    <div className="w-full space-y-4 text-start">
      <Card className={cn(PURPLE)}>
        <CardHeader>
          <CardTitle className="text-base text-violet-200">{t("hubTitle")}</CardTitle>
          <CardDescription>
            {t("hubDesc")}{" "}
            <Button
              variant="link"
              size="sm"
              className="h-auto p-0 text-violet-400"
              render={
                <a
                  href="https://github.com/simplevpbot/simplevpbot/blob/main/relay-server/SETUP-GUIDE-FA.md"
                  target="_blank"
                  rel="noreferrer"
                />
              }
            >
              {t("setupGuideLink")}
            </Button>
          </CardDescription>
        </CardHeader>
      </Card>

      <Tabs defaultValue="overview" className="w-full">
        <TabsList className="flex h-auto flex-wrap gap-1 bg-violet-950/40 p-1">
          <TabsTrigger value="overview">{t("tabOverview")}</TabsTrigger>
          <TabsTrigger value="connection">{t("tabConnection")}</TabsTrigger>
          <TabsTrigger value="telegram">{t("tabTelegram")}</TabsTrigger>
          <TabsTrigger value="ssl">{t("tabSsl")}</TabsTrigger>
          <TabsTrigger value="server">{t("tabServer")}</TabsTrigger>
          <TabsTrigger value="wizard">{t("tabWizard")}</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="space-y-4 pt-4">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {[
              ["uptime", dash?.uptime_sec != null ? `${dash.uptime_sec}s` : "—"],
              ["queue", dash?.forward_queue_depth != null ? String(dash.forward_queue_depth) : "—"],
              ["systemd", String(dash?.systemd ?? "—")],
              ["nginx", dash?.nginx_ok === true ? "OK" : dash?.nginx_ok === false ? "FAIL" : "—"],
            ].map(([k, v]) => (
              <Card key={k} className={PURPLE}>
                <CardContent className="pt-4 text-sm">
                  <p className="text-muted-foreground">{k}</p>
                  <p className="font-mono text-lg text-violet-100" dir="ltr">
                    {v}
                  </p>
                </CardContent>
              </Card>
            ))}
          </div>
          <p className="text-sm text-muted-foreground">
            {t("tenantId")}: <span className="font-mono text-foreground" dir="ltr">{tenantId || "—"}</span>
          </p>
          <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void refreshDashboard()}>
            {t("refreshStatus")}
          </Button>
        </TabsContent>

        <TabsContent value="connection" className="space-y-4 pt-4">
          <Card className={PURPLE}>
            <CardContent className="space-y-4 pt-6">
              <div className="flex items-center justify-between gap-3">
                <Label htmlFor="telegram_relay_enabled">{t("enabled")}</Label>
                <Switch
                  id="telegram_relay_enabled"
                  checked={form.telegram_relay_enabled}
                  onCheckedChange={(v) => setForm((f) => ({ ...f, telegram_relay_enabled: v }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="telegram_relay_vps_ip">{t("vpsIp")}</Label>
                <Input
                  id="telegram_relay_vps_ip"
                  value={form.telegram_relay_vps_ip}
                  onChange={(e) => setForm((f) => ({ ...f, telegram_relay_vps_ip: e.target.value }))}
                  placeholder="203.0.113.5"
                  dir="ltr"
                  className="font-mono"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="telegram_relay_admin_url">{t("adminUrl")}</Label>
                <Input
                  id="telegram_relay_admin_url"
                  value={form.telegram_relay_admin_url}
                  onChange={(e) => setForm((f) => ({ ...f, telegram_relay_admin_url: e.target.value }))}
                  placeholder="https://203.0.113.5"
                  dir="ltr"
                  className="font-mono"
                />
              </div>
              <div className="flex items-center justify-between gap-3">
                <Label htmlFor="telegram_relay_admin_ssl_verify">{t("adminSslVerify")}</Label>
                <Switch
                  id="telegram_relay_admin_ssl_verify"
                  checked={form.telegram_relay_admin_ssl_verify}
                  onCheckedChange={(v) => setForm((f) => ({ ...f, telegram_relay_admin_ssl_verify: v }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="telegram_relay_shared_secret">{t("sharedSecret")}</Label>
                <Input
                  id="telegram_relay_shared_secret"
                  type="password"
                  value={form.telegram_relay_shared_secret}
                  onChange={(e) => setForm((f) => ({ ...f, telegram_relay_shared_secret: e.target.value }))}
                  placeholder={secretSet ? "••••••••" : ""}
                  dir="ltr"
                  className="font-mono"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="telegram_relay_allowed_ips">{t("allowedIps")}</Label>
                <Input
                  id="telegram_relay_allowed_ips"
                  value={form.telegram_relay_allowed_ips}
                  onChange={(e) => setForm((f) => ({ ...f, telegram_relay_allowed_ips: e.target.value }))}
                  placeholder="auto-detected on save"
                  dir="ltr"
                  className="font-mono"
                />
              </div>
            </CardContent>
          </Card>
          <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
          <div className="flex flex-wrap gap-2">
            <Button type="button" className={PURPLE_BTN} disabled={saving} onClick={() => void onSave()}>
              {t("save")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_test")}>
              {t("testConnection")}
            </Button>
          </div>
        </TabsContent>

        <TabsContent value="telegram" className="space-y-4 pt-4">
          <div className="space-y-2">
            <Label htmlFor="telegram_relay_laravel_forward_url">{t("laravelForwardUrl")}</Label>
            <Input
              id="telegram_relay_laravel_forward_url"
              value={form.telegram_relay_laravel_forward_url}
              onChange={(e) =>
                setForm((f) => ({ ...f, telegram_relay_laravel_forward_url: e.target.value }))
              }
              placeholder="https://api.example.com"
              dir="ltr"
              className="font-mono"
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="telegram_relay_public_url">{t("publicUrl")}</Label>
            <Input
              id="telegram_relay_public_url"
              value={form.telegram_relay_public_url}
              onChange={(e) => setForm((f) => ({ ...f, telegram_relay_public_url: e.target.value }))}
              placeholder="https://tg.example.com"
              dir="ltr"
              className="font-mono"
            />
          </div>
          <ul className="list-inside list-disc font-mono text-xs" dir="ltr">
            {domains.map((d) => (
              <li key={d}>{d}</li>
            ))}
          </ul>
          <div className="flex flex-wrap gap-2">
            <Button type="button" className={PURPLE_BTN} disabled={busy !== ""} onClick={() => void runOp("telegram_relay_sync")}>
              {t("syncConfig")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_domains_sync")}>
              {t("syncDomains")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_set_webhook")}>
              {t("setWebhook")}
            </Button>
          </div>
        </TabsContent>

        <TabsContent value="ssl" className="space-y-4 pt-4">
          <div className="grid gap-2 sm:grid-cols-2">
            <Input value={sslDomain} onChange={(e) => setSslDomain(e.target.value)} placeholder="tg.example.com" dir="ltr" />
            <Input value={sslEmail} onChange={(e) => setSslEmail(e.target.value)} placeholder="email@example.com" dir="ltr" />
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              className={PURPLE_BTN}
              disabled={busy !== "" || !sslDomain}
              onClick={() =>
                void runOp("telegram_relay_admin_ssl_issue", {
                  domain: sslDomain,
                  email: sslEmail,
                  method: "certbot",
                })
              }
            >
              {t("sslIssue")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_ssl_renew", { method: "certbot" })}>
              {t("sslRenew")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_ssl_status")}>
              {t("sslStatus")}
            </Button>
          </div>
          {sslStatus ? (
            <pre className="max-h-48 overflow-auto rounded-md border bg-muted/40 p-2 text-xs" dir="ltr">
              {JSON.stringify(sslStatus, null, 2)}
            </pre>
          ) : null}
        </TabsContent>

        <TabsContent value="server" className="space-y-4 pt-4">
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_nginx_render")}>
              {t("nginxRender")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_nginx_test")}>
              {t("nginxTest")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_nginx_reload")}>
              {t("nginxReload")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_service_restart")}>
              {t("serviceRestart")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_update")}>
              {t("relayUpdate")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_logs", { lines: 100 })}>
              {t("viewLogs")}
            </Button>
            <Button type="button" variant="outline" disabled={busy !== ""} onClick={() => void runOp("telegram_relay_admin_doctor")}>
              {t("doctor")}
            </Button>
          </div>
          {logs ? (
            <pre className="max-h-64 overflow-auto rounded-md border bg-muted/40 p-2 text-xs" dir="ltr">
              {logs}
            </pre>
          ) : null}
        </TabsContent>

        <TabsContent value="wizard" className="space-y-4 pt-4">
          <Card className={PURPLE}>
            <CardContent className="space-y-2 pt-6 text-sm">
              <p>1. {t("wizInstall")}</p>
              <p>2. {t("wizIp")}</p>
              <p>3. {t("wizSecret")}</p>
              <p>4. {t("wizSave")}</p>
              <p>5. {t("wizDomain")}</p>
              <p>6. {t("wizWebhook")}</p>
            </CardContent>
          </Card>
          <Button type="button" className={PURPLE_BTN} disabled={busy !== ""} onClick={() => void runOp("telegram_relay_auto_sync")}>
            {t("runAutoSync")}
          </Button>
        </TabsContent>
      </Tabs>

      {actionMsg ? <p className="text-sm text-muted-foreground">{actionMsg}</p> : null}
    </div>
  )
}
