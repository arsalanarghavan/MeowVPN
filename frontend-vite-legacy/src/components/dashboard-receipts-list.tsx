"use client"

import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useTranslation } from "react-i18next"

import { DashboardDateTimePicker } from "@/components/dashboard-datetime-picker"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DataPagination } from "@/components/data-pagination"
import {
  adminMutateErrorText,
  postAdminMutate,
  type AdminMutateResult,
} from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import { receiptSelectedService } from "@/lib/format-receipt"
import { DashSelect } from "@/components/dash-select"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>

export type ReceiptAggregateRow = {
  status: string
  count: number
  sumAmount: number
}

export type ReceiptsListFilters = {
  q: string
  status: string
  sort: string
  dateFrom: string
  dateTo: string
  amountMin: string
  amountMax: string
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function parseAggregates(raw: unknown): ReceiptAggregateRow[] {
  if (!Array.isArray(raw)) return []
  const out: ReceiptAggregateRow[] = []
  for (const row of raw) {
    if (!row || typeof row !== "object") continue
    const r = row as Record<string, unknown>
    out.push({
      status: String(r.status ?? ""),
      count: num(r.count),
      sumAmount: num(r.sumAmount ?? r.sum_amount),
    })
  }
  return out
}

function receiptStatusVariant(st: string): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved") return "default"
  if (st === "rejected") return "destructive"
  return "secondary"
}

function receiptStatusLabel(st: string, tp: (k: string) => string): string {
  if (st === "pending") return tp("statusPending")
  if (st === "processing") return tp("statusProcessing")
  if (st === "approved") return tp("statusApproved")
  if (st === "rejected") return tp("statusRejected")
  return st || "—"
}

function formatReceiptAmount(amount: number, isFa: boolean, tp: (k: string) => string): string {
  if (Math.abs(amount) < 0.009) {
    return tp("amountFree")
  }
  return formatNumber(amount, isFa)
}

function receiptMutateDetailMessage(raw: string, tp: (k: string) => string): string {
  if (raw === "bad_amount") return tp("badAmount")
  if (raw === "invalid_html_response") return tp("invalidHtmlResponse")
  if (raw === "server_error" || raw === "response_encode_failed") return tp("serverError")
  if (raw.startsWith("bad_json")) return tp("invalidHtmlResponse")
  return raw
}

function formatReceiptMutateFeedback(res: AdminMutateResult, tp: (k: string) => string): string | null {
  const d = res.data
  if (!res.ok) {
    const raw = adminMutateErrorText(res, tp("mutateError"))
    const detail = receiptMutateDetailMessage(raw, tp)
    return `${tp("mutateError")}: ${detail}`
  }
  const msgKey = typeof res.message === "string" ? res.message : ""
  const msgMap: Record<string, string> = {
    topup_delta_applied: tp("amountTopupAdjusted"),
    amount_updated: tp("amountUpdated"),
    amount_unchanged: tp("amountUnchanged"),
    commission_may_need_manual_review: tp("commissionReviewWarning"),
  }
  if (msgKey && msgMap[msgKey]) {
    const warnings = (res as { warnings?: unknown }).warnings
    if (Array.isArray(warnings) && warnings.includes("commission_may_need_manual_review")) {
      return `${msgMap[msgKey]} ${tp("commissionReviewWarning")}`
    }
    return msgMap[msgKey]
  }
  if (d && typeof d === "object" && "ok" in d && (d as { ok?: unknown }).ok === false) {
    const rec = d as Record<string, unknown>
    const detail =
      typeof rec.provision_error === "string" && rec.provision_error
        ? rec.provision_error
        : typeof rec.message === "string" && rec.message
          ? rec.message
          : typeof rec.reason === "string"
            ? rec.reason
            : JSON.stringify(d)
    return `${tp("approveFailed")}: ${detail}`
  }
  if (d && typeof d === "object" && (d as { reason?: unknown }).reason === "processing") {
    return tp("statusProcessing")
  }
  return null
}

