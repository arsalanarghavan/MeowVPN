"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import { DataPagination } from "@/components/data-pagination"
import { SiteSettingsSaveFeedback } from "@/components/site-settings/site-settings-save-feedback"
import { getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatServiceExpiryLine, formatNumber } from "@/lib/format-locale"
import { useDashLocale } from "@/lib/dash-locale-context"
import { useSiteSettingsSave } from "@/lib/use-site-settings-save"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

type PurgeRow = {
  id: number
  user_id: number
  remark: string
  expires_at: string
  days_since_expiry: number
  days_until_purge: number
  status: string
}

type PanelRow = { id: number; name: string }

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function daysToString(raw: unknown): string {
  if (Array.isArray(raw)) {
    return (raw as unknown[]).map((x) => String(Number(x))).filter((x) => x !== "NaN").join(",")
  }
  return String(raw ?? "7,3,1,0")
}

const PREVIEW_COLS = ["8%", "18%", "10%", "16%", "12%", "12%", "12%", "12%"]

export function SiteSettingsPurgeTab({
  settings,
  panels,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
  panels: PanelRow[]
  onMutateSuccess?: () => void
}) {
  const { t } = useTranslation()
  const { isFa, iconGapClass, ltrCell } = useDashLocale()
  const tp = (k: string, opts?: Record<string, string | number>) =>
    t(`siteSettings.purge.${k}`, opts)
  const s = settings ?? {}

  const initial = useMemo(
    () => ({
      purge_expired_enabled: bool(s.purge_expired_enabled),
      purge_expired_grace_days: String(Math.max(1, Math.min(365, num(s.purge_expired_grace_days) || 7))),
      purge_expired_warn_days: daysToString(s.purge_expired_warn_days ?? "7,3,1,0"),
      purge_expired_notify_user: bool(s.purge_expired_notify_user ?? true),
    }),
    [s]
  )

  const lastPurgeRun = useMemo(() => {
    const raw = s.last_purge_expired_run
    return raw && typeof raw === "object" ? (raw as Record<string, unknown>) : null
  }, [s.last_purge_expired_run])

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])

  const { saving, error, okMsg, saveSettingsTab } = useSiteSettingsSave(onMutateSuccess)

  const [statusFilter, setStatusFilter] = useState("all")
  const [page, setPage] = useState(1)
  const [perPage] = useState(25)
  const [rows, setRows] = useState<PurgeRow[]>([])
  const [totals, setTotals] = useState({ all: 0, in_grace: 0, ready: 0 })
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(false)
  const [listError, setListError] = useState<string | null>(null)
  const [actionMsg, setActionMsg] = useState<string | null>(null)
  const [actionErr, setActionErr] = useState<string | null>(null)
  const [actionBusy, setActionBusy] = useState(false)

  const [panelId, setPanelId] = useState("")
  const [immediateCount, setImmediateCount] = useState(0)
  const [immediateAck, setImmediateAck] = useState(false)
  const [expConfirm, setExpConfirm] = useState("")
  const [expBusy, setExpBusy] = useState(false)

  const [readyOpen, setReadyOpen] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<PurgeRow | null>(null)
  const [deleteEarly, setDeleteEarly] = useState(false)

  const loadPreview = useCallback(async () => {
    setLoading(true)
    setListError(null)
    try {
      const json = await getAdminJson("/dashboard/admin/purge-expired", {
        page,
        per_page: perPage,
        status: statusFilter,
        panel_id: panelId ? num(panelId) : 0,
      })
      if (!json.ok) {
        setListError(tp("actionFailed"))
        return
      }
      const items = Array.isArray(json.items) ? json.items : []
      setRows(
        items.map((row) => {
          const r = row as Record<string, unknown>
          return {
            id: num(r.id),
            user_id: num(r.user_id),
            remark: String(r.remark ?? ""),
            expires_at: String(r.expires_at ?? ""),
            days_since_expiry: num(r.days_since_expiry),
            days_until_purge: num(r.days_until_purge),
            status: String(r.status ?? ""),
          }
        })
      )
      const tot = json.totals as Record<string, unknown> | undefined
      setTotals({
        all: num(tot?.all),
        in_grace: num(tot?.in_grace),
        ready: num(tot?.ready),
      })
      const pag = json.pagination as Record<string, unknown> | undefined
      setPagination(
        parsePaginationMeta({
          page: num(pag?.page) || page,
          perPage: num(pag?.per_page) || perPage,
          total: num(pag?.total),
          totalPages: num(pag?.total_pages),
        })
      )
      const imm = json.immediate_batch as Record<string, unknown> | undefined
      setImmediateCount(num(imm?.count))
    } catch {
      setListError(tp("actionFailed"))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, panelId, statusFilter, tp])

  useEffect(() => {
    void loadPreview()
  }, [loadPreview])

  const onSave = useCallback(async () => {
    await saveSettingsTab("purge_expired", {
      purge_expired_enabled: form.purge_expired_enabled ? 1 : 0,
      purge_expired_grace_days: Math.max(1, Math.min(365, num(form.purge_expired_grace_days))),
      purge_expired_warn_days: form.purge_expired_warn_days.trim(),
      purge_expired_notify_user: form.purge_expired_notify_user ? 1 : 0,
    })
  }, [form, saveSettingsTab])

  const runMutation = useCallback(
    async (op: string, payload: Record<string, unknown> = {}) => {
      setActionBusy(true)
      setActionMsg(null)
      setActionErr(null)
      try {
        const res = await postAdminMutate(op, payload)
        if (!res.ok) {
          setActionErr(String(res.message ?? tp("actionFailed")))
          return false
        }
        setActionMsg(tp("actionOk"))
        onMutateSuccess?.()
        await loadPreview()
        return true
      } catch {
        setActionErr(tp("actionFailed"))
        return false
      } finally {
        setActionBusy(false)
      }
    },
    [loadPreview, onMutateSuccess, tp]
  )

  const runImmediate = useCallback(async () => {
    const pid = num(panelId)
    const expect = immediateCount
    const typed = num(expConfirm)
    if (pid < 1 || expect < 1 || !immediateAck || typed !== expect) return
    setExpBusy(true)
    setActionMsg(null)
    setActionErr(null)
    try {
      const res = await postAdminMutate("configs_delete_expired_linked", {
        panel_id: pid,
        confirm_count: typed,
      })
      if (!res.ok) {
        setActionErr(String(res.message ?? tp("actionFailed")))
        return
      }
      setActionMsg(tp("actionOk"))
      setExpConfirm("")
      setImmediateAck(false)
      onMutateSuccess?.()
      await loadPreview()
    } catch {
      setActionErr(tp("actionFailed"))
    } finally {
      setExpBusy(false)
    }
  }, [expConfirm, immediateAck, immediateCount, loadPreview, onMutateSuccess, panelId, tp])

  const confirmDeleteOne = useCallback(async () => {
    if (!deleteTarget) return
    const ok = await runMutation("purge_expired_purge_one", {
      service_id: deleteTarget.id,
      force_early: deleteEarly ? 1 : 0,
    })
    if (ok) {
      setDeleteTarget(null)
      setDeleteEarly(false)
    }
  }, [deleteEarly, deleteTarget, runMutation])

  const chk = (key: keyof typeof form, labelKey: string) => (
    <label className={iconGapClass("text-sm")}>
      <input
        type="checkbox"
        className="size-4 rounded border-input"
        checked={Boolean(form[key])}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.checked }))}
      />
      {tp(labelKey)}
    </label>
  )

  return (
    <div className="w-full space-y-6 text-start" data-testid="dash-purge-tab">
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("settingsTitle")}</CardTitle>
            <CardDescription>{tp("settingsDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {chk("purge_expired_enabled", "purgeEnabled")}
            <div className="space-y-2">
              <Label htmlFor="purge_grace">{tp("purgeGraceDays")}</Label>
              <Input
                id="purge_grace"
                type="number"
                min={1}
                max={365}
                value={form.purge_expired_grace_days}
                onChange={(e) => setForm((f) => ({ ...f, purge_expired_grace_days: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="purge_warn">{tp("purgeWarnDays")}</Label>
              <Input
                id="purge_warn"
                value={form.purge_expired_warn_days}
                onChange={(e) => setForm((f) => ({ ...f, purge_expired_warn_days: e.target.value }))}
                placeholder="7,3,1,0"
                dir="ltr"
                className={ltrCell("font-mono")}
              />
              <p className="text-xs text-muted-foreground">{tp("purgeWarnDaysHint")}</p>
            </div>
            {chk("purge_expired_notify_user", "purgeNotifyUser")}
            {lastPurgeRun && num(lastPurgeRun.at) > 0 ? (
              <p className="text-xs text-muted-foreground">
                {tp("purgeLastRun", {
                  at: new Date(num(lastPurgeRun.at) * 1000).toLocaleString(isFa ? "fa-IR" : undefined),
                  purged: formatNumber(num(lastPurgeRun.purged), isFa),
                  warned: formatNumber(num(lastPurgeRun.warned), isFa),
                  failed: formatNumber(num(lastPurgeRun.failed), isFa),
                })}
              </p>
            ) : (
              <p className="text-xs text-muted-foreground">{tp("purgeLastRunNever")}</p>
            )}
            <Button type="button" disabled={saving} onClick={() => void onSave()}>
              {saving ? tp("loading") : tp("save")}
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{tp("actionsTitle")}</CardTitle>
            <CardDescription>{tp("actionsDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Button type="button" variant="secondary" disabled={actionBusy} onClick={() => void runMutation("purge_expired_run_cron")}>
                {actionBusy ? tp("loading") : tp("runCron")}
              </Button>
              <p className="text-xs text-muted-foreground">{tp("runCronHint")}</p>
            </div>
            <div className="space-y-2">
              <Button type="button" variant="outline" disabled={actionBusy || totals.ready < 1} onClick={() => setReadyOpen(true)}>
                {tp("purgeReady")} ({formatNumber(totals.ready, isFa)})
              </Button>
              <p className="text-xs text-muted-foreground">{tp("purgeReadyHint")}</p>
            </div>
            <div className="space-y-3 rounded-lg border border-destructive/40 bg-destructive/5 p-4">
              <p className="text-sm font-medium">{tp("immediateTitle")}</p>
              <p className="text-xs text-muted-foreground">{tp("immediateDesc")}</p>
              <p className="text-xs text-muted-foreground">{tp("immediateHint")}</p>
              <div className="space-y-2">
                <Label>{tp("selectPanel")}</Label>
                <DashSelect
                  value={panelId || ""}
                  onValueChange={setPanelId}
                  allowEmpty
                  placeholder={tp("selectPanelNone")}
                  options={panels.map((p) => ({ value: String(p.id), label: p.name }))}
                />
              </div>
              {panelId && immediateCount > 0 ? (
                <>
                  <label className={cn("flex items-start gap-2 text-xs text-muted-foreground")}>
                    <input
                      type="checkbox"
                      className="mt-0.5"
                      checked={immediateAck}
                      onChange={(e) => setImmediateAck(e.target.checked)}
                    />
                    <span>{tp("immediateAck")}</span>
                  </label>
                  <div className={cn("flex flex-wrap items-end gap-2")}>
                    <div className="grid gap-1">
                      <Label className="text-xs">{tp("confirmCount")}</Label>
                      <Input
                        className="w-40"
                        inputMode="numeric"
                        value={expConfirm}
                        onChange={(e) => setExpConfirm(e.target.value)}
                        placeholder={String(immediateCount)}
                        dir="ltr"
                      />
                    </div>
                    <Button
                      type="button"
                      variant="destructive"
                      disabled={expBusy || !immediateAck || num(expConfirm) !== immediateCount}
                      onClick={() => void runImmediate()}
                    >
                      {expBusy ? tp("loading") : tp("runImmediate")}
                    </Button>
                  </div>
                </>
              ) : panelId ? (
                <p className="text-xs text-muted-foreground">{tp("noRows")}</p>
              ) : null}
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-2">
          <div>
            <CardTitle className="text-base">{tp("previewTitle")}</CardTitle>
            <CardDescription>{tp("previewDesc")}</CardDescription>
          </div>
          <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void loadPreview()}>
            {tp("refresh")}
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-muted-foreground">
            {tp("totalsSummary", {
              all: formatNumber(totals.all, isFa),
              inGrace: formatNumber(totals.in_grace, isFa),
              ready: formatNumber(totals.ready, isFa),
            })}
          </p>
          <div className="flex flex-wrap gap-2">
            {(["all", "in_grace", "ready"] as const).map((f) => (
              <Button
                key={f}
                type="button"
                size="sm"
                variant={statusFilter === f ? "default" : "outline"}
                onClick={() => {
                  setStatusFilter(f)
                  setPage(1)
                }}
              >
                {tp(f === "all" ? "filterAll" : f === "in_grace" ? "filterInGrace" : "filterReady")}
              </Button>
            ))}
          </div>
          {listError ? <p className="text-sm text-destructive">{listError}</p> : null}
          {actionMsg ? <p className="text-sm text-green-600 dark:text-green-400">{actionMsg}</p> : null}
          {actionErr ? <p className="text-sm text-destructive">{actionErr}</p> : null}
          <DashTableShell colWidths={PREVIEW_COLS}>
            <thead>
              <tr>
                <DashTh>{tp("colId")}</DashTh>
                <DashTh>{tp("colRemark")}</DashTh>
                <DashTh>{tp("colUser")}</DashTh>
                <DashTh>{tp("colExpires")}</DashTh>
                <DashTh>{tp("colDaysSince")}</DashTh>
                <DashTh>{tp("colDaysUntil")}</DashTh>
                <DashTh>{tp("colStatus")}</DashTh>
                <DashTh>{tp("colActions")}</DashTh>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <DashTd colSpan={8}>{tp("loading")}</DashTd>
                </tr>
              ) : rows.length < 1 ? (
                <tr>
                  <DashTd colSpan={8}>{tp("noRows")}</DashTd>
                </tr>
              ) : (
                rows.map((row) => (
                  <tr key={row.id}>
                    <DashTd className={ltrCell("font-mono")}>{row.id}</DashTd>
                    <DashTd>{row.remark || "—"}</DashTd>
                    <DashTd className={ltrCell("font-mono")}>{row.user_id}</DashTd>
                    <DashTd className={ltrCell()}>{formatServiceExpiryLine(row.expires_at, isFa)}</DashTd>
                    <DashTd className={ltrCell()}>{formatNumber(row.days_since_expiry, isFa)}</DashTd>
                    <DashTd className={ltrCell()}>{formatNumber(row.days_until_purge, isFa)}</DashTd>
                    <DashTd>
                      <Badge variant={row.status === "ready" ? "destructive" : "secondary"}>
                        {row.status === "ready" ? tp("statusReady") : tp("statusInGrace")}
                      </Badge>
                    </DashTd>
                    <DashTd>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={actionBusy}
                        onClick={() => {
                          setDeleteTarget(row)
                          setDeleteEarly(row.status === "in_grace")
                        }}
                      >
                        {row.status === "in_grace" ? tp("deleteOneEarly") : tp("deleteOne")}
                      </Button>
                    </DashTd>
                  </tr>
                ))
              )}
            </tbody>
          </DashTableShell>
          {pagination ? (
            <DataPagination meta={pagination} isFa={isFa} onPageChange={setPage} />
          ) : null}
        </CardContent>
      </Card>

      <SiteSettingsSaveFeedback error={error} okMsg={okMsg} />

      <AlertDialog open={readyOpen} onOpenChange={setReadyOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{tp("purgeReady")}</AlertDialogTitle>
            <AlertDialogDescription>{tp("purgeReadyConfirm")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tp("cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                setReadyOpen(false)
                void runMutation("purge_expired_purge_ready", { confirm: 1 })
              }}
            >
              {tp("purgeReady")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{deleteEarly ? tp("deleteOneEarly") : tp("deleteOne")}</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteEarly
                ? tp("deleteOneEarlyConfirm")
                : tp("deleteOneConfirm", {
                    id: deleteTarget?.id ?? 0,
                    remark: deleteTarget?.remark ?? "",
                  })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tp("cancel")}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDeleteOne()}>{tp("deleteOne")}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
