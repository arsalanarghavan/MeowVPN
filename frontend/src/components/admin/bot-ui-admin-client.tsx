"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import {
  DndContext,
  PointerSensor,
  closestCorners,
  type DragEndEvent,
  useSensor,
  useSensors,
  useDroppable,
} from "@dnd-kit/core"
import {
  SortableContext,
  arrayMove,
  horizontalListSortingStrategy,
  useSortable,
} from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"
import { ArrowDown, ArrowUp, GripVertical, Plus, Trash2 } from "lucide-react"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"
import { cn } from "@/lib/utils"
import { ColorEditor } from "@/components/bot-ui-studio/color-editor"
import { GuideSection } from "@/components/bot-ui-studio/guide-section"
import type { UiSurfaceAction } from "@/components/bot-ui-studio/types"

const ITEM_PREFIX = "item:"

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
    return row
      .map((cell) => {
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
      })
      .filter((cell) => cell.id)
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

function stripItemPrefix(id: string): string {
  return id.startsWith(ITEM_PREFIX) ? id.slice(ITEM_PREFIX.length) : ""
}

function emptyDropId(surface: string, rowIndex: number): string {
  return `empty-row|${surface}|${rowIndex}`
}

function parseEmptyDropId(id: string): { surface: string; rowIndex: number } | null {
  const p = String(id).split("|")
  if (p[0] !== "empty-row" || p.length < 3) return null
  const rowIndex = parseInt(p[p.length - 1]!, 10)
  if (Number.isNaN(rowIndex)) return null
  const surface = p.slice(1, -1).join("|")
  return { surface, rowIndex }
}

function findRowIndex(rows: UiStudioCell[][], actionId: string): number {
  for (let i = 0; i < rows.length; i++) {
    if (rows[i]!.some((c) => c.id === actionId)) return i
  }
  return -1
}

function EmptyRowDropZone({
  surface,
  rowIndex,
  label,
}: {
  surface: string
  rowIndex: number
  label: string
}) {
  const id = emptyDropId(surface, rowIndex)
  const { setNodeRef, isOver } = useDroppable({ id })
  return (
    <div
      ref={setNodeRef}
      className={cn(
        "flex min-h-14 items-center justify-center rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground",
        isOver && "border-primary bg-primary/10 text-foreground"
      )}
    >
      {label}
    </div>
  )
}

