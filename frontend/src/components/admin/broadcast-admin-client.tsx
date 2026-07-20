"use client"

import { useTranslations } from "next-intl"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"

import { useCallback, useEffect, useMemo, useState } from "react"

import { BroadcastRichEditor } from "@/components/broadcast-rich-editor"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashPage } from "@/components/dash-page"
import {
  hasBaleUnsupportedFeatures,
  htmlForTelegramPreview,
  htmlToBalePreviewMarkdown,
} from "@/lib/broadcast-preview"
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
import { Separator } from "@/components/ui/separator"
import { DataPagination } from "@/components/data-pagination"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { type PaginationMeta, parsePaginationMeta } from "@/lib/dash-pagination"
import { DashSelect } from "@/components/dash-select"
import { postDashboardMediaUpload } from "@/lib/dash-admin-upload"
import { formatNumber, formatNumericString } from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import type { BotPlatformId } from "@/config/bot-platforms"
import { BOT_PLATFORMS } from "@/config/bot-platforms"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>

export type BroadcastAggRow = {
  broadcastId: number
  bot: string
  status: string
  failureKind: string
  count: number
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function parseAggs(raw: unknown): BroadcastAggRow[] {
  if (!Array.isArray(raw)) return []
  const out: BroadcastAggRow[] = []
  for (const x of raw) {
    if (!x || typeof x !== "object") continue
    const r = x as Record<string, unknown>
    out.push({
      broadcastId: num(r.broadcastId ?? r.broadcast_id),
      bot: String(r.bot ?? ""),
      status: String(r.status ?? ""),
      failureKind: String(r.failureKind ?? r.failure_kind ?? ""),
      count: num(r.count),
    })
  }
  return out
}

type BotSlice = {
  pending: number
  sending: number
  sent: number
  failed: number
  blocked: number
  cancelled: number
}

function emptyBotSlice(): BotSlice {
  return { pending: 0, sending: 0, sent: 0, failed: 0, blocked: 0, cancelled: 0 }
}

function summarizeQueue(broadcastId: number, rows: BroadcastAggRow[]) {
  const total: BotSlice = emptyBotSlice()
  const tg = emptyBotSlice()
  const bale = emptyBotSlice()
  for (const a of rows) {
    if (a.broadcastId !== broadcastId || a.count <= 0) continue
    const c = a.count
    const slice = a.bot === "bale" ? bale : tg
    if (a.status === "pending") {
      total.pending += c
      slice.pending += c
    } else if (a.status === "sending") {
      total.sending += c
      slice.sending += c
    } else if (a.status === "sent") {
      total.sent += c
      slice.sent += c
    } else if (a.status === "failed") {
      if (a.failureKind === "blocked") {
        total.blocked += c
        slice.blocked += c
      } else if (a.failureKind === "cancelled") {
        total.cancelled += c
        slice.cancelled += c
      } else {
        total.failed += c
        slice.failed += c
      }
    }
  }
  return { total, tg, bale }
}

function stripHtmlToPlain(html: string): string {
  return html
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim()
}

function parseBroadcastContentFromStored(content: unknown): { html: string; urls: string[] } {
  if (typeof content !== "string") return { html: "", urls: [] }
  try {
    const j = JSON.parse(content) as Record<string, unknown>
    const urls: string[] = []
    if (Array.isArray(j.media_urls)) {
      for (const u of j.media_urls) {
        if (typeof u === "string" && u.trim() !== "") urls.push(u.trim())
      }
    }
    const legacy = typeof j.photo === "string" ? j.photo.trim() : ""
    if (urls.length === 0 && legacy !== "") urls.push(legacy)
    return { html: String(j.text ?? ""), urls }
  } catch {
    return { html: content, urls: [] }
  }
}

function previewFromContent(content: unknown): {
  text: string
  mediaCount: number
  targets: string
} {
  const { html, urls } = parseBroadcastContentFromStored(content)
  const plain = stripHtmlToPlain(html)
  let targets = ""
  if (typeof content === "string") {
  try {
    const j = JSON.parse(content) as Record<string, unknown>
      targets = String(j.targets ?? "")
    } catch {
      targets = ""
    }
  }
    return {
      text: plain.slice(0, 200),
    mediaCount: urls.length,
    targets,
  }
}

function targetsLabel(raw: string, tp: (k: string) => string): string {
  if (raw === "both") return tp("targetsBoth")
  if (raw === "telegram") return tp("targetsTelegram")
  if (raw === "bale") return tp("targetsBale")
  return raw
}

function broadcastStatusLabel(st: string, tp: (k: string) => string): string {
  const key = `broadcastStatus_${st}`
  const tr = tp(key)
  if (tr !== key) return tr
  return st || "—"
}

function queueRowStatusLabel(
  row: { status: string; failureKind: string },
  t: (k: string, opts?: Record<string, string>) => string
): string {
  if (row.status === "sent") return t("qs_sent")
  if (row.status === "pending") return t("qs_pending")
  if (row.status === "sending") return t("qs_sending")
  if (row.status === "failed") {
    const fk = row.failureKind || "other"
    return t(`qs_failed_${fk}`, { defaultValue: t("qs_failed_other") })
  }
  return row.status
}

type QueueUserRow = {
  id: number
  bot: string
  status: string
  failureKind: string
  lastError: string
  tries: number
}

type QueueUser = {
  userId: number
  displayName: string
  username: string
  rows: QueueUserRow[]
}

function parseQueueUsers(raw: unknown): QueueUser[] {
  if (!Array.isArray(raw)) return []
  const out: QueueUser[] = []
  for (const x of raw) {
    if (!x || typeof x !== "object") continue
    const o = x as Record<string, unknown>
    const rowsRaw = o.rows
    const rows: QueueUserRow[] = []
    if (Array.isArray(rowsRaw)) {
      for (const r of rowsRaw) {
        if (!r || typeof r !== "object") continue
        const q = r as Record<string, unknown>
        rows.push({
          id: num(q.id),
          bot: String(q.bot ?? ""),
          status: String(q.status ?? ""),
          failureKind: String(q.failureKind ?? q.failure_kind ?? ""),
          lastError: String(q.lastError ?? q.last_error ?? ""),
          tries: num(q.tries),
        })
      }
    }
    out.push({
      userId: num(o.userId ?? o.user_id),
      displayName: String(o.displayName ?? o.display_name ?? ""),
      username: String(o.username ?? ""),
      rows,
    })
  }
  return out
}

function userQueueCounts(rows: QueueUserRow[]) {
  let pending = 0
  let sending = 0
  let sent = 0
  let failed = 0
  let blocked = 0
  let cancelled = 0
  for (const r of rows) {
    if (r.status === "pending") pending++
    else if (r.status === "sending") sending++
    else if (r.status === "sent") sent++
    else if (r.status === "failed") {
      if (r.failureKind === "blocked") blocked++
      else if (r.failureKind === "cancelled") cancelled++
      else failed++
    }
  }
  return { pending, sending, sent, failed, blocked, cancelled }
}

type DeliveryStripVariant =
  | "delivered"
  | "waiting"
  | "cancelled_only"
  | "partial_error"
  | "partial_cancelled"
  | "failed_all"

function deriveUserDeliverySummary(rows: QueueUserRow[]): {
  variant: DeliveryStripVariant
  barClass: string
} {
  if (rows.length === 0) {
    return { variant: "waiting", barClass: "border-s-4 border-amber-500" }
  }
  if (rows.every((r) => r.status === "sent")) {
    return { variant: "delivered", barClass: "border-s-4 border-emerald-500" }
  }
  if (rows.some((r) => r.status === "pending" || r.status === "sending")) {
    return { variant: "waiting", barClass: "border-s-4 border-amber-500" }
  }
  const sentRows = rows.filter((r) => r.status === "sent")
  const failedRows = rows.filter((r) => r.status === "failed")
  const allFailed = failedRows.length === rows.length
  const allCancelled =
    failedRows.length > 0 && failedRows.every((r) => r.failureKind === "cancelled")

  if (allFailed && allCancelled) {
    return { variant: "cancelled_only", barClass: "border-s-4 border-slate-500" }
  }
  if (sentRows.length > 0 && failedRows.some((r) => r.failureKind !== "cancelled")) {
    return { variant: "partial_error", barClass: "border-s-4 border-orange-500" }
  }
  if (sentRows.length > 0 && failedRows.length > 0 && failedRows.every((r) => r.failureKind === "cancelled")) {
    return { variant: "partial_cancelled", barClass: "border-s-4 border-amber-700" }
  }
  if (sentRows.length > 0) {
    return { variant: "partial_error", barClass: "border-s-4 border-orange-500" }
  }
  return { variant: "failed_all", barClass: "border-s-4 border-red-500" }
}

function recipientStripLabel(variant: DeliveryStripVariant, tp: (k: string) => string): string {
  const key =
    variant === "delivered"
      ? "recipientStrip_delivered"
      : variant === "waiting"
        ? "recipientStrip_waiting"
        : variant === "cancelled_only"
          ? "recipientStrip_cancelled_only"
          : variant === "partial_error"
            ? "recipientStrip_partial_error"
            : variant === "partial_cancelled"
              ? "recipientStrip_partial_cancelled"
              : "recipientStrip_failed_all"
  return tp(key)
}

function channelRowAccent(row: QueueUserRow): string {
  if (row.status === "sent") return "border-s-2 border-emerald-500"
  if (row.status === "pending" || row.status === "sending") return "border-s-2 border-amber-500"
  if (row.status === "failed" && row.failureKind === "cancelled") return "border-s-2 border-slate-400"
  if (row.status === "failed") return "border-s-2 border-red-500"
  return "border-s-2 border-muted"
}

function BroadcastHtmlPreview({
  html,
  urls,
  className,
}: {
  html: string
  urls: string[]
  className?: string
}) {
  const previewHtml = htmlForTelegramPreview(html)
  return (
    <div className={cn("space-y-3", className)}>
      {urls.length > 0 ? (
        <div className="flex flex-wrap gap-2">
          {urls.map((u) => (
            <img key={u} src={u} alt="" className="max-h-48 max-w-full rounded-md border object-contain" loading="lazy" />
          ))}
        </div>
      ) : null}
      {html.trim() !== "" ? (
        <div
          className={cn(
            "min-h-[4rem] whitespace-pre-wrap rounded-md border border-border/80 bg-muted/20 p-3 text-sm [&_a]:text-primary [&_a]:underline"
          )}
          dangerouslySetInnerHTML={{ __html: previewHtml }}
        />
      ) : urls.length === 0 ? (
        <p className="text-sm text-muted-foreground">—</p>
      ) : null}
    </div>
  )
}

function BroadcastBalePreview({
  html,
  urls,
  className,
  baleNote,
}: {
  html: string
  urls: string[]
  className?: string
  baleNote: string
}) {
  const md = htmlToBalePreviewMarkdown(html)
  const warn = hasBaleUnsupportedFeatures(html)
  return (
    <div className={cn("space-y-3", className)}>
      {urls.length > 0 ? (
        <p className="text-xs text-muted-foreground">{urls.length} image(s) — caption on first only</p>
      ) : null}
      {warn ? <p className="text-xs text-amber-800 dark:text-amber-200">{baleNote}</p> : null}
      {md !== "" ? (
        <pre
          className={cn(
            "min-h-[4rem] whitespace-pre-wrap rounded-md border border-border/80 bg-muted/20 p-3 font-sans text-sm")}
        >
          {md}
        </pre>
      ) : urls.length === 0 ? (
        <p className="text-sm text-muted-foreground">—</p>
      ) : null}
    </div>
  )
}

function BroadcastRecipientsBlock({
  broadcastId,
}: {
  broadcastId: number
}) {
  const { isFa } = useDashLocale()
  const t = useTranslations("broadcastAdmin")
  const tp = (k: string) => t(`${k}`)
  const [open, setOpen] = useState(false)
  const [page, setPage] = useState(1)
  const perPage = 25
  const [loading, setLoading] = useState(false)
  const [users, setUsers] = useState<QueueUser[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [detailUser, setDetailUser] = useState<QueueUser | null>(null)

  useEffect(() => {
    if (!open) return
    let cancelled = false
    setLoading(true)
    void getAdminJson("/dashboard/admin/broadcast-queue", {
      broadcast_id: broadcastId,
      page,
      per_page: perPage,
    })
      .then((json) => {
        if (cancelled) return
        const u = parseQueueUsers(json.users)
        setUsers(u)
        setMeta(parsePaginationMeta(json.pagination))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [open, broadcastId, page])

  return (
    <div className="border-t pt-3">
      {!open ? (
        <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)}>
          {t("recipientsLoad")}
        </Button>
      ) : (
        <div className="space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-sm font-medium">{t("recipientsTitle")}</p>
            <Button type="button" variant="ghost" size="sm" onClick={() => setOpen(false)}>
              {t("hide")}
            </Button>
          </div>
          {loading ? (
            <p className="text-xs text-muted-foreground">{t("recipientsLoading")}</p>
          ) : users.length === 0 ? (
            <p className="text-xs text-muted-foreground">{t("recipientsEmpty")}</p>
          ) : (
            <ul className="space-y-2">
              {users.map((u) => {
                const summary = deriveUserDeliverySummary(u.rows)
                const label = recipientStripLabel(summary.variant, tp)
                const c = userQueueCounts(u.rows)
                return (
                  <li
                    key={u.userId}
                    className={cn(
                      "flex flex-wrap items-stretch justify-between gap-2 overflow-hidden rounded-md border border-border/60 bg-muted/20 text-xs",
                      summary.barClass
                    )}
                  >
                    <div className={cn("min-w-0 flex-1 space-y-1 px-2 py-2")}>
                      <div className="font-medium leading-snug">{u.displayName || `#${formatNumericString(String(u.userId), isFa)}`}</div>
                      <p className="text-sm font-medium text-foreground">{label}</p>
                      {u.rows.length > 1 ? (
                        <div className="mt-1 space-y-1">
                          {u.rows.map((row) => (
                            <div
                              key={row.id}
                              className={cn(
                                "flex flex-wrap items-baseline gap-2 rounded-sm bg-background/50 py-1 ps-2",
                                channelRowAccent(row)
                              )}
                            >
                              <span className="shrink-0 font-medium text-muted-foreground">
                                {row.bot === "bale" ? t("platformBale") : t("platformTelegram")}
                              </span>
                              <span className="text-muted-foreground">{queueRowStatusLabel(row, t)}</span>
                            </div>
                          ))}
                        </div>
                      ) : null}
                      <p className="text-[11px] text-muted-foreground">
                        {t("miniPending")}: {formatNumber(c.pending, isFa)} · {t("miniSent")}: {formatNumber(c.sent, isFa)} ·{" "}
                        {t("miniFailed")}: {formatNumber(c.failed, isFa)} · {t("miniBlocked")}: {formatNumber(c.blocked, isFa)}
                        {c.cancelled > 0 ? (
                          <>
                            {" "}
                            · {t("miniCancelled")}: {formatNumber(c.cancelled, isFa)}
                          </>
                        ) : null}
                      </p>
                    </div>
                    <div className="flex shrink-0 items-start p-2">
                      <Button type="button" variant="secondary" size="sm" onClick={() => setDetailUser(u)}>
                        {t("viewStatus")}
                      </Button>
                    </div>
                  </li>
                )
              })}
            </ul>
          )}
          {meta && meta.total > perPage ? (
            <DataPagination
              meta={meta}
        onPageChange={(p) => setPage(p)}
              onPerPageChange={() => {}}
            />
          ) : null}
        </div>
      )}

      <Dialog open={detailUser !== null} onOpenChange={(v) => !v && setDetailUser(null)}>
        <DashDialogContent className={cn("sm:max-w-lg")}>
          <DashDialogHeader>
            <DialogTitle>{t("statusDialogTitle")}</DialogTitle>
            <DialogDescription className="font-mono tabular-nums">
              {detailUser ? `#${formatNumericString(String(detailUser.userId), isFa)} · ${detailUser.displayName}` : ""}
            </DialogDescription>
          </DashDialogHeader>
          {detailUser ? (
            <ul className="space-y-3 text-sm">
              {detailUser.rows.map((row) => (
                <li key={row.id} className="rounded-md border border-border/70 p-2">
                  <div className="font-medium text-muted-foreground">
                    {row.bot === "bale" ? t("platformBale") : t("platformTelegram")}
                  </div>
                  <div>{queueRowStatusLabel(row, t)}</div>
                  {row.lastError ? (
                    <pre className="mt-1 max-h-24 overflow-auto whitespace-pre-wrap break-all text-xs text-muted-foreground">
                      {row.lastError}
                    </pre>
                  ) : null}
                  <div className="mt-1 text-xs text-muted-foreground">
                    {t("triesLabel", { count: row.tries })}
                  </div>
                </li>
              ))}
            </ul>
          ) : null}
          <DashDialogFooter>
            <Button type="button" variant="secondary" onClick={() => setDetailUser(null)}>
              {t("close")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </div>
  )
}

export function BroadcastAdminView({
  broadcasts,
  broadcastQueueAggregates,
  pagination,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
  enabledPlatforms = BOT_PLATFORMS.map((p) => p.id),
  isReseller = false,
}: {
  broadcasts: DashRecord[]
  broadcastQueueAggregates?: unknown
  pagination: PaginationMeta | null
onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
  enabledPlatforms?: BotPlatformId[]
  isReseller?: boolean
}) {
  const { isFa } = useDashLocale()

  const t = useTranslations("broadcastAdmin")
  const tp = (k: string) => t(`${k}`)

  const aggs = useMemo(() => parseAggs(broadcastQueueAggregates), [broadcastQueueAggregates])

  const [html, setHtml] = useState("")
  const showTg = enabledPlatforms.includes("telegram")
  const showBale = enabledPlatforms.includes("bale")
  const defaultTarget = showTg && showBale ? "both" : showTg ? "telegram" : "bale"
  const [targets, setTargets] = useState<"both" | "telegram" | "bale">(defaultTarget as "both" | "telegram" | "bale")
  const [mediaUrls, setMediaUrls] = useState<string[]>([])
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [fullMsgOpen, setFullMsgOpen] = useState(false)
  const [fullMsgContent, setFullMsgContent] = useState<unknown>(null)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [cancelId, setCancelId] = useState<number | null>(null)
  const [cancelling, setCancelling] = useState(false)
  const [processLoading, setProcessLoading] = useState(false)
  const [processNotice, setProcessNotice] = useState<string | null>(null)

  const onPickFiles = useCallback(
    async (files: FileList | null) => {
      if (!files?.length) return
      setError(null)
      setUploading(true)
      try {
        const next = [...mediaUrls]
        for (let i = 0; i < files.length && next.length < 10; i++) {
          const f = files.item(i)
          if (!f) continue
          const r = await postDashboardMediaUpload(f)
          if (!r.ok) {
            const msg = "message" in r ? r.message : "upload_failed"
            setError(t(`err_${msg}`, { defaultValue: msg }))
            break
          }
          if (!next.includes(r.url)) next.push(r.url)
        }
        setMediaUrls(next.slice(0, 10))
      } finally {
        setUploading(false)
      }
    },
    [mediaUrls, t]
  )

  const onSend = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const payload: Record<string, unknown> = {
        bc_text: html,
        bc_targets: targets,
        bc_media_urls: mediaUrls,
      }
      const res = await postAdminMutate("broadcast_send", payload)
      if (!res.ok) {
        const m = res.message || "error"
        setError(t(`err_${m}`, { defaultValue: m }))
        return
      }
      setHtml("")
      setMediaUrls([])
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [html, mediaUrls, onMutateSuccess, t, targets])

  const runCancel = useCallback(async () => {
    if (cancelId == null) return
    setCancelling(true)
    setError(null)
    try {
      const res = await postAdminMutate("broadcast_cancel", { broadcast_id: cancelId })
      if (!res.ok) {
        const m = res.message || "error"
        setError(t(`err_${m}`, { defaultValue: t("err_not_cancellable") }))
        return
      }
      setCancelOpen(false)
      setCancelId(null)
      onMutateSuccess?.()
    } finally {
      setCancelling(false)
    }
  }, [cancelId, onMutateSuccess, t])

  const runProcessQueue = useCallback(async () => {
    setProcessLoading(true)
    setProcessNotice(null)
    setError(null)
    try {
      const res = await postAdminMutate("broadcast_run_worker", { max_iterations: 30 })
      if (!res.ok) {
        const m = res.message || "error"
        setError(t(`err_${m}`, { defaultValue: m }))
        return
      }
      const n = res.iterations ?? 0
      setProcessNotice(t("processQueueDone", { batches: String(n) }))
      onMutateSuccess?.()
    } finally {
      setProcessLoading(false)
    }
  }, [onMutateSuccess, t])

  return (
    <DashPage className="w-full min-w-0 space-y-8" data-testid="dash-broadcast-tab">
      <DashboardPageHeader
        title={t("title")}
        description={
          <>
            <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
            <p className="mt-1 text-xs text-muted-foreground">{t("cronHint")}</p>
            <p className="mt-1 text-xs text-muted-foreground">{t("cronHintSysCron")}</p>
          </>
        }
        actions={
          isReseller ? null : (
            <>
              <Button type="button" variant="secondary" size="sm" disabled={processLoading} onClick={() => void runProcessQueue()}>
                {processLoading ? t("processQueueRunning") : t("processQueueNow")}
              </Button>
              {processNotice ? <span className="text-xs text-muted-foreground">{processNotice}</span> : null}
            </>
          )
        }
      />

      {isReseller ? (
        <p className="text-sm text-muted-foreground">{t("scopedResellerHint")}</p>
      ) : null}

      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("composeTitle")}</CardTitle>
          <CardDescription>{t("composeHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid min-w-0 gap-6 lg:grid-cols-2 lg:items-start">
            <div className="min-w-0 space-y-4">
          <div className="space-y-2">
            <Label>{t("fieldText")}</Label>
            <BroadcastRichEditor
              value={html}
              onChange={setHtml}
              disabled={saving}
              placeholder={t("editorPlaceholder")}
            />
          </div>
          <div className="space-y-2">
            <Label>{t("fieldMedia")}</Label>
            <p className="text-xs text-muted-foreground">{t("mediaHint")}</p>
            <div className="flex flex-wrap items-center gap-2">
              <input
                type="file"
                accept="image/jpeg,image/png,image/gif,image/webp"
                multiple
                className="max-w-full text-sm"
                disabled={uploading || saving || mediaUrls.length >= 10}
                onChange={(e) => void onPickFiles(e.target.files)}
              />
              {uploading ? <span className="text-xs text-muted-foreground">{t("uploading")}</span> : null}
            </div>
            {mediaUrls.length > 0 ? (
              <ul className="space-y-1 text-xs break-all">
                {mediaUrls.map((u, i) => (
                  <li key={u} className="flex flex-wrap items-center gap-2">
                    <span className="font-mono tabular-nums">{formatNumber(i + 1, isFa)}</span>
                    <span className="min-w-0 flex-1 text-muted-foreground">{u}</span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-7 shrink-0"
                      onClick={() => setMediaUrls((xs) => xs.filter((_, j) => j !== i))}
                    >
                      {t("removeMedia")}
                    </Button>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
          <div className="space-y-2">
            <Label>{t("fieldTargets")}</Label>
            <DashSelect
              value={targets}
              onValueChange={(v) => setTargets(v as typeof targets)}
              options={[
                ...(showTg && showBale ? [{ value: "both", label: t("targetsBoth") }] : []),
                ...(showTg ? [{ value: "telegram", label: t("targetsTelegram") }] : []),
                ...(showBale ? [{ value: "bale", label: t("targetsBale") }] : []),
              ]}
            />
          </div>
          <Button type="button" disabled={saving || uploading} onClick={() => void onSend()}>
            {t("send")}
          </Button>
            </div>
            <div className="space-y-3">
              <Card className="border-dashed">
                <CardHeader className="pb-2">
                  <CardTitle className="text-base">{t("previewTelegram")}</CardTitle>
                </CardHeader>
                <CardContent>
                  <BroadcastHtmlPreview html={html} urls={mediaUrls} />
                </CardContent>
              </Card>
              <Card className="border-dashed">
                <CardHeader className="pb-2">
                  <CardTitle className="text-base">{t("previewBale")}</CardTitle>
                </CardHeader>
                <CardContent>
                  <BroadcastBalePreview html={html} urls={mediaUrls} baleNote={t("baleFormatNote")} />
                </CardContent>
              </Card>
            </div>
          </div>
        </CardContent>
      </Card>

      <Separator />

      <div className="space-y-3">
        <h3 className="text-base font-medium">{t("historyTitle")}</h3>
        {broadcasts.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t("historyEmpty")}</p>
        ) : (
          <ul className="space-y-4">
            {broadcasts.map((b) => {
              const id = num(b.id)
              const st = String(b.status ?? "")
              const pv = previewFromContent(b.content)
              const q = summarizeQueue(id, aggs)
              const targetsTotal = num(b.total_targets)
              const canStop =
                st !== "done" &&
                st !== "cancelled" &&
                (st === "sending" || q.total.pending > 0 || q.total.sending > 0)
              const badgeVariant =
                st === "done" || st === "cancelled" ? "secondary" : st === "sending" ? "default" : "outline"
              return (
                <li key={id}>
                  <Card>
                    <CardHeader className="space-y-1 pb-2">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle className="text-base font-mono">
                          #{formatNumericString(String(id), isFa)}
                        </CardTitle>
                        <div className="flex flex-wrap items-center gap-2">
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setFullMsgContent(b.content)
                              setFullMsgOpen(true)
                            }}
                          >
                            {t("viewFullMessage")}
                          </Button>
                          {canStop ? (
                            <Button
                              type="button"
                              variant="destructive"
                              size="sm"
                              onClick={() => {
                                setCancelId(id)
                                setCancelOpen(true)
                              }}
                            >
                              {t("cancelBroadcast")}
                            </Button>
                          ) : null}
                          <Badge variant={badgeVariant}>{broadcastStatusLabel(st, tp)}</Badge>
                        </div>
                      </div>
                      <CardDescription className="line-clamp-3 whitespace-pre-wrap">
                        {pv.mediaCount > 0
                          ? `${t("mediaBadge", { count: pv.mediaCount })} · `
                          : ""}
                        {pv.text || "—"}
                      </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                      <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                        {pv.targets ? (
                          <span>
                            {t("labelTargets")}: {targetsLabel(pv.targets, tp)}
                          </span>
                        ) : null}
                      </div>
                      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <StatBox
                          label={t("statTotalTargets")}
                          value={
                            targetsTotal ||
                            q.total.pending +
                              q.total.sending +
                              q.total.sent +
                              q.total.failed +
                              q.total.blocked +
                              q.total.cancelled
                          }
        />
                        <StatBox label={t("statSent")} value={q.total.sent}
        />
                        <StatBox label={t("statPending")} value={q.total.pending}
        />
                        <StatBox label={t("statSending")} value={q.total.sending}
        />
                        <StatBox label={t("statFailed")} value={q.total.failed}
        />
                        <StatBox label={t("statBlocked")} value={q.total.blocked}
        />
                        <StatBox label={t("statCancelled")} value={q.total.cancelled}
        />
                        <StatBox label={t("statBlockedDb")} value={num(b.blocked_count)}
        />
                        <StatBox label={t("statFailedDb")} value={num(b.failed_count)}
        />
                      </div>
                      <div className="grid gap-2 border-t pt-2 text-xs sm:grid-cols-2">
                        {showTg ? (
                        <div>
                          <p className="font-medium text-muted-foreground">{t("platformTelegram")}</p>
                          <p className="text-muted-foreground">
                            {t("miniPending")}: {formatNumber(q.tg.pending, isFa)} · {t("miniSent")}:{" "}
                            {formatNumber(q.tg.sent, isFa)} · {t("miniFailed")}: {formatNumber(q.tg.failed, isFa)} ·{" "}
                            {t("miniBlocked")}: {formatNumber(q.tg.blocked, isFa)}
                            {q.tg.cancelled > 0 ? (
                              <>
                                {" "}
                                · {t("miniCancelled")}: {formatNumber(q.tg.cancelled, isFa)}
                              </>
                            ) : null}
                          </p>
                        </div>
                        ) : null}
                        {showBale ? (
                        <div>
                          <p className="font-medium text-muted-foreground">{t("platformBale")}</p>
                          <p className="text-muted-foreground">
                            {t("miniPending")}: {formatNumber(q.bale.pending, isFa)} · {t("miniSent")}:{" "}
                            {formatNumber(q.bale.sent, isFa)} · {t("miniFailed")}: {formatNumber(q.bale.failed, isFa)} ·{" "}
                            {t("miniBlocked")}: {formatNumber(q.bale.blocked, isFa)}
                            {q.bale.cancelled > 0 ? (
                              <>
                                {" "}
                                · {t("miniCancelled")}: {formatNumber(q.bale.cancelled, isFa)}
                              </>
                            ) : null}
                          </p>
                        </div>
                        ) : null}
                      </div>
                      <BroadcastRecipientsBlock broadcastId={id}
        />
                    </CardContent>
                  </Card>
                </li>
              )
            })}
          </ul>
        )}
        <DataPagination
          meta={pagination}
        onPageChange={onPageChange}
          onPerPageChange={onPerPageChange}
        />
      </div>

      <Dialog open={fullMsgOpen} onOpenChange={setFullMsgOpen}>
        <DashDialogContent className={cn("sm:max-w-2xl")}>
          <DashDialogHeader>
            <DialogTitle>{t("viewFullMessage")}</DialogTitle>
          </DashDialogHeader>
          {(() => {
            const parsed = parseBroadcastContentFromStored(fullMsgContent)
            return <BroadcastHtmlPreview html={parsed.html} urls={parsed.urls} />
          })()}
          <DashDialogFooter>
            <Button type="button" variant="secondary" onClick={() => setFullMsgOpen(false)}>
              {t("close")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
        <DashDialogContent className="text-start">
          <DashDialogHeader>
            <DialogTitle>{t("cancelBroadcast")}</DialogTitle>
            <DialogDescription>{t("cancelConfirm")}</DialogDescription>
          </DashDialogHeader>
          <DashDialogFooter className={cn("gap-2 sm:space-x-0")}>
            <Button type="button" variant="secondary" onClick={() => setCancelOpen(false)} disabled={cancelling}>
              {t("cancelAction")}
            </Button>
            <Button type="button" variant="destructive" disabled={cancelling} onClick={() => void runCancel()}>
              {t("cancelBroadcast")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}

function StatBox({ label, value }: { label: string; value: number }) {
  const { isFa } = useDashLocale()
  return (
    <div className="rounded-md border border-border/80 bg-muted/30 px-2 py-1.5">
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className={cn("text-lg font-semibold tabular-nums")}>{formatNumber(value, isFa)}</div>
    </div>
  )
}

export function BroadcastAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, enabledPlatforms, isReseller } =
    useAdminTabState("broadcast")
  const t = useTranslations("broadcastAdmin")
  if (loading && rows(data.broadcasts).length === 0) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  return (
    <BroadcastAdminView
      broadcasts={rows(data.broadcasts ?? data.broadcastJobs)}
      broadcastQueueAggregates={data.broadcastQueueAggregates}
      pagination={pickPagination("broadcasts")}
      onMutateSuccess={reload}
      onPageChange={(p) => setPage("broadcasts", p)}
      onPerPageChange={(n) => setPer("broadcasts", n)}
      enabledPlatforms={enabledPlatforms as import("@/config/bot-platforms").BotPlatformId[]}
      isReseller={isReseller}
    />
  )
}
