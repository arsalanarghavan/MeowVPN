"use client"

import { useCallback, useEffect, useMemo, useState, type ChangeEvent } from "react"
import { useTranslations } from "next-intl"
import { ChevronDown } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"

type TextRow = Record<string, unknown>
type TextDefaultBundle = { fa: string; en: string }
type TextTranslator = ReturnType<typeof useTranslations>

const MAX_LEN = 8000

function stripControls(s: string): string {
  return s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, "")
}

function keyName(row: TextRow): string {
  return String(row.key_name ?? row.text_key ?? "")
}

function bundleDefaults(def: unknown): TextDefaultBundle {
  if (def && typeof def === "object" && ("fa" in def || "en" in def)) {
    const o = def as { fa?: unknown; en?: unknown }
    return { fa: String(o.fa ?? ""), en: String(o.en ?? "") }
  }
  if (typeof def === "string") return { fa: def, en: "" }
  return { fa: "", en: "" }
}

function rowLocaleStrings(row: TextRow): TextDefaultBundle {
  const fa = row.value_fa ?? row.valueFa
  const en = row.value_en ?? row.valueEn
  if (fa !== undefined || en !== undefined) return { fa: String(fa ?? ""), en: String(en ?? "") }
  if (row.value !== undefined) return { fa: String(row.value ?? ""), en: "" }
  return { fa: "", en: "" }
}

export function TextsAdminClient() {
  const t = useTranslations("textsAdmin")
  const [texts, setTexts] = useState<TextRow[]>([])
  const [defaults, setDefaults] = useState<Record<string, unknown>>({})
  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [openCategory, setOpenCategory] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setErr(null)
    try {
      const data = await getAdminState("texts", { texts_per_page: 500 })
      setTexts(Array.isArray(data.texts) ? (data.texts as TextRow[]) : [])
      setDefaults(data.textDefaults && typeof data.textDefaults === "object" ? (data.textDefaults as Record<string, unknown>) : {})
    } catch {
      setErr(t("saveError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const categoryLabel = useCallback(
    (category: string) => {
      try {
        return t(`categories.${category}`)
      } catch {
        return category
      }
    },
    [t]
  )

  const byCategory = useMemo(() => {
    const map = new Map<string, TextRow[]>()
    for (const row of texts) {
      const cat = String(row.category ?? "general")
      if (!map.has(cat)) map.set(cat, [])
      map.get(cat)!.push(row)
    }
    for (const rows of map.values()) rows.sort((a, b) => keyName(a).localeCompare(keyName(b)))
    return Array.from(map.entries()).sort(([a], [b]) => a.localeCompare(b))
  }, [texts])

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
          <p className="text-xs text-muted-foreground">{t("placeholdersHint")}</p>
        </div>
        <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
          {t("refresh")}
        </Button>
      </div>
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}
      {err ? <p className="text-sm text-destructive">{err}</p> : null}

      {byCategory.map(([category, rows]) => (
        <Collapsible
          key={category}
          open={openCategory === category}
          onOpenChange={(open) => setOpenCategory(open ? category : null)}
          className="rounded-md border"
        >
          <CollapsibleTrigger className="flex w-full items-center justify-between gap-2 px-3 py-2 text-sm font-medium hover:bg-muted/50">
            <span>{t("category")}: {categoryLabel(category)}</span>
            <ChevronDown className="size-4 shrink-0 transition-transform [[data-state=open]_&]:rotate-180" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div className="grid gap-4 border-t p-3 md:grid-cols-2">
              {rows.map((row) => (
                <TextKeyEditor
                  key={keyName(row) || String(row.id)}
                  row={row}
                  defaultBundle={bundleDefaults(defaults[keyName(row)])}
                  t={t}
                  onMutateSuccess={() => void load()}
                />
              ))}
            </div>
          </CollapsibleContent>
        </Collapsible>
      ))}

      {!loading && byCategory.length === 0 ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("title")}</CardTitle>
            <CardDescription>{t("noDefaultHint")}</CardDescription>
          </CardHeader>
        </Card>
      ) : null}
    </div>
  )
}

