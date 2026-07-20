"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
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
import {
  downloadAdminBackupFile,
  getAdminJson,
  postAdminFormData,
  postAdminJson,
  postAdminMutate,
} from "@/lib/dash-admin-mutate"
import { formatNumber, formatServiceExpiryLine } from "@/lib/format-locale"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DashSelect } from "@/components/dash-select"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DataPagination } from "@/components/data-pagination"

type BackupRow = {
  filename: string
  size_bytes: number
  created_at: number | string
  has_panel_db: boolean
  panel_db_status?: string
  panel_db_detail?: string
}

type DashRecord = Record<string, unknown>

type DeliveryBucket = {
  enabled?: boolean
  ok?: number
  fail?: number
  skipped?: number
}

type BackupRunData = {
  message?: string
  panel_db_warning?: string
  panel_db_critical?: boolean
  panel_db_critical_msg?: string
  panel_db_failures?: Array<{ panel_id?: number; label?: string; step?: string; getdb_url?: string }>
  sent?: number
  stored_on_site?: boolean
  storage_fallback?: boolean
  delivery?: Record<string, DeliveryBucket>
}

type LastBackupRun = {
  at?: number
  built?: boolean
  sent?: number
  failed?: number
  skipped_reason?: string
  delivery?: Record<string, DeliveryBucket>
}

type PanelOption = {
  id: number
  label: string
}

type DbInboundRow = {
  id: number
  remark?: string
  port?: number
  protocol?: string
  service_count?: number
  on_panel_now?: boolean
}

type PanelInboundRow = {
  id: number
  remark?: string
  port?: number
  protocol?: string
}

type RebuildTotals = {
  created?: number
  patched?: number
  skipped?: number
  failed?: number
}

type RestoreStats = {
  users_matched?: number
  users_inserted?: number
  users_skipped?: number
  errors?: unknown[]
  panel_restore?: { ok_count?: number; fail_count?: number }
  panel_db_restored?: number
  panel_db_errors?: unknown[]
}

const BACKUP_POLL_INTERVAL_MS = 3000
const BACKUP_POLL_MAX_MS = 10 * 60 * 1000
const BACKUP_POLL_LONG_HINT_MS = 2 * 60 * 1000

const SKIPPED_REASON_KEYS: Record<string, string> = {
  lock: "skippedReasonLock",
  enabled: "skippedReasonEnabled",
  zip: "skippedReasonZip",
  max_size: "skippedReasonMaxSize",
}

function sleepMs(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function formatBytes(bytes: number, isFa: boolean): string {
  if (bytes < 1024) return `${formatNumber(bytes, isFa)} B`
  if (bytes < 1024 * 1024) return `${formatNumber(Math.round(bytes / 1024), isFa)} KB`
  return `${formatNumber(Math.round((bytes / (1024 * 1024)) * 10) / 10, isFa)} MB`
}

function tsLabel(unix: number, isFa: boolean): string {
  if (unix < 1) return "—"
  return formatServiceExpiryLine(new Date(unix * 1000).toISOString(), isFa)
}

function backupRowDateLabel(createdAt: number | string | undefined, isFa: boolean): string {
  if (createdAt == null || createdAt === "") return "—"
  if (typeof createdAt === "number" && createdAt > 0) return tsLabel(createdAt, isFa)
  const n = num(createdAt)
  if (n > 1_000_000_000) return tsLabel(n, isFa)
  return formatServiceExpiryLine(String(createdAt), isFa)
}

function formatPanelDbStep(
  step: string,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  const s = String(step ?? "").trim()
  if (!s) return tp("panelDbStep_unknown")
  if (s.startsWith("http_")) return tp("panelDbStep_http", { code: s.slice(5) || "?" })
  const key = `panelDbStep_${s}`
  const tr = tp(key)
  return tr !== key ? tr : tp("panelDbStep_unknown", { step: s })
}

function translatePanelDbDetail(
  detail: string,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  if (!detail) return ""
  return detail.replace(/\(([^)]+)\)/g, (_, step: string) => `(${formatPanelDbStep(step, tp)})`)
}

function formatDeliveryBucket(
  key: string,
  bucket: DeliveryBucket | undefined,
  tp: (k: string, o?: Record<string, string | number>) => string
): string | null {
  if (!bucket?.enabled) return null
  const ok = num(bucket.ok)
  const fail = num(bucket.fail)
  const skipped = num(bucket.skipped)
  if (skipped > 0 && ok < 1 && fail < 1) return tp(`delivery_${key}_skipped`)
  return tp(`delivery_${key}_result`, { ok, fail })
}

function formatBackupRunReport(
  data: BackupRunData | undefined,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  if (!data) return ""
  const parts: string[] = []
  if (typeof data.message === "string" && data.message) parts.push(data.message)
  if (data.panel_db_critical && typeof data.panel_db_critical_msg === "string") {
    parts.push(data.panel_db_critical_msg)
  }
  if (typeof data.panel_db_warning === "string" && data.panel_db_warning) {
    parts.push(tp("backupPanelWarning", { warning: data.panel_db_warning }))
  }
  const deliveryLines = [
    formatDeliveryBucket("telegram_admins", data.delivery?.telegram_admins, tp),
    formatDeliveryBucket("telegram_channel", data.delivery?.telegram_channel, tp),
    formatDeliveryBucket("bale_admins", data.delivery?.bale_admins, tp),
    formatDeliveryBucket("bale_channel", data.delivery?.bale_channel, tp),
  ].filter((line): line is string => Boolean(line))
  if (deliveryLines.length > 0) {
    parts.push([tp("deliveryReportTitle"), ...deliveryLines].join("\n"))
  }
  if (data.stored_on_site) {
    parts.push(data.storage_fallback ? tp("storageFallbackUsed") : tp("storedOnSiteOk"))
  } else if (num(data.sent) < 1) {
    parts.push(tp("deliveryNoneSent"))
  }
  const failures = Array.isArray(data.panel_db_failures) ? data.panel_db_failures : []
  if (failures.length > 0) {
    const lines = failures.map((f) => {
      const label = String(f.label ?? "").trim() || `#${num(f.panel_id)}`
      const step = formatPanelDbStep(String(f.step ?? ""), tp)
      const url = String(f.getdb_url ?? "").trim()
      return [tp("panelDbFailureLine", { label, step }), url].filter(Boolean).join("\n")
    })
    parts.push([tp("panelDbFailuresTitle"), ...lines].join("\n"))
  }
  return parts.filter(Boolean).join("\n\n")
}

function formatSkippedReason(
  reason: string,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  const key = SKIPPED_REASON_KEYS[reason]
  if (!key) return reason
  const translated = tp(key)
  return translated !== key ? translated : reason
}

