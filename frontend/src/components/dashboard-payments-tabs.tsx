"use client"

import { useCallback, useEffect, useState } from "react"
import { useTranslations } from "next-intl"
import { ArrowLeftRight, FileImage, ShoppingCart } from "lucide-react"
import {
  DashboardReceiptsList,
  type PaymentsListFilters,
  type PaymentsListMode,
} from "@/components/dashboard-receipts-list"
import type { PaginationMeta } from "@/lib/dash-pagination"
import {
  readPaymentsViewFromUrl,
  writePaymentsViewToUrl,
  type PaymentsView,
} from "@/lib/payments-view-subtab"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"

type DashRecord = Record<string, unknown>

export const paymentsTabTriggerClass =
  "gap-2 rounded-none border-b-2 border-transparent px-3 py-2 data-active:border-primary data-active:bg-transparent data-active:shadow-none"

export function DashboardPaymentsTabs({
  paymentsView,
  onPaymentsViewChange,
  receipts,
  receiptAggregates,
  payments,
  paymentAggregates,
  orders,
  orderAggregates,
  settings,
  receiptsPagination,
  paymentsPagination,
  ordersPagination,
  isReseller = false,
  canReviewReceipts = true,
  listFilters,
  onListFiltersChange,
  dashboardBaseUrl = "",
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
  variant = "page",
  embedEmptyHint,
}: {
  paymentsView: PaymentsView
  onPaymentsViewChange: (view: PaymentsView) => void
  receipts: DashRecord[]
  receiptAggregates?: unknown
  payments: DashRecord[]
  paymentAggregates?: unknown
  orders: DashRecord[]
  orderAggregates?: unknown
  settings?: DashRecord
  receiptsPagination: PaginationMeta | null
  paymentsPagination: PaginationMeta | null
  ordersPagination: PaginationMeta | null
  isReseller?: boolean
  canReviewReceipts?: boolean
  listFilters: PaymentsListFilters
  onListFiltersChange: (patch: Partial<PaymentsListFilters>) => void
  dashboardBaseUrl?: string
  onMutateSuccess?: () => void
  onPageChange: (view: PaymentsView, page: number) => void
  onPerPageChange: (view: PaymentsView, perPage: number) => void
  variant?: "page" | "userEmbed"
  embedEmptyHint?: string
}) {
  const { dir } = useDashLocale()
  const t = useTranslations("paymentsAdmin")

  const [view, setView] = useState<PaymentsView>(() => paymentsView || readPaymentsViewFromUrl())

  useEffect(() => {
    setView(paymentsView || readPaymentsViewFromUrl())
  }, [paymentsView])

  const onSubViewChange = useCallback(
    (v: string) => {
      const next = v as PaymentsView
      setView(next)
      writePaymentsViewToUrl(next)
      onPaymentsViewChange(next)
    },
    [onPaymentsViewChange]
  )

  const subtitleFor = (mode: PaymentsListMode) => {
    if (mode === "receipts") return t("subtitleReceipts")
    if (mode === "transactions") return t("subtitleTransactions")
    return t("subtitleOrders")
  }

  const listFor = (mode: PaymentsListMode) => {
    if (mode === "receipts") return receipts
    if (mode === "transactions") return payments
    return orders
  }

  const aggregatesFor = (mode: PaymentsListMode) => {
    if (mode === "receipts") return receiptAggregates
    if (mode === "transactions") return paymentAggregates
    return orderAggregates
  }

  const paginationFor = (mode: PaymentsListMode) => {
    if (mode === "receipts") return receiptsPagination
    if (mode === "transactions") return paymentsPagination
    return ordersPagination
  }

  const renderList = (mode: PaymentsListMode) => (
    <DashboardReceiptsList
      variant={variant}
      listMode={mode}
      receipts={listFor(mode)}
      receiptAggregates={aggregatesFor(mode)}
      settings={settings}
      pagination={paginationFor(mode)}
      isReseller={isReseller}
      canReviewReceipts={canReviewReceipts && mode === "receipts"}
      listFilters={listFilters}
      onListFiltersChange={onListFiltersChange}
      dashboardBaseUrl={dashboardBaseUrl}
      onMutateSuccess={onMutateSuccess}
      onPageChange={(p) => onPageChange(mode, p)}
      onPerPageChange={(n) => onPerPageChange(mode, n)}
      embedEmptyHint={embedEmptyHint}
    />
  )

  return (
    <Tabs dir={dir} value={view} onValueChange={onSubViewChange} className="w-full">
      <TabsList
        variant="line"
        className={cn("h-auto w-full flex-wrap justify-start gap-1 bg-transparent p-0 text-start")}
      >
        <TabsTrigger value="receipts" className={paymentsTabTriggerClass}>
          <FileImage className="size-4 shrink-0" aria-hidden />
          {t("tabReceipts")}
        </TabsTrigger>
        <TabsTrigger value="transactions" className={paymentsTabTriggerClass}>
          <ArrowLeftRight className="size-4 shrink-0" aria-hidden />
          {t("tabTransactions")}
        </TabsTrigger>
        <TabsTrigger value="orders" className={paymentsTabTriggerClass}>
          <ShoppingCart className="size-4 shrink-0" aria-hidden />
          {t("tabOrders")}
        </TabsTrigger>
      </TabsList>

      <TabsContent value="receipts" className="mt-4 space-y-2 text-start">
        <p className="text-sm text-muted-foreground">{subtitleFor("receipts")}</p>
        {renderList("receipts")}
      </TabsContent>
      <TabsContent value="transactions" className="mt-4 space-y-2 text-start">
        <p className="text-sm text-muted-foreground">{subtitleFor("transactions")}</p>
        {renderList("transactions")}
      </TabsContent>
      <TabsContent value="orders" className="mt-4 space-y-2 text-start">
        <p className="text-sm text-muted-foreground">{subtitleFor("orders")}</p>
        {renderList("orders")}
      </TabsContent>
    </Tabs>
  )
}
