"use client"

import { useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { adminMutateErrorText, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import { parsePaginationMeta } from "@/lib/dash-pagination"
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
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DataPagination } from "@/components/data-pagination"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

export function ResellerChargeAdminClient() {
  const tc = useTranslations("resellerCharge")
  const tf = useTranslations("resellerFinance")
  const locale = useLocale()
  const isFa = locale === "fa"

  const { data, loading, reload, listQuery, patchQuery } = useAdminTabState("reseller_charge")

  const chargeTypeFilter = String(listQuery.customerChargesType ?? "all")
  const chargeDateFrom = String(listQuery.customerChargesDateFrom ?? "")
  const chargeDateTo = String(listQuery.customerChargesDateTo ?? "")

  const userBal = data.user && typeof data.user === "object" ? (data.user as DashRecord) : {}
  const actorBalance = typeof userBal.balance === "number" ? userBal.balance : undefined

  const customerCharges = Array.isArray(data.resellerCustomerCharges)
    ? (data.resellerCustomerCharges as DashRecord[])
    : []
  const customerChargesPagination = parsePaginationMeta(data.resellerCustomerChargesPagination)

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
      reload()
    } finally {
      setTopUpBusy(false)
    }
  }

  const setChargeType = (type: string) => {
    patchQuery({
      customerChargesType: type === "all" ? "" : type,
      customerChargesPage: "1",
    })
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
                onValueChange={setChargeType}
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
                onChange={(e) =>
                  patchQuery({
                    customerChargesDateFrom: e.target.value,
                    customerChargesPage: "1",
                  })
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="reseller-charge-date-to">{tc("filterDateTo")}</Label>
              <Input
                id="reseller-charge-date-to"
                type="date"
                dir="ltr"
                value={chargeDateTo}
                onChange={(e) =>
                  patchQuery({
                    customerChargesDateTo: e.target.value,
                    customerChargesPage: "1",
                  })
                }
              />
            </div>
          </div>

          {loading ? <p className="text-sm text-muted-foreground">{tc("busy")}</p> : null}

          {customerCharges.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tc("customerChargesEmpty")}</p>
          ) : (
            <ul className="space-y-2">
              {customerCharges.map((row) => {
                const id = num(row.id)
                const amt = num(row.amount)
                const label = String(row.customer_label ?? row.display_name ?? "")
                const chargeType = String(row.charge_type ?? row.type ?? "purchase")
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
                        {createdAt ? <span>{formatDateTime(createdAt, isFa)}</span> : null}
                        {planLabel ? <span>{planLabel}</span> : null}
                      </div>
                    </div>
                    <span className="text-xs text-muted-foreground">#{formatNumber(id, isFa)}</span>
                  </li>
                )
              })}
            </ul>
          )}

          {customerChargesPagination ? (
            <DataPagination
              meta={customerChargesPagination}
              onPageChange={(p) => patchQuery({ customerChargesPage: String(Math.max(1, p)) })}
              onPerPageChange={(n) =>
                patchQuery({
                  customerChargesPerPage: String(Math.max(1, n)),
                  customerChargesPage: "1",
                })
              }
            />
          ) : null}
        </CardContent>
      </Card>
    </DashPage>
  )
}
