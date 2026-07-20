"use client"

import { useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { useRouter, useSearchParams } from "next/navigation"
import { apiBase, apiHeaders, ensureCsrfCookie } from "@/lib/api"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"

export default function MagicLoginPage() {
  const t = useTranslations("dashboardLogin")
  const locale = useLocale()
  const router = useRouter()
  const searchParams = useSearchParams()
  const [message, setMessage] = useState<string>(() => t("busy"))

  const params = useMemo(() => {
    const out: Record<string, string> = {}
    for (const key of ["svp_dl", "svp_p", "svp_uid", "svp_e", "svp_n", "svp_s"]) {
      const v = searchParams.get(key)
      if (v) out[key] = v
    }
    return out
  }, [searchParams])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      if (!params.svp_dl) {
        router.replace(`/${locale}/login?dash_auth_err=invalid_link`)
        return
      }
      try {
        await ensureCsrfCookie()
        const qs = new URLSearchParams(params).toString()
        const res = await fetch(`${apiBase()}/dashboard/login/magic?${qs}`, {
          method: "POST",
          headers: apiHeaders(),
          credentials: "include",
          body: JSON.stringify({ remember: true }),
        })
        const json = (await res.json()) as { ok?: boolean; code?: string; redirect?: string }
        if (cancelled) return
        if (json.ok) {
          router.replace(`/${locale}/dashboard`)
          router.refresh()
          return
        }
        const code = json.code || "invalid_link"
        router.replace(`/${locale}/login?dash_auth_err=${encodeURIComponent(code)}`)
      } catch {
        if (!cancelled) {
          setMessage(t("error"))
        }
      }
    })()
    return () => {
      cancelled = true
    }
  }, [locale, params, router, t])

  return (
    <div className="flex min-h-svh items-center justify-center p-6">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle>{t("title")}</CardTitle>
          <CardDescription>{message}</CardDescription>
        </CardHeader>
        <CardContent />
      </Card>
    </div>
  )
}
