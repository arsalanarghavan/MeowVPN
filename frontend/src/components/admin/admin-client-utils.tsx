"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatDateTime, formatNumber } from "@/lib/format-locale"

export type DashRecord = Record<string, unknown>

export type Pagination = { page: number; perPage: number; total: number }

export type AdminColumn = {
  key: string
  label: string
  kind?: "text" | "number" | "date" | "status" | "money" | "bool"
  empty?: string
}

export type AdminFilter = {
  key: string
  label: string
  placeholder?: string
  type?: "text" | "number" | "date" | "select"
  options?: Array<{ value: string; label: string }>
}

export type AdminFormField = {
  key: string
  label: string
  placeholder?: string
  type?: "text" | "number" | "textarea" | "select" | "checkbox"
  value?: string | number | boolean
  options?: Array<{ value: string; label: string }>
}

export type AdminAction = {
  label: string
  op: string
  confirm?: string
  buildPayload: (row: DashRecord) => Record<string, unknown>
}

export type AdminStat = {
  label: string
  value: unknown
  kind?: "number" | "money" | "text"
}

type AdminClientProps = {
  namespace: string
  activeTab: string
  titleKey?: string
  subtitleKey?: string
  rowsKey: string
  fallbackRowsKeys?: string[]
  paginationKey?: string
  columns: AdminColumn[]
  filters?: AdminFilter[]
  initialQuery?: Record<string, string | number>
  stats?: (data: DashRecord) => AdminStat[]
  create?: {
    title: string
    op: string
    fields: AdminFormField[]
    submitLabel?: string
    buildPayload?: (values: Record<string, string | number | boolean>) => Record<string, unknown>
  }
  actions?: AdminAction[]
  rowKey?: (row: DashRecord, index: number) => string
}

export function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

export function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

export function rowsFrom(data: DashRecord, key: string, fallbackKeys: string[] = []): DashRecord[] {
  for (const k of [key, ...fallbackKeys]) {
    const raw = data[k]
    if (Array.isArray(raw)) return raw.filter((x): x is DashRecord => !!x && typeof x === "object")
  }
  return []
}

export function paginationFrom(data: DashRecord, key?: string): Pagination | null {
  const pagination = data.pagination && typeof data.pagination === "object" ? data.pagination as DashRecord : {}
  const raw = key ? data[key] ?? pagination[key] : null
  if (!raw || typeof raw !== "object") return null
  const r = raw as DashRecord
  const page = num(r.page)
  const perPage = num(r.perPage ?? r.per_page)
  const total = num(r.total)
  return page > 0 && perPage > 0 ? { page, perPage, total } : null
}

export function displayName(row: DashRecord): string {
  const name = `${String(row.first_name ?? row.firstName ?? "").trim()} ${String(row.last_name ?? row.lastName ?? "").trim()}`.trim()
  if (name) return name
  const username = String(row.username ?? row.user_username ?? "").trim()
  if (username) return username.startsWith("@") ? username : `@${username}`
  const label = String(row.user_label ?? row.displayName ?? row.display_name ?? row.name ?? row.label ?? "").trim()
  if (label) return label
  const id = row.id ?? row.user_id ?? row.svp_user_id ?? row.reseller_id
  return id == null ? "—" : `#${String(id)}`
}

function valueFor(row: DashRecord, key: string): unknown {
  if (key === "display_name") return displayName(row)
  if (key.includes(".")) {
    return key.split(".").reduce<unknown>((cur, part) => (
      cur && typeof cur === "object" ? (cur as DashRecord)[part] : undefined
    ), row)
  }
  return row[key]
}

function statusVariant(value: unknown): "default" | "secondary" | "destructive" | "outline" {
  const s = String(value ?? "").toLowerCase()
  if (["approved", "active", "sent", "success", "done", "converted", "enabled"].includes(s)) return "default"
  if (["rejected", "failed", "cancelled", "blocked", "disabled", "expired"].includes(s)) return "destructive"
  if (["pending", "processing", "sending", "issued"].includes(s)) return "secondary"
  return "outline"
}

