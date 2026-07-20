"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { ArrowDown, ArrowUp, ArrowUpDown } from "lucide-react"
import { getAdminState } from "@/lib/dash-admin-mutate"
import { formatDateOnly, formatNumber } from "@/lib/format-locale"
import {
  calendarParam,
  currentMonthRange,
  previousMonthRange,
  type PanelFinancialPreset,
} from "@/lib/panel-financial-period"
import { LocaleDatePicker } from "@/components/locale-date-picker"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { cn } from "@/lib/utils"

export type PanelFinancialRow = {
  panel_id?: number
  label?: string
  sales_gb?: number
  sales_toman?: number
  sales_count?: number
  receipts_toman?: number
  cost_toman?: number
  profit_toman?: number
  margin_pct?: number | null
}

export type PanelFinancialReportsPayload = {
  period?: { from?: string; to?: string; days?: number; calendar?: string }
  summary?: PanelFinancialRow
  rows?: PanelFinancialRow[]
  unresolved?: PanelFinancialRow | null
}

type SortKey =
  | "label"
  | "sales_gb"
  | "sales_toman"
  | "receipts_toman"
  | "cost_toman"
  | "profit_toman"
  | "margin_pct"

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function formatMoney(value: number, suffix: string, isFa: boolean): string {
  return `${formatNumber(value, isFa)} ${suffix}`
}

function formatPct(value: number | null | undefined, isFa: boolean): string {
  if (value == null || !Number.isFinite(value)) return "—"
  return `${formatNumber(value, isFa)}%`
}

function parseYmd(s: string): Date | undefined {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s.trim())
  if (!m) return undefined
  return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
}

function toYmd(d: Date | undefined): string {
  if (!d) return ""
  const pad = (n: number) => String(n).padStart(2, "0")
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardDescription>{label}</CardDescription>
        <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
      </CardHeader>
    </Card>
  )
}

