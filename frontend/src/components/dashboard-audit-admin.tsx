"use client"

import { useCallback, useEffect, useState } from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DashPage } from "@/components/dash-page"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { DataPagination } from "@/components/data-pagination"
import { getAdminJson } from "@/lib/dash-admin-mutate"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import {
  canonicalAuditEventType,
  formatAuditActor,
  formatAuditDomain,
  formatAuditEventLabel,
  formatAuditSummary,
  formatAuditTarget,
  type AuditRow,
} from "@/lib/format-audit-log"
import { formatServiceExpiryLine } from "@/lib/format-locale"
import { useAdminTp } from "@/lib/use-admin-tp"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"

const DOMAIN_OPTIONS = ["admin", "billing", "bot", "security", "reseller"] as const
const AUDIT_TABLE_COLS_FA = ["28%", "10%", "14%", "14%", "14%", "20%"]
const AUDIT_TABLE_COLS_EN = ["20%", "10%", "14%", "14%", "14%", "28%"]

export function DashboardAuditAdmin() {
  const { isFa, ltrCell } = useDashLocale()
  const { t } = useTranslation()
  const tp = useAdminTp("auditAdmin")
  const [rows, setRows] = useState<AuditRow[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [domain, setDomain] = useState("")
  const [eventType, setEventType] = useState("")
  const [search, setSearch] = useState("")
  const [searchInput, setSearchInput] = useState("")
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const auditT = useCallback(
    (key: string, opts?: Record<string, string | number>) => t(`auditAdmin.${key}`, opts),
    [t]
  )

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const json = await getAdminJson("/dashboard/admin/audit", {
        page,
        per_page: perPage,
        domain,
        event_type: eventType,
        q: search,
      })
      if (!json.ok) {
        setError(String(json.message || t("auditAdmin.loadError")))
        return
      }
      const list = Array.isArray(json.rows) ? (json.rows as AuditRow[]) : []
      setRows(list)
      setPagination(parsePaginationMeta(json.pagination))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, domain, eventType, search, t])

  useEffect(() => {
    void load()
  }, [page, perPage, domain, eventType, search, load])

  const renderRowCells = (row: AuditRow) => {
    const summary = formatAuditSummary(row, auditT, isFa)
    const time = (
      <DashTd className={cn("whitespace-nowrap text-xs", ltrCell())}>
        {formatServiceExpiryLine(row.created_at, isFa)}
      </DashTd>
    )
    const domainCell = (
      <DashTd className="text-xs text-start">{formatAuditDomain(row.domain, auditT)}</DashTd>
    )
    const eventCell = (
      <DashTd className="text-xs text-start">
        <span className="font-medium">{formatAuditEventLabel(row.event_type, auditT)}</span>
        {row.event_type ? (
          <div dir="ltr" className={cn("mt-0.5 font-mono text-[10px] text-muted-foreground", ltrCell())}>
            {canonicalAuditEventType(row.event_type) || row.event_type}
          </div>
        ) : null}
      </DashTd>
    )
    const actorCell = (
      <DashTd className="text-xs text-start">{formatAuditActor(row, auditT, isFa)}</DashTd>
    )
    const targetCell = (
      <DashTd className="text-xs text-start">{formatAuditTarget(row, auditT, isFa)}</DashTd>
    )
    const summaryCell = (
      <DashTd className="text-xs text-start">
        <p className="text-foreground">{summary.headline}</p>
        {summary.details.length > 0 ? (
          <ul className="mt-1 space-y-0.5 text-muted-foreground">
            {summary.details.slice(0, 4).map((line) => (
              <li key={line}>{line}</li>
            ))}
          </ul>
        ) : (
          <p className="mt-1 text-muted-foreground">{tp("payloadEmpty")}</p>
        )}
      </DashTd>
    )

    if (isFa) {
      return (
        <>
          {summaryCell}
          {targetCell}
          {actorCell}
          {eventCell}
          {domainCell}
          {time}
        </>
      )
    }

    return (
      <>
        {time}
        {domainCell}
        {eventCell}
        {actorCell}
        {targetCell}
        {summaryCell}
      </>
    )
  }

  const headerCells = isFa ? (
    <>
      <DashTh>{tp("colSummary")}</DashTh>
      <DashTh>{tp("colTarget")}</DashTh>
      <DashTh>{tp("colActor")}</DashTh>
      <DashTh>{tp("colEvent")}</DashTh>
      <DashTh>{tp("colDomain")}</DashTh>
      <DashTh className="whitespace-nowrap">{tp("colTime")}</DashTh>
    </>
  ) : (
    <>
      <DashTh className="whitespace-nowrap">{tp("colTime")}</DashTh>
      <DashTh>{tp("colDomain")}</DashTh>
      <DashTh>{tp("colEvent")}</DashTh>
      <DashTh>{tp("colActor")}</DashTh>
      <DashTh>{tp("colTarget")}</DashTh>
      <DashTh>{tp("colSummary")}</DashTh>
    </>
  )

  return (
    <DashPage className="space-y-4">
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />

      <div className="flex flex-wrap items-end gap-3 text-start">
        <div className="space-y-1.5">
          <Label className="text-xs">{tp("filterDomain")}</Label>
          <DashSelect
            triggerClassName="w-[160px]"
            value={domain || "__all"}
            onValueChange={(v) => setDomain(v === "__all" ? "" : v)}
            options={[
              { value: "__all", label: tp("domainAll") },
              ...DOMAIN_OPTIONS.map((d) => ({
                value: d,
                label: formatAuditDomain(d, auditT),
              })),
            ]}
          />
        </div>
        <div className="space-y-1.5">
          <Label className="text-xs">{tp("filterEvent")}</Label>
          <Input
            value={eventType}
            onChange={(e) => setEventType(e.target.value)}
            placeholder={tp("eventPlaceholder")}
            className={cn("h-9 w-[180px]", ltrCell())}
            dir="ltr"
          />
        </div>
        <div className="space-y-1.5">
          <Label className="text-xs">{tp("search")}</Label>
          <div className="flex gap-2">
            <Input
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder={tp("searchPlaceholder")}
              className={cn("h-9 w-[200px]", ltrCell())}
              dir="ltr"
            />
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => {
                setSearch(searchInput.trim())
                setPage(1)
              }}
            >
              {tp("searchBtn")}
            </Button>
          </div>
        </div>
      </div>

      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}

      <DashTableShell
        minWidth="48rem"
        colWidths={isFa ? AUDIT_TABLE_COLS_FA : AUDIT_TABLE_COLS_EN}
      >
        <thead>
          <tr className="bg-muted/40">{headerCells}</tr>
        </thead>
        <tbody>
          {loading && rows.length === 0 ? (
            <tr>
              <DashTd colSpan={6} className="text-center text-muted-foreground">
                {tp("loading")}
              </DashTd>
            </tr>
          ) : null}
          {!loading && rows.length === 0 ? (
            <tr>
              <DashTd colSpan={6} className="text-center text-muted-foreground">
                {tp("empty")}
              </DashTd>
            </tr>
          ) : null}
          {rows.map((row) => (
            <tr key={row.id}>{renderRowCells(row)}</tr>
          ))}
        </tbody>
      </DashTableShell>

      <DataPagination
        meta={pagination}
        onPageChange={setPage}
        onPerPageChange={(n) => {
          setPerPage(n)
          setPage(1)
        }}
      />
    </DashPage>
  )
}
