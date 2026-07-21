"use client"

import { useMemo, useState } from "react"
import { useTranslations } from "next-intl"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { PreviewPanel } from "./preview-panel"
import { TelegramButtonPreview } from "./telegram-button-chip"
import type { UiButtonStyle, UiStudioCell, UiSurfaceAction } from "./types"
import { cellLabel } from "./utils"

const PAGE_SIZE = 9
const STYLES: UiButtonStyle[] = ["", "primary", "success", "danger"]

export function ColorEditor({
  rows,
  metaById,
  textDefaults,
  readOnly,
  onStyleChange,
}: {
  rows: UiStudioCell[][]
  metaById: Map<string, UiSurfaceAction>
  textDefaults?: Record<string, unknown>
  readOnly?: boolean
  onStyleChange: (cellId: string, style: UiButtonStyle) => void
}) {
  const { isFa, ltrCell } = useDashLocale()
  const t = useTranslations("botUiStudio")
  const [page, setPage] = useState(0)
  const [showColors, setShowColors] = useState(true)

  const flatCells = useMemo(() => {
    const out: UiStudioCell[] = []
    for (const row of rows) {
      for (const c of row) {
        if (c.id) out.push(c)
      }
    }
    return out
  }, [rows])

  const pageCount = Math.max(1, Math.ceil(flatCells.length / PAGE_SIZE))
  const pageCells = flatCells.slice(page * PAGE_SIZE, page * PAGE_SIZE + PAGE_SIZE)

  const styleLabel = (s: UiButtonStyle) => {
    if (s === "") return t("styleDefault")
    if (s === "primary") return t("stylePrimary")
    if (s === "success") return t("styleSuccess")
    return t("styleDanger")
  }

  return (
    <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
      <PreviewPanel
        title={t("previewTitle")}
        rows={rows}
        metaById={metaById}
        textDefaults={textDefaults}
        showColors={showColors}
        stickyMobile
      />

      <div className="min-w-0 space-y-4">
        <div className="flex flex-wrap items-center gap-3">
          <Button type="button" variant={showColors ? "default" : "outline"} size="sm" onClick={() => setShowColors((v) => !v)}>
            {showColors ? t("disableColorPreview") : t("enableColorPreview")}
          </Button>
        </div>

        <div className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {pageCells.map((cell) => {
            const meta = metaById.get(cell.id)
            const glassOn = Boolean(cell.glass) || Boolean(meta?.glassDefault)
            const label = cellLabel(cell.id, meta, textDefaults, isFa, glassOn)
            return (
              <div
                key={cell.id}
                className="min-w-0 space-y-2 rounded-xl border border-border/70 bg-card/40 p-3 backdrop-blur-sm"
              >
                <p className={cn("truncate font-mono text-[10px] text-muted-foreground", ltrCell())}>{cell.id}</p>
                <TelegramButtonPreview label={label} style={showColors ? (cell.style ?? "") : ""} />
                <div className="flex flex-wrap gap-1.5">
                  {STYLES.map((s) => (
                    <button
                      key={s || "default"}
                      type="button"
                      disabled={readOnly}
                      className={cn(
                        "rounded-md border px-2 py-1 text-[10px] transition",
                        (cell.style ?? "") === s
                          ? "border-primary bg-primary/15 text-primary"
                          : "border-border hover:border-primary/50"
                      )}
                      onClick={() => onStyleChange(cell.id, s)}
                    >
                      {styleLabel(s)}
                    </button>
                  ))}
                </div>
              </div>
            )
          })}
        </div>

        {pageCount > 1 ? (
          <div className="flex flex-wrap items-center justify-center gap-2 text-sm">
            <Button type="button" variant="outline" size="sm" disabled={page <= 0} onClick={() => setPage((p) => p - 1)}>
              {t("prevPage")}
            </Button>
            <span className="text-muted-foreground">
              {t("pageOf", { page: page + 1, total: pageCount, count: flatCells.length })}
            </span>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={page >= pageCount - 1}
              onClick={() => setPage((p) => p + 1)}
            >
              {t("nextPage")}
            </Button>
          </div>
        ) : null}
      </div>
    </div>
  )
}