export function PanelFinancialReportsClient() {
  const t = useTranslations("panelFinancialReportsAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"
  const currency = t("currencySuffix")

  const initial = useMemo(() => currentMonthRange(isFa), [isFa])
  const [preset, setPreset] = useState<PanelFinancialPreset>("this_month")
  const [dateFrom, setDateFrom] = useState(initial.from)
  const [dateTo, setDateTo] = useState(initial.to)
  const [reports, setReports] = useState<PanelFinancialReportsPayload | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [sortKey, setSortKey] = useState<SortKey>("sales_toman")
  const [sortDir, setSortDir] = useState<"asc" | "desc">("desc")

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("panel_financial_reports", {
        date_from: dateFrom,
        date_to: dateTo,
        calendar: calendarParam(isFa),
      })
      const payload = (data.panelFinancialReports ?? null) as PanelFinancialReportsPayload | null
      setReports(payload)
    } catch {
      setError(t("loadError"))
      setReports(null)
    } finally {
      setLoading(false)
    }
  }, [dateFrom, dateTo, isFa, t])

  useEffect(() => {
    void load()
  }, [load])

  const applyPreset = (next: PanelFinancialPreset) => {
    if (next === "custom") {
      setPreset("custom")
      return
    }
    const range = next === "this_month" ? currentMonthRange(isFa) : previousMonthRange(isFa)
    setPreset(next)
    setDateFrom(range.from)
    setDateTo(range.to)
  }

  const rows = useMemo(() => {
    const base = Array.isArray(reports?.rows) ? [...reports.rows] : []
    const dir = sortDir === "asc" ? 1 : -1
    base.sort((a, b) => {
      if (sortKey === "label") {
        return dir * String(a.label ?? "").localeCompare(String(b.label ?? ""), isFa ? "fa" : "en")
      }
      const av = sortKey === "margin_pct" ? num(a.margin_pct) : num(a[sortKey])
      const bv = sortKey === "margin_pct" ? num(b.margin_pct) : num(b[sortKey])
      return dir * (av - bv)
    })
    return base
  }, [isFa, reports?.rows, sortDir, sortKey])

  const toggleSort = (key: SortKey) => {
    setSortKey((prev) => {
      if (prev === key) {
        setSortDir((d) => (d === "desc" ? "asc" : "desc"))
        return prev
      }
      setSortDir("desc")
      return key
    })
  }

  const SortIcon = ({ col }: { col: SortKey }) => {
    if (sortKey !== col) return <ArrowUpDown className="ms-1 inline h-3.5 w-3.5 opacity-40" />
    return sortDir === "asc" ? (
      <ArrowUp className="ms-1 inline h-3.5 w-3.5" />
    ) : (
      <ArrowDown className="ms-1 inline h-3.5 w-3.5" />
    )
  }

  const period = reports?.period
  const summary = reports?.summary
  const periodLabel =
    period?.from && period?.to
      ? `${formatDateOnly(period.from, isFa)} — ${formatDateOnly(period.to, isFa)}`
      : "—"

  return (
    <div className="w-full space-y-6">
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">{t("title")}</h1>
        <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
      </div>

      <p className="text-xs text-muted-foreground">{t("costProrationHint")}</p>

      <div className="flex flex-wrap items-end gap-3">
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            size="sm"
            variant={preset === "this_month" ? "default" : "outline"}
            onClick={() => applyPreset("this_month")}
          >
            {t("presetThisMonth")}
          </Button>
          <Button
            type="button"
            size="sm"
            variant={preset === "last_month" ? "default" : "outline"}
            onClick={() => applyPreset("last_month")}
          >
            {t("presetLastMonth")}
          </Button>
          <Button
            type="button"
            size="sm"
            variant={preset === "custom" ? "default" : "outline"}
            onClick={() => applyPreset("custom")}
          >
            {t("presetCustom")}
          </Button>
        </div>
        <div className="space-y-1.5">
          <Label>{t("dateFrom")}</Label>
          <LocaleDatePicker
            value={parseYmd(dateFrom)}
            onChange={(d) => {
              setPreset("custom")
              setDateFrom(toYmd(d))
            }}
            placeholder={t("dateFrom")}
          />
        </div>
        <div className="space-y-1.5">
          <Label>{t("dateTo")}</Label>
          <LocaleDatePicker
            value={parseYmd(dateTo)}
            onChange={(d) => {
              setPreset("custom")
              setDateTo(toYmd(d))
            }}
            placeholder={t("dateTo")}
          />
        </div>
        <Button type="button" size="sm" disabled={loading} onClick={() => void load()}>
          {t("apply")}
        </Button>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}

      <p className="text-sm text-muted-foreground">
        {t("periodLabel", {
          range: periodLabel,
          days: String(period?.days ?? "—"),
          calendar: period?.calendar === "jalali" ? t("calendarJalali") : t("calendarGregorian"),
        })}
      </p>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label={t("kpiSales")}
          value={loading ? "…" : formatMoney(num(summary?.sales_toman), currency, isFa)}
        />
        <StatCard
          label={t("kpiVolume")}
          value={loading ? "…" : `${formatNumber(num(summary?.sales_gb), isFa)} ${t("gbSuffix")}`}
        />
        <StatCard
          label={t("kpiReceipts")}
          value={loading ? "…" : formatMoney(num(summary?.receipts_toman), currency, isFa)}
        />
        <StatCard
          label={t("kpiProfit")}
          value={loading ? "…" : formatMoney(num(summary?.profit_toman), currency, isFa)}
        />
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t("tableTitle")}</CardTitle>
          <CardDescription>{t("tableDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                {(
                  [
                    ["label", "colPanel"],
                    ["sales_gb", "colVolume"],
                    ["sales_toman", "colSales"],
                    ["receipts_toman", "colReceipts"],
                    ["cost_toman", "colCost"],
                    ["profit_toman", "colProfit"],
                    ["margin_pct", "colMargin"],
                  ] as const
                ).map(([key, labelKey]) => (
                  <TableHead key={key}>
                    <button type="button" className="inline-flex items-center" onClick={() => toggleSort(key)}>
                      {t(labelKey)}
                      <SortIcon col={key} />
                    </button>
                  </TableHead>
                ))}
                <TableHead>{t("colTxCount")}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                <TableRow>
                  <TableCell colSpan={8} className="text-center text-muted-foreground">
                    {t("loading")}
                  </TableCell>
                </TableRow>
              ) : rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="text-center text-muted-foreground">
                    {t("empty")}
                  </TableCell>
                </TableRow>
              ) : (
                rows.map((row) => {
                  const profit = num(row.profit_toman)
                  const loss = profit < 0
                  return (
                    <TableRow key={String(row.panel_id ?? row.label)}>
                      <TableCell>{String(row.label ?? "—")}</TableCell>
                      <TableCell className="tabular-nums" dir="ltr">
                        {formatNumber(num(row.sales_gb), isFa)}
                      </TableCell>
                      <TableCell className="tabular-nums" dir="ltr">
                        {formatMoney(num(row.sales_toman), currency, isFa)}
                      </TableCell>
                      <TableCell className="tabular-nums" dir="ltr">
                        {formatMoney(num(row.receipts_toman), currency, isFa)}
                      </TableCell>
                      <TableCell className="tabular-nums" dir="ltr">
                        {formatMoney(num(row.cost_toman), currency, isFa)}
                      </TableCell>
                      <TableCell className={cn("tabular-nums", loss && "font-medium text-destructive")} dir="ltr">
                        {formatMoney(profit, currency, isFa)}
                        {loss ? (
                          <Badge variant="destructive" className="ms-2">
                            {t("lossBadge")}
                          </Badge>
                        ) : null}
                      </TableCell>
                      <TableCell className="tabular-nums" dir="ltr">
                        {formatPct(row.margin_pct, isFa)}
                      </TableCell>
                      <TableCell className="tabular-nums" dir="ltr">
                        {formatNumber(num(row.sales_count), isFa)}
                      </TableCell>
                    </TableRow>
                  )
                })
              )}
              {!loading && reports?.unresolved ? (
                <TableRow className="bg-muted/30">
                  <TableCell>{String(reports.unresolved.label ?? t("unresolvedLabel"))}</TableCell>
                  <TableCell className="tabular-nums" dir="ltr">
                    {formatNumber(num(reports.unresolved.sales_gb), isFa)}
                  </TableCell>
                  <TableCell className="tabular-nums" dir="ltr">
                    {formatMoney(num(reports.unresolved.sales_toman), currency, isFa)}
                  </TableCell>
                  <TableCell className="tabular-nums" dir="ltr">
                    {formatMoney(num(reports.unresolved.receipts_toman), currency, isFa)}
                  </TableCell>
                  <TableCell className="tabular-nums" dir="ltr">
                    {formatMoney(num(reports.unresolved.cost_toman), currency, isFa)}
                  </TableCell>
                  <TableCell className="tabular-nums" dir="ltr">
                    {formatMoney(num(reports.unresolved.profit_toman), currency, isFa)}
                  </TableCell>
                  <TableCell className="tabular-nums" dir="ltr">
                    {formatPct(reports.unresolved.margin_pct, isFa)}
                  </TableCell>
                  <TableCell className="tabular-nums" dir="ltr">
                    {formatNumber(num(reports.unresolved.sales_count), isFa)}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
