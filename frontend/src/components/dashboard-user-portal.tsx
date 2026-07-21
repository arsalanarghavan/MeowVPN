"use client"

import { useCallback, useEffect, useState } from "react"
import { useTranslations } from "next-intl"
import { ExternalLink } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { apiBase, apiHeaders } from "@/lib/api"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type PortalService = {
  id: number
  display_label?: string
  label?: string
  status?: string
  expire_at?: string
  quota_gb?: number
  used_gb?: number
  portal_url?: string
  quota_hidden_from_user?: number
}

type PortalPayload = {
  ok?: boolean
  portal_url?: string
  services?: PortalService[]
  user?: { label?: string; balance?: number }
}

export function DashboardUserPortal({ restUrl }: { restUrl?: string }) {
  const t = useTranslations("layout")
  const tRoot = useTranslations()
  const { ltrCell } = useDashLocale()
  const [data, setData] = useState<PortalPayload | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const base = restUrl?.replace(/\/$/, "") || apiBase()
      const res = await fetch(`${base}/me/portal`, {
        credentials: "include",
        headers: apiHeaders(),
      })
      const json = (await res.json()) as PortalPayload
      if (!res.ok || !json.ok) {
        setError(t("userPortalLoadError"))
        setData(null)
        return
      }
      setData(json)
    } catch {
      setError(t("userPortalLoadError"))
    } finally {
      setLoading(false)
    }
  }, [restUrl, t])

  useEffect(() => {
    void load()
  }, [load])

  if (loading) {
    return (
      <p className="text-sm text-muted-foreground" data-testid="dash-user-portal">
        {tRoot("loading")}
      </p>
    )
  }

  if (error) {
    return (
      <p className="text-sm text-destructive" data-testid="dash-user-portal">
        {error}
      </p>
    )
  }

  const services = data?.services ?? []

  return (
    <div className="space-y-4 text-start" data-testid="dash-user-portal">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("userPortalTitle")}</CardTitle>
          <CardDescription>{t("userPortalDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap items-center gap-3">
          {data?.portal_url ? (
            <Button asChild variant="outline" size="sm">
              <a href={data.portal_url} target="_blank" rel="noreferrer">
                <ExternalLink className="me-2 size-4" />
                {t("userPortalOpenAll")}
              </a>
            </Button>
          ) : null}
          <Button type="button" variant="ghost" size="sm" onClick={() => void load()}>
            {t("refresh")}
          </Button>
        </CardContent>
      </Card>

      {services.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("userPortalNoServices")}</p>
      ) : (
        <div className="grid gap-3 md:grid-cols-2">
          {services.map((svc) => (
            <Card key={svc.id}>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium">
                  {(svc.display_label ?? svc.label)?.trim() || `#${svc.id}`}
                </CardTitle>
                <CardDescription className={cn(ltrCell("font-mono text-xs"))}>
                  {svc.status ?? "—"}
                  {svc.expire_at ? ` · ${svc.expire_at}` : ""}
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <p>
                  {t("userPortalTraffic")}:{" "}
                  <span className={ltrCell("font-mono")}>
                    {svc.quota_hidden_from_user === 1 || !(svc.quota_gb && svc.quota_gb > 0)
                      ? `${svc.used_gb ?? 0} GB / ∞`
                      : `${svc.used_gb ?? 0} / ${svc.quota_gb ?? 0} GB`}
                  </span>
                </p>
                {svc.portal_url ? (
                  <Button asChild variant="secondary" size="sm">
                    <a href={svc.portal_url} target="_blank" rel="noreferrer">
                      {t("userPortalOpenService")}
                    </a>
                  </Button>
                ) : null}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
