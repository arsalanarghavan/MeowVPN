"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"

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

export function ResellerSettingsAdminClient() {
  const t = useTranslations("resellerSettingsAdmin")
  const { data, reload } = useAdminTabState("reseller_settings")

  const settings = data.settings && typeof data.settings === "object" ? (data.settings as DashRecord) : {}
  const botsList = Array.isArray(data.botsList) ? (data.botsList as DashRecord[]) : []
  const panels = Array.isArray(data.panels) ? (data.panels as DashRecord[]) : []
  const actorSvpUserId = num(data.actorSvpUserId)

  const siteNamingMode = String(settings.service_naming_mode ?? "legacy")
  const prefixNumberedActive = siteNamingMode === "prefix_numbered"
  const numberedActive = siteNamingMode === "numbered"

  const ownRow = useMemo(() => {
    const rid = actorSvpUserId
    if (rid < 1) return botsList[0] ?? null
    return botsList.find((r) => num(r.reseller_id) === rid) ?? botsList[0] ?? null
  }, [actorSvpUserId, botsList])

  const initialOverride = useMemo(() => String(ownRow?.config_label_override ?? ""), [ownRow?.config_label_override])
  const initialPrefix = useMemo(() => String(ownRow?.config_label_prefix ?? ""), [ownRow?.config_label_prefix])
  const initialInboundMap = useMemo(
    () => parseInboundMap(ownRow?.inbound_display_names),
    [ownRow?.inbound_display_names]
  )

  const panelRows = useMemo(
    () =>
      panels
        .map((p) => ({
          id: Number(p.id) || 0,
          name: String(p.name ?? p.title ?? p.label ?? "").trim() || `#${p.id}`,
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
      setCatalogErr(t("pickPanel"))
      return
    }
    setLoadBusy(true)
    setCatalogErr(null)
    try {
      const json = await getAdminJson("/admin/inbound-display-catalog", { panel_id: panelId })
      if (!json.ok) {
        setCatalogErr(String(json.message ?? t("catalogError")))
        setInbounds([])
        return
      }
      const payload = json.data && typeof json.data === "object" ? (json.data as DashRecord) : json
      const raw = Array.isArray(payload.inbounds) ? (payload.inbounds as DashRecord[]) : []
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
  }, [panelId, t])

  const setAlias = (key: string, value: string) => {
    setInboundAliases((prev) => ({ ...prev, [key]: value }))
  }

  const onSave = useCallback(async () => {
    if (resellerId < 1) {
      setError(t("noProfile"))
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
        setError(res.message || t("saveError"))
        return
      }
      setOkMsg(t("saved"))
      reload()
    } finally {
      setSaving(false)
    }
  }, [inboundAliases, overrideValue, prefixValue, reload, resellerId, t])

  return (
    <DashPage>
      <DashboardPageHeader title={t("title")} description={t("desc")} />

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("configLabelTitle")}</CardTitle>
          <CardDescription>
            {prefixNumberedActive
              ? t("configLabelDescPrefixMode")
              : numberedActive
                ? t("configLabelDescNumberedMode")
                : t("configLabelDesc")}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {!prefixNumberedActive && !numberedActive ? (
            <p className="text-xs text-muted-foreground">{t("prefixModeInactiveHint")}</p>
          ) : null}
          {prefixNumberedActive ? (
            <div className="space-y-1.5">
              <Label htmlFor="config_label_prefix">{t("configLabelPrefixField")}</Label>
              <Input
                id="config_label_prefix"
                value={prefixValue}
                onChange={(e) => setPrefixValue(e.target.value)}
                disabled={saving || resellerId < 1}
                className="h-9 max-w-md"
                placeholder={t("configLabelPrefixPlaceholder")}
              />
              <p className="text-xs text-muted-foreground">{t("configLabelPrefixHint")}</p>
            </div>
          ) : null}
          <div className="space-y-1.5">
            <Label htmlFor="config_label_override">{t("configLabelField")}</Label>
            <Input
              id="config_label_override"
              value={overrideValue}
              onChange={(e) => setOverrideValue(e.target.value)}
              disabled={saving || resellerId < 1}
              className="h-9 max-w-md"
              placeholder={t("configLabelPlaceholder")}
            />
            <p className="text-xs text-muted-foreground">{t("configLabelHint")}</p>
          </div>
        </CardContent>
      </Card>

      <Card className="mt-4">
        <CardHeader>
          <CardTitle className="text-base">{t("inboundTitle")}</CardTitle>
          <CardDescription>{t("inboundDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="min-w-[12rem] flex-1 space-y-1">
              <Label>{t("panel")}</Label>
              <DashSelect
                value={panelId > 0 ? String(panelId) : ""}
                onValueChange={(v) => setPanelId(Number(v) || 0)}
                allowEmpty
                placeholder={t("panelPlaceholder")}
                options={panelRows.map((p) => ({ value: String(p.id), label: p.name }))}
              />
            </div>
            <Button type="button" variant="outline" size="sm" disabled={loadBusy} onClick={() => void loadCatalog()}>
              {loadBusy ? t("loading") : t("loadInbounds")}
            </Button>
          </div>
          {catalogErr ? <p className="text-sm text-destructive">{catalogErr}</p> : null}
          {inbounds.length > 0 ? (
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-muted/50 text-muted-foreground">
                    <th className="px-3 py-2 text-start">{t("colId")}</th>
                    <th className="px-3 py-2 text-start">{t("panelRemark")}</th>
                    <th className="px-3 py-2 text-start">{t("displayAlias")}</th>
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
                            placeholder={row.remark || t("aliasPlaceholder")}
                          />
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="text-xs text-muted-foreground">{t("inboundEmpty")}</p>
          )}
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
          {okMsg ? <p className="text-sm text-emerald-600 dark:text-emerald-400">{okMsg}</p> : null}
          <Button type="button" size="sm" disabled={saving || resellerId < 1} onClick={() => void onSave()}>
            {t("save")}
          </Button>
        </CardContent>
      </Card>
    </DashPage>
  )
}