function formatRestoreReport(
  data: unknown,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  const d = data && typeof data === "object" && !Array.isArray(data) ? (data as RestoreStats) : null
  if (!d) return ""
  const lines: string[] = [
    tp("restoreReportUsers", {
      matched: Number(d.users_matched ?? 0),
      inserted: Number(d.users_inserted ?? 0),
      skipped: Number(d.users_skipped ?? 0),
    }),
  ]
  const pr = d.panel_restore
  if (pr && typeof pr === "object") {
    lines.push(
      tp("restoreReportPanel", {
        ok: Number(pr.ok_count ?? 0),
        fail: Number(pr.fail_count ?? 0),
      })
    )
  } else if (d.panel_db_restored != null || Array.isArray(d.panel_db_errors)) {
    lines.push(
      tp("restoreReportPanel", {
        ok: Number(d.panel_db_restored ?? 0),
        fail: Array.isArray(d.panel_db_errors) ? d.panel_db_errors.length : 0,
      })
    )
  }
  const errN = Array.isArray(d.errors) ? d.errors.length : 0
  if (errN > 0) {
    lines.push(tp("restoreReportErrors", { n: errN }))
  }
  return lines.join("\n")
}

async function pollManualBackupUntilDone(
  tp: (k: string, o?: Record<string, string | number>) => string,
  onPollTick?: (elapsedMs: number) => void
): Promise<DashRecord> {
  const pollStarted = Date.now()
  const deadline = pollStarted + BACKUP_POLL_MAX_MS
  while (Date.now() < deadline) {
    onPollTick?.(Date.now() - pollStarted)
    const st = await getAdminJson("/admin/backup/status")
    const status = String(st.status ?? "")
    if (status === "done" || status === "error") return st
    await sleepMs(BACKUP_POLL_INTERVAL_MS)
  }
  return { ok: false, status: "error", message: tp("backupPollTimeout") }
}

function backupMsgFromManualStatus(
  st: DashRecord,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  const status = String(st.status ?? "")
  const code = String(st.code ?? "")
  if (code === "already_running") return tp("backupAlreadyRunning")
  if (status === "error" || st.ok === false) {
    return typeof st.message === "string" && st.message ? st.message : tp("backupNowError")
  }
  const data = st.data as BackupRunData | undefined
  const report = formatBackupRunReport(data, tp)
  return report || tp("backupNowSuccess")
}

function panelDbListLabel(row: BackupRow, tp: (k: string, o?: Record<string, string | number>) => string): string {
  const status = String(row.panel_db_status ?? (row.has_panel_db ? "full" : "none"))
  const detail = translatePanelDbDetail(String(row.panel_db_detail ?? "").trim(), tp)
  if (status === "full") return tp("panelYes")
  if (status === "partial") return detail ? `${tp("panelPartial")}: ${detail}` : tp("panelPartial")
  if (status === "none") return detail ? `${tp("panelNoneFailed")}: ${detail}` : tp("panelNoneFailed")
  if (status === "na") return tp("panelNa")
  return row.has_panel_db ? tp("panelYes") : tp("panelNo")
}

function normalizePanelInbound(row: unknown): PanelInboundRow | null {
  if (!row || typeof row !== "object") return null
  const r = row as Record<string, unknown>
  const id = num(r.id)
  if (id < 1) return null
  return {
    id,
    remark: String(r.remark ?? ""),
    port: num(r.port),
    protocol: String(r.protocol ?? ""),
  }
}

