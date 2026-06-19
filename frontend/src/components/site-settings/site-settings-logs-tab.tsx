"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { DataPagination } from "@/components/data-pagination"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import { formatServiceExpiryLine } from "@/lib/format-locale"
import { useDashLocale } from "@/lib/dash-locale-context"
import { useAdminTp } from "@/lib/use-admin-tp"
import { cn } from "@/lib/utils"
import { DashDialogContent, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogTitle } from "@/components/ui/dialog"

type LogRow = {
  id: number
  level: string
  message: string
  context: unknown
  created_at: string
}

const LOGS_TABLE_COLS = ["10%", "12%", "48%", "20%", "10%"]

export function SiteSettingsLogsTab() {
  const { isFa, ltrCell } = useDashLocale()
  const { t } = useTranslation()
  const tp = useAdminTp("siteSettings.logs")
  const [rows, setRows] = useState<LogRow[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [level, setLevel] = useState("")
  const [search, setSearch] = useState("")
  const [searchInput, setSearchInput] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [detail, setDetail] = useState<LogRow | null>(null)
  const [clearDays, setClearDays] = useState("30")
  const [clearing, setClearing] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const json = await getAdminJson("/dashboard/admin/logs", {
        page,
        per_page: perPage,
        level,
        q: search,
      })
      if (!json.ok) {
        setError(String(json.message || t("siteSettings.logs.loadError")))
        return
      }
      const list = Array.isArray(json.rows) ? (json.rows as LogRow[]) : []
      setRows(list)
      setPagination(parsePaginationMeta(json.pagination))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, level, search, t])

  useEffect(() => {
    void load()
  }, [page, perPage, level, search, load])

  const contextPreview = useMemo(() => {
    if (!detail?.context) return "—"
    try {
      return JSON.stringify(detail.context, null, 2)
    } catch {
      return String(detail.context)
    }
  }, [detail])

  const onClear = useCallback(async (all: boolean) => {
    setClearing(true)
    setError(null)
    try {
      const res = await postAdminMutate("logs_clear", {
        confirm: 1,
        older_than_days: all ? 0 : Math.max(0, Number(clearDays) || 0),
      })
      if (!res.ok) {
        setError(res.message || tp("clearError"))
        return
      }
      setPage(1)
      await load()
    } finally {
      setClearing(false)
    }
  }, [clearDays, load, tp])

  return (
    <div className={cn("space-y-4 text-start")} data-testid="dash-logs-tab">
      <div className={cn("flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between")}>
        <div className={cn("flex flex-wrap gap-2")}>
          <div className="space-y-1">
            <Label className="text-xs">{tp("filterLevel")}</Label>
            <DashSelect
              triggerClassName="w-[140px]"
              value={level || "all"}
              onValueChange={(v) => {
                setLevel(v === "all" ? "" : v)
                setPage(1)
              }}
              options={[
                { value: "all", label: tp("levelAll") },
                { value: "info", label: "info" },
                { value: "warning", label: "warning" },
                { value: "error", label: "error" },
              ]}
            />
          </div>
          <div className="space-y-1">
            <Label className="text-xs">{tp("search")}</Label>
            <div className="flex gap-2">
              <Input
                value={searchInput}
                onChange={(e) => setSearchInput(e.target.value)}
                placeholder={tp("searchPlaceholder")}
                className={cn("w-48", ltrCell())}
                dir="ltr"
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    setSearch(searchInput.trim())
                    setPage(1)
                  }
                }}
              />
              <Button type="button" variant="secondary" onClick={() => { setSearch(searchInput.trim()); setPage(1) }}>
                {tp("searchBtn")}
              </Button>
            </div>
          </div>
        </div>
        <AlertDialog>
          <AlertDialogTrigger asChild>
            <Button type="button" variant="destructive" disabled={clearing}>
              {tp("clearBtn")}
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent className={cn("text-start")}>
            <AlertDialogHeader className={cn("text-start")}>
              <AlertDialogTitle>{tp("clearTitle")}</AlertDialogTitle>
              <AlertDialogDescription>{tp("clearDesc")}</AlertDialogDescription>
            </AlertDialogHeader>
            <div className="space-y-2">
              <Label htmlFor="clear_days">{tp("clearOlderDays")}</Label>
              <Input
                id="clear_days"
                type="number"
                min={0}
                value={clearDays}
                onChange={(e) => setClearDays(e.target.value)}
                dir="ltr"
                className={ltrCell("tabular-nums")}
              />
              <p className="text-xs text-muted-foreground">{tp("clearOlderHint")}</p>
            </div>
            <AlertDialogFooter className="gap-2">
              <AlertDialogCancel>{tp("cancel")}</AlertDialogCancel>
              <AlertDialogAction onClick={() => void onClear(false)} disabled={clearing}>
                {tp("clearConfirm")}
              </AlertDialogAction>
              <AlertDialogAction
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                onClick={() => void onClear(true)}
                disabled={clearing}
              >
                {tp("clearAll")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>

      {error ? (
        <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </div>
      ) : null}

      <DashTableShell minWidth="40rem" colWidths={LOGS_TABLE_COLS}>
        <thead>
          <tr className="bg-muted/40">
            <DashTh>ID</DashTh>
            <DashTh>{tp("colLevel")}</DashTh>
            <DashTh>{tp("colMessage")}</DashTh>
            <DashTh>{tp("colTime")}</DashTh>
            <DashTh />
          </tr>
        </thead>
        <tbody>
          {loading && rows.length === 0 ? (
            <tr>
              <DashTd colSpan={5} className="text-center text-muted-foreground">
                {tp("loading")}
              </DashTd>
            </tr>
          ) : null}
          {!loading && rows.length === 0 ? (
            <tr>
              <DashTd colSpan={5} className="text-center text-muted-foreground">
                {tp("empty")}
              </DashTd>
            </tr>
          ) : null}
          {rows.map((r) => (
            <tr key={r.id}>
              <DashTd dir="ltr" className={ltrCell("font-mono text-xs")}>
                {r.id}
              </DashTd>
              <DashTd dir="ltr" className={ltrCell("font-mono text-xs")}>
                {r.level}
              </DashTd>
              <DashTd className="max-w-md truncate text-sm">{r.message}</DashTd>
              <DashTd className="whitespace-nowrap text-xs text-muted-foreground">
                {formatServiceExpiryLine(r.created_at, isFa)}
              </DashTd>
              <DashTd>
                <Button type="button" size="sm" variant="ghost" onClick={() => setDetail(r)}>
                  {tp("details")}
                </Button>
              </DashTd>
            </tr>
          ))}
        </tbody>
      </DashTableShell>

      {pagination ? (
        <DataPagination
          meta={pagination}
        onPageChange={setPage}
          onPerPageChange={(n) => {
            setPerPage(n)
            setPage(1)
          }}
        />
      ) : null}

      <Dialog open={detail != null} onOpenChange={(o) => !o && setDetail(null)}>
        <DashDialogContent className={cn("sm:max-w-2xl")}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{tp("detailTitle")}</DialogTitle>
          </DashDialogHeader>
          {detail ? (
            <div className="space-y-3 text-sm text-start">
              <p>
                <span className="text-muted-foreground">{tp("colLevel")}: </span>
                {detail.level}
              </p>
              <p className="whitespace-pre-wrap break-words">{detail.message}</p>
              <pre className={ltrCell("max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs")} dir="ltr">
                {contextPreview}
              </pre>
            </div>
          ) : null}
        </DashDialogContent>
      </Dialog>
    </div>
  )
}
