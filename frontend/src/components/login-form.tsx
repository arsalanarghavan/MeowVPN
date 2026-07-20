"use client"

import { type FormEvent, useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { useRouter, useSearchParams } from "next/navigation"
import { cn } from "@/lib/utils"
import { apiBase, apiHeaders, ensureCsrfCookie } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Field,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field"
import { Input } from "@/components/ui/input"

type TelegramAuthUser = {
  id: number
  first_name?: string
  last_name?: string
  username?: string
  photo_url?: string
  auth_date: number
  hash: string
}

type LoginBoot = {
  telegram_login_enabled?: boolean
  bale_login_enabled?: boolean
  telegram_bot_username?: string
  bale_bot_deep_link?: string
  login_url?: string
  telegram_login_url?: string
  magic_issue_url?: string
  magic_consume_url?: string
}

declare global {
  interface Window {
    onTelegramAuth?: (user: TelegramAuthUser) => void
  }
}

function sanitizeRedirect(raw: string, fallback: string, locale: string): string {
  const v = String(raw || "").trim()
  if (!v || /\/login/i.test(v)) return fallback
  if (v.startsWith(`/${locale}/`)) return v
  return fallback
}

export function LoginForm({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const t = useTranslations("dashboardLogin")
  const locale = useLocale()
  const router = useRouter()
  const searchParams = useSearchParams()
  const [log, setLog] = useState("")
  const [pwd, setPwd] = useState("")
  const [remember, setRemember] = useState(true)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [boot, setBoot] = useState<LoginBoot>({})
  const tgMountRef = useRef<HTMLDivElement>(null)

  const dashboardPath = useMemo(() => `/${locale}/dashboard`, [locale])
  const redirectTo = useMemo(() => {
    const next = searchParams.get("next")
    return next ? sanitizeRedirect(next, dashboardPath, locale) : ""
  }, [dashboardPath, locale, searchParams])

  useEffect(() => {
    const code = searchParams.get("dash_auth_err")
    if (!code) return
    if (code === "not_linked") setErr(t("authNotLinked"))
    else if (code === "used_link") setErr(t("authUsedLink"))
    else if (code === "invalid_link") setErr(t("authInvalidLink"))
    else setErr(t("error"))
  }, [searchParams, t])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      try {
        const res = await fetch(`${apiBase()}/dashboard/login`, {
          credentials: "include",
          headers: { Accept: "application/json" },
        })
        const json = (await res.json()) as LoginBoot & { ok?: boolean }
        if (!cancelled && json.ok !== false) {
          setBoot(json)
        }
      } catch {
        // password login still works without boot payload
      }
    })()
    return () => {
      cancelled = true
    }
  }, [])

  const finishLogin = useCallback(
    (redirect: string) => {
      router.replace(sanitizeRedirect(redirect, dashboardPath, locale))
      router.refresh()
    },
    [dashboardPath, locale, router]
  )

  const postLogin = useCallback(
    async (path: string, body: Record<string, unknown>) => {
      setErr(null)
      setBusy(true)
      try {
        await ensureCsrfCookie()
        const res = await fetch(`${apiBase()}${path}`, {
          method: "POST",
          headers: apiHeaders(),
          credentials: "include",
          body: JSON.stringify({
            ...body,
            remember,
            redirect_to: redirectTo || undefined,
          }),
        })
        const json = (await res.json()) as {
          ok?: boolean
          redirect?: string
          code?: string
        }
        if (res.status === 429 || json.code === "rate_limited") {
          setErr(t("rateLimited"))
          return
        }
        if (json.code === "not_linked") {
          setErr(t("authNotLinked"))
          return
        }
        if (!json.ok) {
          setErr(t("error"))
          return
        }
        finishLogin(json.redirect || dashboardPath)
      } catch {
        setErr(t("error"))
      } finally {
        setBusy(false)
      }
    },
    [dashboardPath, finishLogin, redirectTo, remember, t]
  )

  const onSubmit = useCallback(
    async (e: FormEvent) => {
      e.preventDefault()
      await postLogin("/dashboard/login", { log: log.trim(), pwd })
    },
    [log, pwd, postLogin]
  )

  const onTelegramAuth = useCallback(
    (user: TelegramAuthUser) => {
      void postLogin("/dashboard/login/telegram", { telegram_auth: user })
    },
    [postLogin]
  )

  useEffect(() => {
    window.onTelegramAuth = onTelegramAuth
    return () => {
      if (window.onTelegramAuth === onTelegramAuth) {
        delete window.onTelegramAuth
      }
    }
  }, [onTelegramAuth])

  useEffect(() => {
    const enabled = Boolean(boot.telegram_login_enabled)
    const username = String(boot.telegram_bot_username || "").replace(/^@/, "")
    if (!enabled || !username || !tgMountRef.current) return
    tgMountRef.current.innerHTML = ""
    const script = document.createElement("script")
    script.src = "https://telegram.org/js/telegram-widget.js?22"
    script.async = true
    script.setAttribute("data-telegram-login", username)
    script.setAttribute("data-size", "large")
    script.setAttribute("data-radius", "8")
    script.setAttribute("data-onauth", "onTelegramAuth(user)")
    script.setAttribute("data-request-access", "write")
    tgMountRef.current.appendChild(script)
  }, [boot.telegram_bot_username, boot.telegram_login_enabled])

  const openBaleBot = useCallback(() => {
    const deepLink = String(boot.bale_bot_deep_link || "")
    if (!deepLink) return
    const url = `${deepLink}${deepLink.includes("?") ? "&" : "?"}start=dlogin`
    window.open(url, "_blank", "noopener,noreferrer")
  }, [boot.bale_bot_deep_link])

  const showAlt = Boolean(boot.telegram_login_enabled || boot.bale_login_enabled)

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <form className="p-6 md:p-8" onSubmit={(e) => void onSubmit(e)}>
            <FieldGroup>
              <div className="flex flex-col items-center gap-2 text-center">
                <h1 className="text-2xl font-bold">{t("title")}</h1>
                <p className="text-balance text-muted-foreground">
                  {t("subtitle")}
                </p>
              </div>
              <Field>
                <FieldLabel htmlFor="svp-dash-log">{t("username")}</FieldLabel>
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
              </Field>
              <Field>
                <FieldLabel htmlFor="svp-dash-pwd">{t("password")}</FieldLabel>
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
              </Field>
              <Field>
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    className="size-4 rounded border"
                    checked={remember}
                    onChange={(e) => setRemember(e.target.checked)}
                    disabled={busy}
                  />
                  {t("remember")}
                </label>
              </Field>
              {err ? (
                <p className="text-sm text-destructive" role="alert">
                  {err}
                </p>
              ) : null}
              <Field>
                <Button type="submit" disabled={busy}>
                  {busy ? t("busy") : t("submit")}
                </Button>
              </Field>

              {showAlt ? (
                <>
                  <div className="relative py-1">
                    <div className="absolute inset-0 flex items-center">
                      <span className="w-full border-t border-border/60" />
                    </div>
                    <div className="relative flex justify-center text-xs uppercase">
                      <span className="bg-card px-2 text-muted-foreground">
                        {t("orDivider")}
                      </span>
                    </div>
                  </div>
                  <div className="flex flex-col gap-3">
                    {boot.telegram_login_enabled ? (
                      <div className="flex flex-col items-center gap-2">
                        <div ref={tgMountRef} className="min-h-[44px]" />
                        <p className="text-xs text-muted-foreground">
                          {t("loginWithTelegram")}
                        </p>
                      </div>
                    ) : null}
                    {boot.bale_login_enabled ? (
                      <>
                        <Button
                          type="button"
                          variant="outline"
                          className="w-full"
                          disabled={busy || !boot.bale_bot_deep_link}
                          onClick={openBaleBot}
                        >
                          {t("loginWithBale")}
                        </Button>
                        <p className="text-center text-xs text-muted-foreground">
                          {t("baleOpenBotHint")}
                        </p>
                      </>
                    ) : null}
                  </div>
                </>
              ) : null}
            </FieldGroup>
          </form>
          <div className="relative hidden bg-muted md:block">
            <div className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-primary/20 via-background to-muted">
              <span className="text-3xl font-bold tracking-tight text-foreground">
                MeowVPN
              </span>
            </div>
          </div>
        </CardContent>
      </Card>
      <FieldDescription className="px-6 text-center">{t("terms")}</FieldDescription>
    </div>
  )
}
