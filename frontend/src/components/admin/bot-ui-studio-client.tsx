"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslations } from "next-intl"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Textarea } from "@/components/ui/textarea"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"

type DashRecord = Record<string, unknown>

type Surface = {
  key: string
  label: string
  value: unknown
}

function safeJson(value: unknown): string {
  try {
    return JSON.stringify(value ?? {}, null, 2)
  } catch {
    return "{}"
  }
}

export function BotUiStudioClient() {
  const t = useTranslations("botUiStudio")
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [layout, setLayout] = useState<DashRecord>({})
  const [registry, setRegistry] = useState<DashRecord>({})
  const [activeSurface, setActiveSurface] = useState("main")
  const [drafts, setDrafts] = useState<Record<string, string>>({})

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("bot_ui")
      setLayout(data.uiLayout && typeof data.uiLayout === "object" ? (data.uiLayout as DashRecord) : {})
      setRegistry(data.uiRegistry && typeof data.uiRegistry === "object" ? (data.uiRegistry as DashRecord) : {})
    } catch {
      setError(t("loadError", { defaultValue: "Could not load bot UI layout." }))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const surfaces = useMemo<Surface[]>(() => {
    const raw = layout.surfaces
    if (Array.isArray(raw)) {
      return raw
        .filter((x): x is DashRecord => !!x && typeof x === "object")
        .map((x, idx) => ({
          key: String(x.key ?? x.id ?? `surface-${idx}`),
          label: String(x.label ?? x.title ?? x.key ?? `Surface ${idx + 1}`),
          value: x,
        }))
    }
    return [
      { key: "main", label: t("surfaceMain", { defaultValue: "Main" }), value: layout.main ?? layout },
      { key: "menu", label: t("surfaceMenu", { defaultValue: "Menu" }), value: layout.menu ?? {} },
      { key: "profile", label: t("surfaceProfile", { defaultValue: "Profile" }), value: layout.profile ?? {} },
    ]
  }, [layout, t])

  useEffect(() => {
    if (surfaces.length > 0 && !surfaces.some((s) => s.key === activeSurface)) {
      setActiveSurface(surfaces[0]!.key)
    }
  }, [activeSurface, surfaces])

  useEffect(() => {
    setDrafts((curr) => {
      const next = { ...curr }
      for (const surface of surfaces) {
        if (!(surface.key in next)) next[surface.key] = safeJson(surface.value)
      }
      return next
    })
  }, [surfaces])

  const save = async () => {
    setSaving(true)
    setMessage(null)
    setError(null)
    try {
      const surfacesPayload = surfaces.map((surface) => {
        const raw = drafts[surface.key] ?? safeJson(surface.value)
        try {
          return { ...JSON.parse(raw), key: surface.key }
        } catch {
          return { key: surface.key, raw }
        }
      })
      const res = await postAdminMutate("bot_ui_layout_save", { surfaces: surfacesPayload })
      if (!res.ok) {
        setError(res.message || t("saveError", { defaultValue: "Save failed." }))
        return
      }
      setMessage(t("saved", { defaultValue: "Saved." }))
      await load()
    } finally {
      setSaving(false)
    }
  }

  const reset = async () => {
    if (!window.confirm(t("resetConfirm", { defaultValue: "Reset the bot UI layout?" }))) return
    setSaving(true)
    setMessage(null)
    setError(null)
    try {
      const res = await postAdminMutate("bot_ui_layout_reset", {})
      if (!res.ok) {
        setError(res.message || t("resetError", { defaultValue: "Reset failed." }))
        return
      }
      setMessage(t("resetOk", { defaultValue: "Layout reset." }))
      await load()
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title", { defaultValue: "Bot UI Studio" })}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle", { defaultValue: "Edit bot surfaces and copy." })}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
            {t("refresh", { defaultValue: "Refresh" })}
          </Button>
          <Button type="button" variant="outline" size="sm" disabled={saving} onClick={() => void reset()}>
            {t("reset", { defaultValue: "Reset" })}
          </Button>
          <Button type="button" size="sm" disabled={saving} onClick={() => void save()}>
            {t("save", { defaultValue: "Save" })}
          </Button>
        </div>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {message ? <p className="text-sm text-muted-foreground">{message}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("loading", { defaultValue: "Loading..." })}</p> : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("registryTitle", { defaultValue: "Registry" })}</CardTitle>
          <CardDescription>{t("registryDesc", { defaultValue: "All surfaces are stored as JSON." })}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          {Object.keys(registry).length > 0 ? (
            <pre className="max-h-56 overflow-auto rounded-md border bg-muted/30 p-3 text-xs" dir="ltr">
              {JSON.stringify(registry, null, 2)}
            </pre>
          ) : (
            <p className="text-muted-foreground">{t("registryEmpty", { defaultValue: "No registry data." })}</p>
          )}
        </CardContent>
      </Card>

      <Tabs value={activeSurface} onValueChange={setActiveSurface} className="w-full">
        <TabsList variant="line" className="h-auto flex-wrap justify-start gap-1 bg-transparent p-0">
          {surfaces.map((surface) => (
            <TabsTrigger key={surface.key} value={surface.key}>
              {surface.label}
            </TabsTrigger>
          ))}
        </TabsList>
        {surfaces.map((surface) => (
          <TabsContent key={surface.key} value={surface.key} className="mt-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{surface.label}</CardTitle>
                <CardDescription>{surface.key}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <Textarea
                  dir="ltr"
                  className="min-h-[20rem] font-mono text-xs"
                  value={drafts[surface.key] ?? safeJson(surface.value)}
                  onChange={(e) => setDrafts((curr) => ({ ...curr, [surface.key]: e.target.value }))}
                />
                <p className="text-xs text-muted-foreground">
                  {t("surfaceHint", { defaultValue: "Edit the JSON and save to update the live layout." })}
                </p>
              </CardContent>
            </Card>
          </TabsContent>
        ))}
      </Tabs>
    </div>
  )
}