function TextKeyEditor({
  row,
  defaultBundle,
  t,
  onMutateSuccess,
}: {
  row: TextRow
  defaultBundle: TextDefaultBundle
  t: TextTranslator
  onMutateSuccess: () => void
}) {
  const key = keyName(row)
  const seed = rowLocaleStrings(row)
  const [valueFa, setValueFa] = useState(seed.fa)
  const [valueEn, setValueEn] = useState(seed.en)
  const [saving, setSaving] = useState(false)
  const [resetting, setResetting] = useState(false)
  const [err, setErr] = useState<string | null>(null)

  useEffect(() => {
    const s = rowLocaleStrings(row)
    setValueFa(s.fa)
    setValueEn(s.en)
  }, [row])

  const onSave = useCallback(async () => {
    setSaving(true)
    setErr(null)
    const faTrim = stripControls(valueFa.slice(0, MAX_LEN))
    const enTrim = stripControls(valueEn.slice(0, MAX_LEN))
    try {
      const res = await postAdminMutate("texts_save", { texts: { [key]: { fa: faTrim, en: enTrim } } })
      if (!res.ok) {
        setErr(res.message || t("saveError"))
        return
      }
      setValueFa(faTrim)
      setValueEn(enTrim)
      onMutateSuccess()
    } finally {
      setSaving(false)
    }
  }, [key, onMutateSuccess, t, valueEn, valueFa])

  const onReset = useCallback(async () => {
    if (!defaultBundle.fa && !defaultBundle.en) return
    if (!window.confirm(t("resetConfirm"))) return
    setResetting(true)
    setErr(null)
    try {
      const res = await postAdminMutate("text_reset_one", { text_key: key })
      if (!res.ok) {
        setErr(res.message === "unknown_key" ? t("resetUnknownKey") : res.message || t("resetError"))
        return
      }
      setValueFa(defaultBundle.fa)
      setValueEn(defaultBundle.en)
      onMutateSuccess()
    } finally {
      setResetting(false)
    }
  }, [defaultBundle.en, defaultBundle.fa, key, onMutateSuccess, t])

  return (
    <Card>
      <CardContent className="space-y-3 pt-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <Label className="font-mono text-xs">{key}</Label>
          <div className="flex items-center gap-2">
            {row.catalog_only === true ? <span className="rounded bg-muted px-1.5 py-0.5 text-[10px]">{t("catalogOnlyBadge")}</span> : null}
            <span className="text-xs text-muted-foreground">{valueFa.length + valueEn.length}/{MAX_LEN * 2}</span>
          </div>
        </div>
        <div className="space-y-1">
          <Label className="text-xs text-muted-foreground">{t("labelFa")}</Label>
          <Textarea dir="rtl" className="min-h-[7rem] font-mono text-sm" value={valueFa} maxLength={MAX_LEN} onChange={(e: ChangeEvent<HTMLTextAreaElement>) => setValueFa(e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label className="text-xs text-muted-foreground">{t("labelEn")}</Label>
          <Textarea dir="ltr" className="min-h-[7rem] font-mono text-sm" value={valueEn} maxLength={MAX_LEN} onChange={(e: ChangeEvent<HTMLTextAreaElement>) => setValueEn(e.target.value)} />
        </div>
        {defaultBundle.fa || defaultBundle.en ? (
          <p className="line-clamp-2 text-xs text-muted-foreground">
            {t("defaultPreview")}: FA: {defaultBundle.fa || "-"} | EN: {defaultBundle.en || "-"}
          </p>
        ) : (
          <p className="text-xs text-amber-700 dark:text-amber-400">{t("noDefaultHint")}</p>
        )}
        {err ? <p className="text-xs text-destructive">{err}</p> : null}
        <div className="flex flex-wrap gap-2">
          <Button type="button" size="sm" disabled={saving} onClick={() => void onSave()}>{t("saveOne")}</Button>
          <Button type="button" size="sm" variant="outline" disabled={resetting || (!defaultBundle.fa && !defaultBundle.en)} onClick={() => void onReset()}>{t("resetOne")}</Button>
        </div>
      </CardContent>
    </Card>
  )
}
