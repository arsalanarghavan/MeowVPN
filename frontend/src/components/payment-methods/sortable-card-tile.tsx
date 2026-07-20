"use client"

import { useSortable } from "@dnd-kit/sortable"
import { CSS } from "@dnd-kit/utilities"
import { EllipsisVerticalIcon, GripVertical } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Switch } from "@/components/ui/switch"
import { formatNumber } from "@/lib/format-locale"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isActiveRow(c: DashRecord): boolean {
  return c.active === true || c.active === 1 || c.active === "1"
}

export function SortableCardTile({
  c,
  canDrag,
  busy,
  saving,
  tp,
  cardSubtitle,
  showMethod = false,
  methodLabel,
  onToggleActive,
  onEdit,
  onDelete,
}: {
  c: DashRecord
  canDrag: boolean
  busy: boolean
  saving: boolean
  tp: (k: string) => string
  cardSubtitle: (c: DashRecord) => string
  showMethod?: boolean
  methodLabel?: (raw: unknown) => string
  onToggleActive: (c: DashRecord, checked: boolean) => void
  onEdit: (c: DashRecord) => void
  onDelete: (c: DashRecord) => void
}) {
  const { isFa } = useDashLocale()
  const id = num(c.id)
  const act = isActiveRow(c)
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    disabled: !canDrag || busy || saving,
  })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  }

  return (
    <div ref={setNodeRef} style={style} className={cn(isDragging && "z-10 opacity-80")}>
      <Card>
        <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
          <div className="flex min-w-0 flex-1 items-start gap-2">
            {canDrag ? (
              <button
                type="button"
                className="mt-0.5 shrink-0 cursor-grab touch-none text-muted-foreground active:cursor-grabbing"
                aria-label={tp("dragHint")}
                {...attributes}
                {...listeners}
              >
                <GripVertical className="size-4" />
              </button>
            ) : null}
            <div className="min-w-0 space-y-1">
              <CardTitle className="text-base">{String(c.bank_name ?? "—")}</CardTitle>
              <CardDescription className="font-mono text-xs">{cardSubtitle(c)}</CardDescription>
            </div>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <Switch
              checked={act}
              disabled={busy || saving}
              onCheckedChange={(checked) => onToggleActive(c, checked)}
              aria-label={act ? tp("badgeActive") : tp("badgeInactive")}
            />
            <DropdownMenu>
              <DropdownMenuTrigger
                render={
                  <Button type="button" variant="ghost" size="icon" className="size-8" />
                }
              >
                <EllipsisVerticalIcon className="size-4" />
              </DropdownMenuTrigger>
              <DropdownMenuContent align={isFa ? "start" : "end"}>
                <DropdownMenuItem onClick={() => onEdit(c)}>{tp("edit")}</DropdownMenuItem>
                <DropdownMenuItem className="text-destructive" onClick={() => onDelete(c)}>
                  {tp("delete")}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </CardHeader>
        <CardContent className="text-xs text-muted-foreground">
          {showMethod && methodLabel ? (
            <>
              <span>
                {tp("method")}: {methodLabel(c.method_key)}
              </span>
              {" · "}
            </>
          ) : null}
          <span>
            {tp("dailyLimit")}: {formatNumber(num(c.daily_limit), isFa)}
          </span>
        </CardContent>
      </Card>
    </div>
  )
}
