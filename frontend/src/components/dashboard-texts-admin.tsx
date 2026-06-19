"use client"

import { useCallback, useEffect, useMemo, useState, type ChangeEvent } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"
import { ChevronDown } from "lucide-react"

type TextRow = Record<string, unknown>

export type TextDefaultBundle = { fa: string; en: string }

const MAX_LEN = 8000

/** Strip ASCII control chars (align with PHP sanitize). */
function stripControls(s: string): string {
  return s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, "")
}

function bundleDefaults(def: unknown): TextDefaultBundle {
  if (def && typeof def === "object" && def !== null && ("fa" in def || "en" in def)) {
    const o = def as { fa?: unknown; en?: unknown }
    return { fa: String(o.fa ?? ""), en: String(o.en ?? "") }
  }
  if (typeof def === "string") {
    return { fa: def, en: "" }
  }
  return { fa: "", en: "" }
}

function rowLocaleStrings(row: TextRow): TextDefaultBundle {
  const fa = row.value_fa ?? row.valueFa
  const en = row.value_en ?? row.valueEn
  if (fa !== undefined || en !== undefined) {
    return { fa: String(fa ?? ""), en: String(en ?? "") }
  }
  const legacy = row.value
  if (legacy !== undefined) {
    return { fa: String(legacy ?? ""), en: "" }
  }
  return { fa: "", en: "" }
}

export function DashboardTextsAdmin({
  texts,
  textDefaults,
  onMutateSuccess,
}: {
  texts: TextRow[]
  textDefaults: Record<string, TextDefaultBundle | string>
onMutateSuccess?: () => void
}) {

  const { t } = useTranslation()
  const tp = (k: string) => t(`textsAdmin.${k}`)

  const categoryLabel = useCallback(
    (category: string) => {
      const k = `textsAdmin.categories.${category}`
      const tr = t(k)
      return tr === k ? category : tr
    },
    [t])

  const byCategory = useMemo(() => {
    const m = new Map<string, TextRow[]>()
    for (const row of texts) {
      const cat = String(row.category ?? "general")
      if (!m.has(cat)) m.set(cat, [])
      m.get(cat)!.push(row)
    }
    for (const arr of m.values()) {
      arr.sort((a, b) => String(a.key_name ?? "").localeCompare(String(b.key_name ?? "")))
    }
    return Array.from(m.entries()).sort(([a], [b]) => a.localeCompare(b))
  }, [texts])

  const [openCategory, setOpenCategory] = useState<string | null>(null)

  return (
    <DashPage>
      <DashboardPageHeader
        title={tp("title")}
        description={
          <>
            <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
            <p className="mt-1 text-xs text-muted-foreground">{tp("placeholdersHint")}</p>
          </>
        }
      />
      {byCategory.map(([category, rows]) => (
        <Collapsible
          key={category}
          open={openCategory === category}
          onOpenChange={(open) => setOpenCategory(open ? category : null)}
          className="rounded-md border border-border"
        >
          <CollapsibleTrigger className="flex w-full items-center justify-between gap-2 px-3 py-2 text-sm font-medium hover:bg-muted/50">
            <span>
              {tp("category")}: {categoryLabel(category)}
            </span>
            <ChevronDown className="size-4 shrink-0 transition-transform [[data-state=open]_&]:rotate-180" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <div className="grid gap-4 border-t border-border p-3 md:grid-cols-2">
              {rows.map((row) => (
                <TextKeyEditor
                  key={String(row.key_name ?? row.id)}
                  row={row}
                  defaultBundle={bundleDefaults(textDefaults[String(row.key_name ?? "")])}
                  tp={tp}
                  onMutateSuccess={onMutateSuccess}
                />
              ))}
            </div>
          </CollapsibleContent>
        </Collapsible>
      ))}
    </DashPage>
  )
}

