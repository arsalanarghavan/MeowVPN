"use client"

import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import type { UiStudioCell, UiSurfaceAction } from "./types"
import { TelegramButtonPreview } from "./telegram-button-chip"
import { cellLabel } from "./utils"

export function PreviewPanel({
  title,
  rows,
  metaById,
  textDefaults,
  showColors = true,
  className,
  stickyMobile = false,
}: {
  title: string
  rows: UiStudioCell[][]
  metaById: Map<string, UiSurfaceAction>
  textDefaults?: Record<string, unknown>
  showColors?: boolean
  className?: string
  stickyMobile?: boolean
}) {
  const { isFa } = useDashLocale()
  const visibleRows = rows
    .map((row) => row.filter((c) => c.enabled !== false && c.id))
    .filter((row) => row.length > 0)

  return (
    <div
      className={cn(
        "min-w-0 rounded-xl border border-border/80 bg-card/60 p-4 backdrop-blur-sm",
        stickyMobile && "sticky top-0 z-10 max-h-[min(50dvh,20rem)] overflow-y-auto lg:static lg:max-h-none",
        className
      )}
    >
      <div className="mb-3 flex items-center gap-2 text-sm font-medium">
        <span className="size-2 shrink-0 rounded-full bg-green-500" />
        {title}
      </div>
      <div className="mx-auto w-full min-w-0 max-w-[280px] space-y-2 rounded-2xl border border-border/60 bg-muted/30 p-3">
        {visibleRows.length === 0 ? (
          <p className="py-6 text-center text-xs text-muted-foreground">—</p>
        ) : (
          visibleRows.map((row, ri) => (
            <div key={`pr-${ri}`} className="flex flex-wrap gap-1.5">
              {row.map((cell) => {
                const meta = metaById.get(cell.id)
                const glassOn = Boolean(cell.glass) || Boolean(meta?.glassDefault)
                const label = cellLabel(cell.id, meta, textDefaults, isFa, glassOn)
                return (
                  <div key={cell.id} className="min-w-0 flex-1 basis-[45%]">
                    <TelegramButtonPreview label={label} style={showColors ? (cell.style ?? "") : ""} />
                  </div>
                )
              })}
            </div>
          ))
        )}
      </div>
    </div>
  )
}
