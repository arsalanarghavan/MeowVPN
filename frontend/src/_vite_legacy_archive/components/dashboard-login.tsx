"use client"

import { type FormEvent, useCallback, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { apiBase, apiHeaders } from "@/lib/api-base"

export function DashboardLogin() {
  const { t } = useTranslation()
  const tl = (k: string) => t(`dashboardLogin.${k}`)
  const boot = useMemo(() => window.__SIMPLEVPBOT_DASH__ || {}, [])

  const [log, setLog] = useState("")
  const [pwd, setPwd] = useState("")
  const [remember, setRemember] = useState(true)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)

  const redirectTo = useMemo(() => {
    const q = new URLSearchParams(window.location.search).get("redirect_to")
    return q && q.length > 0 ? q : ""
  }, [])

  const onSubmit = useCallback(
    async (e: FormEvent) => {
      e.preventDefault()
      setErr(null)
      const restBase = apiBase(boot as Record<string, unknown>)
      if (!restBase) {
        setErr(tl("error"))
        return
      }
      setBusy(true)
      try {
        await fetch("/sanctum/csrf-cookie", { credentials: "include" })
        const res = await fetch(`${restBase}/auth/login`, {
          method: "POST",
          headers: apiHeaders(),
          credentials: "include",
          body: JSON.stringify({
            log: log.trim(),
            pwd,
            remember,
            redirect_to: redirectTo || undefined,
          }),
        })
        const json = (await res.json()) as { ok?: boolean; redirect?: string; code?: string }
        if (res.status === 429 || json.code === "rate_limited") {
          setErr(tl("rateLimited"))
          return
        }
        if (!json.ok || !json.redirect) {
          setErr(tl("error"))
          return
        }
        window.location.assign(String(json.redirect))
      } catch {
        setErr(tl("error"))
      } finally {
        setBusy(false)
      }
    },
    [boot, log, pwd, remember, redirectTo, tl]
  )

  return (
    <div
      className="flex min-h-svh w-full items-center justify-center bg-background p-4"
    >
      <Card className="w-full max-w-md shadow-sm">
        <CardHeader className="space-y-1">
          <CardTitle className="text-xl">{tl("title")}</CardTitle>
          <CardDescription>{tl("subtitle")}</CardDescription>
        </CardHeader>
        <CardContent>
          <form className="grid gap-4" onSubmit={(e) => void onSubmit(e)}>
            <div className="grid gap-2">
              <Label htmlFor="svp-dash-log">{tl("username")}</Label>
              <Input
                id="svp-dash-log"
                name="log"
                type="text"
                autoComplete="username"
                value={log}
                onChange={(e) => setLog(e.target.value)}
                disabled={busy}
                required
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="svp-dash-pwd">{tl("password")}</Label>
              <Input
                id="svp-dash-pwd"
                name="pwd"
                type="password"
                autoComplete="current-password"
                value={pwd}
                onChange={(e) => setPwd(e.target.value)}
                disabled={busy}
                required
              />
            </div>
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
              <input
                type="checkbox"
                checked={remember}
                onChange={(e) => setRemember(e.target.checked)}
                disabled={busy}
              />
              {tl("remember")}
            </label>
            {err ? <p className="text-sm text-destructive">{err}</p> : null}
            <Button type="submit" className="w-full" disabled={busy}>
              {busy ? "…" : tl("submit")}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
