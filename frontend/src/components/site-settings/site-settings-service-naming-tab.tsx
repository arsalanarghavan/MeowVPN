"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { DashSelect } from "@/components/dash-select"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { getAdminJson } from "@/lib/dash-admin-mutate"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>
type PanelRow = { id: number; name: string }
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

export function SiteSettingsServiceNamingTab({
  settings,
  panels,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  panels: PanelRow[]
  onMutateSuccess?: () => void
}) {
  const t = useTranslations("siteSettings.serviceNaming")
  const { ltrCell } = useDashLocale()
  const tp = t
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      service_naming_mode: String(s.service_naming_mode || "legacy"),
      subscription_config_label_override: String(s.subscription_config_label_override ?? ""),
      config_label_prefix: String(s.config_label_prefix ?? ""),
      config_label_number_start: String(Number(s.config_label_number_start) || 1001),
      config_label_prepend_inbound: Boolean(s.config_label_prepend_inbound),
      inbound_display_names: parseInboundMap(s.inbound_display_names),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])

  const [panelId, setPanelId] = useState(0)
  const [inbounds, setInbounds] = useState<InboundRow[]>([])
  const [loadBusy, setLoadBusy] = useState(false)
  const [catalogErr, setCatalogErr] = useState<string | null>(null)
  const { saving, error, okMsg, saveSettingsTab } = useSiteSettingsSave(onMutateSuccess)

  const prefixNumberedMode = form.service_naming_mode === "prefix_numbered"
  const numberedMode = form.service_naming_mode === "numbered"
  const sampleInbound = tp("previewInboundSample")

  const previewSuffix = useMemo(() => {
    const mode = form.service_naming_mode
    if (mode === "platform_slug") return "bot-t12345678-abcdef"
    if (mode === "prefix_numbered") {
      const pref = form.config_label_prefix.trim() || "GoatVPN"
      const start = Math.max(1, Number(form.config_label_number_start) || 1001)
      return `${pref}-${start}`
    }
    if (mode === "numbered") {
      const start = Math.max(1, Number(form.config_label_number_start) || 1001)
      return String(start)
    }
    return "u1-abc@svp.local"
  }, [form])

  const previewLabel = form.config_label_prepend_inbound
    ? `${sampleInbound} - ${previewSuffix}`
    : previewSuffix

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
    setForm((f) => ({
      ...f,
      inbound_display_names: { ...f.inbound_display_names, [key]: value },
    }))
  }

  const onSave = useCallback(async () => {
    await saveSettingsTab("service_naming", {
        service_naming_mode: form.service_naming_mode,
        subscription_config_label_override: form.subscription_config_label_override.trim(),
        config_label_prefix: form.config_label_prefix.trim(),
        config_label_number_start: Math.max(1, Number(form.config_label_number_start) || 1001),
        config_label_prepend_inbound: form.config_label_prepend_inbound ? 1 : 0,
        inbound_display_names: form.inbound_display_names,
      })
  }, [form, saveSettingsTab])

  return (
    <div className={cn("w-full space-y-6 text-start")}>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("modeTitle")}</CardTitle>
          <CardDescription>{tp("modeDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>{tp("serviceNamingMode")}</Label>
            <DashSelect
              value={form.service_naming_mode}
              onValueChange={(v) => setForm((f) => ({ ...f, service_naming_mode: v }))}
              options={[
                { value: "legacy", label: tp("serviceNamingLegacy") },
                { value: "platform_slug", label: tp("serviceNamingPlatformSlug") },
                { value: "prefix_numbered", label: tp("serviceNamingPrefixNumbered") },
                { value: "numbered", label: tp("serviceNamingNumbered") },
              ]}
            />
          </div>
          {(prefixNumberedMode || numberedMode) && (
            <div className="space-y-1.5">
              <Label htmlFor="config_label_number_start">{tp("configLabelNumberStart")}</Label>
              <Input
                id="config_label_number_start"
                type="number"
                min={1}
                dir="ltr"
                value={form.config_label_number_start}
                onChange={(e) =>
                  setForm((f) => ({ ...f, config_label_number_start: e.target.value }))
                }
                disabled={saving}
                className={ltrCell("h-9 max-w-xs tabular-nums")}
              />
              <p className="text-xs text-muted-foreground">{tp("configLabelNumberStartHint")}</p>
            </div>
          )}
          {prefixNumberedMode && (
            <div className="space-y-1.5">
              <Label htmlFor="config_label_prefix">{tp("configLabelPrefix")}</Label>
              <Input
                id="config_label_prefix"
                value={form.config_label_prefix}
                onChange={(e) => setForm((f) => ({ ...f, config_label_prefix: e.target.value }))}
                disabled={saving}
                className="h-9 max-w-md"
                placeholder={tp("configLabelPrefixPlaceholder")}
              />
              <p className="text-xs text-muted-foreground">{tp("configLabelPrefixHint")}</p>
            </div>
          )}
          <div className="flex items-center justify-between gap-4 rounded-md border px-3 py-3">
            <div className="min-w-0 space-y-0.5">
              <Label htmlFor="config_label_prepend_inbound">{tp("prependInbound")}</Label>
              <p className="text-xs text-muted-foreground">{tp("prependInboundHint")}</p>
            </div>
            <Switch
              id="config_label_prepend_inbound"
              checked={form.config_label_prepend_inbound}
              onCheckedChange={(v) =>
                setForm((f) => ({ ...f, config_label_prepend_inbound: Boolean(v) }))
              }
              disabled={saving}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="subscription_config_label_override">{tp("configLabelOverride")}</Label>
            <Input
              id="subscription_config_label_override"
              value={form.subscription_config_label_override}
              onChange={(e) =>
                setForm((f) => ({ ...f, subscription_config_label_override: e.target.value }))
              }
              disabled={saving}
              className="h-9 max-w-md"
              placeholder={tp("configLabelOverridePlaceholder")}
            />
            <p className="text-xs text-muted-foreground">
              {prefixNumberedMode || numberedMode
                ? tp("configLabelOverrideHintPrefixMode")
                : tp("configLabelOverrideHint")}
            </p>
          </div>
          <p className={ltrCell("rounded-md border bg-muted/40 px-3 py-2 font-mono text-xs")} dir="ltr">
            {tp("previewLabel")}: {previewLabel}
          </p>
        </CardContent>
      </Card>

      <Card>
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
                options={panels.map((p) => ({
                  value: String(p.id),
                  label: p.name || `#${p.id}`,
                }))}
              />
            </div>
            <Button type="button" variant="outline" size="sm" disabled={loadBusy} onClick={() => void loadCatalog()}>
              {loadBusy ? tp("loading") : tp("loadInbounds")}
            </Button>
          </div>
          {catalogErr ? <p className="text-sm text-destructive">{catalogErr}</p> : null}
          {inbounds.length > 0 ? (
            <DashTableShell minWidth="32rem" colWidths={["18%", "32%", "50%"]}>
              <thead>
                <tr className="bg-muted/40 text-muted-foreground">
                  <DashTh>ID</DashTh>
                  <DashTh>{tp("panelRemark")}</DashTh>
                  <DashTh>{tp("displayAlias")}</DashTh>
                </tr>
              </thead>
              <tbody>
                {inbounds.map((row) => {
                  const key = `${panelId}:${row.id}`
                  return (
                    <tr key={key}>
                      <DashTd dir="ltr" className={ltrCell("font-mono text-xs")}>
                        {row.id}
                        {row.port > 0 ? ` :${row.port}` : ""}
                      </DashTd>
                      <DashTd className="text-muted-foreground">{row.remark || "—"}</DashTd>
                      <DashTd>
                        <Input
                          value={form.inbound_display_names[key] ?? ""}
                          onChange={(e) => setAlias(key, e.target.value)}
                          disabled={saving}
                          className="h-8"
                          placeholder={row.remark || tp("aliasPlaceholder")}
                        />
                      </DashTd>
                    </tr>
                  )
                })}
              </tbody>
            </DashTableShell>
          ) : (
            <p className="text-xs text-muted-foreground">{tp("inboundEmpty")}</p>
          )}
        </CardContent>
      </Card>

      <div className="flex flex-wrap items-center gap-3">
        <Button type="button" disabled={saving} onClick={() => void onSave()}>
          {tp("save")}
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={saving}
          onClick={() => setForm(initial)}
        >
          {tp("reset")}
        </Button>
        <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />
      </div>
    </div>
  )
}
