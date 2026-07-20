"use client"

import { EllipsisVerticalIcon } from "lucide-react"
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

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isActiveRow(c: DashRecord): boolean {
  return c.active === true || c.active === 1 || c.active === "1"
}

/** Card tile without drag-and-drop (order managed by parent list / mutate). */
export function SortableCardTile({
  c,
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
  canDrag?: boolean
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
  const act = isActiveRow(c)

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
        <div className="min-w-0 flex-1 space-y-1">
          <CardTitle className="text-base">{String(c.bank_name ?? "—")}</CardTitle>
          <CardDescription className="font-mono text-xs">{cardSubtitle(c)}</CardDescription>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <Switch
            checked={act}
            disabled={busy || saving}
            onCheckedChange={(checked) => onToggleActive(c, checked)}
            aria-label={act ? tp("badgeActive") : tp("badgeInactive")}
          />
          <DropdownMenu>
            <DropdownMenuTrigger className="inline-flex size-8 items-center justify-center rounded-lg hover:bg-muted">
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
  )
}
