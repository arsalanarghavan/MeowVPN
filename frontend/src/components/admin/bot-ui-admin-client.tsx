"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"

type UiButtonStyle = "" | "primary" | "success" | "danger"
type UiStudioCell = {
  id: string
  enabled?: boolean
  glass?: boolean
  style?: UiButtonStyle
  iconCustomEmojiId?: string
}
type UiSurfacePack = {
  actions?: Array<{
    id: string
    textKey?: string
    glassDefault?: boolean
    labelFa?: string
    labelEn?: string
  }>
  labelFa?: string
  labelEn?: string
  defaultRows?: string[][]
}

function normalizeButtonStyle(raw: unknown): UiButtonStyle {
  const s = String(raw ?? "").toLowerCase()
  if (s === "primary" || s === "success" || s === "danger") return s
  return ""
}

function normalizeEmojiId(raw: unknown): string {
  const id = String(raw ?? "").trim()
  return /^\d+$/.test(id) ? id : ""
}

function cloneRows(raw: unknown): UiStudioCell[][] {
  if (!Array.isArray(raw)) return []
  return raw.map((row) => {
    if (!Array.isArray(row)) return []
    return row.map((cell) => {
      if (typeof cell === "string") return { id: cell, enabled: true, glass: false }
      if (!cell || typeof cell !== "object") return { id: "", enabled: true, glass: false }
      const o = cell as Record<string, unknown>
      return {
        id: String(o.id ?? ""),
        enabled: o.enabled !== false,
        glass: Boolean(o.glass),
        style: normalizeButtonStyle(o.style),
        iconCustomEmojiId: normalizeEmojiId(o.icon_custom_emoji_id ?? o.iconCustomEmojiId),
      }
    }).filter((cell) => cell.id)
  })
}

function rowsFromDefault(pack: UiSurfacePack | undefined): UiStudioCell[][] {
  return (pack?.defaultRows ?? []).map((row) => row.map((id) => ({ id, enabled: true, glass: false })))
}

function pickLabelPreview(textKey: string, textDefaults: Record<string, unknown>, isFa: boolean): string {
  if (!textKey) return ""
  const row = textDefaults[textKey]
  if (row && typeof row === "object" && ("fa" in row || "en" in row)) {
    const o = row as { fa?: unknown; en?: unknown }
    return String((isFa ? o.fa : o.en) ?? "") || textKey
  }
  if (typeof row === "string") return row || textKey
  return textKey
}

function findDuplicateActionId(rows: UiStudioCell[][]): string | null {
  const seen = new Set<string>()
  for (const row of rows) {
    for (const cell of row) {
      if (seen.has(cell.id)) return cell.id
      seen.add(cell.id)
    }
  }
  return null
}

