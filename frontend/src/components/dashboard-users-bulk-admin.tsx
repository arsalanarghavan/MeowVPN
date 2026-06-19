"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"
import { Badge } from "@/components/ui/badge"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashPage } from "@/components/dash-page"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Separator } from "@/components/ui/separator"
import { Textarea } from "@/components/ui/textarea"
import { DataPagination } from "@/components/data-pagination"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { type PaginationMeta, parsePaginationMeta } from "@/lib/dash-pagination"
import { formatNumber, formatNumericString } from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

import { DashSelect } from "@/components/dash-select"

type BulkOp = "wallet" | "volume" | "extend" | "slots" | "alerts"
type JobRow = Record<string, unknown>
type JobItemRow = Record<string, unknown>
type DashRecord = Record<string, unknown>

type BulkAggRow = { jobId: number; status: string; count: number }

type JobStats = {
  total: number
  pending: number
  processing: number
  done: number
  failed: number
  skipped: number
}

type InboundRow = { id: number; remark: string; port: number; protocol: string }

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function panelLabel(p: DashRecord): string {
  return String(p.label ?? p.xp_label ?? p.name ?? `#${num(p.id)}`)
}

function parseJobPayload(raw: unknown): Record<string, unknown> {
  if (raw && typeof raw === "object") return raw as Record<string, unknown>
  if (typeof raw === "string" && raw.trim()) {
    try {
      const j = JSON.parse(raw) as unknown
      if (j && typeof j === "object") return j as Record<string, unknown>
    } catch {
      /* ignore */
    }
  }
  return {}
}

function parseAggs(raw: unknown): BulkAggRow[] {
  if (!Array.isArray(raw)) return []
  const out: BulkAggRow[] = []
  for (const x of raw) {
    if (!x || typeof x !== "object") continue
    const r = x as Record<string, unknown>
    out.push({
      jobId: num(r.jobId ?? r.job_id),
      status: String(r.status ?? ""),
      count: num(r.count),
    })
  }
  return out
}

function emptyJobStats(): JobStats {
  return { total: 0, pending: 0, processing: 0, done: 0, failed: 0, skipped: 0 }
}

function summarizeBulkJob(jobId: number, rows: BulkAggRow[]): JobStats {
  const s = emptyJobStats()
  for (const a of rows) {
    if (a.jobId !== jobId || a.count <= 0) continue
    s.total += a.count
    const st = a.status
    if (st === "pending") s.pending += a.count
    else if (st === "processing") s.processing += a.count
    else if (st === "success" || st === "done") s.done += a.count
    else if (st === "failed") s.failed += a.count
    else if (st === "cancelled" || st === "skipped") s.skipped += a.count
  }
  return s
}

function itemStatusLabel(st: string, t: (k: string, opts?: { defaultValue?: string }) => string): string {
  const k = `usersBulkAdmin.itemStatus_${st}`
  return t(k, { defaultValue: st })
}

function StatBox({
 label, value }: { label: string; value: number; }) {
  const { isFa } = useDashLocale()

  return (
    <div className="rounded-md border border-border/80 bg-muted/30 px-2 py-1.5">
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className={cn("text-lg font-semibold tabular-nums")}>{formatNumber(value, isFa)}</div>
    </div>
  )
}

