"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { ArrowLeftRight, FileImage, ShoppingCart } from "lucide-react"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"
import { getAdminState } from "@/lib/dash-admin-mutate"
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import {
  readPaymentsViewFromUrl,
  writePaymentsViewToUrl,
  type PaymentsView,
} from "@/lib/payments-view-subtab"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import {
  DashboardReceiptsList,
  type ReceiptsListFilters,
} from "@/components/dashboard-receipts-list"
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

type AggregateRow = { status: string; count: number; sumAmount: number }

const DEFAULT_RECEIPT_FILTERS: ReceiptsListFilters = {
  q: "",
  status: "all",
  sort: "created_desc",
  dateFrom: "",
  dateTo: "",
  amountMin: "",
  amountMax: "",
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function parseAggregates(raw: unknown): AggregateRow[] {
  if (!Array.isArray(raw)) return []
  return raw
    .filter((row): row is Record<string, unknown> => !!row && typeof row === "object")
    .map((r) => ({
      status: String(r.status ?? ""),
      count: num(r.count),
      sumAmount: num(r.sumAmount ?? r.sum_amount),
    }))
}

function rowId(r: DashRecord): number {
  return num(r.id ?? r.receipt_id ?? r.transaction_id)
}

function rowStatus(r: DashRecord): string {
  return String(r.status ?? "")
}

function rowAmount(r: DashRecord): number {
  return num(r.amount ?? r.tx_amount)
}

function userLabel(r: DashRecord): string {
  const label = String(r.user_label ?? "").trim()
  if (label) return label
  const name = String(r.user_name ?? "").trim()
  if (name) return name
  const username = String(r.username ?? "").trim()
  if (username) return username.startsWith("@") ? username : `@${username}`
  return `#${String(r.user_id ?? "—")}`
}

function statusVariant(st: string): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved" || st === "delivered") return "default"
  if (st === "rejected" || st === "cancelled") return "destructive"
  return "secondary"
}

function pickPagination(data: DashRecord, key: string): PaginationMeta | null {
  const raw = data.pagination
  if (raw && typeof raw === "object") {
    return parsePaginationMeta((raw as DashRecord)[key])
  }
  return parsePaginationMeta(data[`${key}Pagination`])
}

const triggerClass =
  "gap-2 rounded-none border-b-2 border-transparent px-3 py-2 data-active:border-primary data-active:bg-transparent data-active:shadow-none"