/** FA/EN textareas use fixed content direction (rtl/ltr), not dashboard UI locale. */
function TextKeyEditor({
  row,
  defaultBundle,
  tp,
  onMutateSuccess,
}: {
  row: TextRow
  defaultBundle: TextDefaultBundle
tp: (k: string) => string
  onMutateSuccess?: () => void
}) {
  const key = String(row.key_name ?? "")
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
  }, [key, row.value_fa, row.value_en, row.value])

  const onSave = useCallback(async () => {
    setSaving(true)
    setErr(null)
    const faTrim = stripControls(valueFa.slice(0, MAX_LEN))
    const enTrim = stripControls(valueEn.slice(0, MAX_LEN))
    try {
      const res = await postAdminMutate("texts_save", { texts: { [key]: { fa: faTrim, en: enTrim } } })
      if (!res.ok) {
        setErr(res.message || tp("saveError"))
        return
      }
      setValueFa(faTrim)
      setValueEn(enTrim)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [key, onMutateSuccess, tp, valueEn, valueFa])

  const onReset = useCallback(async () => {
    if (!defaultBundle.fa && !defaultBundle.en) return
    if (!window.confirm(tp("resetConfirm"))) return
    setResetting(true)
    setErr(null)
    try {
      const res = await postAdminMutate("text_reset_one", { text_key: key })
      if (!res.ok) {
        setErr(res.message === "unknown_key" ? tp("resetUnknownKey") : res.message || tp("resetError"))
        return
      }
      setValueFa(defaultBundle.fa)
      setValueEn(defaultBundle.en)
      onMutateSuccess?.()
    } finally {
      setResetting(false)
    }
  }, [defaultBundle.en, defaultBundle.fa, key, onMutateSuccess, tp])

  const totalLen = valueFa.length + valueEn.length

  return (
    <div className="space-y-3 rounded-md border border-border/80 bg-muted/20 p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Label className="font-mono text-xs">{key}</Label>
        <div className="flex flex-wrap items-center gap-2">
          {row.catalog_only === true ? (
            <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
              {tp("catalogOnlyBadge")}
            </span>
          ) : null}
          <span className="text-xs text-muted-foreground">
            {totalLen}/{MAX_LEN * 2}
          </span>
        </div>
      </div>
      <div className="space-y-1">
        <Label className="text-xs text-muted-foreground">{tp("labelFa")}</Label>
        <Textarea
          dir="rtl"
          className="min-h-[7rem] font-mono text-sm"
          value={valueFa}
          maxLength={MAX_LEN}
          onChange={(e: ChangeEvent<HTMLTextAreaElement>) => setValueFa(e.target.value)}
        />
      </div>
      <div className="space-y-1">
        <Label className="text-xs text-muted-foreground">{tp("labelEn")}</Label>
        <Textarea
          dir="ltr"
          className="min-h-[7rem] font-mono text-sm"
          value={valueEn}
          maxLength={MAX_LEN}
          onChange={(e: ChangeEvent<HTMLTextAreaElement>) => setValueEn(e.target.value)}
        />
      </div>
      {defaultBundle.fa || defaultBundle.en ? (
        <p className="text-xs text-muted-foreground">
          {tp("defaultPreview")}:{" "}
          <span className="line-clamp-2">
            FA: {defaultBundle.fa || "—"} | EN: {defaultBundle.en || "—"}
          </span>
        </p>
      ) : (
        <p className="text-xs text-amber-700 dark:text-amber-400">{tp("noDefaultHint")}</p>
      )}
      {err ? (
        <div role="alert" className="rounded border border-destructive/40 bg-destructive/10 px-2 py-1 text-xs text-destructive">
          {err}
        </div>
      ) : null}
      <div className={cn("flex flex-wrap gap-2")}>
        <Button type="button" size="sm" disabled={saving} onClick={() => void onSave()}>
          {tp("saveOne")}
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={resetting || (!defaultBundle.fa && !defaultBundle.en)}
          onClick={() => void onReset()}
        >
          {tp("resetOne")}
        </Button>
      </div>
    </div>
  )
}
