"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { DashPage } from "@/components/dash-page"

type DashRecord = Record<string, unknown>
type InboundRow = { id: number; remark: string; port: number }

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function parseInboundMap(raw: unknown): Record<string, string> {
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) return {}
  const out: Record<string, string> = {}
  for (const [k, v] of Object.entries(raw as Record<string, unknown>)) {
    out[String(k)] = String(v ?? "")
  }
  return out
}

export function DashboardResellerSettings({
  settings,
  botsList,
  panels,
  actorSvpUserId,
  onMutateSuccess,
}: {
  settings?: DashRecord
  botsList: DashRecord[]
  panels?: DashRecord[]
  actorSvpUserId: number
  onMutateSuccess?: () => void
}) {

  const { t } = useTranslation()
  const tp = (k: string) => t(`resellerSettingsAdmin.${k}`)

  const siteNamingMode = String(settings?.service_naming_mode ?? "legacy")
  const prefixNumberedActive = siteNamingMode === "prefix_numbered"
  const numberedActive = siteNamingMode === "numbered"

  const ownRow = useMemo(() => {
    const rid = actorSvpUserId
    if (rid < 1) return botsList[0] ?? null
    return (
      botsList.find((r) => num(r.reseller_id) === rid) ??
      botsList[0] ??
      null
    )
  }, [actorSvpUserId, botsList])

  const initialOverride = useMemo(
    () => String(ownRow?.config_label_override ?? ""),
    [ownRow?.config_label_override]
  )
  const initialPrefix = useMemo(
    () => String(ownRow?.config_label_prefix ?? ""),
    [ownRow?.config_label_prefix]
  )
  const initialInboundMap = useMemo(
    () => parseInboundMap(ownRow?.inbound_display_names),
    [ownRow?.inbound_display_names]
  )

  const panelRows = useMemo(
    () =>
      (panels ?? [])
        .map((p) => ({
          id: Number(p.id) || 0,
          name: String(p.name ?? p.title ?? "").trim() || `#${p.id}`,
        }))
        .filter((p) => p.id > 0),
    [panels]
  )

  const [overrideValue, setOverrideValue] = useState(initialOverride)
  const [prefixValue, setPrefixValue] = useState(initialPrefix)
  const [inboundAliases, setInboundAliases] = useState(initialInboundMap)
  useEffect(() => setOverrideValue(initialOverride), [initialOverride])
  useEffect(() => setPrefixValue(initialPrefix), [initialPrefix])
  useEffect(() => setInboundAliases(initialInboundMap), [initialInboundMap])

  const [panelId, setPanelId] = useState(0)
  const [inbounds, setInbounds] = useState<InboundRow[]>([])
  const [loadBusy, setLoadBusy] = useState(false)
  const [catalogErr, setCatalogErr] = useState<string | null>(null)

  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [okMsg, setOkMsg] = useState<string | null>(null)

  const resellerId = num(ownRow?.reseller_id) || actorSvpUserId

  const loadCatalog = useCallback(async () => {
    if (panelId < 1) {
      setCatalogErr(tp("pickPanel"))
      return
    }
    setLoadBusy(true)
    setCatalogErr(null)
    try {
      const json = await getAdminJson("/dashboard/admin/inbound-display-catalog", {
        panel_id: panelId,
      })
      if (!json.ok) {
        setCatalogErr(String(json.message ?? tp("catalogError")))
        setInbounds([])
        return
      }
      const data = json.data as Record<string, unknown> | undefined
      const raw = data && Array.isArray(data.inbounds) ? (data.inbounds as Record<string, unknown>[]) : []
      setInbounds(
        raw.map((r) => ({
          id: num(r.id),
          remark: String(r.remark ?? ""),
          port: num(r.port),
        }))
      )
    } finally {
      setLoadBusy(false)
    }
  }, [panelId, tp])

  const setAlias = (key: string, value: string) => {
    setInboundAliases((prev) => ({ ...prev, [key]: value }))
  }

  const onSave = useCallback(async () => {
    if (resellerId < 1) {
      setError(tp("noProfile"))
      return
    }
    setSaving(true)
    setError(null)
    setOkMsg(null)
    try {
      const res = await postAdminMutate("bot_reseller_save", {
        reseller_svp_user_id: resellerId,
        config_label_override: overrideValue.trim(),
        config_label_prefix: prefixValue.trim(),
        inbound_display_names: inboundAliases,
      })
      if (!res.ok) {
        setError(res.message || tp("saveError"))
        return
      }
      setOkMsg(tp("saved"))
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [inboundAliases, onMutateSuccess, overrideValue, prefixValue, resellerId, tp])

  return (
    <DashPage>
      <DashboardPageHeader title={tp("title")} description={tp("desc")} />
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("configLabelTitle")}</CardTitle>
          <CardDescription>
            {prefixNumberedActive
              ? tp("configLabelDescPrefixMode")
              : numberedActive
                ? tp("configLabelDescNumberedMode")
                : tp("configLabelDesc")}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {!prefixNumberedActive && !numberedActive ? (
            <p className="text-xs text-muted-foreground">{tp("prefixModeInactiveHint")}</p>
          ) : null}
          {prefixNumberedActive ? (
            <div className="space-y-1.5">
              <Label htmlFor="config_label_prefix">{tp("configLabelPrefixField")}</Label>
              <Input
                id="config_label_prefix"
                value={prefixValue}
                onChange={(e) => setPrefixValue(e.target.value)}
                disabled={saving || resellerId < 1}
                className="h-9 max-w-md"
                placeholder={tp("configLabelPrefixPlaceholder")}
              />
              <p className="text-xs text-muted-foreground">{tp("configLabelPrefixHint")}</p>
            </div>
          ) : null}
          <div className="space-y-1.5">
            <Label htmlFor="config_label_override">{tp("configLabelField")}</Label>
            <Input
              id="config_label_override"
              value={overrideValue}
              onChange={(e) => setOverrideValue(e.target.value)}
              disabled={saving || resellerId < 1}
              className="h-9 max-w-md"
              placeholder={tp("configLabelPlaceholder")}
            />
            <p className="text-xs text-muted-foreground">{tp("configLabelHint")}</p>
          </div>
        </CardContent>
      </Card>

      <Card className="mt-4">
        <CardHeader>
          <CardTitle className="text-base">{tp("inboundTitle")}</CardTitle>
          <CardDescription>{tp("inboundDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="min-w-[12rem] flex-1 space-y-1">
              <Label>{tp("panel")}</Label>
              <DashSelect
                value={panelId > 0 ? String(panelId) : ""}
                onValueChange={(v) => setPanelId(Number(v) || 0)}
                allowEmpty
                placeholder={tp("panelPlaceholder")}
                options={panelRows.map((p) => ({ value: String(p.id), label: p.name }))}
              />
            </div>
            <Button type="button" variant="outline" size="sm" disabled={loadBusy} onClick={() => void loadCatalog()}>
              {loadBusy ? tp("loading") : tp("loadInbounds")}
            </Button>
          </div>
          {catalogErr ? <p className="text-sm text-destructive">{catalogErr}</p> : null}
          {inbounds.length > 0 ? (
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-muted/50 text-muted-foreground">
                    <th className="px-3 py-2 text-start">{tp("colId")}</th>
                    <th className="px-3 py-2 text-start">{tp("panelRemark")}</th>
                    <th className="px-3 py-2 text-start">{tp("displayAlias")}</th>
                  </tr>
                </thead>
                <tbody>
                  {inbounds.map((row) => {
                    const key = `${panelId}:${row.id}`
                    return (
                      <tr key={key} className="border-b last:border-0">
                        <td className="px-3 py-2 font-mono text-xs" dir="ltr">
                          {row.id}
                          {row.port > 0 ? ` :${row.port}` : ""}
                        </td>
                        <td className="px-3 py-2 text-muted-foreground">{row.remark || "—"}</td>
                        <td className="px-3 py-2">
                          <Input
                            value={inboundAliases[key] ?? ""}
                            onChange={(e) => setAlias(key, e.target.value)}
                            disabled={saving}
                            className="h-8"
                            placeholder={row.remark || tp("aliasPlaceholder")}
                          />
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="text-xs text-muted-foreground">{tp("inboundEmpty")}</p>
          )}
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
          {okMsg ? <p className="text-sm text-emerald-600 dark:text-emerald-400">{okMsg}</p> : null}
          <Button type="button" size="sm" disabled={saving || resellerId < 1} onClick={() => void onSave()}>
            {tp("save")}
          </Button>
        </CardContent>
      </Card>
    </DashPage>
  )
}