function FormControl({
  field,
  value,
  onChange,
}: {
  field: AdminFormField
  value: string | number | boolean
  onChange: (value: string | number | boolean) => void
}) {
  if (field.type === "textarea") {
    return (
      <Textarea
        value={String(value ?? "")}
        placeholder={field.placeholder}
        onChange={(e) => onChange(e.target.value)}
      />
    )
  }
  if (field.type === "select") {
    return (
      <select
        className="h-9 rounded-md border bg-background px-3 text-sm"
        value={String(value ?? "")}
        onChange={(e) => onChange(e.target.value)}
      >
        {(field.options ?? []).map((opt) => (
          <option key={opt.value} value={opt.value}>{opt.label}</option>
        ))}
      </select>
    )
  }
  if (field.type === "checkbox") {
    return (
      <input
        type="checkbox"
        className="size-4"
        checked={Boolean(value)}
        onChange={(e) => onChange(e.target.checked)}
      />
    )
  }
  return (
    <Input
      type={field.type === "number" ? "number" : "text"}
      value={String(value ?? "")}
      placeholder={field.placeholder}
      onChange={(e) => onChange(field.type === "number" ? Number(e.target.value) : e.target.value)}
    />
  )
}

export function WaveAdminClient({
  namespace,
  activeTab,
  titleKey = "title",
  subtitleKey = "subtitle",
  rowsKey,
  fallbackRowsKeys,
  paginationKey,
  columns,
  filters = [],
  initialQuery = {},
  stats,
  create,
  actions = [],
  rowKey,
}: AdminClientProps) {
  const t = useTranslations(namespace)
  const common = useTranslations("adminClientCommon")
  const locale = useLocale()
  const isFa = locale === "fa"

  const [data, setData] = useState<DashRecord>({})
  const [rows, setRows] = useState<DashRecord[]>([])
  const [query, setQuery] = useState<Record<string, string | number>>(initialQuery)
  const [pagination, setPagination] = useState<Pagination | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState<string | null>(null)
  const [formValues, setFormValues] = useState<Record<string, string | number | boolean>>(() => {
    const out: Record<string, string | number | boolean> = {}
    for (const f of create?.fields ?? []) out[f.key] = f.value ?? (f.type === "checkbox" ? false : "")
    return out
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const state = await getAdminState(activeTab, {
        ...query,
        [`${rowsKey}_page`]: page,
        [`${rowsKey}_per_page`]: perPage,
      })
      setData(state)
      setRows(rowsFrom(state, rowsKey, fallbackRowsKeys))
      setPagination(paginationFrom(state, paginationKey ?? rowsKey))
    } catch {
      setError(common("loadError"))
    } finally {
      setLoading(false)
    }
  }, [activeTab, common, fallbackRowsKeys, page, paginationKey, perPage, query, rowsKey])

  useEffect(() => {
    void load()
  }, [load])

  const computedStats = useMemo(() => stats?.(data) ?? [], [data, stats])

  const renderValue = (row: DashRecord, col: AdminColumn) => {
    const value = valueFor(row, col.key)
    if (col.kind === "date") return formatDateTime(value as string | number | null, isFa)
    if (col.kind === "number" || col.kind === "money") return formatNumber(num(value), isFa)
    if (col.kind === "bool") return bool(value) ? common("yes") : common("no")
    if (col.kind === "status") {
      const text = String(value ?? col.empty ?? "—")
      return <Badge variant={statusVariant(text)}>{text || "—"}</Badge>
    }
    return String(value ?? col.empty ?? "—") || "—"
  }

  const runAction = async (action: AdminAction, row: DashRecord, index: number) => {
    if (action.confirm && !window.confirm(action.confirm)) return
    const id = `${action.op}-${index}`
    setBusy(id)
    setMessage(null)
    try {
      const res = await postAdminMutate(action.op, action.buildPayload(row))
      if (!res.ok) {
        setMessage(res.message || res.reason || common("mutateError"))
        return
      }
      setMessage(common("saved"))
      await load()
    } finally {
      setBusy(null)
    }
  }

  const submitCreate = async () => {
    if (!create) return
    setBusy(create.op)
    setMessage(null)
    try {
      const payload = create.buildPayload ? create.buildPayload(formValues) : formValues
      const res = await postAdminMutate(create.op, payload)
      if (!res.ok) {
        setMessage(res.message || res.reason || common("mutateError"))
        return
      }
      setMessage(common("saved"))
      setFormValues(() => {
        const out: Record<string, string | number | boolean> = {}
        for (const f of create.fields) out[f.key] = f.value ?? (f.type === "checkbox" ? false : "")
        return out
      })
      await load()
    } finally {
      setBusy(null)
    }
  }

  const totalPages = pagination ? Math.max(1, Math.ceil(pagination.total / pagination.perPage)) : 1

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t(titleKey)}</h1>
          <p className="text-sm text-muted-foreground">{t(subtitleKey)}</p>
        </div>
        <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
          {common("refresh")}
        </Button>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {message ? <p className="text-sm text-muted-foreground">{message}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{common("loading")}</p> : null}

      {computedStats.length ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {computedStats.map((stat) => (
            <Card key={stat.label}>
              <CardHeader className="pb-2">
                <CardDescription>{stat.label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">
                  {stat.kind === "text" ? String(stat.value ?? "—") : formatNumber(num(stat.value), isFa)}
                </CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>
      ) : null}

      {create ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{create.title}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-3 md:grid-cols-2">
              {create.fields.map((field) => (
                <div key={field.key} className="space-y-1.5">
                  <Label>{field.label}</Label>
                  <FormControl
                    field={field}
                    value={formValues[field.key] ?? ""}
                    onChange={(value) => setFormValues((cur) => ({ ...cur, [field.key]: value }))}
                  />
                </div>
              ))}
            </div>
            <Button type="button" disabled={busy === create.op} onClick={() => void submitCreate()}>
              {create.submitLabel ?? common("save")}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {filters.length ? (
        <Card>
          <CardContent className="flex flex-wrap items-end gap-3 pt-6">
            {filters.map((filter) => (
              <div key={filter.key} className="min-w-[12rem] flex-1 space-y-1.5">
                <Label>{filter.label}</Label>
                {filter.type === "select" ? (
                  <select
                    className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                    value={String(query[filter.key] ?? "")}
                    onChange={(e) => {
                      setPage(1)
                      setQuery((cur) => ({ ...cur, [filter.key]: e.target.value }))
                    }}
                  >
                    {(filter.options ?? []).map((opt) => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                ) : (
                  <Input
                    type={filter.type === "number" ? "number" : filter.type === "date" ? "date" : "text"}
                    value={String(query[filter.key] ?? "")}
                    placeholder={filter.placeholder}
                    onChange={(e) => {
                      setPage(1)
                      setQuery((cur) => ({ ...cur, [filter.key]: e.target.value }))
                    }}
                  />
                )}
              </div>
            ))}
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{common("list")}</CardTitle>
          {pagination ? <CardDescription>{common("total", { total: formatNumber(pagination.total, isFa) })}</CardDescription> : null}
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="overflow-x-auto rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  {columns.map((col) => <TableHead key={col.key}>{col.label}</TableHead>)}
                  {actions.length ? <TableHead>{common("actions")}</TableHead> : null}
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={columns.length + (actions.length ? 1 : 0)} className="text-center text-muted-foreground">
                      {common("empty")}
                    </TableCell>
                  </TableRow>
                ) : (
                  rows.map((row, index) => (
                    <TableRow key={rowKey ? rowKey(row, index) : String(row.id ?? row.code ?? index)}>
                      {columns.map((col) => (
                        <TableCell key={col.key} className={col.kind === "number" || col.kind === "money" ? "tabular-nums" : undefined}>
                          {renderValue(row, col)}
                        </TableCell>
                      ))}
                      {actions.length ? (
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            {actions.map((action) => (
                              <Button
                                key={action.op + action.label}
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={busy === `${action.op}-${index}`}
                                onClick={() => void runAction(action, row, index)}
                              >
                                {action.label}
                              </Button>
                            ))}
                          </div>
                        </TableCell>
                      ) : null}
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <span>{common("page", { page: formatNumber(page, isFa), pages: formatNumber(totalPages, isFa) })}</span>
              <select
                className="h-8 rounded-md border bg-background px-2"
                value={perPage}
                onChange={(e) => {
                  setPage(1)
                  setPerPage(Number(e.target.value))
                }}
              >
                {[25, 50, 100, 150, 200].map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
            </div>
            <div className="flex gap-2">
              <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
                {common("prev")}
              </Button>
              <Button type="button" variant="outline" size="sm" disabled={page >= totalPages} onClick={() => setPage((p) => p + 1)}>
                {common("next")}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
