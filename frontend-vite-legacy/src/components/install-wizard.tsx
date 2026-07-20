"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { cn } from "@/lib/utils"
import {
  completeSetupWizard,
  fetchSetupDomains,
  importWordpressBackup,
  probeSetupDomains,
  readInstallToken,
  registerSetupWebhooks,
  restoreMeowvpnBackup,
  saveSetupAdminCredentials,
  updateSetupDomains,
  type DomainProbe,
} from "@/lib/setup-wizard-api"

const STEPS = [1, 2, 3, 4] as const

type BackupKind = "none" | "meowvpn" | "wordpress"

export function InstallWizard() {
  const { t } = useTranslation()
  const tw = (k: string) => t(`installWizard.${k}`)
  const token = useMemo(() => readInstallToken(), [])

  const [step, setStep] = useState<(typeof STEPS)[number]>(1)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [probes, setProbes] = useState<DomainProbe[]>([])
  const [reinstallHint, setReinstallHint] = useState<string | null>(null)

  const [coreUrl, setCoreUrl] = useState("")
  const [dashboardUrl, setDashboardUrl] = useState("")
  const [telegramUrl, setTelegramUrl] = useState("")
  const [baleUrl, setBaleUrl] = useState("")
  const [relayUrl, setRelayUrl] = useState("")

  const [backupKind, setBackupKind] = useState<BackupKind>("none")
  const [backupFile, setBackupFile] = useState<File | null>(null)
  const [restorePanelDb, setRestorePanelDb] = useState(false)
  const [backupDone, setBackupDone] = useState(false)

  const [username, setUsername] = useState("admin")
  const [password, setPassword] = useState("")
  const [passwordConfirm, setPasswordConfirm] = useState("")
  const [adminDone, setAdminDone] = useState(false)

  const [loginUrl, setLoginUrl] = useState<string | null>(null)

  const loadDomains = useCallback(async () => {
    setErr(null)
    setBusy(true)
    try {
      const json = await fetchSetupDomains()
      if (json.code === "invalid_install_token") {
        setErr(tw("invalidToken"))
        return
      }
      const urls = json.urls as Record<string, string> | undefined
      if (urls) {
        setCoreUrl(urls.core_url || "")
        setDashboardUrl(urls.dashboard_url || "")
        setTelegramUrl(urls.telegram_url || "")
        setBaleUrl(urls.bale_url || "")
        setRelayUrl(urls.relay_url || "")
      }
      setProbes((json.probes as DomainProbe[]) || [])
      if (json.reinstall_hint) {
        setReinstallHint(String(json.reinstall_hint))
      }
    } catch {
      setErr(tw("loadFailed"))
    } finally {
      setBusy(false)
    }
  }, [tw])

  useEffect(() => {
    if (!token) {
      setErr(tw("missingToken"))
      return
    }
    void loadDomains()
  }, [loadDomains, token, tw])

  const onSaveDomains = async () => {
    setBusy(true)
    setErr(null)
    try {
      const json = await updateSetupDomains({
        core_url: coreUrl.trim(),
        dashboard_url: dashboardUrl.trim(),
        telegram_url: telegramUrl.trim(),
        bale_url: baleUrl.trim(),
        relay_url: relayUrl.trim(),
      })
      setProbes((json.probes as DomainProbe[]) || [])
      if (json.reinstall_hint) {
        setReinstallHint(String(json.reinstall_hint))
      }
      if (!json.ok) {
        setErr(tw("saveFailed"))
      }
    } catch {
      setErr(tw("saveFailed"))
    } finally {
      setBusy(false)
    }
  }

  const onProbe = async () => {
    setBusy(true)
    setErr(null)
    try {
      const json = await probeSetupDomains()
      setProbes(json.probes || [])
    } catch {
      setErr(tw("probeFailed"))
    } finally {
      setBusy(false)
    }
  }

  const onRegisterWebhooks = async () => {
    setBusy(true)
    setErr(null)
    try {
      await registerSetupWebhooks()
    } catch {
      setErr(tw("webhookFailed"))
    } finally {
      setBusy(false)
    }
  }

  const onRunBackup = async () => {
    if (backupKind === "none") {
      setBackupDone(true)
      return
    }
    if (!backupFile) {
      setErr(tw("backupFileRequired"))
      return
    }
    setBusy(true)
    setErr(null)
    try {
      let json: Record<string, unknown>
      if (backupKind === "meowvpn") {
        json = await restoreMeowvpnBackup(backupFile, restorePanelDb)
      } else {
        const dry = await importWordpressBackup(backupFile, { dryRun: true })
        if (!dry.ok) {
          setErr(tw("backupFailed"))
          return
        }
        json = await importWordpressBackup(backupFile, { force: true })
      }
      if (!json.ok) {
        setErr(tw("backupFailed"))
        return
      }
      setBackupDone(true)
    } catch {
      setErr(tw("backupFailed"))
    } finally {
      setBusy(false)
    }
  }

  const onSaveAdmin = async () => {
    setBusy(true)
    setErr(null)
    try {
      const json = await saveSetupAdminCredentials({
        username: username.trim(),
        password,
        password_confirm: passwordConfirm,
      })
      if (!json.ok) {
        setErr(tw(String(json.message || "adminFailed")))
        return
      }
      setAdminDone(true)
      setStep(4)
    } catch {
      setErr(tw("adminFailed"))
    } finally {
      setBusy(false)
    }
  }

  const onComplete = async () => {
    setBusy(true)
    setErr(null)
    try {
      const json = await completeSetupWizard()
      if (!json.ok || !json.dashboard_login_url) {
        setErr(tw("completeFailed"))
        return
      }
      setLoginUrl(json.dashboard_login_url)
      window.setTimeout(() => {
        window.location.assign(json.dashboard_login_url!)
      }, 1500)
    } catch {
      setErr(tw("completeFailed"))
    } finally {
      setBusy(false)
    }
  }

  const coreOk = probes.some((p) => p.key === "core" && p.ok)
  const dashOk = probes.length === 0 || probes.some((p) => p.key === "dashboard" && p.ok)

  return (
    <div className="flex min-h-svh w-full items-center justify-center bg-background p-4">
      <Card className="w-full max-w-2xl shadow-sm">
        <CardHeader>
          <CardTitle className="text-xl">{tw("title")}</CardTitle>
          <CardDescription>{tw("subtitle")}</CardDescription>
          <div className="flex flex-wrap gap-2 pt-2">
            {STEPS.map((n) => (
              <span
                key={n}
                className={cn(
                  "rounded-full px-3 py-1 text-xs font-medium",
                  step === n ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground"
                )}
              >
                {tw(`step${n}Label`)}
              </span>
            ))}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {err ? <p className="text-sm text-destructive">{err}</p> : null}

          {step === 1 ? (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">{tw("step1Help")}</p>
              <div className="grid gap-3">
                <div className="grid gap-1">
                  <Label>{tw("coreUrl")}</Label>
                  <Input value={coreUrl} onChange={(e) => setCoreUrl(e.target.value)} disabled={busy} />
                </div>
                <div className="grid gap-1">
                  <Label>{tw("dashboardUrl")}</Label>
                  <Input value={dashboardUrl} onChange={(e) => setDashboardUrl(e.target.value)} disabled={busy} />
                </div>
                <div className="grid gap-1">
                  <Label>{tw("telegramUrl")}</Label>
                  <Input value={telegramUrl} onChange={(e) => setTelegramUrl(e.target.value)} disabled={busy} />
                </div>
                <div className="grid gap-1">
                  <Label>{tw("baleUrl")}</Label>
                  <Input value={baleUrl} onChange={(e) => setBaleUrl(e.target.value)} disabled={busy} />
                </div>
                <div className="grid gap-1">
                  <Label>{tw("relayUrl")}</Label>
                  <Input value={relayUrl} onChange={(e) => setRelayUrl(e.target.value)} disabled={busy} />
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button type="button" variant="secondary" disabled={busy} onClick={() => void onSaveDomains()}>
                  {tw("saveDomains")}
                </Button>
                <Button type="button" variant="outline" disabled={busy} onClick={() => void onProbe()}>
                  {tw("probeAgain")}
                </Button>
                <Button type="button" variant="outline" disabled={busy} onClick={() => void onRegisterWebhooks()}>
                  {tw("registerWebhooks")}
                </Button>
              </div>
              {reinstallHint ? (
                <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs">{reinstallHint}</pre>
              ) : null}
              <ul className="space-y-2 text-sm">
                {probes.map((p) => (
                  <li
                    key={p.key}
                    className={cn(
                      "rounded-md border p-3",
                      p.ok ? "border-green-500/40" : "border-destructive/40"
                    )}
                  >
                    <div className="font-medium">{p.label}</div>
                    <div className="text-muted-foreground">{p.url}</div>
                    <div>{p.ok ? tw("probeOk") : tw("probeFail")}</div>
                    {p.hints?.length ? (
                      <ul className="mt-1 list-disc ps-4 text-xs text-muted-foreground">
                        {p.hints.map((h) => (
                          <li key={h}>{h}</li>
                        ))}
                      </ul>
                    ) : null}
                  </li>
                ))}
              </ul>
              <Button
                type="button"
                disabled={busy || !coreOk || !dashOk}
                onClick={() => setStep(2)}
              >
                {tw("continue")}
              </Button>
            </div>
          ) : null}

          {step === 2 ? (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">{tw("step2Help")}</p>
              <div className="flex flex-wrap gap-2">
                {(["none", "meowvpn", "wordpress"] as BackupKind[]).map((k) => (
                  <Button
                    key={k}
                    type="button"
                    variant={backupKind === k ? "default" : "outline"}
                    onClick={() => {
                      setBackupKind(k)
                      setBackupDone(false)
                    }}
                  >
                    {tw(`backup_${k}`)}
                  </Button>
                ))}
              </div>
              {backupKind !== "none" ? (
                <>
                  <Input
                    type="file"
                    accept={backupKind === "meowvpn" ? ".zip" : ".sql,.zip"}
                    disabled={busy}
                    onChange={(e) => setBackupFile(e.target.files?.[0] ?? null)}
                  />
                  {backupKind === "meowvpn" ? (
                    <label className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={restorePanelDb}
                        onChange={(e) => setRestorePanelDb(e.target.checked)}
                      />
                      {tw("restorePanelDb")}
                    </label>
                  ) : null}
                </>
              ) : null}
              <div className="flex gap-2">
                <Button type="button" variant="outline" onClick={() => setStep(1)}>
                  {tw("back")}
                </Button>
                <Button type="button" disabled={busy} onClick={() => void onRunBackup()}>
                  {backupDone ? tw("backupDone") : tw("runBackup")}
                </Button>
                <Button type="button" disabled={busy || (backupKind !== "none" && !backupDone)} onClick={() => setStep(3)}>
                  {tw("continue")}
                </Button>
              </div>
            </div>
          ) : null}

          {step === 3 ? (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">{tw("step3Help")}</p>
              <div className="grid gap-3">
                <div className="grid gap-1">
                  <Label>{tw("username")}</Label>
                  <Input value={username} onChange={(e) => setUsername(e.target.value)} disabled={busy} />
                </div>
                <div className="grid gap-1">
                  <Label>{tw("password")}</Label>
                  <Input
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    disabled={busy}
                    autoComplete="new-password"
                  />
                </div>
                <div className="grid gap-1">
                  <Label>{tw("passwordConfirm")}</Label>
                  <Input
                    type="password"
                    value={passwordConfirm}
                    onChange={(e) => setPasswordConfirm(e.target.value)}
                    disabled={busy}
                    autoComplete="new-password"
                  />
                </div>
              </div>
              <div className="flex gap-2">
                <Button type="button" variant="outline" onClick={() => setStep(2)}>
                  {tw("back")}
                </Button>
                <Button type="button" disabled={busy || adminDone} onClick={() => void onSaveAdmin()}>
                  {tw("saveAdmin")}
                </Button>
              </div>
            </div>
          ) : null}

          {step === 4 ? (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">{tw("step4Help")}</p>
              {loginUrl ? (
                <p className="text-sm">
                  <a className="text-primary underline" href={loginUrl}>
                    {loginUrl}
                  </a>
                </p>
              ) : null}
              <Button type="button" disabled={busy} onClick={() => void onComplete()}>
                {tw("openDashboard")}
              </Button>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  )
}
