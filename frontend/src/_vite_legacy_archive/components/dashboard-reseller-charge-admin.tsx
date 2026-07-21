"use client"

import { useState } from "react"
import { useTranslation } from "react-i18next"

import { DashPage } from "@/components/dash-page"
import { DashSelect } from "@/components/dash-select"
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
import { adminMutateErrorText, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DataPagination } from "@/components/data-pagination"
import { useDashLocale } from "@/lib/dash-locale-context"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

export function DashboardResellerChargeAdmin({
  actorBalance,
  customerCharges = [],
  customerChargesPagination = null,
  chargeTypeFilter = "all",
  chargeDateFrom = "",
  chargeDateTo = "",
  onChargeTypeFilterChange,
  onChargeDateFromChange,
  onChargeDateToChange,
  onCustomerChargesPageChange,
  onCustomerChargesPerPageChange,
  onMutateSuccess,
}: {
  actorBalance?: number
  customerCharges?: DashRecord[]
  customerChargesPagination?: PaginationMeta | null
  chargeTypeFilter?: string
  chargeDateFrom?: string
  chargeDateTo?: string
  onChargeTypeFilterChange?: (type: string) => void
  onChargeDateFromChange?: (value: string) => void
  onChargeDateToChange?: (value: string) => void
  onCustomerChargesPageChange?: (page: number) => void
  onCustomerChargesPerPageChange?: (perPage: number) => void
  onMutateSuccess?: () => void
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tc = (k: string, opts?: Record<string, string | number>) => t(`resellerCharge.${k}`, opts)
  const tf = (k: string, opts?: Record<string, string | number>) => t(`resellerFinance.${k}`, opts)

  const [topUpAmount, setTopUpAmount] = useState("")
  const [topUpBusy, setTopUpBusy] = useState(false)
  const [topUpMsg, setTopUpMsg] = useState<string | null>(null)

  async function onTopUp() {
    const raw = topUpAmount.replace(/,/g, ".").trim()
    const amt = parseFloat(raw)
    if (!Number.isFinite(amt) || amt <= 0) {
      setTopUpMsg(tf("topUpInvalid"))
      return
    }
    setTopUpBusy(true)
    setTopUpMsg(null)
    try {
      const res = await postAdminMutate("reseller_wallet_topup_checkout", { amount: amt })
      if (!res.ok) {
        setTopUpMsg(adminMutateErrorText(res, tf("topUpError")))
        return
      }
      const tid = (res as { transaction_id?: number }).transaction_id
      const botHint = (res as { notify_sent?: boolean }).notify_sent ? tf("topUpSentBot") : tf("topUpNoBot")
      setTopUpMsg(
        tf("topUpQueued", {
          id: tid != null && tid > 0 ? formatNumber(tid, isFa) : "—",
          bot: botHint,
        })
      )
      setTopUpAmount("")
      onMutateSuccess?.()
    } finally {
      setTopUpBusy(false)
    }
  }

  return (
    <DashPage>
      <DashboardPageHeader title={tc("title")} description={tc("subtitle")} />

      {typeof actorBalance === "number" ? (
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">{tf("balanceTitle")}</CardTitle>
            <CardDescription>{tc("balanceHint")}</CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-semibold tabular-nums">{formatNumber(actorBalance, isFa)}</p>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tf("topUpTitle")}</CardTitle>
          <CardDescription>{tf("topUpHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {topUpMsg ? (
            <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">{topUpMsg}</p>
          ) : null}
          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-2">
              <Label htmlFor="reseller-topup-amt">{tf("topUpAmount")}</Label>
              <Input
                id="reseller-topup-amt"
                dir="ltr"
                inputMode="decimal"
                value={topUpAmount}
                onChange={(e) => setTopUpAmount(e.target.value)}
                placeholder={tf("topUpPlaceholder")}
              />
            </div>
            <Button type="button" disabled={topUpBusy} onClick={() => void onTopUp()}>
              {topUpBusy ? tc("busy") : tf("topUpSubmit")}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tc("customerChargesTitle")}</CardTitle>
          <CardDescription>{tc("customerChargesHint")}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex flex-wrap items-end gap-3">
            <div className="space-y-2">
              <Label htmlFor="reseller-charge-type">{tc("filterType")}</Label>
              <DashSelect
                id="reseller-charge-type"
                triggerClassName="w-[11rem]"
                value={chargeTypeFilter || "all"}
                onValueChange={(v) => onChargeTypeFilterChange?.(v)}
                options={[
                  { value: "all", label: tc("filterTypeAll") },
                  { value: "purchase", label: tc("filterTypePurchase") },
                  { value: "renew", label: tc("filterTypeRenew") },
                  { value: "volume", label: tc("filterTypeVolume") },
                  { value: "topup", label: tc("filterTypeTopup") },
                ]}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="reseller-charge-date-from">{tc("filterDateFrom")}</Label>
              <Input
                id="reseller-charge-date-from"
                type="date"
                dir="ltr"
                value={chargeDateFrom}
                onChange={(e) => onChargeDateFromChange?.(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="reseller-charge-date-to">{tc("filterDateTo")}</Label>
              <Input
                id="reseller-charge-date-to"
                type="date"
                dir="ltr"
                value={chargeDateTo}
                onChange={(e) => onChargeDateToChange?.(e.target.value)}
              />
            </div>
          </div>
          {customerCharges.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tc("customerChargesEmpty")}</p>
          ) : (
            <ul className="space-y-2">
              {customerCharges.map((row) => {
                const id = num(row.id)
                const amt = num(row.amount)
                const label = String(row.customer_label ?? "")
                const chargeType = String(row.charge_type ?? "purchase")
                const typeKey = ["purchase", "renew", "volume", "topup"].includes(chargeType)
                  ? chargeType
                  : "purchase"
                const createdAt = String(row.charge_created_at ?? row.created_at ?? "")
                const planLabel = String(row.charge_plan_label ?? "")
                return (
                  <li
                    key={id}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                  >
                    <div className="min-w-0 space-y-0.5">
                      <span className="font-medium tabular-nums text-destructive">
                        {tc(`chargeType_${typeKey}`, {
                          amount: formatNumber(amt, isFa),
                          name: label || `#${num(row.customer_svp_user_id)}`,
                        })}
                      </span>
                      <div className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                        {createdAt ? (
                          <span>{formatDateTime(createdAt, isFa)}</span>
                        ) : null}
                        {planLabel ? <span>{planLabel}</span> : null}
                      </div>
                    </div>
                    <span className="text-xs text-muted-foreground">#{formatNumber(id, isFa)}</span>
                  </li>
                )
              })}
            </ul>
          )}
          {customerChargesPagination && onCustomerChargesPageChange ? (
            <DataPagination
              meta={customerChargesPagination}
              onPageChange={onCustomerChargesPageChange}
              onPerPageChange={onCustomerChargesPerPageChange ?? (() => {})}
            />
          ) : null}
        </CardContent>
      </Card>
    </DashPage>
  )
}