function SortableCellCard({
  cell,
  dragDisabled,
  actionLabel,
  textKey,
  preview,
  layoutReadOnly,
  onRemove,
  onUpdate,
}: {
  cell: UiStudioCell
  dragDisabled: boolean
  actionLabel: string
  textKey: string
  preview: string
  layoutReadOnly: boolean
  onRemove: () => void
  onUpdate: (patch: Partial<UiStudioCell>) => void
}) {
  const t = useTranslations("botUiStudio")
  const sortId = `${ITEM_PREFIX}${cell.id}`
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: sortId,
    disabled: dragDisabled || layoutReadOnly,
  })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        "space-y-3 rounded-lg border bg-card p-3",
        isDragging && "opacity-70",
        cell.enabled === false && "opacity-50"
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="flex min-w-0 items-start gap-2">
          {!layoutReadOnly ? (
            <button
              type="button"
              className="mt-0.5 cursor-grab touch-none text-muted-foreground hover:text-foreground"
              aria-label="Drag"
              {...attributes}
              {...listeners}
            >
              <GripVertical className="size-4" />
            </button>
          ) : null}
          <div className="min-w-0">
            <p className="truncate text-sm font-medium">{actionLabel}</p>
            <p className="break-all font-mono text-xs text-muted-foreground">{cell.id}</p>
            {textKey ? <p className="break-all font-mono text-[11px] text-muted-foreground">{textKey}</p> : null}
          </div>
        </div>
        {!layoutReadOnly ? (
          <Button type="button" size="icon" variant="ghost" className="text-destructive" onClick={onRemove}>
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
              <Switch size="sm" checked={cell.enabled !== false} onCheckedChange={(v) => onUpdate({ enabled: v })} />
              {t("enabled")}
            </label>
            <label className="flex items-center gap-2 text-xs">
              <Switch size="sm" checked={Boolean(cell.glass)} onCheckedChange={(v) => onUpdate({ glass: v })} />
              {t("glass")}
            </label>
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            <div className="space-y-1">
              <Label className="text-xs">{t("style")}</Label>
              <select
                className="h-8 w-full rounded-md border border-input bg-transparent px-2 text-xs"
                value={cell.style || ""}
                onChange={(e) => onUpdate({ style: normalizeButtonStyle(e.target.value) })}
              >
                <option value="">{t("styleDefault")}</option>
                <option value="primary">{t("stylePrimary")}</option>
                <option value="success">{t("styleSuccess")}</option>
                <option value="danger">{t("styleDanger")}</option>
              </select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t("customEmojiId")}</Label>
              <Input
                className="h-8 font-mono text-xs"
                inputMode="numeric"
                value={cell.iconCustomEmojiId ?? ""}
                onChange={(e) => onUpdate({ iconCustomEmojiId: normalizeEmojiId(e.target.value) })}
              />
              <p className="text-[10px] leading-snug text-muted-foreground">{t("customEmojiHint")}</p>
            </div>
          </div>
        </>
      ) : (
        <div className="grid gap-2 sm:grid-cols-2">
          <p className="text-xs text-muted-foreground">
            {t("enabled")}: {cell.enabled !== false ? "✓" : "—"}
          </p>
          <p className="text-xs text-muted-foreground">
            {t("glass")}: {cell.glass ? "✓" : "—"}
          </p>
        </div>
      )}
    </div>
  )
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
  const [studioMode, setStudioMode] = useState<"layout" | "colors">("layout")

  const surfacesReg = useMemo(() => {
    const reg = uiRegistry as { surfaces?: Record<string, UiSurfacePack> }
    return reg.surfaces ?? {}
  }, [uiRegistry])

  const surfaceIds = useMemo(() => Object.keys(surfacesReg).sort(), [surfacesReg])
  const pack = surface ? surfacesReg[surface] : undefined
  const rows = rowsBySurface[surface] ?? rowsFromDefault(pack)

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }))

  const load = useCallback(async () => {
    setLoading(true)
    setErr(null)
    try {
      const data = await getAdminState("bot_ui")
      const layout = data.uiLayout && typeof data.uiLayout === "object" ? (data.uiLayout as Record<string, unknown>) : {}
      const registry = data.uiRegistry && typeof data.uiRegistry === "object" ? (data.uiRegistry as Record<string, unknown>) : {}
      setUiLayout(layout)
      setUiRegistry(registry)
      setTextDefaults(
        data.textDefaults && typeof data.textDefaults === "object" ? (data.textDefaults as Record<string, unknown>) : {}
      )
      const rawSurfaces =
        layout.surfaces && typeof layout.surfaces === "object" ? (layout.surfaces as Record<string, unknown>) : {}
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
  const availableActions = useMemo(
    () => (pack?.actions ?? []).filter((action) => !usedIds.has(action.id)),
    [pack?.actions, usedIds]
  )

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

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      if (layoutReadOnly) return
      const { active, over } = event
      if (!over) return
      const activeAid = stripItemPrefix(String(active.id))
      if (!activeAid) return

      const overStr = String(over.id)
      const emptyDrop = parseEmptyDropId(overStr)
      if (emptyDrop && emptyDrop.surface === surface) {
        const srcRi = findRowIndex(rows, activeAid)
        if (srcRi < 0) return
        const copy = rows.map((r) => r.map((c) => ({ ...c })))
        const srcRow = copy[srcRi]!
        const sIx = srcRow.findIndex((c) => c.id === activeAid)
        if (sIx < 0) return
        const [cell] = srcRow.splice(sIx, 1)
        const tgtRi = emptyDrop.rowIndex
        if (!copy[tgtRi]) return
        copy[tgtRi]!.push(cell!)
        setRows(copy)
        return
      }

      const overAid = stripItemPrefix(overStr)
      if (!overAid) return

      const srcRi = findRowIndex(rows, activeAid)
      const dstRi = findRowIndex(rows, overAid)
      if (srcRi < 0 || dstRi < 0) return

      if (srcRi === dstRi) {
        const row = rows[srcRi]!
        const oldIndex = row.findIndex((c) => c.id === activeAid)
        const newIndex = row.findIndex((c) => c.id === overAid)
        if (oldIndex < 0 || newIndex < 0 || oldIndex === newIndex) return
        const nextRow = arrayMove(row, oldIndex, newIndex)
        const copy = [...rows]
        copy[srcRi] = nextRow
        setRows(copy)
        return
      }

      const copy = rows.map((r) => r.map((c) => ({ ...c })))
      const srcRow = copy[srcRi]!
      const dstRow = copy[dstRi]!
      const sIx = srcRow.findIndex((c) => c.id === activeAid)
      const dIx = dstRow.findIndex((c) => c.id === overAid)
      if (sIx < 0 || dIx < 0) return
      const [cell] = srcRow.splice(sIx, 1)
      dstRow.splice(dIx, 0, cell!)
      setRows(copy)
    },
    [layoutReadOnly, rows, setRows, surface]
  )

  const updateCell = (rowIndex: number, cellIndex: number, patch: Partial<UiStudioCell>) => {
    if (layoutReadOnly) return
    const next = rows.map((row) => row.map((cell) => ({ ...cell })))
    const cell = next[rowIndex]?.[cellIndex]
    if (!cell) return
    next[rowIndex]![cellIndex] = { ...cell, ...patch }
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
    next[rowIndex]!.push({
      id: actionId,
      enabled: true,
      glass: Boolean(actionById.get(actionId)?.glassDefault),
    })
    setRows(next)
  }

  const removeCell = (rowIndex: number, cellIndex: number) => {
    if (layoutReadOnly) return
    const next = rows.map((row) => row.map((cell) => ({ ...cell })))
    next[rowIndex]?.splice(cellIndex, 1)
    setRows(next)
  }

  const deleteRow = (rowIndex: number) => {
    if (layoutReadOnly) return
    const row = rows[rowIndex]
    const hasCells = row && row.length > 0
    if (hasCells || rows.length > 1) {
      if (!window.confirm(t("confirmDeleteRow"))) return
    }
    setRows(rows.filter((_, i) => i !== rowIndex))
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
            if (normalizeEmojiId(cell.iconCustomEmojiId)) {
              out.icon_custom_emoji_id = normalizeEmojiId(cell.iconCustomEmojiId)
            }
            return out
          })
        ),
      }
      const res = await postAdminMutate("bot_ui_layout_save", { surfaces: surfacesPayload })
      if (!res.ok) {
        const data = res.data as { errors?: string[] } | undefined
        setErr(
          res.message === "validation_failed" && data?.errors?.length
            ? data.errors.join(" · ")
            : res.message || t("saveError")
        )
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
        {surface.startsWith("svc_menu_") || surface.includes("inline") ? (
          <p className="text-xs text-muted-foreground">{t("hintInline")}</p>
        ) : null}
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant={studioMode === "layout" ? "default" : "outline"}
            size="sm"
            onClick={() => setStudioMode("layout")}
          >
            {t("modeLayout")}
          </Button>
          <Button
            type="button"
            variant={studioMode === "colors" ? "default" : "outline"}
            size="sm"
            onClick={() => setStudioMode("colors")}
          >
            {t("modeColors")}
          </Button>
        </div>
        <div className="space-y-2">
          <Label htmlFor="bot-ui-surface">{t("surface")}</Label>
          <select
            id="bot-ui-surface"
            className="flex h-9 min-w-60 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
            value={surface}
            onChange={(e) => setSurface(e.target.value)}
          >
            {surfaceIds.map((id) => (
              <option key={id} value={id}>
                {surfaceLabel(id)}
              </option>
            ))}
          </select>
        </div>
        {!layoutReadOnly ? (
          <>
            <Button type="button" disabled={saving || !surface} onClick={() => void save()}>
              {saving ? t("saving") : t("save")}
            </Button>
            <Button type="button" variant="outline" disabled={resetting} onClick={() => void reset()}>
              {resetting ? t("resetting") : t("reset")}
            </Button>
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

      {studioMode === "layout" && !layoutReadOnly && !customGroupId && surface && !surface.startsWith("svc_menu_") ? (
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

      {studioMode === "layout" && !layoutReadOnly && surface ? (
        <p className="text-xs text-muted-foreground">{t("dragAcrossRowsHint")}</p>
      ) : null}

      {studioMode === "colors" && surface ? (
        <ColorEditor
          rows={rows}
          metaById={actionById}
          textDefaults={textDefaults}
          readOnly={layoutReadOnly}
          onStyleChange={(cellId, style) => {
            const next = rows.map((r) => r.map((c) => (c.id === cellId ? { ...c, style } : c)))
            setRows(next)
          }}
        />
      ) : (
      <DndContext
        sensors={sensors}
        collisionDetection={closestCorners}
        onDragEnd={layoutReadOnly ? () => {} : handleDragEnd}
      >
        <div className={cn("space-y-4", layoutReadOnly && "pointer-events-none opacity-90")}>
          {rows.map((row, rowIndex) => {
            const sortIds = row.map((c) => `${ITEM_PREFIX}${c.id}`)
            return (
              <Card key={`${surface}-${rowIndex}`}>
                <CardHeader className="pb-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <CardTitle className="text-base">{t("row", { n: rowIndex + 1 })}</CardTitle>
                      <CardDescription>{row.length === 0 ? t("dropZoneEmpty") : t("preview")}</CardDescription>
                    </div>
                    {!layoutReadOnly ? (
                      <div className="flex flex-wrap gap-1">
                        <Button
                          type="button"
                          size="icon"
                          variant="ghost"
                          disabled={rowIndex === 0}
                          onClick={() => moveRow(rowIndex, -1)}
                        >
                          <ArrowUp className="size-4" />
                        </Button>
                        <Button
                          type="button"
                          size="icon"
                          variant="ghost"
                          disabled={rowIndex === rows.length - 1}
                          onClick={() => moveRow(rowIndex, 1)}
                        >
                          <ArrowDown className="size-4" />
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          className="text-destructive"
                          onClick={() => deleteRow(rowIndex)}
                        >
                          {t("deleteRow")}
                        </Button>
                      </div>
                    ) : null}
                  </div>
                </CardHeader>
                <CardContent className="space-y-3">
                  {!layoutReadOnly ? (
                    <div className="flex flex-wrap items-center gap-2">
                      <select
                        className="h-8 min-w-56 rounded-md border border-input bg-transparent px-2 text-sm"
                        value=""
                        onChange={(e) => addActionToRow(rowIndex, e.target.value)}
                      >
                        <option value="">{t("addButton")}</option>
                        {availableActions.map((action) => (
                          <option key={action.id} value={action.id}>
                            {actionLabel(action.id)} ({action.id})
                          </option>
                        ))}
                      </select>
                      {availableActions.length === 0 ? (
                        <span className="text-xs text-muted-foreground">{t("noAvailableActions")}</span>
                      ) : null}
                    </div>
                  ) : null}
                  {row.length === 0 ? (
                    <EmptyRowDropZone surface={surface} rowIndex={rowIndex} label={t("dropZoneEmpty")} />
                  ) : (
                    <SortableContext items={sortIds} strategy={horizontalListSortingStrategy}>
                      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {row.map((cell, cellIndex) => {
                          const action = actionById.get(cell.id)
                          const textKey = action?.textKey ?? ""
                          const preview = pickLabelPreview(textKey, textDefaults, isFa) || actionLabel(cell.id)
                          return (
                            <SortableCellCard
                              key={`${cell.id}-${cellIndex}`}
                              cell={cell}
                              dragDisabled={cell.enabled === false}
                              actionLabel={actionLabel(cell.id)}
                              textKey={textKey}
                              preview={preview}
                              layoutReadOnly={layoutReadOnly}
                              onRemove={() => removeCell(rowIndex, cellIndex)}
                              onUpdate={(patch) => updateCell(rowIndex, cellIndex, patch)}
                            />
                          )
                        })}
                      </div>
                    </SortableContext>
                  )}
                </CardContent>
              </Card>
            )
          })}
          {surface && rows.length === 0 ? <p className="text-sm text-muted-foreground">{t("noRowsHint")}</p> : null}
        </div>
      </DndContext>
      )}

      <GuideSection />
    </div>
  )
}
