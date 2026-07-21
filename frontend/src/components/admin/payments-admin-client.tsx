"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"
import { DashboardPaymentsTabs } from "@/components/dashboard-payments-tabs"
import type { PaymentsListFilters } from "@/components/dashboard-receipts-list"
import { getAdminState } from "@/lib/dash-admin-mutate"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import {
  readPaymentsFiltersFromUrl,
  readPaymentsViewFromUrl,
  writePaymentsViewToUrl,
  type PaymentsView,
} from "@/lib/payments-view-subtab"
import { Button } from "@/components/ui/button"

type DashRecord = Record<string, unknown>

const DEFAULT_FILTERS: PaymentsListFilters = {
  q: "",
  status: "all",
  type: "",
  method: "",
  sort: "created_desc",
  dateFrom: "",
  dateTo: "",
  amountMin: "",
  amountMax: "",
}

function pickPagination(data: DashRecord, key: string): PaginationMeta | null {
  const raw = data.pagination
  if (raw && typeof raw === "object") {
    return parsePaginationMeta((raw as DashRecord)[key])
  }
  return parsePaginationMeta(data[`${key}Pagination`])
}

export function PaymentsAdminClient() {
  const t = useTranslations("paymentsAdmin")
  const locale = useLocale()
  const dashboardBaseUrl = `/${locale}/dashboard`
  const shell = useDashboardShellOptional()
  const isReseller = Boolean(shell?.isReseller)
  const actorPermissions = shell?.actorPermissions ?? {}
  const canReviewReceipts = !isReseller || actorPermissions["receipts.review"] === true

  const [view, setView] = useState<PaymentsView>(() =>
    typeof window === "undefined" ? "receipts" : readPaymentsViewFromUrl()
  )
  const urlFilters = useMemo(
    () => (typeof window === "undefined" ? null : readPaymentsFiltersFromUrl(readPaymentsViewFromUrl())),
    []
  )
  const [receipts, setReceipts] = useState<DashRecord[]>([])
  const [payments, setPayments] = useState<DashRecord[]>([])
  const [orders, setOrders] = useState<DashRecord[]>([])
  const [receiptAggregates, setReceiptAggregates] = useState<unknown>(null)
  const [paymentAggregates, setPaymentAggregates] = useState<unknown>(null)
  const [orderAggregates, setOrderAggregates] = useState<unknown>(null)
  const [settings, setSettings] = useState<DashRecord>({})
  const [receiptPagination, setReceiptPagination] = useState<PaginationMeta | null>(null)
  const [txPagination, setTxPagination] = useState<PaginationMeta | null>(null)
  const [ordersPagination, setOrdersPagination] = useState<PaginationMeta | null>(null)
  const [receiptPage, setReceiptPage] = useState(1)
  const [receiptPerPage, setReceiptPerPage] = useState(40)
  const [txPage, setTxPage] = useState(1)
  const [txPerPage, setTxPerPage] = useState(40)
  const [ordersPage, setOrdersPage] = useState(1)
  const [ordersPerPage, setOrdersPerPage] = useState(40)
  const [receiptFilters, setReceiptFilters] = useState<PaymentsListFilters>(() =>
    urlFilters ? { ...DEFAULT_FILTERS, ...urlFilters } : DEFAULT_FILTERS
  )
  const [txFilters, setTxFilters] = useState<PaymentsListFilters>(() =>
    urlFilters ? { ...DEFAULT_FILTERS, ...urlFilters } : DEFAULT_FILTERS
  )
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  const appendPaymentFilters = useCallback(
    (query: Record<string, string | number>, filters: PaymentsListFilters, prefix: "receipts" | "payments") => {
      if (filters.q.trim()) query[`${prefix}_q`] = filters.q.trim()
      if (filters.status && filters.status !== "all") query[`${prefix}_status`] = filters.status
      if (filters.sort) query[`${prefix}_sort`] = filters.sort
      if (filters.dateFrom.trim()) query[`${prefix}_date_from`] = filters.dateFrom.trim()
      if (filters.dateTo.trim()) query[`${prefix}_date_to`] = filters.dateTo.trim()
      if (filters.amountMin.trim()) query[`${prefix}_amount_min`] = filters.amountMin.trim()
      if (filters.amountMax.trim()) query[`${prefix}_amount_max`] = filters.amountMax.trim()
      if (filters.type.trim()) query[`${prefix}_type`] = filters.type.trim()
      if (filters.method.trim()) query[`${prefix}_method`] = filters.method.trim()
    },
    []
  )

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
        appendPaymentFilters(query, receiptFilters, "receipts")
      } else if (view === "transactions") {
        query.transactions_page = txPage
        query.transactions_per_page = txPerPage
        appendPaymentFilters(query, txFilters, "payments")
      } else {
        query.orders_page = ordersPage
        query.orders_per_page = ordersPerPage
        appendPaymentFilters(query, txFilters, "payments")
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
      setTxPagination(pickPagination(data, "transactions") ?? pickPagination(data, "payments"))
      setOrdersPagination(pickPagination(data, "orders"))
    } catch {
      setError(t("loadError"))
    } finally {
      setLoading(false)
    }
  }, [
    appendPaymentFilters,
    ordersPage,
    ordersPerPage,
    receiptFilters,
    receiptPage,
    receiptPerPage,
    t,
    txFilters,
    txPage,
    txPerPage,
    view,
  ])

  useEffect(() => {
    void load()
  }, [load])

  const onViewChange = (v: PaymentsView) => {
    setView(v)
    writePaymentsViewToUrl(v)
  }

  const onReceiptFiltersChange = (patch: Partial<PaymentsListFilters>) => {
    setReceiptFilters((prev) => ({ ...prev, ...patch }))
    setReceiptPage(1)
  }

  const onTxFiltersChange = (patch: Partial<PaymentsListFilters>) => {
    setTxFilters((prev) => ({ ...prev, ...patch }))
    setTxPage(1)
    setOrdersPage(1)
  }

  const listFilters = view === "receipts" ? receiptFilters : txFilters
  const onListFiltersChange = view === "receipts" ? onReceiptFiltersChange : onTxFiltersChange

  const onPageChange = (tabView: PaymentsView, page: number) => {
    if (tabView === "receipts") setReceiptPage(page)
    else if (tabView === "transactions") setTxPage(page)
    else setOrdersPage(page)
  }

  const onPerPageChange = (tabView: PaymentsView, perPage: number) => {
    if (tabView === "receipts") {
      setReceiptPerPage(perPage)
      setReceiptPage(1)
    } else if (tabView === "transactions") {
      setTxPerPage(perPage)
      setTxPage(1)
    } else {
      setOrdersPerPage(perPage)
      setOrdersPage(1)
    }
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

      <DashboardPaymentsTabs
        paymentsView={view}
        onPaymentsViewChange={onViewChange}
        receipts={receipts}
        receiptAggregates={receiptAggregates}
        payments={payments}
        paymentAggregates={paymentAggregates}
        orders={orders}
        orderAggregates={orderAggregates}
        settings={settings}
        receiptsPagination={receiptPagination}
        paymentsPagination={txPagination}
        ordersPagination={ordersPagination}
        isReseller={isReseller}
        canReviewReceipts={canReviewReceipts}
        listFilters={listFilters}
        onListFiltersChange={onListFiltersChange}
        dashboardBaseUrl={dashboardBaseUrl}
        onMutateSuccess={() => void load()}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
        variant="page"
      />
    </div>
  )
}