function BulkJobItemsBlock({
 jobId }: { jobId: number; }) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`usersBulkAdmin.${k}`, opts)
  const [open, setOpen] = useState(false)
  const [page, setPage] = useState(1)
  const perPage = 25
  const [loading, setLoading] = useState(false)
  const [items, setItems] = useState<JobItemRow[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [detailItem, setDetailItem] = useState<JobItemRow | null>(null)

  useEffect(() => {
    if (!open) return
    let cancelled = false
    setLoading(true)
    void getAdminJson("/dashboard/admin/users-bulk-job-items", {
      job_id: jobId,
      page,
      per_page: perPage,
    })
      .then((json) => {
        if (cancelled) return
        setItems(Array.isArray(json.rows) ? (json.rows as JobItemRow[]) : [])
        setMeta(
          parsePaginationMeta(
            json.pagination ?? {
              page: num(json.page),
              perPage: num(json.perPage),
              total: num(json.total),
            }
          )
        )
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [open, jobId, page])

  return (
    <div className="border-t pt-3">
      {!open ? (
        <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)}>
          {tp("viewJobReport")}
        </Button>
      ) : (
        <div className="space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-sm font-medium">{tp("viewJobReport")}</p>
            <Button type="button" variant="ghost" size="sm" onClick={() => setOpen(false)}>
              {tp("hideJobReport")}
            </Button>
          </div>
          {loading ? (
            <p className="text-xs text-muted-foreground">{tp("reportLoading")}</p>
          ) : items.length === 0 ? (
            <p className="text-xs text-muted-foreground">{tp("reportEmpty")}</p>
          ) : (
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full min-w-[32rem] text-sm">
                <thead>
                  <tr className="bg-muted/40">
                    <th className="p-2">{tp("affectedUsers")}</th>
                    <th className="p-2">{tp("colPanelClient")}</th>
                    <th className="p-2">{tp("colItemStatus")}</th>
                    <th className="p-2">{tp("colTries")}</th>
                    <th className="p-2" />
                  </tr>
                </thead>
                <tbody>
                  {items.map((it) => {
                    const uid = num(it.user_id)
                    const pid = num(it.panel_id)
                    const em = String(it.client_email ?? "")
                    const clientLabel =
                      pid > 0 && em ? `panel ${pid} · ${em}` : pid > 0 ? `panel ${pid}` : em || "—"
                    return (
                      <tr key={String(it.id ?? "")} className="border-t">
                        <td className="p-2 font-mono tabular-nums">
                          {uid > 0 ? formatNumericString(String(uid), isFa) : "—"}
                        </td>
                        <td className="p-2 text-xs text-muted-foreground">{clientLabel}</td>
                        <td className="p-2">{itemStatusLabel(String(it.status ?? ""), t)}</td>
                        <td className="p-2 tabular-nums">{String(it.tries ?? "0")}</td>
                        <td className="p-2">
                          <Button type="button" variant="secondary" size="sm" onClick={() => setDetailItem(it)}>
                            {tp("jobDetailTitle")}
                          </Button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
          {meta && meta.total > perPage ? (
            <DataPagination meta={meta}
        onPageChange={(p) => setPage(p)} onPerPageChange={() => {}} />
          ) : null}
        </div>
      )}

      <Dialog open={detailItem !== null} onOpenChange={(v) => !v && setDetailItem(null)}>
        <DashDialogContent className={cn("sm:max-w-lg")}>
          <DashDialogHeader>
            <DialogTitle>{tp("jobDetailTitle")}</DialogTitle>
            <DialogDescription className="font-mono tabular-nums">
              {detailItem ? `#${formatNumericString(String(detailItem.id ?? ""), isFa)}` : ""}
            </DialogDescription>
          </DashDialogHeader>
          {detailItem ? (
            <ul className="space-y-2 text-sm">
              <li>
                <span className="text-muted-foreground">{tp("affectedUsers")}: </span>
                <span className="font-mono">{String(detailItem.user_id ?? "—")}</span>
              </li>
              <li>
                <span className="text-muted-foreground">{tp("colPanelClient")}: </span>
                {String(detailItem.client_email ?? "—")}
                {num(detailItem.panel_id) > 0 ? ` · panel ${num(detailItem.panel_id)}` : ""}
              </li>
              <li>
                <span className="text-muted-foreground">{tp("colItemStatus")}: </span>
                {itemStatusLabel(String(detailItem.status ?? ""), t)}
              </li>
              <li>
                <span className="text-muted-foreground">{tp("colTries")}: </span>
                {String(detailItem.tries ?? "0")}
              </li>
              {detailItem.last_error ? (
                <li>
                  <span className="text-muted-foreground">{tp("colReason")}: </span>
                  <pre className="mt-1 max-h-32 overflow-auto whitespace-pre-wrap break-all text-xs">
                    {String(detailItem.last_error)}
                  </pre>
                </li>
              ) : null}
            </ul>
          ) : null}
          <DashDialogFooter>
            <Button type="button" variant="secondary" onClick={() => setDetailItem(null)}>
              {tp("close")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </div>
  )
}

export function DashboardUsersBulkAdmin({
  panels = [],
  onMutateSuccess,
  canRunBulkWorker = true,
}: {
  panels?: DashRecord[]
onMutateSuccess?: () => void
  canRunBulkWorker?: boolean
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`usersBulkAdmin.${k}`, opts)
  const isResellerActor = Boolean(window.__SIMPLEVPBOT_DASH__?.isReseller)

  const [scope, setScope] = useState("all_approved")
  const [customIds, setCustomIds] = useState("")
  const [panelId, setPanelId] = useState(0)
  const [inboundId, setInboundId] = useState(0)
  const [inbounds, setInbounds] = useState<InboundRow[]>([])
  const [inboundsBusy, setInboundsBusy] = useState(false)

  const [op, setOp] = useState<BulkOp>("wallet")
  const [delta, setDelta] = useState("")
  const [extraGb, setExtraGb] = useState("10")
  const [days, setDays] = useState("3")
  const [extraUsers, setExtraUsers] = useState("1")
  const [reduceMode, setReduceMode] = useState(false)
  const [notify, setNotify] = useState(true)
  const [notifyMessage, setNotifyMessage] = useState("")
  const [alertsEnabled, setAlertsEnabled] = useState(true)
  const [alertsVolume, setAlertsVolume] = useState(true)
  const [alertsExpiry, setAlertsExpiry] = useState(true)
  const [alertsUsers, setAlertsUsers] = useState(true)

  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [drySummary, setDrySummary] = useState<string | null>(null)
  const [jobs, setJobs] = useState<JobRow[]>([])
  const [itemAggregates, setItemAggregates] = useState<BulkAggRow[]>([])
  const [jobsPage, setJobsPage] = useState(1)
  const [jobsPerPage, setJobsPerPage] = useState(10)
  const [jobsMeta, setJobsMeta] = useState<PaginationMeta | null>(null)
  const [processLoading, setProcessLoading] = useState(false)
  const [processNotice, setProcessNotice] = useState<string | null>(null)

  const activePanels = useMemo(
    () => panels.filter((p) => num(p.id) > 0 && (p.active === true || p.active === 1 || p.active === "1")),
    [panels]
  )

  const loadInbounds = useCallback(async (pid: number) => {
    if (pid < 1) {
      setInbounds([])
      setInboundId(0)
      return
    }
    setInboundsBusy(true)
    try {
      const json = await getAdminJson("/dashboard/admin/panel-inbounds", { panel_id: pid })
      const rows = Array.isArray(json.inbounds) ? (json.inbounds as InboundRow[]) : []
      setInbounds(rows)
      setInboundId(0)
    } catch {
      setInbounds([])
    } finally {
      setInboundsBusy(false)
    }
  }, [])

  useEffect(() => {
    void loadInbounds(panelId)
  }, [panelId, loadInbounds])

  const loadJobs = useCallback(async () => {
    const r = await getAdminJson("/dashboard/admin/users-bulk-jobs", {
      page: jobsPage,
      per_page: jobsPerPage,
    })
    setJobs(Array.isArray(r.jobs) ? (r.jobs as JobRow[]) : [])
    setItemAggregates(parseAggs(r.itemAggregates))
    setJobsMeta(parsePaginationMeta(r.pagination))
  }, [jobsPage, jobsPerPage])

  useEffect(() => {
    void loadJobs()
  }, [loadJobs])

  function filterPayload(): Record<string, unknown> {
    return {
      panel_id: panelId > 0 ? panelId : 0,
      inbound_id: panelId > 0 && inboundId > 0 ? inboundId : 0,
    }
  }

  function scopePayload(): Record<string, unknown> {
    if (scope === "custom_ids") {
      const ids = customIds
        .split(/[\s,]+/)
        .map((s) => parseInt(s.trim(), 10))
        .filter((n) => n > 0)
      return { scope: "custom_ids", user_ids: ids, ...filterPayload() }
    }
    return { scope, ...filterPayload() }
  }

  function formatDryRun(data: Record<string, unknown> | undefined) {
    if (!data) {
      setDrySummary(null)
      return
    }
    const users = num(data.user_count)
    const services = num(data.service_count)
    const panelTargets = num(data.panel_target_count)
    const targetCount = panelTargets > 0 ? panelTargets : services
    if (targetCount < 1 && users < 1) {
      setDrySummary(tp("dryRunEmpty"))
      return
    }
    let line =
      panelTargets > 0
        ? tp("dryRunPanelSummary", {
            targets: formatNumber(panelTargets, isFa),
            users: formatNumber(users, isFa),
          })
        : tp("dryRunSummary", {
            users: formatNumber(users, isFa),
            services: formatNumber(services, isFa),
          })
    const pid = num(data.panel_id)
    const iid = num(data.inbound_id)
    if (pid > 0) line += ` · ${tp("dryRunSummaryPanel", { panel: pid })}`
    if (iid > 0) line += ` · ${tp("dryRunSummaryInbound", { inbound: iid })}`
    setDrySummary(line)
  }

  async function runMutation(dryRun: boolean) {
    setBusy(true)
    setErr("")
    if (!dryRun) setDrySummary(null)
    try {
      const base = {
        ...scopePayload(),
        dry_run: dryRun,
        notify,
        ...(op === "volume" || op === "extend" || op === "slots" ? { notify_message: notifyMessage } : {}),
      }
      let res
      if (op === "wallet") {
        res = await postAdminMutate("users_bulk_wallet", { ...base, delta: Number(delta) })
      } else if (op === "volume") {
        res = await postAdminMutate("users_bulk_volume", {
          ...base,
          extra_gb: Number(extraGb),
          reduce: reduceMode ? 1 : 0,
        })
      } else if (op === "extend") {
        res = await postAdminMutate("users_bulk_extend", {
          ...base,
          days: Number(days),
          reduce: reduceMode ? 1 : 0,
        })
      } else if (op === "slots") {
        res = await postAdminMutate("users_bulk_slots", {
          ...base,
          extra_users: Number(extraUsers),
          reduce: reduceMode ? 1 : 0,
        })
      } else {
        res = await postAdminMutate("users_bulk_alerts", {
          ...base,
          alerts_enabled: alertsEnabled ? 1 : 0,
          alerts_volume: alertsVolume ? 1 : 0,
          alerts_expiry: alertsExpiry ? 1 : 0,
          alerts_users: alertsUsers ? 1 : 0,
        })
      }
      if (!res.ok) {
        setErr(res.message || tp("error"))
        return
      }
      const data = res.data as Record<string, unknown> | undefined
      if (dryRun) {
        formatDryRun(data)
      } else {
        onMutateSuccess?.()
        setJobsPage(1)
        await loadJobs()
      }
    } finally {
      setBusy(false)
    }
  }

  const runProcessQueue = useCallback(async () => {
    if (!canRunBulkWorker) return
    setProcessLoading(true)
    setProcessNotice(null)
    setErr("")
    try {
      const res = await postAdminMutate("users_bulk_run_worker", { max_iterations: 20 })
      if (!res.ok) {
        setErr(res.message || tp("error"))
        return
      }
      const n = res.iterations ?? 0
      setProcessNotice(tp("processQueueDone", { batches: n }))
      await loadJobs()
      onMutateSuccess?.()
    } finally {
      setProcessLoading(false)
    }
  }, [canRunBulkWorker, loadJobs, onMutateSuccess, tp])

  const showReduce = op === "volume" || op === "extend" || op === "slots"
  const showServiceNotify = op === "volume" || op === "extend" || op === "slots"

  const notifyMessagePlaceholder = useMemo(() => {
    if (op === "volume") return tp("notifyPlaceholderVolume")
    if (op === "extend") return tp("notifyPlaceholderExtend")
    if (op === "slots") return tp("notifyPlaceholderSlots")
    return ""
  }, [op, tp])

  return (
    <DashPage className={"w-full space-y-8"} data-testid="dash-bulk-tab">
      <DashboardPageHeader
        title={tp("title")}
        description={
          <>
            <p className="text-sm text-muted-foreground">{tp("subtitle")}</p>
            <p className="mt-1 text-xs text-muted-foreground">{tp("cronHint")}</p>
          </>
        }
        actions={
          canRunBulkWorker ? (
            <>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                disabled={processLoading}
                onClick={() => void runProcessQueue()}
              >
                {processLoading ? tp("processQueueRunning") : tp("processQueueNow")}
              </Button>
              {processNotice ? <span className="text-xs text-muted-foreground">{processNotice}</span> : null}
            </>
          ) : undefined
        }
      />

      {err ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {err}
        </div>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>{tp("composeTitle")}</CardTitle>
          <CardDescription>{tp("composeHint")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-6 xl:grid-cols-2 xl:items-start">
            <div className="space-y-4">
              <div className="space-y-3 rounded-md border border-border/60 p-3">
                <p className="text-sm font-medium">{tp("scope")}</p>
                <p className="text-xs text-muted-foreground">{tp("scopeHint")}</p>
                {isResellerActor ? (
                  <p className="text-xs text-amber-700 dark:text-amber-400">{tp("resellerScopeHint")}</p>
                ) : null}
                <div className="space-y-2">
                  <Label>{tp("scopeLabel")}</Label>
                  <DashSelect
                    value={scope}
                    onValueChange={setScope}
                    disabled={busy}
                    options={[
                      { value: "all_approved", label: tp("scopeAllApproved") },
                      { value: "approved_with_active_service", label: tp("scopeActiveSvc") },
                      { value: "panel_active_clients", label: tp("scopePanelActive") },
                      { value: "custom_ids", label: tp("scopeCustom") },
                    ]}
                  />
                  {(op === "volume" || op === "extend") &&
                  (scope === "approved_with_active_service" || scope === "panel_active_clients") ? (
                    <p className="text-xs text-muted-foreground">{tp("scopePanelVolumeHint")}</p>
                  ) : null}
                </div>
                {scope === "custom_ids" ? (
                  <div className="space-y-2">
                    <Label htmlFor="ids">{tp("customIds")}</Label>
                    <Input
                      id="ids"
                      dir="ltr"
                      value={customIds}
                      onChange={(e) => setCustomIds(e.target.value)}
                      placeholder={tp("customIdsPlaceholder")}
                      disabled={busy}
                    />
                  </div>
                ) : null}
              </div>

              <div className="space-y-3 rounded-md border border-border/60 p-3">
                <p className="text-sm font-medium">{tp("filterServerTitle")}</p>
                <p className="text-xs text-muted-foreground">{tp("filterServerHint")}</p>
                <div className="space-y-2">
                  <Label>{tp("filterPanel")}</Label>
                  <DashSelect
                    value={String(panelId)}
                    onValueChange={(v) => setPanelId(parseInt(v, 10) || 0)}
                    disabled={busy}
                    options={[
                      { value: "0", label: tp("filterAllPanels") },
                      ...activePanels.map((p) => ({
                        value: String(num(p.id)),
                        label: panelLabel(p),
                      })),
                    ]}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{tp("filterInbound")}</Label>
                  <DashSelect
                    value={String(inboundId)}
                    onValueChange={(v) => setInboundId(parseInt(v, 10) || 0)}
                    disabled={busy || panelId < 1 || inboundsBusy}
                    options={[
                      { value: "0", label: tp("filterAllInbounds") },
                      ...inbounds.map((ib) => ({
                        value: String(ib.id),
                        label: `#${ib.id} ${ib.remark ? `— ${ib.remark}` : ""} (${ib.protocol}:${ib.port})`,
                      })),
                    ]}
                  />
                </div>
              </div>

              <div className="space-y-3">
                <div className="space-y-2">
                  <Label>{tp("operation")}</Label>
                  <DashSelect
                    value={op}
                    onValueChange={(v) => setOp(v as BulkOp)}
                    disabled={busy}
                    options={[
                      { value: "wallet", label: tp("opWallet") },
                      { value: "volume", label: tp("opVolume") },
                      { value: "extend", label: tp("opExtend") },
                      { value: "slots", label: tp("opSlots") },
                      { value: "alerts", label: tp("opAlerts") },
                    ]}
                  />
                </div>

                {showReduce ? (
                  <div className={cn("flex flex-wrap gap-2")}>
                    <Button
                      type="button"
                      size="sm"
                      variant={!reduceMode ? "default" : "outline"}
                      disabled={busy}
                      onClick={() => setReduceMode(false)}
                    >
                      {tp("directionAdd")}
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant={reduceMode ? "default" : "outline"}
                      disabled={busy}
                      onClick={() => setReduceMode(true)}
                    >
                      {tp("directionReduce")}
                    </Button>
                  </div>
                ) : null}

                {op === "wallet" ? (
                  <div className="space-y-2">
                    <Label htmlFor="delta">{tp("delta")}</Label>
                    <Input
                      id="delta"
                      dir="ltr"
                      value={delta}
                      onChange={(e) => setDelta(e.target.value)}
                      placeholder={tp("deltaPlaceholder")}
                      disabled={busy}
                    />
                  </div>
                ) : null}

                {op === "volume" ? (
                  <div className="space-y-2">
                    <Label htmlFor="gb">{tp("extraGb")}</Label>
                    <Input
                      id="gb"
                      dir="ltr"
                      value={extraGb}
                      onChange={(e) => setExtraGb(e.target.value)}
                      disabled={busy}
                    />
                  </div>
                ) : null}

                {op === "extend" ? (
                  <div className="space-y-2">
                    <Label htmlFor="days">{tp("days")}</Label>
                    <Input id="days" dir="ltr" value={days} onChange={(e) => setDays(e.target.value)} disabled={busy} />
                  </div>
                ) : null}

                {op === "slots" ? (
                  <div className="space-y-2">
                    <Label htmlFor="slots">{tp("extraUsers")}</Label>
                    <Input
                      id="slots"
                      dir="ltr"
                      value={extraUsers}
                      onChange={(e) => setExtraUsers(e.target.value)}
                      disabled={busy}
                    />
                  </div>
                ) : null}

                {op === "alerts" ? (
                  <div className="grid gap-2 sm:grid-cols-2">
                    {(
                      [
                        ["alertsEnabled", alertsEnabled, setAlertsEnabled, "alertMaster"],
                        ["alertsVolume", alertsVolume, setAlertsVolume, "alertVolume"],
                        ["alertsExpiry", alertsExpiry, setAlertsExpiry, "alertExpiry"],
                        ["alertsUsers", alertsUsers, setAlertsUsers, "alertUsers"],
                      ] as const
                    ).map(([key, checked, setter, labelKey]) => (
                      <label key={key} className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          className="size-4 rounded border-input accent-primary"
                          checked={checked}
                          disabled={busy}
                          onChange={(e) => setter(e.target.checked)}
                        />
                        {tp(labelKey)}
                      </label>
                    ))}
                  </div>
                ) : null}

                {op === "wallet" ? (
                  <label className={cn("flex items-center gap-2 text-sm")}>
                    <input
                      type="checkbox"
                      className="size-4 rounded border-input accent-primary"
                      checked={notify}
                      onChange={(e) => setNotify(e.target.checked)}
                      disabled={busy}
                    />
                    {tp("notifyUsers")}
                  </label>
                ) : null}

                {showServiceNotify ? (
                  <div className="space-y-3 rounded-md border border-border/60 bg-muted/20 p-3">
                    <p className="text-sm font-medium">{tp("notifySection")}</p>
                    <label className={cn("flex items-center gap-2 text-sm")}>
                      <input
                        type="checkbox"
                        className="size-4 rounded border-input accent-primary"
                        checked={notify}
                        onChange={(e) => setNotify(e.target.checked)}
                        disabled={busy}
                      />
                      {tp("notifyUsers")}
                    </label>
                    {notify ? (
                      <div className="space-y-2">
                        <Label htmlFor="notify-message">{tp("notifyMessage")}</Label>
                        <Textarea
                          id="notify-message"
                          rows={4}
                          value={notifyMessage}
                          onChange={(e) => setNotifyMessage(e.target.value)}
                          placeholder={notifyMessagePlaceholder}
                          disabled={busy}
                          className="text-start"
                        />
                        <p className="text-xs text-muted-foreground">{tp("notifyMessageHint")}</p>
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </div>
            </div>

            <Card className="border-dashed">
              <CardHeader className="pb-2">
                <CardTitle className="text-base">{tp("previewDryRun")}</CardTitle>
              </CardHeader>
              <CardContent>
                {drySummary ? (
                  <p className="text-sm whitespace-pre-wrap" role="status">
                    {drySummary}
                  </p>
                ) : (
                  <p className="text-sm text-muted-foreground">{tp("previewDryRunEmpty")}</p>
                )}
              </CardContent>
            </Card>
          </div>

          <Separator />

          <div className={cn("flex flex-wrap gap-2")}>
            <Button type="button" variant="secondary" disabled={busy} onClick={() => void runMutation(true)}>
              {tp("dryRun")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void runMutation(false)}>
              {tp("execute")}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Separator />

      <div className="space-y-3">
        <h3 className="text-base font-medium">{tp("historyTitle")}</h3>
        {jobs.length === 0 ? (
          <p className="text-sm text-muted-foreground">{tp("historyEmpty")}</p>
        ) : (
          <ul className="space-y-4">
            {jobs.map((j) => {
              const jid = num(j.id)
              const st = String(j.status ?? "pending")
              const pl = parseJobPayload(j.payload_json)
              const pid = num(pl.panel_id)
              const iid = num(pl.inbound_id)
              const q = summarizeBulkJob(jid, itemAggregates)
              const canStop = st === "pending" || st === "processing"
              const canResume = st === "cancelled"
              const badgeVariant =
                st === "done" || st === "cancelled" ? "secondary" : st === "processing" ? "default" : "outline"
              const desc = [
                String(j.operation ?? ""),
                String(j.scope ?? ""),
                pid > 0 ? `panel ${pid}` : "",
                iid > 0 ? `inbound ${iid}` : "",
              ]
                .filter(Boolean)
                .join(" · ")
              return (
                <li key={jid}>
                  <Card>
                    <CardHeader className="space-y-1 pb-2">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <CardTitle className="text-base font-mono">
                          #{formatNumericString(String(jid), isFa)}
                        </CardTitle>
                        <div className="flex flex-wrap items-center gap-2">
                          {canStop ? (
                            <Button
                              type="button"
                              variant="destructive"
                              size="sm"
                              onClick={async () => {
                                await postAdminMutate("users_bulk_job_cancel", { job_id: jid })
                                await loadJobs()
                              }}
                            >
                              {tp("jobStop")}
                            </Button>
                          ) : null}
                          {canResume ? (
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={async () => {
                                await postAdminMutate("users_bulk_job_resume", { job_id: jid })
                                await loadJobs()
                              }}
                            >
                              {tp("jobResume")}
                            </Button>
                          ) : null}
                          <Badge variant={badgeVariant}>
                            {t(`usersBulkAdmin.jobStatus_${st}`, { defaultValue: tp("jobStatus_unknown") })}
                          </Badge>
                        </div>
                      </div>
                      <CardDescription>{desc || "—"}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <StatBox label={tp("statTotal")} value={q.total}
        />
                        <StatBox label={tp("statPending")} value={q.pending}
        />
                        <StatBox label={tp("statProcessing")} value={q.processing}
        />
                        <StatBox label={tp("statDone")} value={q.done}
        />
                        <StatBox label={tp("statFailed")} value={q.failed}
        />
                        <StatBox label={tp("statSkipped")} value={q.skipped}
        />
                      </div>
                      <BulkJobItemsBlock jobId={jid}
        />
                    </CardContent>
                  </Card>
                </li>
              )
            })}
          </ul>
        )}
        <DataPagination
          meta={jobsMeta}
        onPageChange={(p) => setJobsPage(p)}
          onPerPageChange={(n) => {
            setJobsPerPage(n)
            setJobsPage(1)
          }}
        />
      </div>
    </DashPage>
  )
}
