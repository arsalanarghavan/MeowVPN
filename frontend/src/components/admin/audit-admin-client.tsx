"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { getAdminState } from "@/lib/dash-admin-mutate"
import { formatDateTime, formatNumber } from "@/lib/format-locale"

type AuditRow = {
  id?: number
  created_at?: string | number | null
  domain?: string
  event_type?: string
  actor_type?: string
  actor_id?: number | string | null
  actor_label?: string | null
  target_type?: string
  target_id?: number | string | null
  payload_json?: string | Record<string, unknown> | null
}

type Pagination = { page: number; perPage: number; total: number }

const domains = ["admin", "billing", "bot", "security", "reseller"]

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function parsePagination(raw: unknown): Pagination {
  if (!raw || typeof raw !== "object") return { page: 1, perPage: 30, total: 0 }
  const r = raw as Record<string, unknown>
  return { page: num(r.page) || 1, perPage: num(r.perPage ?? r.per_page) || 30, total: num(r.total) }
}

function parsePayload(raw: AuditRow["payload_json"]): Record<string, unknown> {
  if (!raw) return {}
  if (typeof raw === "object") return raw as Record<string, unknown>
  try {
    const data = JSON.parse(raw)
    return data && typeof data === "object" && !Array.isArray(data) ? (data as Record<string, unknown>) : {}
  } catch {
    return {}
  }
}

function canonicalEvent(event: string): string {
  return event.trim().replace(/[.:]/g, "_")
}

export function AuditAdminClient() {
  const t = useTranslations("auditAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"

  const [rows, setRows] = useState<AuditRow[]>([])
  const [pagination, setPagination] = useState<Pagination>({ page: 1, perPage: 30, total: 0 })
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(30)
  const [domain, setDomain] = useState("")
  const [eventType, setEventType] = useState("")
  const [searchInput, setSearchInput] = useState("")
  const [search, setSearch] = useState("")
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const tr = useCallback(
    (key: string, fallback: string, values?: Record<string, string | number>) => {
      try {
        return t(key, values)
      } catch {
        return fallback
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
      setRows(Array.isArray(data.auditRows) ? (data.auditRows as AuditRow[]) : [])
      setPagination(parsePagination(data.auditPagination))
    } catch {
      setError(t("loadError"))
    } finally {
      setLoading(false)
    }
  }, [domain, eventType, page, perPage, search, t])

  useEffect(() => {
    void load()
  }, [load])

  const totalPages = useMemo(
    () => Math.max(1, Math.ceil(pagination.total / (pagination.perPage || perPage))),
    [pagination, perPage]
  )

  const domainLabel = (value: string) => tr(`domain_${value}`, value || "—")
  const eventLabel = (value: string) => {
    const key = canonicalEvent(value)
    return key ? tr(`event_${key}`, value || "—") : "—"
  }
  const actorLabel = (row: AuditRow) => {
    const label = String(row.actor_label ?? "").trim()
    if (label) return label
    const type = String(row.actor_type ?? "unknown")
    const id = String(row.actor_id ?? "").trim()
    const kind = tr(`actor_${type}`, type)
    return id ? `${kind} #${id}` : kind
  }
  const targetLabel = (row: AuditRow) => {
    const type = String(row.target_type ?? "unknown")
    const id = String(row.target_id ?? "").trim()
    const kind = tr(`target_${type}`, type)
    return id ? `${kind} #${id}` : kind
  }
  const payloadLines = (row: AuditRow) => {
    const payload = parsePayload(row.payload_json)
    return Object.entries(payload)
      .slice(0, 5)
      .map(([key, value]) => {
        const label = tr(`payload_${key}`, key)
        const text =
          typeof value === "boolean"
            ? value ? t("payloadYes") : t("payloadNo")
            : typeof value === "object" && value !== null
              ? JSON.stringify(value)
              : String(value ?? "—")
        return `${label}: ${text}`
      })
  }

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
              {domains.map((d) => <option key={d} value={d}>{domainLabel(d)}</option>)}
            </select>
          </div>
          <div className="space-y-1.5">
            <Label>{t("filterEvent")}</Label>
            <Input className="w-48" dir="ltr" value={eventType} placeholder={t("eventPlaceholder")} onChange={(e) => { setEventType(e.target.value); setPage(1) }} />
          </div>
          <div className="min-w-52 flex-1 space-y-1.5">
            <Label>{t("search")}</Label>
            <div className="flex gap-2">
              <Input dir="ltr" value={searchInput} placeholder={t("searchPlaceholder")} onChange={(e) => setSearchInput(e.target.value)} />
              <Button type="button" size="sm" onClick={() => { setSearch(searchInput.trim()); setPage(1) }}>{t("searchBtn")}</Button>
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
                  <TableRow><TableCell colSpan={6} className="text-center text-muted-foreground">{t("loading")}</TableCell></TableRow>
                ) : null}
                {!loading && rows.length === 0 ? (
                  <TableRow><TableCell colSpan={6} className="text-center text-muted-foreground">{t("empty")}</TableCell></TableRow>
                ) : null}
                {rows.map((row) => {
                  const event = String(row.event_type ?? "")
                  const lines = payloadLines(row)
                  return (
                    <TableRow key={String(row.id ?? `${event}-${row.created_at}`)}>
                      <TableCell className="text-xs text-muted-foreground">{formatDateTime(row.created_at, isFa)}</TableCell>
                      <TableCell>{domainLabel(String(row.domain ?? ""))}</TableCell>
                      <TableCell>
                        <span className="font-medium">{eventLabel(event)}</span>
                        <span className="mt-0.5 block font-mono text-[10px] text-muted-foreground" dir="ltr">{event || "—"}</span>
                      </TableCell>
                      <TableCell>{actorLabel(row)}</TableCell>
                      <TableCell>{targetLabel(row)}</TableCell>
                      <TableCell className="max-w-md whitespace-normal text-xs">
                        {lines.length > 0 ? (
                          <ul className="space-y-1 text-muted-foreground">
                            {lines.map((line) => <li key={line}>{line}</li>)}
                          </ul>
                        ) : (
                          <span className="text-muted-foreground">{t("payloadEmpty")}</span>
                        )}
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </div>
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
            <p className="text-muted-foreground">{formatNumber(pagination.total, isFa)}</p>
            <div className="flex items-center gap-2">
              <Button type="button" size="sm" variant="outline" disabled={page <= 1 || loading} onClick={() => setPage((p) => Math.max(1, p - 1))}>‹</Button>
              <span className="tabular-nums" dir="ltr">{formatNumber(page, isFa)} / {formatNumber(totalPages, isFa)}</span>
              <Button type="button" size="sm" variant="outline" disabled={page >= totalPages || loading} onClick={() => setPage((p) => Math.min(totalPages, p + 1))}>›</Button>
              <select className="h-8 rounded-md border bg-background px-2 text-sm" value={perPage} onChange={(e) => { setPerPage(num(e.target.value)); setPage(1) }}>
                {[25, 30, 50, 100].map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
