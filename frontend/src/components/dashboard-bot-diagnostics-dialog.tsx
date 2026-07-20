"use client"

import { useCallback, useEffect, useRef, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Link2, RefreshCw, Send, Stethoscope } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DiagIssue = {
  code?: string
  severity?: string
  message_fa?: string
  message_en?: string
  hint?: string
}

type DiagLog = {
  id?: number
  level?: string
  message?: string
  created_at?: string
}

type DiagData = {
  token_masked?: string
  token_full?: string
  can_reveal_token?: boolean
  token_configured?: boolean
  get_me?: { id?: number; username?: string; first_name?: string }
  registered_webhook_url?: string
  expected_webhook_url?: string
  webhook_url_match?: boolean
  pending_update_count?: number
  last_error_message?: string
  last_error_date?: number
  issues?: DiagIssue[]
  recent_webhook_logs?: DiagLog[]
  recent_send_logs?: DiagLog[]
  broadcast_queue_pending?: number
  local_inbound_queue_pending?: number
  outbound_test?: { attempted?: boolean; ok?: boolean; message?: string; skipped?: string }
  local?: {
    platform_enabled?: boolean
    plugin_bot_processing_enabled?: boolean
    webhook_rate_limit_per_min?: number
    telegram_secret_header_set?: boolean
  }
}

type LoadOptions = {
  revealToken?: boolean
  sendOutboundPing?: boolean
}

function issueMessage(issue: DiagIssue, isFa: boolean): string {
  const fa = String(issue.message_fa ?? "")
  const en = String(issue.message_en ?? "")
  if (isFa && fa) return fa
  if (!isFa && en) return en
  return fa || en || String(issue.code ?? "")
}

function mapChecksToDiagData(checks: Record<string, unknown>): DiagData {
  const getMeWrap = checks.getMe
  const getMeRaw =
    getMeWrap && typeof getMeWrap === "object"
      ? ((getMeWrap as Record<string, unknown>).result ?? getMeWrap)
      : null
  const getMe =
    getMeRaw && typeof getMeRaw === "object"
      ? (getMeRaw as NonNullable<DiagData["get_me"]>)
      : undefined

  return {
    token_configured: Boolean(checks.token_set),
    token_masked: checks.token_set ? "••••••••" : "",
    get_me: getMe?.id ? getMe : undefined,
    expected_webhook_url: String(checks.expected_url ?? ""),
    registered_webhook_url: String(checks.registered_webhook_url ?? checks.expected_url ?? ""),
    webhook_url_match: checks.webhook_url_match === true || checks.webhook_secret_set === true,
    pending_update_count: Number(checks.pending_queue ?? 0),
    local_inbound_queue_pending: Number(checks.pending_queue ?? 0),
    issues: Array.isArray(checks.issues) ? (checks.issues as DiagIssue[]) : undefined,
  }
}

function diagDataFromMutate(res: { data?: unknown; checks?: unknown }): DiagData | null {
  if (res.checks && typeof res.checks === "object") {
    return mapChecksToDiagData(res.checks as Record<string, unknown>)
  }
  if (res.data && typeof res.data === "object") {
    const d = res.data as Record<string, unknown>
    if (d.checks && typeof d.checks === "object") {
      return mapChecksToDiagData(d.checks as Record<string, unknown>)
    }
    return res.data as DiagData
  }
  return null
}

function severityVariant(sev: string): "default" | "secondary" | "destructive" | "outline" {
  if (sev === "error") return "destructive"
  if (sev === "warning") return "secondary"
  return "outline"
}

function Row({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="grid gap-0.5 text-sm">
      <span className="text-xs text-muted-foreground">{label}</span>
      <span className={cn(mono && "break-all font-mono text-xs")} dir={mono ? "ltr" : undefined}>
        {value || "—"}
      </span>
    </div>
  )
}