function parseRejectReasons(settings: DashRecord | undefined): string[] {
  const raw = settings?.receipt_reject_reasons
  if (Array.isArray(raw)) {
    return raw.map((x) => String(x ?? "").trim()).filter(Boolean)
  }
  if (typeof raw === "string" && raw.trim()) {
    try {
      const j = JSON.parse(raw) as unknown
      if (Array.isArray(j)) return j.map((x) => String(x ?? "").trim()).filter(Boolean)
    } catch {
      return raw.split(/\r?\n/).map((x) => x.trim()).filter(Boolean)
    }
  }
  return []
}

function receiptUserLabel(r: DashRecord): string {
  const label = String(r.user_label ?? "").trim()
  if (label) return label
  const name = String(r.user_name ?? "").trim()
  if (name) return name
  const username = String(r.username ?? "").trim()
  if (username) return username.startsWith("@") ? username : `@${username}`
  return `#${String(r.user_id ?? "—")}`
}

export function DashboardReceiptsList({
  receipts,
  receiptAggregates,
  settings,
  pagination,
  variant = "page",
  isReseller = false,
  canReviewReceipts = true,
  listFilters,
  onListFiltersChange,
  dashboardBaseUrl = "",
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
  embedTitle,
  embedEmptyHint,
}: {
  receipts: DashRecord[]
  receiptAggregates?: unknown
  settings?: DashRecord
  pagination: PaginationMeta | null
  variant?: "page" | "userEmbed"
  isReseller?: boolean
  canReviewReceipts?: boolean
  listFilters: ReceiptsListFilters
  onListFiltersChange: (patch: Partial<ReceiptsListFilters>) => void
  dashboardBaseUrl?: string
  onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
  embedTitle?: string
  embedEmptyHint?: string
}) {
  const { isFa } = useDashLocale()
  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`receiptsAdmin.${k}`, opts)
  const tw = (k: string, opts?: Record<string, string | number>) => t(`resellerFinance.${k}`, opts)

  const hideUserColumns = variant === "userEmbed"
  const compactTableOnly = variant === "userEmbed"
  const showFullReviewUi = canReviewReceipts

  const aggregates = useMemo(() => parseAggregates(receiptAggregates), [receiptAggregates])

  const aggByStatus = useMemo(() => {
    const m = new Map<string, { count: number; sum: number }>()
    let totalCount = 0
    let totalSum = 0
    for (const a of aggregates) {
      totalCount += a.count
      totalSum += a.sumAmount
      m.set(a.status, { count: a.count, sum: a.sumAmount })
    }
    return { m, totalCount, totalSum }
  }, [aggregates])

  const approved = aggByStatus.m.get("approved") ?? { count: 0, sum: 0 }
  const pendingRaw = aggByStatus.m.get("pending") ?? { count: 0, sum: 0 }
  const processingRaw = aggByStatus.m.get("processing") ?? { count: 0, sum: 0 }
  const pending = {
    count: pendingRaw.count + processingRaw.count,
    sum: pendingRaw.sum + processingRaw.sum,
  }
  const rejected = aggByStatus.m.get("rejected") ?? { count: 0, sum: 0 }

  const approvedReceipts = useMemo(() => {
    return receipts.filter((r) => String(r.status ?? "").toLowerCase() === "approved")
  }, [receipts])

  const [searchDraft, setSearchDraft] = useState(listFilters.q)
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [alertText, setAlertText] = useState<string | null>(null)
  const [amountTarget, setAmountTarget] = useState<DashRecord | null>(null)
  const [amountDraft, setAmountDraft] = useState("")
  const [rejectTarget, setRejectTarget] = useState<DashRecord | null>(null)
  const [rejectReason, setRejectReason] = useState("")
  const [rejectCustomReason, setRejectCustomReason] = useState("")
  const [previewReceipt, setPreviewReceipt] = useState<DashRecord | null>(null)

  const rejectReasons = useMemo(() => parseRejectReasons(settings), [settings])
  const settingsUrl = `${dashboardBaseUrl.replace(/\/?$/, "")}/site_settings/#whitelabel`

  useEffect(() => {
    setSearchDraft(listFilters.q)
  }, [listFilters.q])

  useEffect(() => {
    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current)
    searchDebounceRef.current = setTimeout(() => {
      const next = searchDraft.trim()
      if (next !== listFilters.q.trim()) {
        onListFiltersChange({ q: next })
      }
    }, 500)
    return () => {
      if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current)
    }
  }, [searchDraft, listFilters.q, onListFiltersChange])

  const updateReceipt = useCallback(
    async (receiptId: number, payload: Record<string, unknown>) => {
      setBusyId(receiptId)
      setAlertText(null)
      try {
        const status = typeof payload.status === "string" ? payload.status : ""
        const rejectReason =
          typeof payload.reject_reason === "string" ? payload.reject_reason : undefined
        const res =
          status === "approved"
            ? await postAdminMutate("receipt_action", {
                receipt_id: receiptId,
                action: "approve",
              })
            : status === "rejected"
              ? await postAdminMutate("receipt_action", {
                  receipt_id: receiptId,
                  action: "reject",
                  ...(rejectReason ? { reject_reason: rejectReason } : {}),
                })
              : status !== ""
                ? await postAdminMutate("receipt_set_status", {
                    receipt_id: receiptId,
                    status,
                    ...(rejectReason ? { reject_reason: rejectReason } : {}),
                  })
                : await postAdminMutate("receipt_update", {
                    receipt_id: receiptId,
                    ...payload,
                  })
        const fb = formatReceiptMutateFeedback(res, tp)
        if (fb) setAlertText(fb)
        if (!fb) {
          onMutateSuccess?.()
        }
      } finally {
        setBusyId(null)
      }
    },
    [onMutateSuccess, tp]
  )

  const openAmountDialog = (r: DashRecord) => {
    setAmountTarget(r)
    setAmountDraft(String(r.amount ?? ""))
  }

  const saveAmount = async () => {
    if (!amountTarget) return
    const id = num(amountTarget.id)
    await updateReceipt(id, { amount: amountDraft })
    setAmountTarget(null)
  }

  const openRejectDialog = (r: DashRecord) => {
    setRejectTarget(r)
    setRejectReason(rejectReasons[0] ?? "")
    setRejectCustomReason("")
  }

  const confirmReject = async () => {
    if (!rejectTarget) return
    const reason = rejectCustomReason.trim() || rejectReason.trim()
    await updateReceipt(num(rejectTarget.id), { status: "rejected", reject_reason: reason })
    setRejectTarget(null)
  }

  const listBody = showFullReviewUi ? (
    <>
      {!compactTableOnly ? (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statTotalCount")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(aggByStatus.totalCount, isFa)}</CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-muted-foreground">
            {tp("statTotalSum")}: {formatNumber(aggByStatus.totalSum, isFa)}
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statApprovedIncome")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(approved.sum, isFa)}</CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-muted-foreground">
            {tp("statCount")}: {formatNumber(approved.count, isFa)}
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statPending")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(pending.count, isFa)}</CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-muted-foreground">
            {tp("statPendingSum")}: {formatNumber(pending.sum, isFa)}
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statRejected")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(rejected.count, isFa)}</CardTitle>
          </CardHeader>
          <CardContent className="text-xs text-muted-foreground">
            {tp("statRejectedSum")}: {formatNumber(rejected.sum, isFa)}
          </CardContent>
        </Card>
      </div>

      {rejectReasons.length === 0 && !isReseller && variant === "page" ? (
        <p className="text-xs text-muted-foreground">
          {tp("settingsRejectHint")}{" "}
          {settingsUrl ? (
            <a href={settingsUrl} className="text-primary underline-offset-2 hover:underline">
              {tp("settingsRejectLink")}
            </a>
          ) : null}
        </p>
      ) : null}

      <div className="space-y-3 rounded-lg border border-border/60 bg-muted/20 p-3">
        <div className="space-y-2">
          <Label htmlFor="rcpt-search">{tp("searchPlaceholder")}</Label>
          <Input
            id="rcpt-search"
            value={searchDraft}
            onChange={(e) => setSearchDraft(e.target.value)}
            placeholder={tp("searchPlaceholder")}
          />
        </div>
        <div className="flex flex-wrap items-end gap-3">
          <div className="space-y-2">
            <Label className="text-xs text-muted-foreground">{tp("filterStatus")}</Label>
            <DashSelect
              triggerClassName="w-auto min-w-[10rem]"
              value={listFilters.status}
              onValueChange={(v) => onListFiltersChange({ status: v })}
              options={[
                { value: "all", label: tp("filterAll") },
                { value: "pending", label: tp("statusPending") },
                { value: "processing", label: tp("statusProcessing") },
                { value: "approved", label: tp("statusApproved") },
                { value: "rejected", label: tp("statusRejected") },
              ]}
            />
          </div>
          <div className="space-y-2">
            <Label className="text-xs text-muted-foreground">{tp("sortLabel")}</Label>
            <DashSelect
              triggerClassName="w-auto min-w-[10rem]"
              value={listFilters.sort}
              onValueChange={(v) => onListFiltersChange({ sort: v })}
              options={[
                { value: "created_desc", label: tp("sortCreatedDesc") },
                { value: "created_asc", label: tp("sortCreatedAsc") },
                { value: "amount_desc", label: tp("sortAmountDesc") },
                { value: "amount_asc", label: tp("sortAmountAsc") },
                { value: "id_desc", label: tp("sortIdDesc") },
              ]}
            />
          </div>
          <div className="min-w-[11rem] flex-1">
            <DashboardDateTimePicker
              label={tp("dateFrom")}
              value={listFilters.dateFrom}
              onChange={(v) => onListFiltersChange({ dateFrom: v })}
            />
          </div>
          <div className="min-w-[11rem] flex-1">
            <DashboardDateTimePicker
              label={tp("dateTo")}
              value={listFilters.dateTo}
              onChange={(v) => onListFiltersChange({ dateTo: v })}
            />
          </div>
          <div className="space-y-2">
            <Label className="text-xs text-muted-foreground">{tp("amountMin")}</Label>
            <Input
              dir="ltr"
              className="w-28 font-mono"
              value={listFilters.amountMin}
              onChange={(e) => onListFiltersChange({ amountMin: e.target.value })}
            />
          </div>
          <div className="space-y-2">
            <Label className="text-xs text-muted-foreground">{tp("amountMax")}</Label>
            <Input
              dir="ltr"
              className="w-28 font-mono"
              value={listFilters.amountMax}
              onChange={(e) => onListFiltersChange({ amountMax: e.target.value })}
            />
          </div>
        </div>
      </div>

      <p className="text-xs text-muted-foreground">
        {pagination
          ? t("receiptsAdmin.listPaginationHint", { total: formatNumber(pagination.total, isFa) })
          : t("receiptsAdmin.sampleHint", { n: receipts.length })}
      </p>

      <Separator />
        </>
      ) : null}

      {receipts.length === 0 ? (
        <p className="text-sm text-muted-foreground">{embedEmptyHint ?? tp("emptyList")}</p>
      ) : (
        <Card>
          <CardContent className="overflow-x-auto p-0">
            <table className={cn("w-full text-sm", hideUserColumns ? "min-w-[760px]" : "min-w-[1040px]")}>
              <thead>
                <tr className="border-b bg-muted/40 text-muted-foreground">
                  <th className="px-3 py-2 text-start">{tp("colReceipt")}</th>
                  {!hideUserColumns ? (
                    <>
                      <th className="px-3 py-2 text-start">{tp("colUserName")}</th>
                      <th className="px-3 py-2 text-start">{tp("colUserId")}</th>
                    </>
                  ) : null}
                  <th className="px-3 py-2 text-start">{tp("colAmount")}</th>
                  <th className="px-3 py-2 text-start">{tp("colSelectedService")}</th>
                  <th className="px-3 py-2 text-start">{tp("colCreated")}</th>
                  <th className="px-3 py-2 text-start">{tp("colStatus")}</th>
                  <th className="px-3 py-2 text-start">{tp("colActions")}</th>
                </tr>
              </thead>
              <tbody>
                {receipts.map((r) => {
                  const id = num(r.id)
                  const st = String(r.status ?? "")
                  const imageUrl = String(r.imageUrl ?? "").trim()
                  const isApproved = st === "approved"
                  return (
                    <tr key={id} className="border-b border-border/70 align-top">
                      <td className="px-3 py-2">
                        <div className="flex items-center gap-2">
                          {imageUrl ? (
                            <button
                              type="button"
                              className="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-border bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                              title={tp("clickToEnlarge")}
                              onClick={() => setPreviewReceipt(r)}
                            >
                              <img
                                src={imageUrl}
                                alt={`${tp("receiptImage")} #${formatNumber(id, isFa)}`}
                                className="h-full w-full cursor-pointer object-cover"
                                loading="lazy"
                              />
                            </button>
                          ) : (
                            <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-dashed border-border bg-muted/20 px-1 text-center text-[10px] leading-tight text-muted-foreground">
                              {tp("noImage")}
                            </span>
                          )}
                          <span className="font-mono text-sm">#{formatNumber(id, isFa)}</span>
                        </div>
                      </td>
                      {!hideUserColumns ? (
                        <>
                          <td className="px-3 py-2">
                            <div className="font-medium">{receiptUserLabel(r)}</div>
                            {String(r.username ?? "").trim() ? (
                              <div className="text-xs text-muted-foreground">
                                @{String(r.username).replace(/^@/, "")}
                              </div>
                            ) : null}
                          </td>
                          <td className="px-3 py-2 tabular-nums">#{formatNumber(num(r.user_id), isFa)}</td>
                        </>
                      ) : null}
                      <td className="px-3 py-2">
                        <div className="font-medium tabular-nums">
                          {formatReceiptAmount(num(r.amount), isFa, tp)}
                        </div>
                        {num(r.transaction_amount) !== num(r.amount) ? (
                          <div className="text-xs text-muted-foreground">
                            {tp("txAmount")}: {formatReceiptAmount(num(r.transaction_amount), isFa, tp)}
                          </div>
                        ) : null}
                      </td>
                      <td className="px-3 py-2">{receiptSelectedService(r)}</td>
                      <td className="px-3 py-2">
                        {formatDateTime(
                          (r.created_at_ts as number | undefined) ?? (r.created_at as string | undefined),
                          isFa
                        )}
                      </td>
                      <td className="px-3 py-2">
                        <Badge variant={receiptStatusVariant(st)}>{receiptStatusLabel(st, tp)}</Badge>
                        <DashSelect
                          size="sm"
                          triggerClassName="mt-2 min-w-28"
                          value={st === "processing" ? "pending" : st}
                          disabled={busyId === id}
                          onValueChange={(next) => {
                            if (next === "rejected") openRejectDialog(r)
                            else void updateReceipt(id, { status: next })
                          }}
                          options={[
                            { value: "pending", label: tp("statusPending") },
                            ...(st === "processing"
                              ? [{ value: "processing", label: tp("statusProcessing") }]
                              : []),
                            { value: "approved", label: tp("statusApproved") },
                            { value: "rejected", label: tp("statusRejected") },
                          ]}
                        />
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex flex-wrap gap-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={busyId === id}
                            onClick={() => openAmountDialog(r)}
                          >
                            {isApproved ? tp("editAmountApproved") : tp("editAmount")}
                          </Button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </CardContent>
        </Card>
      )}
    </>
  ) : (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{tw("approvedReceiptsTitle")}</CardTitle>
        <CardDescription>{tw("approvedReceiptsHint")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-xs text-muted-foreground">{tp("reviewPendingHint")}</p>
        {approvedReceipts.length === 0 ? (
          <p className="text-sm text-muted-foreground">{tw("approvedReceiptsEmpty")}</p>
        ) : (
          <ul className="space-y-2">
            {approvedReceipts.map((r) => {
              const id = num(r.id)
              const amt = num(r.amount)
              return (
                <li
                  key={id || String(r.created_at)}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                >
                  <span className="font-medium tabular-nums">{formatReceiptAmount(amt, isFa, tp)}</span>
                  <span className="text-muted-foreground">
                    #{formatNumber(id, isFa)} · {String(r.created_at ?? "—")}
                  </span>
                  <Badge variant="secondary">{tw("statusApproved")}</Badge>
                </li>
              )
            })}
          </ul>
        )}
      </CardContent>
    </Card>
  )

  const dialogs = (
    <>
      <Dialog open={Boolean(previewReceipt)} onOpenChange={(o) => !o && setPreviewReceipt(null)}>
        <DashDialogContent className="sm:max-w-5xl">
          <DashDialogHeader>
            <DialogTitle>
              {tp("receiptImage")}{" "}
              {previewReceipt ? `#${formatNumber(num(previewReceipt.id), isFa)}` : ""}
            </DialogTitle>
            <DialogDescription>
              {previewReceipt
                ? tp("selectedServiceLine", { service: receiptSelectedService(previewReceipt) })
                : tp("clickToEnlarge")}
            </DialogDescription>
          </DashDialogHeader>
          {previewReceipt && String(previewReceipt.imageUrl ?? "").trim() ? (
            <div className="max-h-[min(78dvh,32rem)] overflow-auto rounded-md bg-muted/30 p-2">
              <img
                src={String(previewReceipt.imageUrl)}
                alt={`receipt-${String(previewReceipt.id ?? "")}`}
                className="mx-auto max-h-[min(72dvh,30rem)] w-auto max-w-full rounded-md object-contain"
              />
            </div>
          ) : null}
        </DashDialogContent>
      </Dialog>

      <Dialog open={Boolean(amountTarget)} onOpenChange={(o) => !o && setAmountTarget(null)}>
        <DashDialogContent className={cn()}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>
              {amountTarget && String(amountTarget.status ?? "") === "approved"
                ? tp("editAmountTitleApproved")
                : tp("editAmountTitle")}
            </DialogTitle>
            <DialogDescription>
              {amountTarget && String(amountTarget.status ?? "") === "approved"
                ? tp("editAmountDescApproved")
                : tp("editAmountDesc")}
            </DialogDescription>
          </DashDialogHeader>
          <div className="space-y-2">
            <Label>{tp("colAmount")}</Label>
            <Input dir="ltr" value={amountDraft} onChange={(e) => setAmountDraft(e.target.value)} />
          </div>
          <DashDialogFooter className="gap-2">
            <Button type="button" variant="outline" onClick={() => setAmountTarget(null)}>
              {tp("cancel")}
            </Button>
            <Button
              type="button"
              disabled={Boolean(amountTarget && busyId === num(amountTarget.id))}
              onClick={() => void saveAmount()}
            >
              {tp("save")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={Boolean(rejectTarget)} onOpenChange={(o) => !o && setRejectTarget(null)}>
        <DashDialogContent className={cn()}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{tp("rejectDialogTitle")}</DialogTitle>
            <DialogDescription>{tp("rejectDialogDesc")}</DialogDescription>
          </DashDialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label>{tp("rejectReason")}</Label>
              <DashSelect
                value={rejectReason}
                onValueChange={setRejectReason}
                allowEmpty={rejectReasons.length === 0}
                placeholder={tp("noRejectReasons")}
                options={rejectReasons.map((reason) => ({ value: reason, label: reason }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("customRejectReason")}</Label>
              <textarea
                className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                value={rejectCustomReason}
                onChange={(e) => setRejectCustomReason(e.target.value)}
                placeholder={tp("customRejectReasonPlaceholder")}
              />
            </div>
          </div>
          <DashDialogFooter className="gap-2">
            <Button type="button" variant="outline" onClick={() => setRejectTarget(null)}>
              {tp("cancel")}
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={Boolean(rejectTarget && busyId === num(rejectTarget.id))}
              onClick={() => void confirmReject()}
            >
              {tp("reject")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </>
  )

  if (variant === "userEmbed") {
    return (
      <div className="space-y-3">
        {alertText ? (
          <div
            role="alert"
            className="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100"
          >
            {alertText}
          </div>
        ) : null}
        {embedTitle ? <h3 className="text-base font-semibold">{embedTitle}</h3> : null}
        {listBody}
        <DataPagination meta={pagination} onPageChange={onPageChange} onPerPageChange={onPerPageChange} />
        {dialogs}
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {alertText ? (
        <div
          role="alert"
          className="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100"
        >
          {alertText}
        </div>
      ) : null}
      {listBody}
      <DataPagination meta={pagination} onPageChange={onPageChange} onPerPageChange={onPerPageChange} />
      {dialogs}
    </div>
  )
}
