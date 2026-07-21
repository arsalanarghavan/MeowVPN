"use client"

import { useTranslations } from "next-intl"
import { Button } from "@/components/ui/button"
import { DashSelect } from "@/components/dash-select"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { useDashLocale } from "@/lib/dash-locale-context"
import { cn } from "@/lib/utils"

export function DataPagination({
  meta,
  isFa: isFaProp,
  onPageChange,
  onPerPageChange,
  perPageOptions = [10, 20, 40, 50, 100],
  className,
  "data-testid": dataTestId = "data-pagination",
}: {
  meta: PaginationMeta | null | undefined
  isFa?: boolean
  onPageChange: (page: number) => void
  onPerPageChange?: (perPage: number) => void
  perPageOptions?: number[]
  className?: string
  "data-testid"?: string
}) {
  const t = useTranslations("pagination")
  const { isFa } = useDashLocale()
  const localize = isFaProp ?? isFa
  if (!meta || meta.total <= 0) return null
  const { page, perPage, total } = meta
  const totalPages = Math.max(1, Math.ceil(total / perPage))
  const from = total === 0 ? 0 : (page - 1) * perPage + 1
  const to = Math.min(total, page * perPage)

  return (
    <div
      data-testid={dataTestId}
      className={cn("flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-sm", className)}
    >
      <p className="text-muted-foreground">
        {t("range", {
          from: formatNumber(from, localize),
          to: formatNumber(to, localize),
          total: formatNumber(total, localize),
        })}
      </p>
      <div className="flex flex-wrap items-center gap-2">
        {onPerPageChange ? (
          <label className="flex items-center gap-1 text-xs text-muted-foreground">
            <span>{t("perPage")}</span>
            <DashSelect
              size="sm"
              triggerClassName="w-auto"
              value={String(perPage)}
              onValueChange={(v) => onPerPageChange(Number(v))}
              options={perPageOptions.map((n) => ({ value: String(n), label: formatNumber(n, localize) }))}
            />
          </label>
        ) : null}
        <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
          {t("prev")}
        </Button>
        <span className="min-w-[5rem] text-center text-xs tabular-nums text-muted-foreground" dir="ltr">
          {formatNumber(page, localize)} / {formatNumber(totalPages, localize)}
        </span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={page >= totalPages}
          onClick={() => onPageChange(page + 1)}
        >
          {t("next")}
        </Button>
      </div>
    </div>
  )
}
