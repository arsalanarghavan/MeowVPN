"use client"

import { useCallback, useEffect, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { DataPagination } from "@/components/data-pagination"
import { getAdminState } from "@/lib/dash-admin-mutate"
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
import { formatDateTime } from "@/lib/format-locale"

const DOMAIN_OPTIONS = ["admin", "billing", "bot", "security", "reseller"] as const

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function parsePayload(raw: unknown): unknown {
  if (raw == null) return {}
  if (typeof raw === "object") return raw
  if (typeof raw !== "string") return {}
  const s = raw.trim()
  if (!s) return {}
  try {
    return JSON.parse(s) as unknown
  } catch {
    return {}
  }
}

function toAuditRow(raw: unknown): AuditRow | null {
  if (!raw || typeof raw !== "object") return null
  const r = raw as Record<string, unknown>
  const id = num(r.id)
  if (id < 1) return null
  return {
    id,
    created_at: String(r.created_at ?? ""),
    domain: String(r.domain ?? ""),
    event_type: String(r.event_type ?? ""),
    actor_kind: String(r.actor_kind ?? r.actor_type ?? "unknown"),
    actor_wp_user_id: num(r.actor_wp_user_id),
    actor_svp_user_id: num(r.actor_svp_user_id ?? r.actor_id),
    target_type: String(r.target_type ?? ""),
    target_id: num(r.target_id),
    reseller_scope_id: num(r.reseller_scope_id),
    payload: parsePayload(r.payload ?? r.payload_json),
  }
}

export function AuditAdminClient() {
  const t = useTranslations("auditAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"

  const [rows, setRows] = useState<AuditRow[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [domain, setDomain] = useState("")
  const [eventType, setEventType] = useState("")
  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const auditT = useCallback(
    (key: string, opts?: Record<string, string | number>) => {
      try {
        return t(key as never, opts as never)
      } catch {
        return key
      }
    },
    [t]
  )

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("audit", {
        audit_page: page,
        audit_per_page: perPage,
        domain,
        event_type: eventType,
        q: search,
      })
      const list = Array.isArray(data.auditRows)
        ? (data.auditRows as unknown[]).map(toAuditRow).filter((row): row is AuditRow => row != null)
        : []
      setRows(list)
      setPagination(parsePaginationMeta(data.auditPagination))
    } catch {
      setError(t("loadError"))
    } finally {
      setLoading(false)
    }
  }, [domain, eventType, page, perPage, search, t])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
          {t("searchBtn")}
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("search")}</CardTitle>
          <CardDescription>{t("subtitle")}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap items-end gap-3">
          <div className="space-y-1.5">
            <Label>{t("filterDomain")}</Label>
            <select
              className="h-9 w-40 rounded-md border bg-background px-3 text-sm"
              value={domain}
              onChange={(e) => {
                setDomain(e.target.value)
                setPage(1)
              }}
            >
              <option value="">{t("domainAll")}</option>
              {DOMAIN_OPTIONS.map((d) => (
                <option key={d} value={d}>
                  {formatAuditDomain(d, auditT)}
                </option>
              ))}
            </select>
          </div>
          <div className="space-y-1.5">
            <Label>{t("filterEvent")}</Label>
            <Input
              className="w-48"
              dir="ltr"
              value={eventType}
              placeholder={t("eventPlaceholder")}
              onChange={(e) => {
                setEventType(e.target.value)
                setPage(1)
              }}
            />
          </div>
          <div className="min-w-52 flex-1 space-y-1.5">
            <Label>{t("search")}</Label>
            <div className="flex gap-2">
              <Input
                dir="ltr"
                value={searchInput}
                placeholder={t("searchPlaceholder")}
                onChange={(e) => setSearchInput(e.target.value)}
              />
              <Button
                type="button"
                size="sm"
                onClick={() => {
                  setSearch(searchInput.trim())
                  setPage(1)
                }}
              >
                {t("searchBtn")}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}

      <Card>
        <CardContent className="pt-6">
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("colTime")}</TableHead>
                  <TableHead>{t("colDomain")}</TableHead>
                  <TableHead>{t("colEvent")}</TableHead>
                  <TableHead>{t("colActor")}</TableHead>
                  <TableHead>{t("colTarget")}</TableHead>
                  <TableHead>{t("colSummary")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loading && rows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                      {t("loading")}
                    </TableCell>
                  </TableRow>
                ) : null}
                {!loading && rows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                      {t("empty")}
                    </TableCell>
                  </TableRow>
                ) : null}
                {rows.map((row) => {
                  const summary = formatAuditSummary(row, auditT, isFa)
                  return (
                    <TableRow key={row.id}>
                      <TableCell className="text-xs text-muted-foreground">
                        {formatDateTime(row.created_at, isFa)}
                      </TableCell>
                      <TableCell>{formatAuditDomain(row.domain, auditT)}</TableCell>
                      <TableCell>
                        <span className="font-medium">{formatAuditEventLabel(row.event_type, auditT)}</span>
                        {row.event_type ? (
                          <span className="mt-0.5 block font-mono text-[10px] text-muted-foreground" dir="ltr">
                            {canonicalAuditEventType(row.event_type) || row.event_type}
                          </span>
                        ) : null}
                      </TableCell>
                      <TableCell>{formatAuditActor(row, auditT, isFa)}</TableCell>
                      <TableCell>{formatAuditTarget(row, auditT, isFa)}</TableCell>
                      <TableCell className="max-w-md whitespace-normal text-xs">
                        <p className="text-foreground">{summary.headline}</p>
                        {summary.details.length > 0 ? (
                          <ul className="mt-1 space-y-0.5 text-muted-foreground">
                            {summary.details.slice(0, 4).map((line) => (
                              <li key={line}>{line}</li>
                            ))}
                          </ul>
                        ) : (
                          <p className="mt-1 text-muted-foreground">{t("payloadEmpty")}</p>
                        )}
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </div>
          <DataPagination
            meta={pagination}
            onPageChange={setPage}
            onPerPageChange={(n) => {
              setPerPage(n)
              setPage(1)
            }}
            perPageOptions={[25, 30, 50, 100]}
          />
        </CardContent>
      </Card>
    </div>
  )
}
