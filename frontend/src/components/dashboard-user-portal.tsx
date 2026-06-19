"use client"

import { useCallback, useEffect, useState } from "react"
import { useTranslation } from "react-i18next"
import { ExternalLink } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { apiBase, apiHeaders } from "@/lib/api-base"
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
}

type PortalPayload = {
  ok?: boolean
  portal_url?: string
  services?: PortalService[]
  user?: { label?: string; balance?: number }
}

export function DashboardUserPortal({ restUrl }: { restUrl: string }) {
  const { t } = useTranslation()
  const { ltrCell } = useDashLocale()
  const [data, setData] = useState<PortalPayload | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const base = apiBase()
      const res = await fetch(`${base}/me/portal`, {
        credentials: "include",
        headers: apiHeaders(),
      })
      const json = (await res.json()) as PortalPayload
      if (!res.ok || !json.ok) {
        setError(t("layout.userPortalLoadError"))
        setData(null)
        return
      }
      setData(json)
    } catch {
      setError(t("layout.userPortalLoadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load, restUrl])

  if (loading) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }

  if (error) {
    return <p className="text-sm text-destructive">{error}</p>
  }

  const services = data?.services ?? []

  return (
    <div className="space-y-4 text-start">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("layout.userPortalTitle")}</CardTitle>
          <CardDescription>{t("layout.userPortalDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap items-center gap-3">
          {data?.portal_url ? (
            <Button asChild variant="outline" size="sm">
              <a href={data.portal_url} target="_blank" rel="noreferrer">
                <ExternalLink className="me-2 size-4" />
                {t("layout.userPortalOpenAll")}
              </a>
            </Button>
          ) : null}
          <Button type="button" variant="ghost" size="sm" onClick={() => void load()}>
            {t("layout.refresh")}
          </Button>
        </CardContent>
      </Card>

      {services.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t("layout.userPortalNoServices")}</p>
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
                  {t("layout.userPortalTraffic")}:{" "}
                  <span className={ltrCell("font-mono")}>
                    {svc.used_gb ?? 0} / {svc.quota_gb ?? 0} GB
                  </span>
                </p>
                {svc.portal_url ? (
                  <Button asChild variant="secondary" size="sm">
                    <a href={svc.portal_url} target="_blank" rel="noreferrer">
                      {t("layout.userPortalOpenService")}
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