export function BackupAdminClient() {
  const t = useTranslations("backupAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"
  const { data, reload, enabledPlatforms } = useAdminTabState("backup")

  const showTg = enabledPlatforms.includes("telegram")
  const showBale = enabledPlatforms.includes("bale")

  const settings = data.settings && typeof data.settings === "object" ? (data.settings as Record<string, unknown>) : {}

  const initial = useMemo(
    () => ({
      backup_interval_minutes: String(Math.max(5, num(settings.backup_interval_minutes) || 60)),
      backup_telegram_chat_id: String(num(settings.backup_telegram_chat_id)),
      backup_bale_chat_id: String(num(settings.backup_bale_chat_id)),
      backup_send_telegram_admins: bool(settings.backup_send_telegram_admins),
      backup_send_bale_admins: bool(settings.backup_send_bale_admins),
      backup_send_telegram_channel: bool(settings.backup_send_telegram_channel),
      backup_send_bale_channel: bool(settings.backup_send_bale_channel),
      backup_store_on_site: bool(settings.backup_store_on_site),
      backup_site_retention_count: String(Math.max(1, Math.min(500, num(settings.backup_site_retention_count) || 14))),
      backup_max_zip_mb: String(Math.max(0, num(settings.backup_max_zip_mb))),
    }),
    [settings]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => setForm(initial), [initial])

  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [backupRows, setBackupRows] = useState<BackupRow[]>([])
  const [backupPage, setBackupPage] = useState(1)
  const [backupPerPage, setBackupPerPage] = useState(15)
  const [storeOnSiteLive, setStoreOnSiteLive] = useState(bool(settings.backup_store_on_site))
  const [lastBackupAt, setLastBackupAt] = useState(0)
  const [lastBuiltAt, setLastBuiltAt] = useState(0)
  const [nextBackupAt, setNextBackupAt] = useState(0)
  const [listLoading, setListLoading] = useState(false)
  const [listError, setListError] = useState<string | null>(null)
  const [backupRunning, setBackupRunning] = useState(false)
  const [backupMsg, setBackupMsg] = useState<string | null>(null)
  const [downloadBusy, setDownloadBusy] = useState<string | null>(null)
  const [restoreTarget, setRestoreTarget] = useState<BackupRow | null>(null)
  const [restorePanelDb, setRestorePanelDb] = useState(false)
  const [restoreBusy, setRestoreBusy] = useState(false)
  const [uploadFile, setUploadFile] = useState<File | null>(null)
  const [uploadConfirm, setUploadConfirm] = useState(false)
  const [uploadRestorePanelDb, setUploadRestorePanelDb] = useState(false)
  const [uploadBusy, setUploadBusy] = useState(false)
  const [uploadMsg, setUploadMsg] = useState<string | null>(null)
  const [resetStuckBusy, setResetStuckBusy] = useState(false)
  const [backupRunStartedAt, setBackupRunStartedAt] = useState(0)
  const [statusDetail, setStatusDetail] = useState<DashRecord | null>(null)
  const [lastRun, setLastRun] = useState<LastBackupRun | null>(null)
  const [deliveryWarning, setDeliveryWarning] = useState<string | null>(null)

  const [cronRegistered, setCronRegistered] = useState(true)
  const [cronSchedule, setCronSchedule] = useState("")
  const [cronWantedSchedule, setCronWantedSchedule] = useState("")
  const [backupDisplayTz, setBackupDisplayTz] = useState("")
  const [siteTimezone, setSiteTimezone] = useState("")
  const [lastCronPingAt, setLastCronPingAt] = useState(0)
  const [cronPingIntervalSeconds, setCronPingIntervalSeconds] = useState(0)
  const [serverCrontabLine, setServerCrontabLine] = useState("")
  const [cronCopyHint, setCronCopyHint] = useState<string | null>(null)

  const [panelOptions, setPanelOptions] = useState<PanelOption[]>([])
  const [rebuildPanelId, setRebuildPanelId] = useState("0")
  const [rebuildDryRun, setRebuildDryRun] = useState(false)
  const [rebuildOpen, setRebuildOpen] = useState(false)
  const [rebuildBusy, setRebuildBusy] = useState(false)
  const [rebuildMsg, setRebuildMsg] = useState<string | null>(null)
  const [rebuildProgress, setRebuildProgress] = useState({ done: 0, total: 0 })
  const [inboundMapLoading, setInboundMapLoading] = useState(false)
  const [inboundMapError, setInboundMapError] = useState<string | null>(null)
  const [inboundMapMsg, setInboundMapMsg] = useState<string | null>(null)
  const [dbInbounds, setDbInbounds] = useState<DbInboundRow[]>([])
  const [panelInbounds, setPanelInbounds] = useState<PanelInboundRow[]>([])
  const [inboundMapDraft, setInboundMapDraft] = useState<Record<string, string>>({})
  const [inboundMapMissing, setInboundMapMissing] = useState(0)
  const [fix51200Count, setFix51200Count] = useState<number | null>(null)
  const [fix51200Open, setFix51200Open] = useState(false)
  const [fix51200Busy, setFix51200Busy] = useState(false)
  const [fix51200Msg, setFix51200Msg] = useState<string | null>(null)
  const [resellerBackfillBusy, setResellerBackfillBusy] = useState(false)
  const [resellerBackfillResult, setResellerBackfillResult] = useState<string | null>(null)

  const inboundRowLabel = useCallback(
    (row: { id: number; remark?: string; port?: number; protocol?: string }, count?: number) =>
      t("inboundMapRowHint", {
        id: row.id,
        protocol: String(row.protocol ?? "—"),
        port: num(row.port),
        remark: String(row.remark ?? "—"),
        count: count ?? 0,
      }),
    [t]
  )

  const buildInboundMapPayload = useCallback(() => {
    const out: Record<string, number> = {}
    for (const row of dbInbounds) {
      const old = row.id
      const neu = num(inboundMapDraft[String(old)] || old)
      if (neu > 0) out[String(old)] = neu
    }
    return out
  }, [dbInbounds, inboundMapDraft])

  const loadInboundMap = useCallback(async () => {
    const pid = num(rebuildPanelId)
    if (pid < 1) {
      setDbInbounds((prev) => (prev.length === 0 ? prev : []))
      setPanelInbounds((prev) => (prev.length === 0 ? prev : []))
      setInboundMapDraft((prev) => (Object.keys(prev).length === 0 ? prev : {}))
      setInboundMapMissing((prev) => (prev === 0 ? prev : 0))
      return
    }
    setInboundMapLoading(true)
    setInboundMapError(null)
    setInboundMapMsg(null)
    try {
      const json = await getAdminJson("/admin/panel/inbound-map", { panel_id: pid, compare: 1 })
      if (!json.ok) {
        setInboundMapError(String(json.message || t("inboundMapLoadError")))
        return
      }
      const db = Array.isArray(json.db_inbounds) ? (json.db_inbounds as DbInboundRow[]) : []
      const liveRaw = Array.isArray(json.panel_inbounds) ? json.panel_inbounds : []
      const live = liveRaw.map(normalizePanelInbound).filter((r): r is PanelInboundRow => r != null)
      const stored = json.map && typeof json.map === "object" ? (json.map as Record<string, number>) : {}
      const suggest =
        json.suggested_map && typeof json.suggested_map === "object"
          ? (json.suggested_map as Record<string, number>)
          : {}
      const draft: Record<string, string> = {}
      for (const row of db) {
        const old = String(row.id)
        const fromStore = stored[row.id] ?? stored[Number(old) as unknown as string]
        const fromSuggest = suggest[row.id] ?? suggest[Number(old) as unknown as string]
        const pick = fromStore ?? fromSuggest ?? row.id
        draft[old] = String(pick)
      }
      setDbInbounds(db)
      setPanelInbounds(live)
      setInboundMapDraft(draft)
      setInboundMapMissing(Array.isArray(json.missing_on_panel) ? json.missing_on_panel.length : 0)
    } finally {
      setInboundMapLoading(false)
    }
  }, [rebuildPanelId, t])

  useEffect(() => {
    void loadInboundMap()
  }, [rebuildPanelId, loadInboundMap])

  const refreshFix51200Count = useCallback(async () => {
    const pid = num(rebuildPanelId)
    if (pid < 1) {
      setFix51200Count(null)
      return
    }
    try {
      const json = await postAdminJson("/admin/panel/fix-51200-traffic", {
        panel_id: pid,
        dry_run: true,
        offset: 0,
      })
      if (json.ok) setFix51200Count(num(json.total))
      else setFix51200Count(null)
    } catch {
      setFix51200Count(null)
    }
  }, [rebuildPanelId])

  useEffect(() => {
    void refreshFix51200Count()
  }, [refreshFix51200Count])

  const applyInboundSuggest = useCallback(() => {
    setInboundMapDraft((prev) => {
      const next = { ...prev }
      for (const row of dbInbounds) {
        const live = panelInbounds.find(
          (p) =>
            String(p.remark ?? "").toLowerCase() === String(row.remark ?? "").toLowerCase() &&
            num(p.port) === num(row.port) &&
            String(p.protocol ?? "").toLowerCase() === String(row.protocol ?? "").toLowerCase()
        )
        if (live) {
          next[String(row.id)] = String(live.id)
          continue
        }
        const byRemark = panelInbounds.filter(
          (p) => String(p.remark ?? "").toLowerCase() === String(row.remark ?? "").toLowerCase()
        )
        if (byRemark.length === 1) next[String(row.id)] = String(byRemark[0].id)
      }
      return next
    })
  }, [dbInbounds, panelInbounds])

  const saveInboundMap = useCallback(
    async (applyToDb: boolean) => {
      const pid = num(rebuildPanelId)
      if (pid < 1) return
      setInboundMapLoading(true)
      setInboundMapMsg(null)
      setInboundMapError(null)
      try {
        const json = await postAdminJson("/admin/panel/inbound-map", {
          panel_id: pid,
          map: buildInboundMapPayload(),
          apply_to_db: applyToDb ? 1 : 0,
        })
        if (!json.ok) {
          setInboundMapError(String(json.message || t("saveError")))
          return
        }
        if (applyToDb) {
          const services = num(
            (json.db_counts as { services?: number } | undefined)?.services ?? json.updated_services
          )
          const plans = num((json.db_counts as { plans?: number } | undefined)?.plans ?? json.updated_plans)
          setInboundMapMsg(t("inboundMapSaveDbOk", { services, plans }))
        } else {
          setInboundMapMsg(t("inboundMapSaved"))
        }
        await loadInboundMap()
      } finally {
        setInboundMapLoading(false)
      }
    },
    [buildInboundMapPayload, loadInboundMap, rebuildPanelId, t]
  )

  const loadCronStatus = useCallback(async () => {
    try {
      const cron = await getAdminJson("/admin/cron-status")
      const line = String(cron.server_crontab_line ?? "").trim()
      if (line) setServerCrontabLine(line)
    } catch {
      /* optional diagnostics — crontab line lives on /admin/cron-status */
    }
  }, [])

  const loadBackups = useCallback(async () => {
    setListLoading(true)
    setListError(null)
    try {
      const json = await getAdminJson("/admin/backups")
      if (json.ok === false) {
        setListError(String(json.message || t("loadError")))
        return
      }
      const list = Array.isArray(json.rows)
        ? (json.rows as BackupRow[])
        : Array.isArray(json.backups)
          ? (json.backups as BackupRow[])
          : []
      setBackupRows(list)
      const panels = Array.isArray(json.panels) ? (json.panels as PanelOption[]) : []
      setPanelOptions(
        panels
          .map((p) => ({ id: num(p.id), label: String(p.label ?? `#${num(p.id)}`) }))
          .filter((p) => p.id > 0)
      )
      setStoreOnSiteLive(bool(json.store_on_site ?? settings.backup_store_on_site))
      setLastBackupAt(num(json.last_backup_at))
      setLastBuiltAt(num(json.last_built_at))
      setNextBackupAt(num(json.next_backup_at))
      setCronRegistered(json.cron_registered !== false)
      setCronSchedule(String(json.cron_schedule ?? ""))
      setCronWantedSchedule(String(json.cron_wanted_schedule ?? ""))
      setBackupDisplayTz(String(json.backup_display_timezone ?? ""))
      setSiteTimezone(String(json.site_timezone ?? ""))
      if (json.last_run && typeof json.last_run === "object") {
        setLastRun(json.last_run as LastBackupRun)
      } else {
        setLastRun(null)
      }
      setLastCronPingAt(num(json.last_cron_ping_at))
      setCronPingIntervalSeconds(Math.max(0, num(json.cron_ping_interval_seconds)))
      const crontabFromBackup = String(json.server_crontab_line ?? "").trim()
      if (crontabFromBackup) setServerCrontabLine(crontabFromBackup)
      const warn = String(json.delivery_warning ?? "").trim()
      setDeliveryWarning(warn || null)
    } catch {
      setListError(t("loadError"))
    } finally {
      setListLoading(false)
    }
  }, [settings.backup_store_on_site, t])

  const loadStatus = useCallback(async () => {
    try {
      const st = await getAdminJson("/admin/backup/status")
      setStatusDetail(st)
      const status = String(st.status ?? "")
      if (status === "running") {
        setBackupRunning(true)
        if (backupRunStartedAt < 1) setBackupRunStartedAt(Date.now())
        setBackupMsg(t("backupRunningAsync"))
      }
      if (st.cron_registered != null) setCronRegistered(st.cron_registered !== false)
      if (st.cron_schedule != null) setCronSchedule(String(st.cron_schedule ?? ""))
      if (st.cron_wanted_schedule != null) setCronWantedSchedule(String(st.cron_wanted_schedule ?? ""))
      if (st.last_cron_ping_at != null) setLastCronPingAt(num(st.last_cron_ping_at))
      if (st.server_crontab_line != null) {
        const line = String(st.server_crontab_line ?? "").trim()
        if (line) setServerCrontabLine(line)
      }
    } catch {
      /* ignore */
    }
  }, [backupRunStartedAt, t])

  useEffect(() => {
    void loadBackups()
    void loadStatus()
    void loadCronStatus()
  }, [loadBackups, loadStatus, loadCronStatus])

  const onCopyCrontabLine = useCallback(async () => {
    const line = serverCrontabLine.trim()
    if (!line) return
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(line)
      } else {
        const ta = document.createElement("textarea")
        ta.value = line
        ta.style.position = "fixed"
        ta.style.left = "-9999px"
        document.body.appendChild(ta)
        ta.select()
        document.execCommand("copy")
        document.body.removeChild(ta)
      }
      setCronCopyHint(t("cronServerCopied"))
    } catch {
      setCronCopyHint(null)
    }
    window.setTimeout(() => setCronCopyHint(null), 2200)
  }, [serverCrontabLine, t])

  const onSave = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "backup",
        backup_interval_minutes: num(form.backup_interval_minutes),
        backup_telegram_chat_id: num(form.backup_telegram_chat_id),
        backup_bale_chat_id: num(form.backup_bale_chat_id),
        backup_send_telegram_admins: form.backup_send_telegram_admins ? 1 : 0,
        backup_send_bale_admins: form.backup_send_bale_admins ? 1 : 0,
        backup_send_telegram_channel: form.backup_send_telegram_channel ? 1 : 0,
        backup_send_bale_channel: form.backup_send_bale_channel ? 1 : 0,
        backup_store_on_site: form.backup_store_on_site ? 1 : 0,
        backup_site_retention_count: Math.max(1, Math.min(500, num(form.backup_site_retention_count))),
        backup_max_zip_mb: Math.max(0, num(form.backup_max_zip_mb)),
      })
      if (!res.ok) {
        setError(res.message || t("saveError"))
        return
      }
      setStoreOnSiteLive(form.backup_store_on_site)
      reload()
    } finally {
      setSaving(false)
    }
  }, [form, reload, t])

  const onResetBackupStuck = useCallback(async () => {
    setResetStuckBusy(true)
    setListError(null)
    try {
      const json = await postAdminJson("/admin/backup/reset-stuck", {})
      if (!json.ok) {
        setListError(String(json.message || t("backupNowError")))
        return
      }
      setBackupRunning(false)
      setBackupRunStartedAt(0)
      setBackupMsg(t("backupResetStuckOk"))
      setStatusDetail(json)
    } finally {
      setResetStuckBusy(false)
    }
  }, [t])

  const backupStuckLikely = useMemo(() => {
    if (!backupRunning || backupRunStartedAt < 1) return false
    return Date.now() - backupRunStartedAt >= BACKUP_POLL_LONG_HINT_MS
  }, [backupRunning, backupRunStartedAt])

  const onBackupNow = useCallback(async () => {
    setBackupRunning(true)
    setBackupRunStartedAt(Date.now())
    setBackupMsg(t("backupNowRunning"))
    try {
      const json = await postAdminJson("/admin/backup/run", {})
      const gateway504 =
        !json.ok && json.message === "invalid_html_response" && Number(json.http_status) === 504
      if (!json.ok && !gateway504) {
        setBackupMsg(backupMsgFromManualStatus(json as DashRecord, t))
        setBackupRunning(false)
        setBackupRunStartedAt(0)
        return
      }
      const warn = String(json.delivery_warning ?? "").trim()
      if (warn) setDeliveryWarning(warn)
      if (gateway504) {
        setBackupMsg(t("backupGatewayTimeout"))
      } else if (json.async === true || json.status === "running") {
        setBackupMsg(
          warn ? `${t("backupRunningAsync")}\n${t("backupDeliveryWarning")}\n${warn}` : t("backupRunningAsync")
        )
        const final = await pollManualBackupUntilDone(t, (elapsed) => {
          if (elapsed >= BACKUP_POLL_LONG_HINT_MS) setBackupMsg(t("backupRunningLong"))
        })
        setBackupMsg(backupMsgFromManualStatus(final, t))
        if (final.status === "done" || (final.data && typeof final.data === "object")) {
          await loadBackups()
        }
        await loadStatus()
        return
      }
      if (json.data && typeof json.data === "object") {
        setBackupMsg(formatBackupRunReport(json.data as BackupRunData, t) || t("backupNowSuccess"))
      } else {
        setBackupMsg(backupMsgFromManualStatus(json as DashRecord, t))
      }
      await loadBackups()
      await loadStatus()
    } finally {
      setBackupRunning(false)
      setBackupRunStartedAt(0)
    }
  }, [loadBackups, loadStatus, t])

  const onDownloadBackup = useCallback(
    async (filename: string) => {
      setDownloadBusy(filename)
      setListError(null)
      try {
        const res = await downloadAdminBackupFile(filename)
        if (!res.ok) setListError(res.message || t("downloadError"))
      } finally {
        setDownloadBusy(null)
      }
    },
    [t]
  )

  const onRestoreFile = useCallback(async () => {
    if (!restoreTarget) return
    setRestoreBusy(true)
    setListError(null)
    try {
      const json = await postAdminJson("/admin/backup/restore", {
        filename: restoreTarget.filename,
        confirm: true,
        restore_panel_db: restorePanelDb ? 1 : 0,
      })
      if (!json.ok) {
        setListError(String(json.message || t("restoreError")))
        return
      }
      const report = formatRestoreReport(json.data, t)
      setBackupMsg([String(json.message || t("restoreSuccess")), report].filter(Boolean).join("\n"))
      setRestoreTarget(null)
      setRestorePanelDb(false)
      reload()
    } finally {
      setRestoreBusy(false)
    }
  }, [reload, restorePanelDb, restoreTarget, t])

  const onUploadRestore = useCallback(async () => {
    if (!uploadFile || !uploadConfirm) return
    setUploadBusy(true)
    setUploadMsg(null)
    try {
      const fd = new FormData()
      fd.append("confirm", "1")
      if (uploadRestorePanelDb) fd.append("restore_panel_db", "1")
      fd.append("file", uploadFile)
      const json = await postAdminFormData("/admin/backup/restore-upload", fd)
      if (!json.ok) {
        setUploadMsg(String(json.message || t("restoreError")))
        return
      }
      const report = formatRestoreReport(json.data, t)
      setUploadMsg([String(json.message || t("restoreSuccess")), report].filter(Boolean).join("\n"))
      setUploadFile(null)
      setUploadConfirm(false)
      setUploadRestorePanelDb(false)
      reload()
      await loadBackups()
    } finally {
      setUploadBusy(false)
    }
  }, [loadBackups, reload, t, uploadConfirm, uploadFile, uploadRestorePanelDb])

  const onRebuildPanels = useCallback(async () => {
    setRebuildBusy(true)
    setRebuildMsg(null)
    setRebuildProgress({ done: 0, total: 0 })
    const totals: RebuildTotals = { created: 0, patched: 0, skipped: 0, failed: 0 }
    const errSamples: string[] = []
    let offset = 0
    let total = 0
    const pid = num(rebuildPanelId)
    const mapPayload = pid > 0 ? buildInboundMapPayload() : undefined
    try {
      for (;;) {
        const body: Record<string, unknown> = {
          confirm: !rebuildDryRun,
          dry_run: rebuildDryRun,
          panel_id: pid,
          offset,
        }
        if (mapPayload && Object.keys(mapPayload).length > 0) body.inbound_map = mapPayload
        const json = await postAdminJson("/admin/panel/rebuild-from-db", body)
        if (!json.ok) {
          setRebuildMsg(String(json.message || t("rebuildError")))
          return
        }
        total = num(json.total)
        const batch = json.totals as RebuildTotals | undefined
        if (batch) {
          totals.created = num(totals.created) + num(batch.created)
          totals.patched = num(totals.patched) + num(batch.patched)
          totals.skipped = num(totals.skipped) + num(batch.skipped)
          totals.failed = num(totals.failed) + num(batch.failed)
        }
        const batchErrs = Array.isArray(json.errors) ? json.errors : []
        for (const e of batchErrs) {
          if (errSamples.length >= 8 || !e || typeof e !== "object") continue
          const row = e as { email?: string; reason?: string }
          const line = `${String(row.email ?? "?")}: ${String(row.reason ?? "?")}`
          if (!errSamples.includes(line)) errSamples.push(line)
        }
        offset = num(json.next_offset)
        setRebuildProgress({ done: offset, total })
        if (bool(json.done)) break
      }
      setRebuildMsg(
        [
          t("rebuildReport", {
            created: num(totals.created),
            patched: num(totals.patched),
            skipped: num(totals.skipped),
            failed: num(totals.failed),
          }),
          errSamples.length > 0 ? errSamples.join("\n") : "",
          rebuildDryRun ? "" : t("rebuildDone"),
        ]
          .filter(Boolean)
          .join("\n")
      )
      setRebuildOpen(false)
      if (!rebuildDryRun) reload()
    } finally {
      setRebuildBusy(false)
    }
  }, [buildInboundMapPayload, rebuildDryRun, rebuildPanelId, reload, t])

  const onFix51200 = useCallback(async () => {
    const pid = num(rebuildPanelId)
    if (pid < 1) return
    setFix51200Busy(true)
    setFix51200Msg(null)
    const totals = { fixed: 0, skipped: 0, failed: 0, noSource: 0 }
    let offset = 0
    try {
      for (;;) {
        const json = await postAdminJson("/admin/panel/fix-51200-traffic", {
          confirm: true,
          panel_id: pid,
          offset,
          inbound_map: buildInboundMapPayload(),
        })
        if (!json.ok) {
          setFix51200Msg(String(json.message || t("saveError")))
          return
        }
        const batch = json.totals as
          | { fixed?: number; skipped?: number; failed?: number; no_source?: number }
          | undefined
        if (batch) {
          totals.fixed += num(batch.fixed)
          totals.skipped += num(batch.skipped)
          totals.failed += num(batch.failed)
          totals.noSource += num(batch.no_source)
        }
        offset = num(json.next_offset)
        if (bool(json.done)) break
      }
      setFix51200Msg(
        [
          totals.fixed < 1 && totals.failed < 1 ? t("fix51200None") : "",
          t("fix51200Report", {
            fixed: totals.fixed,
            skipped: totals.skipped,
            noSource: totals.noSource,
            failed: totals.failed,
          }),
          t("fix51200Done"),
        ]
          .filter(Boolean)
          .join("\n")
      )
      setFix51200Open(false)
      await refreshFix51200Count()
      reload()
    } finally {
      setFix51200Busy(false)
    }
  }, [buildInboundMapPayload, refreshFix51200Count, rebuildPanelId, reload, t])

  const onResellerBackfill = useCallback(async () => {
    setResellerBackfillBusy(true)
    setResellerBackfillResult(null)
    setError(null)
    try {
      const res = await postAdminMutate("reseller_backfill_run", {})
      if (!res.ok) {
        setError(String(res.message || t("resellerBackfillError")))
        return
      }
      const billing = (res.billing ?? {}) as Record<string, unknown>
      const invited = ((res.invited_by ?? res.invited) ?? {}) as Record<string, unknown>
      setResellerBackfillResult(
        t("resellerBackfillResult", {
          billingUpdated: String(billing.updated ?? 0),
          billingScanned: String(billing.scanned ?? 0),
          billingLast: String(billing.last_id ?? 0),
          invitedUpdated: String(invited.updated ?? 0),
          invitedScanned: String(invited.scanned ?? 0),
          invitedLast: String(invited.last_id ?? 0),
        })
      )
    } finally {
      setResellerBackfillBusy(false)
    }
  }, [t])

  const backupListMeta = useMemo((): PaginationMeta | null => {
    if (backupRows.length === 0) return null
    return { page: backupPage, perPage: backupPerPage, total: backupRows.length }
  }, [backupPage, backupPerPage, backupRows.length])

  const pagedBackupRows = useMemo(() => {
    const start = (backupPage - 1) * backupPerPage
    return backupRows.slice(start, start + backupPerPage)
  }, [backupPage, backupPerPage, backupRows])

  const showCronKeeper =
    cronPingIntervalSeconds > 0 || lastCronPingAt > 0 || Boolean(serverCrontabLine.trim())

  const chk = (key: keyof typeof form, labelKey: string) => (
    <label className="flex items-center gap-2 text-sm">
      <input
        type="checkbox"
        className="size-4 rounded border-input"
        checked={Boolean(form[key])}
        onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.checked }))}
      />
      {t(labelKey)}
    </label>
  )

  return (
    <DashPage className="space-y-6">
      <DashboardPageHeader title={t("title")} description={t("subtitle")} />

      <div className="grid gap-6 xl:grid-cols-2 xl:items-start">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("cardTitle")}</CardTitle>
            <CardDescription>{t("cardDesc")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="b_int">{t("intervalMinutes")}</Label>
              <Input
                id="b_int"
                type="number"
                min={5}
                value={form.backup_interval_minutes}
                onChange={(e) => setForm((f) => ({ ...f, backup_interval_minutes: e.target.value }))}
              />
              <p className="text-xs text-muted-foreground">{t("intervalHint", { min: formatNumber(5, isFa) })}</p>
            </div>
            {showTg ? (
              <div className="space-y-2">
                <Label htmlFor="b_tg">{t("telegramChatId")}</Label>
                <Input
                  id="b_tg"
                  type="number"
                  value={form.backup_telegram_chat_id}
                  onChange={(e) => setForm((f) => ({ ...f, backup_telegram_chat_id: e.target.value }))}
                />
              </div>
            ) : null}
            {showBale ? (
              <div className="space-y-2">
                <Label htmlFor="b_bl">{t("baleChatId")}</Label>
                <Input
                  id="b_bl"
                  type="number"
                  value={form.backup_bale_chat_id}
                  onChange={(e) => setForm((f) => ({ ...f, backup_bale_chat_id: e.target.value }))}
                />
              </div>
            ) : null}
            <div className="space-y-3 border-t border-border pt-3">
              {showTg ? chk("backup_send_telegram_admins", "sendTelegramAdmins") : null}
              {showBale ? chk("backup_send_bale_admins", "sendBaleAdmins") : null}
              {showTg ? chk("backup_send_telegram_channel", "sendTelegramChannel") : null}
              {showBale ? chk("backup_send_bale_channel", "sendBaleChannel") : null}
            </div>
            <div className="space-y-3 border-t border-border pt-3">
              <p className="text-sm font-medium">{t("siteStorageTitle")}</p>
              {chk("backup_store_on_site", "storeOnSite")}
              <div className="space-y-2">
                <Label htmlFor="b_ret">{t("retentionCount")}</Label>
                <Input
                  id="b_ret"
                  type="number"
                  min={1}
                  max={500}
                  value={form.backup_site_retention_count}
                  onChange={(e) => setForm((f) => ({ ...f, backup_site_retention_count: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="b_maxmb">{t("maxZipMb")}</Label>
                <Input
                  id="b_maxmb"
                  type="number"
                  min={0}
                  value={form.backup_max_zip_mb}
                  onChange={(e) => setForm((f) => ({ ...f, backup_max_zip_mb: e.target.value }))}
                />
              </div>
            </div>
            {error ? <p className="text-sm text-destructive">{error}</p> : null}
            <Button type="button" disabled={saving} onClick={() => void onSave()}>
              {t("save")}
            </Button>
          </CardContent>
        </Card>

        <Card className="min-w-0">
          <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
            <div>
              <CardTitle className="text-base">{t("storedTitle")}</CardTitle>
              <CardDescription>{t("storedDesc")}</CardDescription>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" variant="secondary" disabled={backupRunning} onClick={() => void onBackupNow()}>
                {backupRunning ? t("backupNowRunning") : t("backupNow")}
              </Button>
              {backupStuckLikely ? (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={resetStuckBusy}
                  onClick={() => void onResetBackupStuck()}
                >
                  {t("backupResetStuck")}
                </Button>
              ) : null}
            </div>
          </CardHeader>
          <CardContent className="space-y-3">
            {deliveryWarning ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">{deliveryWarning}</p>
            ) : null}
            {lastRun && typeof lastRun.delivery === "object" ? (
              <p className="whitespace-pre-wrap text-xs text-muted-foreground">
                {formatBackupRunReport(lastRun as BackupRunData, t)}
              </p>
            ) : null}
            {statusDetail && String(statusDetail.status ?? "") === "running" ? (
              <Badge variant="secondary">{t("backupRunningAsync")}</Badge>
            ) : null}
            {lastBuiltAt > 0 ? (
              <p className="text-xs text-muted-foreground">{t("lastBuiltAt", { at: tsLabel(lastBuiltAt, isFa) })}</p>
            ) : null}
            {lastBackupAt > 0 ? (
              <p className="text-xs text-muted-foreground">{t("lastBackupAt", { at: tsLabel(lastBackupAt, isFa) })}</p>
            ) : null}
            {nextBackupAt > 0 ? (
              <p className="text-xs text-muted-foreground">{t("nextBackupAt", { at: tsLabel(nextBackupAt, isFa) })}</p>
            ) : null}
            {!cronRegistered ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">{t("cronNotRegistered")}</p>
            ) : null}
            {cronRegistered && cronSchedule && cronWantedSchedule && cronSchedule !== cronWantedSchedule ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">
                {t("cronScheduleMismatch", { current: cronSchedule, wanted: cronWantedSchedule })}
              </p>
            ) : null}
            {backupDisplayTz ? (
              <p className="text-xs text-muted-foreground">{t("backupTimezoneCaption", { tz: backupDisplayTz })}</p>
            ) : null}
            {siteTimezone && siteTimezone !== backupDisplayTz ? (
              <p className="text-xs text-muted-foreground">{t("siteTimezoneCaption", { tz: siteTimezone })}</p>
            ) : null}
            {lastRun && num(lastRun.at) > 0 ? (
              <p className="text-xs text-muted-foreground">
                {String(lastRun.skipped_reason ?? "").trim()
                  ? t("lastRunSkipped", {
                      at: tsLabel(num(lastRun.at), isFa),
                      reason: formatSkippedReason(String(lastRun.skipped_reason), t),
                    })
                  : t("lastRunSummary", {
                      at: tsLabel(num(lastRun.at), isFa),
                      built: lastRun.built ? "✓" : "✗",
                      sent: String(num(lastRun.sent)),
                    })}
              </p>
            ) : null}
            {statusDetail && String(statusDetail.status ?? "") !== "idle" ? (
              <p className="text-xs text-muted-foreground">
                {t("backupReportStatus", { status: String(statusDetail.status ?? "") })}
                {statusDetail.message ? ` — ${String(statusDetail.message)}` : ""}
              </p>
            ) : null}
            {deliveryWarning ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">
                {t("backupDeliveryWarning")}
                {"\n"}
                {deliveryWarning}
              </p>
            ) : null}
            {backupStuckLikely ? (
              <div
                role="alert"
                className="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm text-amber-800 dark:text-amber-200"
              >
                <p>{t("backupStuckBanner")}</p>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="mt-2"
                  disabled={resetStuckBusy}
                  onClick={() => void onResetBackupStuck()}
                >
                  {t("backupResetStuck")}
                </Button>
              </div>
            ) : null}
            {!backupStuckLikely && backupRunning ? (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-8 px-2 text-xs"
                disabled={resetStuckBusy}
                onClick={() => void onResetBackupStuck()}
              >
                {t("backupResetStuck")}
              </Button>
            ) : null}
            {showCronKeeper ? (
              <div className="space-y-2 rounded-md border border-border/80 bg-muted/30 px-3 py-2">
                {cronPingIntervalSeconds > 0 || lastCronPingAt > 0 ? (
                  <>
                    <p className="text-sm font-medium">{t("cronKeeperTitle")}</p>
                    {cronPingIntervalSeconds > 0 ? (
                      <p className="text-xs text-muted-foreground">
                        {t("cronKeeperDesc", { seconds: String(cronPingIntervalSeconds) })}
                      </p>
                    ) : null}
                    {lastCronPingAt > 0 ? (
                      <p className="text-xs text-muted-foreground">
                        {t("cronKeeperLastPing", { at: tsLabel(lastCronPingAt, isFa) })}
                      </p>
                    ) : (
                      <p className="text-xs text-muted-foreground">{t("cronKeeperNeverPing")}</p>
                    )}
                  </>
                ) : null}
                {serverCrontabLine.trim() ? (
                  <>
                    <p className="pt-1 text-sm font-medium">{t("cronServerTitle")}</p>
                    <pre className="overflow-x-auto whitespace-pre-wrap break-all rounded bg-background/80 px-2 py-1 font-mono text-xs">
                      {serverCrontabLine}
                    </pre>
                    <div className="flex flex-wrap items-center gap-2">
                      <Button type="button" variant="outline" size="sm" onClick={() => void onCopyCrontabLine()}>
                        {t("cronServerCopy")}
                      </Button>
                      {cronCopyHint ? <span className="text-xs text-emerald-600">{cronCopyHint}</span> : null}
                    </div>
                    <p className="text-xs text-muted-foreground">{t("cronServerHint")}</p>
                  </>
                ) : null}
              </div>
            ) : null}
            {backupMsg ? <p className="whitespace-pre-wrap text-sm text-muted-foreground">{backupMsg}</p> : null}
            {!storeOnSiteLive ? <p className="text-sm text-amber-700 dark:text-amber-400">{t("storeOffHint")}</p> : null}
            {listError ? <p className="text-sm text-destructive">{listError}</p> : null}
            <DashTableShell minWidth="40rem" colWidths={["28%", "12%", "28%", "32%"]}>
              <thead>
                <tr className="bg-muted/40">
                  <DashTh>{t("colDate")}</DashTh>
                  <DashTh>{t("colSize")}</DashTh>
                  <DashTh>{t("colPanel")}</DashTh>
                  <DashTh />
                </tr>
              </thead>
              <tbody>
                {listLoading && backupRows.length === 0 ? (
                  <tr>
                    <DashTd colSpan={4} className="text-center text-muted-foreground">
                      {t("loading")}
                    </DashTd>
                  </tr>
                ) : null}
                {!listLoading && backupRows.length === 0 ? (
                  <tr>
                    <DashTd colSpan={4} className="text-center text-muted-foreground">
                      {t("emptyList")}
                    </DashTd>
                  </tr>
                ) : null}
                {pagedBackupRows.map((row) => (
                  <tr key={row.filename}>
                    <DashTd className="whitespace-nowrap text-xs">{backupRowDateLabel(row.created_at, isFa)}</DashTd>
                    <DashTd className="text-xs tabular-nums">{formatBytes(row.size_bytes, isFa)}</DashTd>
                    <DashTd className="text-xs">{panelDbListLabel(row, t)}</DashTd>
                    <DashTd>
                      <div className="flex flex-wrap gap-2">
                        <Button
                          type="button"
                          size="sm"
                          variant="secondary"
                          disabled={downloadBusy === row.filename}
                          onClick={() => void onDownloadBackup(row.filename)}
                        >
                          {downloadBusy === row.filename ? t("loading") : t("downloadBtn")}
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          onClick={() => {
                            setRestoreTarget(row)
                            setRestorePanelDb(false)
                          }}
                        >
                          {t("restoreBtn")}
                        </Button>
                      </div>
                    </DashTd>
                  </tr>
                ))}
              </tbody>
            </DashTableShell>
            {backupListMeta ? (
              <DataPagination
                meta={backupListMeta}
                onPageChange={setBackupPage}
                onPerPageChange={(n) => {
                  setBackupPerPage(n)
                  setBackupPage(1)
                }}
              />
            ) : null}
          </CardContent>
        </Card>
      </div>

      <Card className="border-destructive/40">
        <CardHeader>
          <CardTitle className="text-base">{t("rebuildPanelTitle")}</CardTitle>
          <CardDescription>{t("rebuildPanelDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="rebuild-panel">{t("rebuildPanelScope")}</Label>
            <DashSelect
              id="rebuild-panel"
              value={rebuildPanelId}
              onValueChange={setRebuildPanelId}
              disabled={rebuildBusy}
              options={[
                { value: "0", label: t("rebuildPanelAll") },
                ...panelOptions.map((p) => ({ value: String(p.id), label: p.label })),
              ]}
            />
          </div>

          <div className="space-y-3 rounded-md border border-amber-500/40 bg-amber-500/5 p-3">
            <p className="text-sm font-medium">{t("inboundMapTitle")}</p>
            <p className="text-xs text-muted-foreground">{t("inboundMapDesc")}</p>
            {num(rebuildPanelId) < 1 ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">{t("inboundMapPickPanel")}</p>
            ) : (
              <>
                {inboundMapError ? <p className="text-xs text-destructive">{inboundMapError}</p> : null}
                {inboundMapMsg ? <p className="text-xs text-muted-foreground">{inboundMapMsg}</p> : null}
                {inboundMapMissing > 0 ? (
                  <p className="text-xs text-amber-700 dark:text-amber-400">
                    {t("inboundMapMissing", { n: inboundMapMissing })}
                  </p>
                ) : null}
                <div className="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void loadInboundMap()}
                  >
                    {t("inboundMapLoad")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={inboundMapLoading || rebuildBusy || dbInbounds.length === 0}
                    onClick={applyInboundSuggest}
                  >
                    {t("inboundMapSuggest")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void saveInboundMap(false)}
                  >
                    {t("inboundMapSave")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void saveInboundMap(true)}
                  >
                    {t("inboundMapSaveDb")}
                  </Button>
                </div>
                {inboundMapLoading && dbInbounds.length === 0 ? (
                  <p className="text-xs text-muted-foreground">{t("loading")}</p>
                ) : null}
                {dbInbounds.length > 0 ? (
                  <DashTableShell minWidth="40rem" colWidths={["45%", "55%"]}>
                    <thead>
                      <tr className="bg-muted/40">
                        <DashTh className="text-xs">{t("inboundMapDbCol")}</DashTh>
                        <DashTh className="text-xs">{t("inboundMapPanelCol")}</DashTh>
                      </tr>
                    </thead>
                    <tbody>
                      {dbInbounds.map((row) => {
                        const oldKey = String(row.id)
                        const selected = inboundMapDraft[oldKey] ?? String(row.id)
                        const sameOnPanel = panelInbounds.some((p) => p.id === row.id)
                        return (
                          <tr key={oldKey}>
                            <DashTd className="text-xs">
                              {inboundRowLabel(row, num(row.service_count))}
                              {row.on_panel_now || sameOnPanel ? (
                                <span className="mt-1 block text-[10px] text-green-700 dark:text-green-400">
                                  {t("inboundMapSameId")}
                                </span>
                              ) : null}
                            </DashTd>
                            <DashTd>
                              <DashSelect
                                size="sm"
                                dir="ltr"
                                triggerClassName="min-w-[12rem] tabular-nums"
                                value={selected}
                                disabled={inboundMapLoading || rebuildBusy}
                                onValueChange={(v) => setInboundMapDraft((d) => ({ ...d, [oldKey]: v }))}
                                allowEmpty
                                placeholder={t("inboundMapNone")}
                                options={panelInbounds.map((p) => ({
                                  value: String(p.id),
                                  label: inboundRowLabel(p),
                                }))}
                              />
                            </DashTd>
                          </tr>
                        )
                      })}
                    </tbody>
                  </DashTableShell>
                ) : null}
              </>
            )}
          </div>

          <div className="space-y-3 rounded-md border border-border/60 bg-muted/20 p-3">
            <p className="text-sm font-medium">{t("resellerBackfillTitle")}</p>
            <p className="text-xs text-muted-foreground">{t("resellerBackfillHint")}</p>
            {resellerBackfillResult ? (
              <p className="whitespace-pre-wrap text-xs text-muted-foreground">{resellerBackfillResult}</p>
            ) : null}
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={resellerBackfillBusy || rebuildBusy}
              onClick={() => void onResellerBackfill()}
            >
              {resellerBackfillBusy ? t("loading") : t("resellerBackfillRun")}
            </Button>
          </div>

          <div className="space-y-3 rounded-md border border-destructive/30 bg-destructive/5 p-3">
            <p className="text-sm font-medium">{t("fix51200Title")}</p>
            <p className="text-xs text-muted-foreground">{t("fix51200Desc")}</p>
            {num(rebuildPanelId) < 1 ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">{t("inboundMapPickPanel")}</p>
            ) : fix51200Count != null ? (
              <p className="text-xs font-medium text-amber-800 dark:text-amber-300">
                {t("fix51200Preview", { n: formatNumber(fix51200Count, isFa) })}
              </p>
            ) : null}
            {fix51200Msg ? <p className="whitespace-pre-wrap text-xs text-muted-foreground">{fix51200Msg}</p> : null}
            <Button
              type="button"
              variant="secondary"
              disabled={fix51200Busy || rebuildBusy || num(rebuildPanelId) < 1 || fix51200Count === 0}
              onClick={() => setFix51200Open(true)}
            >
              {fix51200Busy ? t("fix51200Running") : t("fix51200Run")}
            </Button>
          </div>

          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={rebuildDryRun}
              onChange={(e) => setRebuildDryRun(e.target.checked)}
              disabled={rebuildBusy}
            />
            {t("rebuildDryRun")}
          </label>
          {rebuildBusy && rebuildProgress.total > 0 ? (
            <p className="text-sm text-muted-foreground">
              {t("rebuildProgress", {
                done: formatNumber(rebuildProgress.done, isFa),
                total: formatNumber(rebuildProgress.total, isFa),
              })}
            </p>
          ) : null}
          {rebuildMsg ? <p className="whitespace-pre-wrap text-sm text-muted-foreground">{rebuildMsg}</p> : null}
          <Button type="button" variant="destructive" disabled={rebuildBusy} onClick={() => setRebuildOpen(true)}>
            {rebuildBusy ? t("rebuildRunning") : t("rebuildRun")}
          </Button>
        </CardContent>
      </Card>

      <Card className="max-w-2xl">
        <CardHeader>
          <CardTitle className="text-base">{t("uploadTitle")}</CardTitle>
          <CardDescription>{t("uploadDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="restore_zip">{t("uploadPickFile")}</Label>
            <Input
              id="restore_zip"
              type="file"
              accept=".zip,application/zip"
              onChange={(e) => setUploadFile(e.target.files?.[0] ?? null)}
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={uploadConfirm}
              onChange={(e) => setUploadConfirm(e.target.checked)}
            />
            {t("uploadConfirmLabel")}
          </label>
          <label className="flex items-start gap-2 text-sm">
            <input
              type="checkbox"
              className="mt-0.5 size-4 rounded border-input"
              checked={uploadRestorePanelDb}
              onChange={(e) => setUploadRestorePanelDb(e.target.checked)}
              disabled={uploadBusy}
            />
            <span>
              {t("restorePanelDbLabel")}
              <span className="mt-1 block text-xs text-muted-foreground">{t("restorePanelDbHint")}</span>
            </span>
          </label>
          {uploadMsg ? <p className="whitespace-pre-wrap text-sm text-muted-foreground">{uploadMsg}</p> : null}
          <Button
            type="button"
            variant="destructive"
            disabled={uploadBusy || !uploadFile || !uploadConfirm}
            onClick={() => void onUploadRestore()}
          >
            {t("uploadRestore")}
          </Button>
        </CardContent>
      </Card>

      <AlertDialog open={fix51200Open} onOpenChange={(open) => !open && !fix51200Busy && setFix51200Open(open)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("fix51200ConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("fix51200ConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={fix51200Busy}>{t("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={fix51200Busy} onClick={() => void onFix51200()}>
              {t("fix51200Confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={rebuildOpen} onOpenChange={(open) => !open && !rebuildBusy && setRebuildOpen(open)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("rebuildConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("rebuildConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={rebuildBusy}>{t("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={rebuildBusy} onClick={() => void onRebuildPanels()}>
              {t("rebuildConfirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog
        open={restoreTarget != null}
        onOpenChange={(open) => {
          if (!open) {
            setRestoreTarget(null)
            setRestorePanelDb(false)
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("restoreDialogTitle")}</AlertDialogTitle>
            <AlertDialogDescription className="space-y-3">
              <span className="block">{t("restoreWarning")}</span>
              {restoreTarget?.has_panel_db ? (
                <label className={cn("flex items-start gap-2 text-sm")}>
                  <input
                    type="checkbox"
                    className="mt-0.5 size-4 rounded border-input"
                    checked={restorePanelDb}
                    onChange={(e) => setRestorePanelDb(e.target.checked)}
                    disabled={restoreBusy}
                  />
                  <span>
                    {t("restorePanelDbLabel")}
                    <span className="mt-1 block text-xs opacity-90">{t("restorePanelDbHint")}</span>
                  </span>
                </label>
              ) : null}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={restoreBusy}>{t("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={restoreBusy} onClick={() => void onRestoreFile()}>
              {t("restoreConfirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </DashPage>
  )
}
