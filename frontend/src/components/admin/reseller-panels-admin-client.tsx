"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { DataPagination } from "@/components/data-pagination"
import { getAdminState } from "@/lib/dash-admin-mutate"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function panelAllowed(row: Record<string, unknown> | undefined): boolean {
  if (!row) return false
  const acc = row.panel_access === true || row.panel_access === 1 || row.panel_access === "1"
  const price = Number(String(row.price_per_gb ?? "").replace(/,/g, ""))
  return acc || (Number.isFinite(price) && price > 0)
}

function pickPanelsPagination(data: DashRecord): PaginationMeta | null {
  const raw = data.pagination
  if (raw && typeof raw === "object") {
    return parsePaginationMeta((raw as DashRecord).panels)
  }
  return parsePaginationMeta(data.panelsPagination)
}

/** Read-only reseller panel access overview (legacy dashboard-reseller-panels-admin). */
export function ResellerPanelsAdminClient() {
  const t = useTranslations("resellerPanelsAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"
  const [panels, setPanels] = useState<DashRecord[]>([])
  const [resellerPanelPricesMap, setResellerPanelPricesMap] = useState<
    Record<string, Array<Record<string, unknown>> | undefined>
  >({})
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(20)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("reseller_xui_panels", {
        panels_page: page,
        panels_per_page: perPage,
      })
      const rows = Array.isArray(data.panels)
        ? (data.panels as DashRecord[])
        : Array.isArray(data.panelRows)
          ? (data.panelRows as DashRecord[])
          : []
      setPanels(rows)
      setPagination(pickPanelsPagination(data))
      const map =
        data.resellerPanelPricesMap && typeof data.resellerPanelPricesMap === "object"
          ? (data.resellerPanelPricesMap as Record<string, Array<Record<string, unknown>> | undefined>)
          : {}
      setResellerPanelPricesMap(map)
    } catch {
      setError(t("empty"))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, t])

  useEffect(() => {
    void load()
  }, [load])

  const rows = useMemo(() => {
    const accessCount = new Map<number, number>()
    for (const list of Object.values(resellerPanelPricesMap)) {
      if (!Array.isArray(list)) continue
      for (const row of list) {
        if (!panelAllowed(row)) continue
        const pid = num(row.panel_id)
        if (pid < 1) continue
        accessCount.set(pid, (accessCount.get(pid) ?? 0) + 1)
      }
    }
    return panels
      .map((p) => {
        const id = num(p.id)
        return {
          id,
          label: String(p.label ?? p.name ?? `#${id}`),
          active: p.active === true || p.active === 1 || p.active === "1",
          resellerCount: accessCount.get(id) ?? 0,
        }
      })
      .sort((a, b) => a.label.localeCompare(b.label))
  }, [panels, resellerPanelPricesMap])

  return (
    <div className="space-y-6" data-testid="dash-reseller-panels-tab">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">{t("title")}</h1>
        <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">…</p> : null}
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t("colPanel")}</TableHead>
                <TableHead>{t("colStatus")}</TableHead>
                <TableHead>{t("colResellers")}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="p-4 text-center text-muted-foreground">
                    {t("empty")}
                  </TableCell>
                </TableRow>
              ) : (
                rows.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="truncate font-medium">{row.label}</TableCell>
                    <TableCell>
                      <Badge variant={row.active ? "default" : "secondary"}>
                        {row.active ? t("statusActive") : t("statusInactive")}
                      </Badge>
                    </TableCell>
                    <TableCell className="tabular-nums">
                      {formatNumber(row.resellerCount, isFa)}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
          <DataPagination
            meta={pagination}
            onPageChange={setPage}
            onPerPageChange={(n) => {
              setPerPage(n)
              setPage(1)
            }}
          />
        </CardContent>
      </Card>
      <p className="text-xs text-muted-foreground">{t("hint")}</p>
    </div>
  )
}
