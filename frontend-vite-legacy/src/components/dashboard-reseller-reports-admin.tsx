"use client"

import { useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"
import { KeyRound, LogIn, Search } from "lucide-react"
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from "recharts"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { Skeleton } from "@/components/ui/skeleton"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DataPagination } from "@/components/data-pagination"
import { buildDashboardTabUrl } from "@/lib/dash-tab"
import { useChartPrimaryColor } from "@/lib/chart-accent"
import { dashActionsClass } from "@/lib/dash-locale"
import { useDashLocale } from "@/lib/dash-locale-context"
import { formatChartDayLabel, formatNumber } from "@/lib/format-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"

export type ResellerReportsStats = {
  window_days?: number
  since?: string
  backfill_done?: boolean
  daily_scoped?: boolean
  summary?: {
    reseller_count?: number
    total_sales_toman?: number
    total_wholesale_toman?: number
    total_receipts_toman?: number
    total_downline_users?: number
    margin_est?: number
    top_reseller?: { reseller_id?: number; name?: string; sales_toman?: number }
  }
}

export type ResellerReportRow = {
  reseller_id?: number
  username?: string
  first_name?: string
  last_name?: string
  status?: string
  balance?: number
  downline_users?: number
  active_services?: number
  sales_count?: number
  sales_toman?: number
  wholesale_gb?: number
  wholesale_toman?: number
  receipts_toman?: number
  margin_est?: number
}