export function DashboardBotDiagnosticsDialog({
  open,
  platform,
  resellerId = 0,
  mirrorId = 0,
  onClose,
}: {
  open: boolean
  platform: "telegram" | "bale"
  resellerId?: number
  mirrorId?: number
  onClose: () => void
}) {
  const t = useTranslations("botsAdmin")
  const td = useTranslations("botsAdmin.diagnostics")
  const locale = useLocale()
  const isFa = locale === "fa"

  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [data, setData] = useState<DiagData | null>(null)
  const [tokenFull, setTokenFull] = useState<string | null>(null)
  const [webhookResetMsg, setWebhookResetMsg] = useState<string | null>(null)
  const [webhookResetting, setWebhookResetting] = useState(false)

  const inFlightRef = useRef(false)
  const requestGenRef = useRef(0)

  const fetchDiagnostics = useCallback(
    async (options: LoadOptions = {}) => {
      if (inFlightRef.current) return

      const { revealToken = false, sendOutboundPing = false } = options
      const gen = ++requestGenRef.current
      inFlightRef.current = true
      setLoading(true)
      setError(null)

      try {
        const payload: Record<string, unknown> = { platform }
        if (mirrorId > 0) payload.mirror_id = mirrorId
        else if (resellerId > 0) payload.reseller_svp_user_id = resellerId
        if (revealToken) payload.reveal_token = true
        if (sendOutboundPing) payload.send_outbound_ping = true

        const op = mirrorId > 0 ? "telegram_mirror_diagnostics" : "bot_diagnostics"
        const res = await postAdminMutate(op, payload)
        if (gen !== requestGenRef.current) return

        if (!res.ok) {
          const msg = String(res.message ?? "")
          if (msg === "rate_limited") {
            setError(td("rateLimited"))
          } else {
            setError(res.message || td("loadError"))
          }
          if (!sendOutboundPing) setData(null)
          return
        }

        const d = diagDataFromMutate(res)
        setData(d)
        if (revealToken && d?.token_full) {
          setTokenFull(String(d.token_full))
        }
      } finally {
        if (gen === requestGenRef.current) {
          setLoading(false)
          inFlightRef.current = false
        }
      }
    },
    [platform, resellerId, mirrorId, td]
  )

  useEffect(() => {
    if (!open) {
      requestGenRef.current += 1
      inFlightRef.current = false
      setLoading(false)
      setData(null)
      setError(null)
      setTokenFull(null)
      setWebhookResetMsg(null)
      return
    }
    void fetchDiagnostics()
  }, [open, platform, resellerId, mirrorId, fetchDiagnostics])

  const onRevealToken = () => {
    if (!window.confirm(td("revealConfirm"))) return
    void fetchDiagnostics({ revealToken: true })
  }

  const onSendTest = () => {
    void fetchDiagnostics({ sendOutboundPing: true, revealToken: Boolean(tokenFull) })
  }

  const onReregisterWebhook = async () => {
    if (!window.confirm(td("reregisterConfirm"))) return
    setWebhookResetting(true)
    setWebhookResetMsg(null)
    try {
      const op =
        mirrorId > 0 ? "telegram_mirror_set_webhook" : resellerId > 0 ? "reseller_bot_webhook_set" : "bot_set_webhook"
      const payload: Record<string, unknown> = { platform }
      if (mirrorId > 0) {
        payload.mirror_id = mirrorId
      } else if (resellerId > 0) {
        payload.reseller_svp_user_id = resellerId
      } else {
        payload.bot_id = 0
      }
      const res = await postAdminMutate(op, payload)
      if (res.ok) {
        setWebhookResetMsg(td("reregisterOk"))
        void fetchDiagnostics({ revealToken: Boolean(tokenFull) })
      } else {
        setWebhookResetMsg(res.message || td("reregisterFail"))
      }
    } finally {
      setWebhookResetting(false)
    }
  }

  const tokenOk = Boolean(data?.get_me?.id)
  const webhookOk = Boolean(data?.webhook_url_match && data?.registered_webhook_url)
  const pending = Number(data?.pending_update_count ?? 0)
  const localInboundPending = Number(data?.local_inbound_queue_pending ?? 0)
  const showReregister =
    pending > 0 ||
    Boolean(data?.last_error_message && /504|Gateway Time-out|Gateway Timeout/i.test(String(data.last_error_message)))
  const issues = Array.isArray(data?.issues) ? data!.issues! : []

  const platformLabel = platform === "telegram" ? t("platformTelegram") : t("platformBale")

  const outboundTestLabel = (() => {
    const ot = data?.outbound_test
    if (!ot) return td("outboundNotRun")
    if (ot.skipped === "not_requested") return td("outboundNotRun")
    if (ot.attempted) {
      return ot.ok ? td("outboundOk") : String(ot.message ?? td("outboundFail"))
    }
    return td("outboundSkipped")
  })()

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DashDialogContent className={cn("max-w-2xl")}>
        <DashDialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Stethoscope className="size-4" />
            {td("title")} —             {platformLabel}
            {mirrorId > 0 ? ` ${t("diagnostics.mirrorScope")} #${mirrorId}` : resellerId > 0 ? ` #${resellerId}` : ""}
          </DialogTitle>
          <DialogDescription>{td("subtitle")}</DialogDescription>
        </DashDialogHeader>

        {loading ? (
          <p className="text-sm text-muted-foreground">{td("loading")}</p>
        ) : error ? (
          <div
            role="alert"
            className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
          >
            {error}
          </div>
        ) : data ? (
          <div className="max-h-[min(70vh,32rem)] space-y-4 overflow-y-auto pe-1">
            <div className="flex flex-wrap gap-2">
              <Badge variant={tokenOk ? "default" : "destructive"}>{tokenOk ? td("tokenOk") : td("tokenFail")}</Badge>
              <Badge variant={webhookOk ? "default" : "destructive"}>
                {webhookOk ? td("webhookOk") : td("webhookFail")}
              </Badge>
              <Badge variant={pending > 0 ? "secondary" : "outline"}>
                {td("pendingQueue")}: {formatNumber(pending, isFa)}
              </Badge>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <Row label={td("tokenMasked")} value={tokenFull ?? String(data.token_masked ?? "")} mono />
              {data.get_me ? (
                <>
                  <Row label={td("botId")} value={String(data.get_me.id ?? "—")} mono />
                  <Row
                    label={td("botUsername")}
                    value={data.get_me.username ? `@${data.get_me.username}` : "—"}
                    mono
                  />
                </>
              ) : null}
              <Row label={td("webhookRegistered")} value={String(data.registered_webhook_url ?? "")} mono />
              <Row label={td("webhookExpected")} value={String(data.expected_webhook_url ?? "")} mono />
              <Row label={td("pendingQueue")} value={formatNumber(pending, isFa)} />
              {data.last_error_message ? (
                <Row label={td("lastError")} value={String(data.last_error_message)} mono />
              ) : null}
            </div>

            {data.can_reveal_token && !tokenFull ? (
              <Button type="button" size="sm" variant="outline" onClick={onRevealToken}>
                {td("revealToken")}
              </Button>
            ) : null}

            {showReregister ? (
              <div className="flex flex-wrap items-center gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  disabled={webhookResetting || loading}
                  onClick={() => void onReregisterWebhook()}
                >
                  <Link2 className={cn("size-3.5", webhookResetting && "animate-pulse")} />
                  {td("reregisterWebhook")}
                </Button>
                {webhookResetMsg ? <span className="text-xs text-muted-foreground">{webhookResetMsg}</span> : null}
              </div>
            ) : null}

            <div className="space-y-2">
              <p className="text-sm font-medium">{td("issues")}</p>
              {issues.length === 0 ? (
                <p className="text-sm text-emerald-600 dark:text-emerald-400">{td("noIssues")}</p>
              ) : (
                <ul className="space-y-2">
                  {issues.map((issue, i) => (
                    <li
                      key={`${issue.code ?? i}-${i}`}
                      className="rounded-md border border-border/80 bg-muted/20 px-3 py-2 text-sm"
                    >
                      <div className="mb-1 flex items-center gap-2">
                        <Badge variant={severityVariant(String(issue.severity ?? "info"))} className="text-[10px]">
                          {String(issue.severity ?? "info")}
                        </Badge>
                        <span className="font-mono text-[10px] text-muted-foreground">{issue.code}</span>
                      </div>
                      <p>{issueMessage(issue, isFa)}</p>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <Row
                label={td("broadcastQueuePending")}
                value={formatNumber(Number(data.broadcast_queue_pending ?? 0), isFa)}
              />
              <Row label={td("localInboundQueue")} value={formatNumber(localInboundPending, isFa)} />
              <Row label={td("outboundTest")} value={outboundTestLabel} />
            </div>
            <p className="text-xs text-muted-foreground">{td("outboundNote")}</p>

            <Collapsible>
              <CollapsibleTrigger className="text-sm font-medium hover:underline">
                {td("recentWebhookLogs")}
              </CollapsibleTrigger>
              <CollapsibleContent className="mt-2 space-y-1">
                {(data.recent_webhook_logs ?? []).length === 0 ? (
                  <p className="text-xs text-muted-foreground">{td("noLogs")}</p>
                ) : (
                  (data.recent_webhook_logs ?? []).map((log) => (
                    <div key={log.id} className="rounded border border-border/60 px-2 py-1 font-mono text-[11px]">
                      <span className="text-muted-foreground">{log.created_at}</span> [{log.level}] {log.message}
                    </div>
                  ))
                )}
              </CollapsibleContent>
            </Collapsible>
          </div>
        ) : null}

        <DashDialogFooter className={cn("gap-2")}>
          <Button type="button" variant="outline" onClick={onClose}>
            {t("adminIdCancel")}
          </Button>
          <Button type="button" variant="outline" disabled={loading} onClick={onSendTest}>
            <Send className={cn("size-3.5")} />
            {td("sendTest")}
          </Button>
          <Button
            type="button"
            variant="secondary"
            disabled={loading}
            onClick={() => void fetchDiagnostics({ revealToken: Boolean(tokenFull) })}
          >
            <RefreshCw className={cn("size-3.5", loading && "animate-spin")} />
            {td("refresh")}
          </Button>
        </DashDialogFooter>
      </DashDialogContent>
    </Dialog>
  )
}