export function BotUiAdminClient() {
  const t = useTranslations("botUiStudio")
  const isFa = useLocale() === "fa"
  const shell = useDashboardShellOptional()
  const layoutReadOnly = Boolean(shell?.isReseller)
  const [, setUiLayout] = useState<Record<string, unknown>>({})
  const [uiRegistry, setUiRegistry] = useState<Record<string, unknown>>({})
  const [textDefaults, setTextDefaults] = useState<Record<string, unknown>>({})
  const [surface, setSurface] = useState("")
  const [rowsBySurface, setRowsBySurface] = useState<Record<string, UiStudioCell[][]>>({})
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [resetting, setResetting] = useState(false)
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)
  const [groupLabelFa, setGroupLabelFa] = useState("")
  const [groupLabelEn, setGroupLabelEn] = useState("")
  const [groupMembers, setGroupMembers] = useState<string[]>([])
  const [creatingGroup, setCreatingGroup] = useState(false)
  const [deletingGroup, setDeletingGroup] = useState(false)

  const surfacesReg = useMemo(() => {
    const reg = uiRegistry as { surfaces?: Record<string, UiSurfacePack> }
    return reg.surfaces ?? {}
  }, [uiRegistry])

  const surfaceIds = useMemo(() => Object.keys(surfacesReg).sort(), [surfacesReg])
  const pack = surface ? surfacesReg[surface] : undefined
  const rows = rowsBySurface[surface] ?? rowsFromDefault(pack)

  const load = useCallback(async () => {
    setLoading(true)
    setErr(null)
    try {
      const data = await getAdminState("bot_ui")
      const layout = data.uiLayout && typeof data.uiLayout === "object" ? (data.uiLayout as Record<string, unknown>) : {}
      const registry = data.uiRegistry && typeof data.uiRegistry === "object" ? (data.uiRegistry as Record<string, unknown>) : {}
      setUiLayout(layout)
      setUiRegistry(registry)
      setTextDefaults(data.textDefaults && typeof data.textDefaults === "object" ? (data.textDefaults as Record<string, unknown>) : {})
      const rawSurfaces = layout.surfaces && typeof layout.surfaces === "object" ? (layout.surfaces as Record<string, unknown>) : {}
      const nextRows: Record<string, UiStudioCell[][]> = {}
      for (const id of Object.keys(rawSurfaces)) nextRows[id] = cloneRows(rawSurfaces[id])
      setRowsBySurface(nextRows)
    } catch {
      setErr(t("saveError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (!surface && surfaceIds.length > 0) setSurface(surfaceIds[0]!)
  }, [surface, surfaceIds])

  const setRows = useCallback(
    (next: UiStudioCell[][]) => {
      if (layoutReadOnly) return
      setRowsBySurface((prev) => ({ ...prev, [surface]: next }))
    },
    [layoutReadOnly, surface]
  )

  const actionById = useMemo(() => {
    const m = new Map<string, NonNullable<UiSurfacePack["actions"]>[number]>()
    for (const action of pack?.actions ?? []) m.set(action.id, action)
    return m
  }, [pack?.actions])

  const usedIds = useMemo(() => new Set(rows.flat().map((cell) => cell.id)), [rows])
  const availableActions = useMemo(() => (pack?.actions ?? []).filter((action) => !usedIds.has(action.id)), [pack?.actions, usedIds])

  const surfaceLabel = (id: string) => {
    const p = surfacesReg[id]
    const label = isFa ? p?.labelFa : p?.labelEn
    return `${label || id} (${id})`
  }

  const actionLabel = (id: string) => {
    const action = actionById.get(id)
    const label = isFa ? action?.labelFa : action?.labelEn
    if (label) return label
    return pickLabelPreview(action?.textKey ?? "", textDefaults, isFa) || id
  }

  const updateCell = (rowIndex: number, cellIndex: number, patch: Partial<UiStudioCell>) => {
    if (layoutReadOnly) return
    const next = rows.map((row) => row.map((cell) => ({ ...cell })))
    const cell = next[rowIndex]?.[cellIndex]
    if (!cell) return
    next[rowIndex]![cellIndex] = { ...cell, ...patch }
    setRows(next)
  }

  const moveCell = (rowIndex: number, cellIndex: number, delta: -1 | 1) => {
    if (layoutReadOnly) return
    const next = rows.map((row) => row.map((cell) => ({ ...cell })))
    const row = next[rowIndex]
    if (!row) return
    const to = cellIndex + delta
    if (to < 0 || to >= row.length) return
    const [cell] = row.splice(cellIndex, 1)
    row.splice(to, 0, cell!)
    setRows(next)
  }

  const moveRow = (rowIndex: number, delta: -1 | 1) => {
    if (layoutReadOnly) return
    const to = rowIndex + delta
    if (to < 0 || to >= rows.length) return
    const next = rows.map((row) => row.map((cell) => ({ ...cell })))
    const [row] = next.splice(rowIndex, 1)
    next.splice(to, 0, row!)
    setRows(next)
  }

  const addActionToRow = (rowIndex: number, actionId: string) => {
    if (layoutReadOnly || !actionId) return
    const next = rows.map((row) => row.map((cell) => ({ ...cell })))
    if (!next[rowIndex]) next[rowIndex] = []
    next[rowIndex]!.push({ id: actionId, enabled: true, glass: Boolean(actionById.get(actionId)?.glassDefault) })
    setRows(next)
  }

  const removeCell = (rowIndex: number, cellIndex: number) => {
    if (layoutReadOnly) return
    const next = rows.map((row) => row.map((cell) => ({ ...cell })))
    next[rowIndex]?.splice(cellIndex, 1)
    setRows(next)
  }

  const save = async () => {
    if (layoutReadOnly) return
    const dup = findDuplicateActionId(rows)
    if (dup) {
      setErr(t("duplicateActions", { id: dup }))
      return
    }
    setSaving(true)
    setErr(null)
    setMsg(null)
    try {
      const surfacesPayload: Record<string, unknown> = {
        [surface]: rows.map((row) =>
          row.map((cell) => {
            const out: Record<string, unknown> = {
              id: cell.id,
              enabled: cell.enabled !== false,
              glass: Boolean(cell.glass),
            }
            if (cell.style) out.style = cell.style
            if (normalizeEmojiId(cell.iconCustomEmojiId)) out.icon_custom_emoji_id = normalizeEmojiId(cell.iconCustomEmojiId)
            return out
          })
        ),
      }
      const res = await postAdminMutate("bot_ui_layout_save", { surfaces: surfacesPayload })
      if (!res.ok) {
        const data = res.data as { errors?: string[] } | undefined
        setErr(res.message === "validation_failed" && data?.errors?.length ? data.errors.join(" · ") : res.message || t("saveError"))
        return
      }
      setMsg(t("saved"))
      await load()
    } finally {
      setSaving(false)
    }
  }

  const reset = async () => {
    if (layoutReadOnly) return
    if (!window.confirm(t("resetConfirmAll"))) return
    setResetting(true)
    setErr(null)
    setMsg(null)
    try {
      const res = await postAdminMutate("bot_ui_layout_reset", {})
      if (!res.ok) {
        setErr(res.message || t("resetError"))
        return
      }
      setMsg(t("resetDone"))
      await load()
    } finally {
      setResetting(false)
    }
  }

  const customGroupId = surface.match(/^(user|admin)_custom_/)
    ? surface.replace(/^(user|admin)_custom_/, "")
    : ""

  const createGroup = async () => {
    if (layoutReadOnly) return
    if (!surface || groupMembers.length < 1) {
      setErr(t("groupCreateError"))
      return
    }
    setCreatingGroup(true)
    setErr(null)
    setMsg(null)
    try {
      const res = await postAdminMutate("bot_ui_group_create", {
        parent_surface: surface,
        label_fa: groupLabelFa,
        label_en: groupLabelEn,
        member_actions: groupMembers,
      })
      if (!res.ok) {
        setErr(res.message || t("groupCreateError"))
        return
      }
      const data = (res.data ?? res) as {
        group?: { surfaceId?: string; surface_id?: string }
      }
      const sid = data?.group?.surfaceId ?? data?.group?.surface_id ?? ""
      setGroupLabelFa("")
      setGroupLabelEn("")
      setGroupMembers([])
      setMsg(t("saved"))
      await load()
      if (sid) setSurface(sid)
    } finally {
      setCreatingGroup(false)
    }
  }

  const deleteGroup = async () => {
    if (layoutReadOnly || !customGroupId) return
    if (!window.confirm(t("confirmDeleteGroup"))) return
    setDeletingGroup(true)
    setErr(null)
    setMsg(null)
    try {
      const res = await postAdminMutate("bot_ui_group_delete", {
        group_id: customGroupId,
        restore_to_parent: true,
      })
      if (!res.ok) {
        setErr(res.message || t("groupCreateError"))
        return
      }
      setMsg(t("resetDone"))
      setSurface(surface.startsWith("admin_") ? "admin_main" : "user_main")
      await load()
    } finally {
      setDeletingGroup(false)
    }
  }

  return (
    <div className="space-y-6" data-testid="dash-bot-ui-tab">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">{t("title")}</h1>
        <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        {layoutReadOnly ? <p className="text-xs text-muted-foreground">{t("readOnlyHint")}</p> : null}
        {surface.startsWith("svc_menu_") || surface.includes("inline") ? <p className="text-xs text-muted-foreground">{t("hintInline")}</p> : null}
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-2">
          <Label htmlFor="bot-ui-surface">{t("surface")}</Label>
          <select
            id="bot-ui-surface"
            className="flex h-9 min-w-60 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
            value={surface}
            onChange={(e) => setSurface(e.target.value)}
          >
            {surfaceIds.map((id) => <option key={id} value={id}>{surfaceLabel(id)}</option>)}
          </select>
        </div>
        {!layoutReadOnly ? (
          <>
            <Button type="button" disabled={saving || !surface} onClick={() => void save()}>{saving ? t("saving") : t("save")}</Button>
            <Button type="button" variant="outline" disabled={resetting} onClick={() => void reset()}>{resetting ? t("resetting") : t("reset")}</Button>
            <Button type="button" variant="secondary" disabled={!surface} onClick={() => setRows([...rows, []])}>
              <Plus className="size-4" /> {t("addRow")}
            </Button>
            {customGroupId ? (
              <Button type="button" variant="destructive" disabled={deletingGroup} onClick={() => void deleteGroup()}>
                {deletingGroup ? t("deletingGroup") : t("deleteGroup")}
              </Button>
            ) : null}
          </>
        ) : null}
      </div>

      {!layoutReadOnly && !customGroupId && surface && !surface.startsWith("svc_menu_") ? (
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">{t("tabCreateGroup")}</CardTitle>
            <CardDescription>{t("guideGroups")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="group-fa">{t("groupNameFa")}</Label>
                <Input id="group-fa" value={groupLabelFa} onChange={(e) => setGroupLabelFa(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="group-en">{t("groupNameEn")}</Label>
                <Input id="group-en" value={groupLabelEn} onChange={(e) => setGroupLabelEn(e.target.value)} />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">{t("pickActionsHint")}</p>
            <div className="flex flex-wrap gap-2">
              {rows.flat().map((cell) => (
                <label key={cell.id} className="flex items-center gap-2 rounded-md border px-2 py-1 text-xs">
                  <input
                    type="checkbox"
                    checked={groupMembers.includes(cell.id)}
                    onChange={() =>
                      setGroupMembers((prev) =>
                        prev.includes(cell.id) ? prev.filter((id) => id !== cell.id) : [...prev, cell.id]
                      )
                    }
                  />
                  {actionLabel(cell.id)}
                </label>
              ))}
            </div>
            <Button
              type="button"
              disabled={creatingGroup || groupMembers.length < 1 || (!groupLabelFa.trim() && !groupLabelEn.trim())}
              onClick={() => void createGroup()}
            >
              {creatingGroup ? t("creatingGroup") : t("createGroup")}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {loading ? <p className="text-sm text-muted-foreground">{t("emptySurface")}</p> : null}
      {err ? <p className="text-sm text-destructive">{err}</p> : null}
      {msg ? <p className="text-sm text-emerald-600 dark:text-emerald-400">{msg}</p> : null}

      {!surface ? <p className="text-sm text-muted-foreground">{t("emptySurface")}</p> : null}

      <div className={cn("space-y-4", layoutReadOnly && "pointer-events-none opacity-90")}>
        {rows.map((row, rowIndex) => (
          <Card key={`${surface}-${rowIndex}`}>
            <CardHeader className="pb-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <CardTitle className="text-base">{t("row", { n: rowIndex + 1 })}</CardTitle>
                  <CardDescription>{row.length === 0 ? t("dropZoneEmpty") : t("preview")}</CardDescription>
                </div>
                {!layoutReadOnly ? (
                  <div className="flex flex-wrap gap-1">
                    <Button type="button" size="icon" variant="ghost" disabled={rowIndex === 0} onClick={() => moveRow(rowIndex, -1)}><ArrowUp className="size-4" /></Button>
                    <Button type="button" size="icon" variant="ghost" disabled={rowIndex === rows.length - 1} onClick={() => moveRow(rowIndex, 1)}><ArrowDown className="size-4" /></Button>
                    <Button type="button" size="sm" variant="ghost" className="text-destructive" onClick={() => setRows(rows.filter((_, i) => i !== rowIndex))}>{t("deleteRow")}</Button>
                  </div>
                ) : null}
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              {!layoutReadOnly ? (
                <div className="flex flex-wrap items-center gap-2">
                  <select className="h-8 min-w-56 rounded-md border border-input bg-transparent px-2 text-sm" value="" onChange={(e) => addActionToRow(rowIndex, e.target.value)}>
                    <option value="">{t("addButton")}</option>
                    {availableActions.map((action) => <option key={action.id} value={action.id}>{actionLabel(action.id)} ({action.id})</option>)}
                  </select>
                  {availableActions.length === 0 ? <span className="text-xs text-muted-foreground">{t("noAvailableActions")}</span> : null}
                </div>
              ) : null}
              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {row.map((cell, cellIndex) => {
                  const action = actionById.get(cell.id)
                  const textKey = action?.textKey ?? ""
                  const preview = pickLabelPreview(textKey, textDefaults, isFa) || actionLabel(cell.id)
                  return (
                    <div key={`${cell.id}-${cellIndex}`} className="space-y-3 rounded-lg border bg-card p-3">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                          <p className="truncate text-sm font-medium">{actionLabel(cell.id)}</p>
                          <p className="break-all font-mono text-xs text-muted-foreground">{cell.id}</p>
                          {textKey ? <p className="break-all font-mono text-[11px] text-muted-foreground">{textKey}</p> : null}
                        </div>
                        {!layoutReadOnly ? (
                          <Button type="button" size="icon" variant="ghost" className="text-destructive" onClick={() => removeCell(rowIndex, cellIndex)}>
                            <Trash2 className="size-4" />
                          </Button>
                        ) : null}
                      </div>
                      <p className="text-xs text-muted-foreground">{cell.glass ? `⟨${preview}⟩` : preview}</p>
                      <div className="flex flex-wrap gap-1">
                        {cell.style ? <Badge variant="secondary">{cell.style}</Badge> : null}
                        {cell.iconCustomEmojiId ? <Badge variant="outline">emoji:{cell.iconCustomEmojiId}</Badge> : null}
                      </div>
                      {!layoutReadOnly ? (
                        <>
                          <div className="grid gap-2 sm:grid-cols-2">
                            <label className="flex items-center gap-2 text-xs">
                              <Switch size="sm" checked={cell.enabled !== false} onCheckedChange={(v) => updateCell(rowIndex, cellIndex, { enabled: v })} />
                              {t("enabled")}
                            </label>
                            <label className="flex items-center gap-2 text-xs">
                              <Switch size="sm" checked={Boolean(cell.glass)} onCheckedChange={(v) => updateCell(rowIndex, cellIndex, { glass: v })} />
                              {t("glass")}
                            </label>
                          </div>
                          <div className="grid gap-2 sm:grid-cols-2">
                            <div className="space-y-1">
                              <Label className="text-xs">{t("style")}</Label>
                              <select className="h-8 w-full rounded-md border border-input bg-transparent px-2 text-xs" value={cell.style || ""} onChange={(e) => updateCell(rowIndex, cellIndex, { style: normalizeButtonStyle(e.target.value) })}>
                                <option value="">{t("styleDefault")}</option>
                                <option value="primary">{t("stylePrimary")}</option>
                                <option value="success">{t("styleSuccess")}</option>
                                <option value="danger">{t("styleDanger")}</option>
                              </select>
                            </div>
                            <div className="space-y-1">
                              <Label className="text-xs">{t("customEmojiId")}</Label>
                              <Input className="h-8 font-mono text-xs" inputMode="numeric" value={cell.iconCustomEmojiId ?? ""} onChange={(e) => updateCell(rowIndex, cellIndex, { iconCustomEmojiId: normalizeEmojiId(e.target.value) })} />
                            </div>
                          </div>
                          <div className="flex gap-1">
                            <Button type="button" size="sm" variant="outline" disabled={cellIndex === 0} onClick={() => moveCell(rowIndex, cellIndex, -1)}>{t("prevPage")}</Button>
                            <Button type="button" size="sm" variant="outline" disabled={cellIndex === row.length - 1} onClick={() => moveCell(rowIndex, cellIndex, 1)}>{t("nextPage")}</Button>
                          </div>
                        </>
                      ) : (
                        <div className="grid gap-2 sm:grid-cols-2">
                          <p className="text-xs text-muted-foreground">{t("enabled")}: {cell.enabled !== false ? "✓" : "—"}</p>
                          <p className="text-xs text-muted-foreground">{t("glass")}: {cell.glass ? "✓" : "—"}</p>
                        </div>
                      )}
                    </div>
                  )
                })}
              </div>
            </CardContent>
          </Card>
        ))}
        {surface && rows.length === 0 ? <p className="text-sm text-muted-foreground">{t("noRowsHint")}</p> : null}
      </div>
    </div>
  )
}