export type ResellerReportDaily = {
  date?: string
  sales_toman?: number
  wholesale_toman?: number
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function displayName(row: ResellerReportRow): string {
  const name = `${String(row.first_name ?? "").trim()} ${String(row.last_name ?? "").trim()}`.trim()
  if (name) return name
  const u = String(row.username ?? "").trim()
  if (u) return u.startsWith("@") ? u : `@${u}`
  return `#${String(row.reseller_id ?? "—")}`
}

const USER_STATUS_KEYS = new Set(["pending", "approved", "rejected", "blocked"])

function statusBadgeVariant(st: string): "default" | "secondary" | "destructive" | "outline" {
  const s = st.toLowerCase()
  if (s === "approved") return "default"
  if (s === "pending") return "secondary"
  if (s === "rejected") return "destructive"
  if (s === "blocked") return "outline"
  return "outline"
}

function StatCard({
  label,
  value,
  suffix,
  hint,
}: {
  label: string
  value: number
  suffix?: string
  hint?: string
}) {
  const { isFa } = useDashLocale()
  return (
    <Card title={hint}>
      <CardHeader className="pb-2">
        <CardDescription>{label}</CardDescription>
        <CardTitle className="text-2xl tabular-nums">
          {formatNumber(value, isFa)}
          {suffix ? <span className="ms-1 text-sm font-normal text-muted-foreground">{suffix}</span> : null}
        </CardTitle>
      </CardHeader>
    </Card>
  )
}

const TABLE_COLS = ["14%", "9%", "8%", "8%", "11%", "11%", "9%", "9%", "9%", "12%"]

export function DashboardResellerReportsAdmin({
  stats,
  rows,
  daily,
  pagination,
  dashboardBaseUrl,
  searchQuery,
  windowDays,
  sortKey,
  onSearchChange,
  onWindowDaysChange,
  onSortChange,
  onPageChange,
  onPerPageChange,
  onOpenUserDetail,
  onImpersonateReseller,
  readOnlyAdminActions = false,
}: {
  stats: ResellerReportsStats | null
  rows: ResellerReportRow[]
  daily: ResellerReportDaily[]
  pagination: PaginationMeta | null
  dashboardBaseUrl: string
  searchQuery: string
  windowDays: number
  sortKey: string
  readOnlyAdminActions?: boolean
  onSearchChange: (q: string) => void
  onWindowDaysChange: (days: number) => void
  onSortChange: (sort: string) => void
  onPageChange?: (page: number) => void
  onPerPageChange?: (n: number) => void
  onOpenUserDetail: (id: number) => void
  onImpersonateReseller?: (id: number) => void
}) {
  const { isFa, ltrCell } = useDashLocale()
  const { t } = useTranslation()
  const chartPrimary = useChartPrimaryColor()
  const tr = (k: string, opts?: Record<string, string | number>) => t(`resellerReportsAdmin.${k}`, opts)

  const [searchDraft, setSearchDraft] = useState(searchQuery)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    setSearchDraft(searchQuery)
  }, [searchQuery])

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      if (searchDraft !== searchQuery) onSearchChange(searchDraft)
    }, 350)
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [searchDraft, searchQuery, onSearchChange])

  const loading = stats == null
  const summary = stats?.summary ?? {}
  const window = stats?.window_days ?? windowDays
  const backfillDone = stats?.backfill_done !== false
  const backupUrl = buildDashboardTabUrl(dashboardBaseUrl, "backup")

  const chartData = useMemo(
    () =>
      daily.map((pt) => ({
        ...pt,
        day: pt.date && pt.date.length >= 10 ? formatChartDayLabel(pt.date, isFa) : String(pt.date ?? ""),
        sales: num(pt.sales_toman),
        wholesale: num(pt.wholesale_toman),
      })),
    [daily, isFa]
  )

  const hasChart = chartData.some((d) => d.sales > 0 || d.wholesale > 0)

  const resellerStatusLabel = (raw: unknown) => {
    const st = String(raw ?? "").trim().toLowerCase()
    if (USER_STATUS_KEYS.has(st)) return t(`usersAdmin.status_${st}`)
    return String(raw ?? "").trim() || "—"
  }

  return (
    <DashPage className="w-full space-y-6">
      <DashboardPageHeader
        title={tr("title")}
        description={readOnlyAdminActions ? tr("downlineReportsHint") : tr("subtitle")}
      />

      {!backfillDone && !readOnlyAdminActions ? (
        <div className="flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
          <span>{tr("backfillHint")}</span>
          <Button type="button" variant="outline" size="sm" asChild>
            <a href={backupUrl}>{tr("openBackup")}</a>
          </Button>
        </div>
      ) : null}

      <p className="text-xs text-muted-foreground">{tr("marginDisclaimer")}</p>

      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="reseller-reports-window">{tr("windowDays")}</Label>
          <DashSelect
            id="reseller-reports-window"
            triggerClassName="w-[10rem]"
            value={String(windowDays)}
            onValueChange={(v) => onWindowDaysChange(Number(v))}
            options={[
              { value: "7", label: tr("window7") },
              { value: "30", label: tr("window30") },
              { value: "90", label: tr("window90") },
            ]}
          />
        </div>
        <div className="relative min-w-[12rem] flex-1 space-y-1.5">
          <Label htmlFor="reseller-reports-q">{tr("searchPlaceholder")}</Label>
          <Search className="pointer-events-none absolute start-3 top-[2.35rem] h-4 w-4 text-muted-foreground" />
          <Input
            id="reseller-reports-q"
            className="ps-9"
            value={searchDraft}
            onChange={(e) => setSearchDraft(e.target.value)}
            placeholder={tr("searchPlaceholder")}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="reseller-reports-sort">{tr("sortLabel")}</Label>
          <DashSelect
            id="reseller-reports-sort"
            triggerClassName="w-[12rem]"
            value={sortKey}
            onValueChange={onSortChange}
            options={[
              { value: "sales", label: tr("sortSales") },
              { value: "wholesale", label: tr("sortWholesale") },
              { value: "downline", label: tr("sortDownline") },
              { value: "balance", label: tr("sortBalance") },
              { value: "name", label: tr("sortName") },
            ]}
          />
        </div>
      </div>

      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          {Array.from({ length: 6 }).map((_, i) => (
            <Card key={i}>
              <CardHeader className="pb-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="mt-2 h-8 w-20" />
              </CardHeader>
            </Card>
          ))}
        </div>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <StatCard label={tr("kpiSales")} value={num(summary.total_sales_toman)} suffix={tr("currency")} hint={tr("kpiSalesHint")} />
            <StatCard label={tr("kpiWholesale")} value={num(summary.total_wholesale_toman)} suffix={tr("currency")} hint={tr("kpiWholesaleHint")} />
            <StatCard label={tr("kpiMargin")} value={num(summary.margin_est)} suffix={tr("currency")} hint={tr("kpiMarginHint")} />
            <StatCard label={tr("kpiResellers")} value={num(summary.reseller_count)} />
            <StatCard label={tr("kpiDownline")} value={num(summary.total_downline_users)} />
            <StatCard label={tr("kpiReceipts")} value={num(summary.total_receipts_toman)} suffix={tr("currency")} hint={tr("kpiReceiptsHint")} />
          </div>
          {summary.top_reseller && num(summary.top_reseller.sales_toman) > 0 ? (
            <p className="text-sm text-muted-foreground">
              {tr("topReseller", {
                name: String(summary.top_reseller.name ?? "—"),
                amount: formatNumber(num(summary.top_reseller.sales_toman), isFa),
                unit: tr("currency"),
              })}
            </p>
          ) : null}
        </>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tr("chartTitle")}</CardTitle>
          <CardDescription>
            {stats?.daily_scoped ? tr("chartSubtitleFiltered") : tr("chartSubtitle")}
          </CardDescription>
        </CardHeader>
        <CardContent className="h-[260px] w-full min-w-0">
          {loading ? (
            <Skeleton className="h-full w-full" />
          ) : hasChart ? (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData} margin={{ top: 8, right: 8, left: isFa ? 8 : 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="repSalesFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor={chartPrimary} stopOpacity={0.35} />
                    <stop offset="95%" stopColor={chartPrimary} stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                <XAxis dataKey="day" tick={{ fontSize: 11 }} />
                <YAxis
                  width={52}
                  tick={{ fontSize: 11 }}
                  tickFormatter={(v) => formatNumber(Number(v), isFa)}
                />
                <RechartsTooltip
                  content={({ active, payload }) => {
                    if (!active || !payload?.length) return null
                    const row = payload[0]?.payload as { date?: string; sales?: number; wholesale?: number }
                    return (
                      <div className="rounded-md border bg-background/95 px-2 py-1.5 text-xs shadow-sm">
                        <div className="font-medium">{row.date ?? ""}</div>
                        <div>
                          {tr("chartSales")}: {formatNumber(num(row.sales), isFa)} {tr("currency")}
                        </div>
                        <div className="text-muted-foreground">
                          {tr("chartWholesale")}: {formatNumber(num(row.wholesale), isFa)} {tr("currency")}
                        </div>
                      </div>
                    )
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="sales"
                  name={tr("chartSales")}
                  stroke={chartPrimary}
                  fill="url(#repSalesFill)"
                  strokeWidth={2}
                  dot={false}
                  isAnimationActive={false}
                />
                <Area
                  type="monotone"
                  dataKey="wholesale"
                  name={tr("chartWholesale")}
                  stroke="hsl(var(--muted-foreground))"
                  fill="transparent"
                  strokeWidth={2}
                  strokeDasharray="4 4"
                  dot={false}
                  isAnimationActive={false}
                />
              </AreaChart>
            </ResponsiveContainer>
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
              {tr("chartEmpty")}
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tr("tableTitle")}</CardTitle>
          <CardDescription>
            {tr("tableSubtitle", {
              n: formatNumber(pagination?.total ?? rows.length, isFa),
              days: formatNumber(window, isFa),
            })}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <Skeleton className="h-48 w-full" />
          ) : rows.length > 0 ? (
            <DashTableShell minWidth="56rem" colWidths={TABLE_COLS}>
              <thead>
                <tr className="bg-muted/40">
                  <DashTh>{tr("colName")}</DashTh>
                  <DashTh>{tr("colStatus")}</DashTh>
                  <DashTh title={tr("colDownlineHint")}>{tr("colDownline")}</DashTh>
                  <DashTh title={tr("colActiveSvcHint")}>{tr("colActiveSvc")}</DashTh>
                  <DashTh title={tr("colSalesHint")}>{tr("colSales")}</DashTh>
                  <DashTh title={tr("colWholesaleHint")}>{tr("colWholesale")}</DashTh>
                  <DashTh>{tr("colReceipts")}</DashTh>
                  <DashTh>{tr("colBalance")}</DashTh>
                  <DashTh title={tr("colMarginHint")}>{tr("colMargin")}</DashTh>
                  <DashTh>{tr("colActions")}</DashTh>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => {
                  const id = num(row.reseller_id)
                  return (
                    <tr key={id}>
                      <DashTd>
                        <div className="space-y-0.5">
                          <div className="truncate font-medium">{displayName(row)}</div>
                          <div className={cn("font-mono text-xs text-muted-foreground", ltrCell(""))} dir="ltr">
                            #{formatNumber(id, isFa)}
                          </div>
                        </div>
                      </DashTd>
                      <DashTd>
                        <Badge variant={statusBadgeVariant(String(row.status ?? ""))} className="font-normal">
                          {resellerStatusLabel(row.status)}
                        </Badge>
                      </DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(row.downline_users), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(row.active_services), isFa)}</DashTd>
                      <DashTd>
                        <div className="tabular-nums">{formatNumber(num(row.sales_toman), isFa)}</div>
                        <div className="text-xs text-muted-foreground">
                          {tr("salesCount", { count: formatNumber(num(row.sales_count), isFa) })}
                        </div>
                      </DashTd>
                      <DashTd>
                        <div className="tabular-nums">{formatNumber(num(row.wholesale_toman), isFa)}</div>
                        {num(row.wholesale_gb) > 0 ? (
                          <div className="text-xs text-muted-foreground">
                            {formatNumber(num(row.wholesale_gb), isFa)} {tr("colGbUnit")}
                          </div>
                        ) : null}
                      </DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(row.receipts_toman), isFa)}</DashTd>
                      <DashTd className="tabular-nums">{formatNumber(num(row.balance), isFa)}</DashTd>
                      <DashTd className="tabular-nums">
                        <span title={tr("colMarginHint")}>{formatNumber(num(row.margin_est), isFa)}</span>
                      </DashTd>
                      <DashTd>
                        <div className={dashActionsClass("gap-1")}>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onOpenUserDetail(id)}
                            aria-label={tr("manage")}
                          >
                            <KeyRound className="h-4 w-4" />
                          </Button>
                          {onImpersonateReseller ? (
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => onImpersonateReseller(id)}
                              aria-label={tr("impersonate")}
                            >
                              <LogIn className="h-4 w-4" />
                            </Button>
                          ) : null}
                        </div>
                      </DashTd>
                    </tr>
                  )
                })}
              </tbody>
            </DashTableShell>
          ) : (
            <p className="text-sm text-muted-foreground">{tr("empty")}</p>
          )}
          {pagination && onPageChange && onPerPageChange ? (
            <DataPagination
              meta={pagination}
              onPageChange={onPageChange}
              onPerPageChange={onPerPageChange}
            />
          ) : null}
        </CardContent>
      </Card>
    </DashPage>
  )
}