export function PaymentsAdminClient() {
  const t = useTranslations("paymentsAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"
  const dashboardBaseUrl = `/${locale}/dashboard`
  const shell = useDashboardShellOptional()
  const isReseller = Boolean(shell?.isReseller)
  const actorPermissions = shell?.actorPermissions ?? {}
  const canReviewReceipts = !isReseller || actorPermissions["receipts.review"] === true

  const [view, setView] = useState<PaymentsView>(() =>
    typeof window === "undefined" ? "receipts" : readPaymentsViewFromUrl()
  )
  const [receipts, setReceipts] = useState<DashRecord[]>([])
  const [payments, setPayments] = useState<DashRecord[]>([])
  const [orders, setOrders] = useState<DashRecord[]>([])
  const [receiptAggregates, setReceiptAggregates] = useState<unknown>(null)
  const [paymentAggregates, setPaymentAggregates] = useState<unknown>(null)
  const [orderAggregates, setOrderAggregates] = useState<unknown>(null)
  const [settings, setSettings] = useState<DashRecord>({})
  const [receiptPagination, setReceiptPagination] = useState<PaginationMeta | null>(null)
  const [receiptPage, setReceiptPage] = useState(1)
  const [receiptPerPage, setReceiptPerPage] = useState(40)
  const [receiptFilters, setReceiptFilters] = useState<ReceiptsListFilters>(DEFAULT_RECEIPT_FILTERS)
  const [q, setQ] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const query: Record<string, string | number> = {
        payments_view: view,
      }
      if (view === "receipts") {
        query.receipts_page = receiptPage
        query.receipts_per_page = receiptPerPage
        if (receiptFilters.q.trim()) query.receipts_q = receiptFilters.q.trim()
        if (receiptFilters.status && receiptFilters.status !== "all") query.receipts_status = receiptFilters.status
        if (receiptFilters.sort) query.receipts_sort = receiptFilters.sort
        if (receiptFilters.dateFrom.trim()) query.receipts_date_from = receiptFilters.dateFrom.trim()
        if (receiptFilters.dateTo.trim()) query.receipts_date_to = receiptFilters.dateTo.trim()
        if (receiptFilters.amountMin.trim()) query.receipts_amount_min = receiptFilters.amountMin.trim()
        if (receiptFilters.amountMax.trim()) query.receipts_amount_max = receiptFilters.amountMax.trim()
      } else if (q.trim()) {
        query.payments_q = q.trim()
      }
      const data = await getAdminState("payments", query)
      setReceipts(Array.isArray(data.receipts) ? (data.receipts as DashRecord[]) : [])
      setPayments(Array.isArray(data.payments) ? (data.payments as DashRecord[]) : [])
      setOrders(Array.isArray(data.orders) ? (data.orders as DashRecord[]) : [])
      setReceiptAggregates(data.receiptAggregates ?? null)
      setPaymentAggregates(data.paymentAggregates ?? null)
      setOrderAggregates(data.orderAggregates ?? null)
      setSettings(
        data.settings && typeof data.settings === "object"
          ? (data.settings as DashRecord)
          : {}
      )
      setReceiptPagination(pickPagination(data, "receipts"))
    } catch {
      setError(t("loadError"))
    } finally {
      setLoading(false)
    }
  }, [q, receiptFilters, receiptPage, receiptPerPage, t, view])

  useEffect(() => {
    void load()
  }, [load])

  const onViewChange = (v: string) => {
    const next = v as PaymentsView
    setView(next)
    writePaymentsViewToUrl(next)
  }

  const aggregates = useMemo(() => {
    const raw =
      view === "receipts"
        ? receiptAggregates
        : view === "transactions"
          ? paymentAggregates
          : orderAggregates
    const rows = parseAggregates(raw)
    const totalCount = rows.reduce((s, r) => s + r.count, 0)
    const totalSum = rows.reduce((s, r) => s + r.sumAmount, 0)
    const pick = (st: string) => rows.find((r) => r.status === st) ?? { status: st, count: 0, sumAmount: 0 }
    return { totalCount, totalSum, approved: pick("approved"), pending: pick("pending"), rejected: pick("rejected") }
  }, [orderAggregates, paymentAggregates, receiptAggregates, view])

  const rows =
    view === "receipts" ? receipts : view === "transactions" ? payments : orders

  const emptyLabel =
    view === "receipts"
      ? t("emptyListReceipts")
      : view === "transactions"
        ? t("emptyListTransactions")
        : t("emptyListOrders")

  const subtitle =
    view === "receipts"
      ? t("subtitleReceipts")
      : view === "transactions"
        ? t("subtitleTransactions")
        : t("subtitleOrders")

  const statusLabel = (st: string) => {
    if (st === "pending") return t("statusPending")
    if (st === "processing") return t("statusProcessing")
    if (st === "approved") return t("statusApproved")
    if (st === "rejected") return t("statusRejected")
    if (st === "cancelled") return t("statusCancelled")
    return st || "—"
  }

  const onReceiptFiltersChange = (patch: Partial<ReceiptsListFilters>) => {
    setReceiptFilters((prev) => ({ ...prev, ...patch }))
    setReceiptPage(1)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
          {t("refresh")}
        </Button>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}

      <Tabs value={view} onValueChange={onViewChange} className="w-full">
        <TabsList
          variant="line"
          className={cn("h-auto w-full flex-wrap justify-start gap-1 bg-transparent p-0")}
        >
          <TabsTrigger value="receipts" className={triggerClass}>
            <FileImage className="size-4 shrink-0" aria-hidden />
            {t("tabReceipts")}
          </TabsTrigger>
          <TabsTrigger value="transactions" className={triggerClass}>
            <ArrowLeftRight className="size-4 shrink-0" aria-hidden />
            {t("tabTransactions")}
          </TabsTrigger>
          <TabsTrigger value="orders" className={triggerClass}>
            <ShoppingCart className="size-4 shrink-0" aria-hidden />
            {t("tabOrders")}
          </TabsTrigger>
        </TabsList>

        <TabsContent value="receipts" className="mt-4 space-y-4">
          <p className="text-sm text-muted-foreground">{subtitle}</p>
          {loading && receipts.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("empty")}</p>
          ) : (
            <DashboardReceiptsList
              receipts={receipts}
              receiptAggregates={receiptAggregates}
              settings={settings}
              pagination={receiptPagination}
              listFilters={receiptFilters}
              onListFiltersChange={onReceiptFiltersChange}
              dashboardBaseUrl={dashboardBaseUrl}
              onMutateSuccess={() => void load()}
              onPageChange={setReceiptPage}
              onPerPageChange={(n) => {
                setReceiptPerPage(n)
                setReceiptPage(1)
              }}
              isReseller={isReseller}
              canReviewReceipts={canReviewReceipts}
            />
          )}
        </TabsContent>

        {(["transactions", "orders"] as const).map((mode) => (
          <TabsContent key={mode} value={mode} className="mt-4 space-y-4">
            <p className="text-sm text-muted-foreground">{subtitle}</p>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <Card>
                <CardHeader className="pb-2">
                  <CardDescription>{t("statTotalCount")}</CardDescription>
                  <CardTitle className="text-2xl tabular-nums">
                    {formatNumber(aggregates.totalCount, isFa)}
                  </CardTitle>
                </CardHeader>
                <CardContent className="text-xs text-muted-foreground">
                  {t("statTotalSum")}: {formatNumber(aggregates.totalSum, isFa)}
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="pb-2">
                  <CardDescription>{t("statApprovedIncome")}</CardDescription>
                  <CardTitle className="text-2xl tabular-nums">
                    {formatNumber(aggregates.approved.sumAmount, isFa)}
                  </CardTitle>
                </CardHeader>
                <CardContent className="text-xs text-muted-foreground">
                  {t("statCount")}: {formatNumber(aggregates.approved.count, isFa)}
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="pb-2">
                  <CardDescription>{t("statPending")}</CardDescription>
                  <CardTitle className="text-2xl tabular-nums">
                    {formatNumber(aggregates.pending.count, isFa)}
                  </CardTitle>
                </CardHeader>
                <CardContent className="text-xs text-muted-foreground">
                  {t("statPendingSum")}: {formatNumber(aggregates.pending.sumAmount, isFa)}
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="pb-2">
                  <CardDescription>{t("statRejected")}</CardDescription>
                  <CardTitle className="text-2xl tabular-nums">
                    {formatNumber(aggregates.rejected.count, isFa)}
                  </CardTitle>
                </CardHeader>
                <CardContent className="text-xs text-muted-foreground">
                  {t("statRejectedSum")}: {formatNumber(aggregates.rejected.sumAmount, isFa)}
                </CardContent>
              </Card>
            </div>

            <div className="flex flex-wrap items-end gap-3">
              <div className="min-w-[16rem] flex-1 space-y-1.5">
                <Label htmlFor="payments-q">{t("searchPlaceholder")}</Label>
                <Input
                  id="payments-q"
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                  placeholder={t("searchPlaceholder")}
                />
              </div>
              <Button type="button" size="sm" disabled={loading} onClick={() => void load()}>
                {t("refresh")}
              </Button>
            </div>

            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("colTracking")}</TableHead>
                    <TableHead>{t("colUserName")}</TableHead>
                    <TableHead>{t("colAmount")}</TableHead>
                    <TableHead>{t("colStatus")}</TableHead>
                    <TableHead>{t("colCreated")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-muted-foreground">
                        {t("empty")}
                      </TableCell>
                    </TableRow>
                  ) : rows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-muted-foreground">
                        {emptyLabel}
                      </TableCell>
                    </TableRow>
                  ) : (
                    rows.map((r) => {
                      const id = rowId(r)
                      const st = rowStatus(r)
                      return (
                        <TableRow key={`${mode}-${id}`}>
                          <TableCell className="tabular-nums" dir="ltr">
                            #{id || "—"}
                          </TableCell>
                          <TableCell>{userLabel(r)}</TableCell>
                          <TableCell className="tabular-nums" dir="ltr">
                            {Math.abs(rowAmount(r)) < 0.009
                              ? t("amountFree")
                              : formatNumber(rowAmount(r), isFa)}
                          </TableCell>
                          <TableCell>
                            <Badge variant={statusVariant(st)}>{statusLabel(st)}</Badge>
                          </TableCell>
                          <TableCell className="text-muted-foreground">
                            {formatDateTime(
                              (r.created_at ?? r.created ?? null) as string | number | null,
                              isFa
                            )}
                          </TableCell>
                        </TableRow>
                      )
                    })
                  )}
                </TableBody>
              </Table>
            </div>
          </TabsContent>
        ))}
      </Tabs>
    </div>
  )
}
