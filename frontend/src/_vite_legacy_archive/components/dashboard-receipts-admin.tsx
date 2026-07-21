"use client"

import { useTranslation } from "react-i18next"

import { DashboardPageHeader } from "@/components/dashboard-page-header"
import {
  DashboardReceiptsList,
  type ReceiptsListFilters,
} from "@/components/dashboard-receipts-list"
import { DashPage } from "@/components/dash-page"
import type { PaginationMeta } from "@/lib/dash-pagination"

type DashRecord = Record<string, unknown>

export type { ReceiptsListFilters } from "@/components/dashboard-receipts-list"

export function DashboardReceiptsAdmin({
  receipts,
  receiptAggregates,
  settings,
  pagination,
  isReseller = false,
  canReviewReceipts = true,
  listFilters,
  onListFiltersChange,
  dashboardBaseUrl = "",
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  receipts: DashRecord[]
  receiptAggregates?: unknown
  settings?: DashRecord
  pagination: PaginationMeta | null
  isReseller?: boolean
  canReviewReceipts?: boolean
  listFilters: ReceiptsListFilters
  onListFiltersChange: (patch: Partial<ReceiptsListFilters>) => void
  dashboardBaseUrl?: string
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { t } = useTranslation()
  const tp = (k: string) => t(`receiptsAdmin.${k}`)

  return (
    <DashPage data-testid="dash-receipts-tab">
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />

      <DashboardReceiptsList
        variant="page"
        receipts={receipts}
        receiptAggregates={receiptAggregates}
        settings={settings}
        pagination={pagination}
        isReseller={isReseller}
        canReviewReceipts={canReviewReceipts}
        listFilters={listFilters}
        onListFiltersChange={onListFiltersChange}
        dashboardBaseUrl={dashboardBaseUrl}
        onMutateSuccess={onMutateSuccess}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />
    </DashPage>
  )
}
