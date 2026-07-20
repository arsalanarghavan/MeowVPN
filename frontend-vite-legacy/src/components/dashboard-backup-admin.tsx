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
import { Button } from "@/components/ui/button"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DashPage } from "@/components/dash-page"
import { DataPagination } from "@/components/data-pagination"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  downloadAdminBackupFile,
  getAdminJson,
  postAdminFormData,
  postAdminJson,
  postAdminMutate,
} from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { DashSelect } from "@/components/dash-select"
import { formatNumber, formatServiceExpiryLine } from "@/lib/format-locale"
import { useAdminTp } from "@/lib/use-admin-tp"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"
import { mainEnabledPlatforms } from "@/lib/enabled-platforms"
import { useDashLocale } from "@/lib/dash-locale-context"

type DashRecord = Record<string, unknown>

type BackupRow = {
  filename: string
  size_bytes: number
  created_at: number | string
  has_panel_db: boolean
  panel_db_status?: string
  panel_db_detail?: string
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
  if (typeof createdAt === "number" && createdAt > 0) {
    return tsLabel(createdAt, isFa)
  }
  const n = num(createdAt)
  if (n > 1_000_000_000) {
    return tsLabel(n, isFa)
  }
  return formatServiceExpiryLine(String(createdAt), isFa)
}

type RestoreStats = {
  users_matched?: number
  users_inserted?: number
  users_skipped?: number
  errors?: unknown[]
  panel_restore?: { ok_count?: number; fail_count?: number }
}

type PanelDbFailure = {
  panel_id?: number
  label?: string
  step?: string
  getdb_url?: string
}

function formatPanelDbStep(
  step: string,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  const s = String(step ?? "").trim()
  if (!s) return tp("panelDbStep_unknown")
  if (s.startsWith("http_")) {
    return tp("panelDbStep_http", { code: s.slice(5) || "?" })
  }
  const key = `panelDbStep_${s}`
  const tr = tp(key)
  if (tr !== key) return tr
  return tp("panelDbStep_unknown", { step: s })
}

function translatePanelDbDetail(
  detail: string,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  if (!detail) return ""
  return detail.replace(/\(([^)]+)\)/g, (_, step: string) => `(${formatPanelDbStep(step, tp)})`)
}

function formatBackupApiError(
  json: Record<string, unknown>,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  const raw = String(json.message ?? "").trim()
  if (raw && raw !== "invalid_html_response" && !raw.startsWith("bad_json") && !raw.startsWith("http_")) {
    return raw
  }
  if (raw === "invalid_html_response") {
    const status = Number(json.http_status)
    if (status === 504) {
      return tp("backupGatewayTimeout")
    }
    const base = tp("invalidHtmlResponse")
    const hint = tp("invalidHtmlNetworkHint")
    const line = Number.isFinite(status) && status > 0 ? `${base} (HTTP ${status})` : base
    return `${line}\n${hint}`
  }
  if (raw.startsWith("bad_json")) {
    return `${tp("invalidHtmlResponse")}\n${tp("invalidHtmlNetworkHint")}`
  }
  return raw || tp("backupNowError")
}

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
  panel_db_failures?: PanelDbFailure[]
  sent?: number
  failed?: number
  stored_on_site?: boolean
  storage_fallback?: boolean
  delivery?: Record<string, DeliveryBucket>
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
  if (skipped > 0 && ok < 1 && fail < 1) {
    return tp(`delivery_${key}_skipped`)
  }
  return tp(`delivery_${key}_result`, { ok, fail })
}

const BACKUP_POLL_INTERVAL_MS = 3000
const BACKUP_POLL_MAX_MS = 10 * 60 * 1000
const BACKUP_POLL_LONG_HINT_MS = 2 * 60 * 1000

function sleepMs(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

async function pollManualBackupUntilDone(
  tp: (k: string, o?: Record<string, string | number>) => string,
  onPollTick?: (elapsedMs: number) => void
): Promise<Record<string, unknown>> {
  const pollStarted = Date.now()
  const deadline = pollStarted + BACKUP_POLL_MAX_MS
  while (Date.now() < deadline) {
    onPollTick?.(Date.now() - pollStarted)
    const st = await getAdminJson("/dashboard/admin/backup/status", {})
    const status = String(st.status ?? "")
    if (status === "done" || status === "error") {
      return st
    }
    if (status !== "running") {
      await sleepMs(BACKUP_POLL_INTERVAL_MS)
      continue
    }
    await sleepMs(BACKUP_POLL_INTERVAL_MS)
  }
  return { ok: false, status: "error", message: tp("backupPollTimeout") }
}

function formatBackupRunReport(
  data: BackupRunData | undefined,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  if (!data) return ""
  const parts: string[] = []
  if (typeof data.message === "string" && data.message) {
    parts.push(data.message)
  }
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
  const failBlock = formatPanelDbFailures(failures, tp)
  if (failBlock) parts.push(failBlock)
  return parts.filter(Boolean).join("\n\n")
}

const SKIPPED_REASON_KEYS: Record<string, string> = {
  lock: "skippedReasonLock",
  enabled: "skippedReasonEnabled",
  zip: "skippedReasonZip",
  max_size: "skippedReasonMaxSize",
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

function backupMsgFromManualStatus(
  st: Record<string, unknown>,
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  const status = String(st.status ?? "")
  const code = String(st.code ?? "")
  if (code === "already_running") {
    return tp("backupAlreadyRunning")
  }
  if (status === "error" || st.ok === false) {
    return typeof st.message === "string" && st.message ? st.message : formatBackupApiError(st, tp)
  }
  const data = st.data as BackupRunData | undefined
  const report = formatBackupRunReport(data, tp)
  return report || tp("backupNowSuccess")
}

function formatPanelDbFailures(
  failures: PanelDbFailure[],
  tp: (k: string, o?: Record<string, string | number>) => string
): string {
  if (failures.length === 0) return ""
  const lines = failures.map((f) => {
    const label = String(f.label ?? "").trim() || `#${num(f.panel_id)}`
    const step = formatPanelDbStep(String(f.step ?? ""), tp)
    const url = String(f.getdb_url ?? "").trim()
    return [tp("panelDbFailureLine", { label, step }), url].filter(Boolean).join("\n")
  })
  return [tp("panelDbFailuresTitle"), ...lines].join("\n")
}

function panelDbListLabel(
  row: BackupRow,
  tp: (k: string, o?: Record<string, string | number>) => string): string {
  const status = String(row.panel_db_status ?? (row.has_panel_db ? "full" : "none"))
  if (status === "full") return tp("panelYes")
  if (status === "partial") {
    const detail = translatePanelDbDetail(String(row.panel_db_detail ?? "").trim(), tp)
    return detail ? `${tp("panelPartial")}: ${detail}` : tp("panelPartial")
  }
  if (status === "none") {
    const detail = translatePanelDbDetail(String(row.panel_db_detail ?? "").trim(), tp)
    return detail ? `${tp("panelNoneFailed")}: ${detail}` : tp("panelNoneFailed")
  }
  if (status === "na") return tp("panelNa")
  return row.has_panel_db ? tp("panelYes") : tp("panelNo")
}

function formatRestoreReport(data: unknown, tp: (k: string, o?: Record<string, string | number>) => string): string {
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
      }))
  }
  const errN = Array.isArray(d.errors) ? d.errors.length : 0
  if (errN > 0) {
    lines.push(tp("restoreReportErrors", { n: errN }))
  }
  return lines.join("\n")
}

export function DashboardBackupAdmin({
  settings,
  onMutateSuccess,
}: {
  settings: DashRecord | undefined
onMutateSuccess?: () => void
}) {
  const { isFa, iconGapClass } = useDashLocale()

  const { t } = useTranslation()
  const tp = useAdminTp("backupAdmin")
  const s = settings ?? {}
  const showTg = mainEnabledPlatforms(s).includes("telegram")
  const showBale = mainEnabledPlatforms(s).includes("bale")

  const initial = useMemo(
    () => ({
      backup_interval_minutes: String(Math.max(5, num(s.backup_interval_minutes) || 60)),
      backup_telegram_chat_id: String(num(s.backup_telegram_chat_id)),
      backup_bale_chat_id: String(num(s.backup_bale_chat_id)),
      backup_send_telegram_admins: bool(s.backup_send_telegram_admins),
      backup_send_bale_admins: bool(s.backup_send_bale_admins),
      backup_send_telegram_channel: bool(s.backup_send_telegram_channel),
      backup_send_bale_channel: bool(s.backup_send_bale_channel),
      backup_store_on_site: bool(s.backup_store_on_site),
      backup_site_retention_count: String(Math.max(1, Math.min(500, num(s.backup_site_retention_count) || 14))),
      backup_max_zip_mb: String(Math.max(0, num(s.backup_max_zip_mb))),
    }),
    [s]
  )

  const [form, setForm] = useState(initial)
  useEffect(() => {
    setForm(initial)
  }, [initial])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [backupRows, setBackupRows] = useState<BackupRow[]>([])
  const [backupPage, setBackupPage] = useState(1)
  const [backupPerPage, setBackupPerPage] = useState(15)
  const [storeOnSiteLive, setStoreOnSiteLive] = useState(bool(s.backup_store_on_site))
  const [lastBackupAt, setLastBackupAt] = useState(0)
  const [lastBuiltAt, setLastBuiltAt] = useState(0)
  const [nextBackupAt, setNextBackupAt] = useState(0)
  const [cronRegistered, setCronRegistered] = useState(true)
  const [cronSchedule, setCronSchedule] = useState("")
  const [cronWantedSchedule, setCronWantedSchedule] = useState("")
  const [backupDisplayTz, setBackupDisplayTz] = useState("")
  const [siteTimezone, setSiteTimezone] = useState("")
  const [lastRun, setLastRun] = useState<LastBackupRun | null>(null)
  const [lastCronPingAt, setLastCronPingAt] = useState(0)
  const [cronPingIntervalSeconds, setCronPingIntervalSeconds] = useState(120)
  const [serverCrontabLine, setServerCrontabLine] = useState("")
  const [cronCopyHint, setCronCopyHint] = useState<string | null>(null)
  const [downloadBusy, setDownloadBusy] = useState<string | null>(null)
  const [listLoading, setListLoading] = useState(false)
  const [listError, setListError] = useState<string | null>(null)
  const [backupRunning, setBackupRunning] = useState(false)
  const [backupRunStartedAt, setBackupRunStartedAt] = useState(0)
  const [resetStuckBusy, setResetStuckBusy] = useState(false)
  const [backupMsg, setBackupMsg] = useState<string | null>(null)
  const [restoreTarget, setRestoreTarget] = useState<BackupRow | null>(null)
  const [restorePanelDb, setRestorePanelDb] = useState(false)
  const [restoreBusy, setRestoreBusy] = useState(false)
  const [uploadFile, setUploadFile] = useState<File | null>(null)
  const [uploadConfirm, setUploadConfirm] = useState(false)
  const [uploadRestorePanelDb, setUploadRestorePanelDb] = useState(false)
  const [uploadBusy, setUploadBusy] = useState(false)
  const [uploadMsg, setUploadMsg] = useState<string | null>(null)

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
      tp("inboundMapRowHint", {
        id: row.id,
        protocol: String(row.protocol ?? "—"),
        port: num(row.port),
        remark: String(row.remark ?? "—"),
        count: count ?? 0,
      }),
    [tp])

  const buildInboundMapPayload = useCallback(() => {
    const out: Record<string, number> = {}
    for (const row of dbInbounds) {
      const old = row.id
      const neu = num(inboundMapDraft[String(old)] || old)
      if (neu > 0) {
        out[String(old)] = neu
      }
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
      const json = await getAdminJson("/dashboard/admin/panel/inbound-map", { panel_id: pid })
      if (!json.ok) {
        setInboundMapError(String(json.message || t("backupAdmin.inboundMapLoadError")))
        return
      }
      const db = Array.isArray(json.db_inbounds) ? (json.db_inbounds as DbInboundRow[]) : []
      const live = Array.isArray(json.panel_inbounds) ? (json.panel_inbounds as PanelInboundRow[]) : []
      const stored = json.map && typeof json.map === "object" ? (json.map as Record<string, number>) : {}
      const suggest =
        json.suggested_map && typeof json.suggested_map === "object"
          ? (json.suggested_map as Record<string, number>)
          : {}
      const draft: Record<string, string> = {}
      for (const row of db) {
        const old = String(row.id)
        const fromStore = stored[row.id] ?? stored[Number(old)]
        const fromSuggest = suggest[row.id] ?? suggest[Number(old)]
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
  }, [rebuildPanelId])

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
      const json = await postAdminJson("/dashboard/admin/panel/fix-51200-traffic", {
        panel_id: pid,
        dry_run: true,
        offset: 0,
      })
      if (json.ok) {
        setFix51200Count(num(json.total))
      }
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
            String(p.protocol ?? "").toLowerCase() === String(row.protocol ?? "").toLowerCase())
        if (live) {
          next[String(row.id)] = String(live.id)
          continue
        }
        const byRemark = panelInbounds.filter(
          (p) => String(p.remark ?? "").toLowerCase() === String(row.remark ?? "").toLowerCase())
        if (byRemark.length === 1) {
          next[String(row.id)] = String(byRemark[0].id)
        }
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
        const json = await postAdminJson("/dashboard/admin/panel/inbound-map", {
          panel_id: pid,
          map: buildInboundMapPayload(),
          apply_to_db: applyToDb ? 1 : 0,
        })
        if (!json.ok) {
          setInboundMapError(String(json.message || tp("saveError")))
          return
        }
        if (applyToDb && json.db_counts && typeof json.db_counts === "object") {
          const c = json.db_counts as { services?: number; plans?: number }
          setInboundMapMsg(
            tp("inboundMapSaveDbOk", {
              services: num(c.services),
              plans: num(c.plans),
            }))
        } else {
          setInboundMapMsg(tp("inboundMapSaved"))
        }
        await loadInboundMap()
      } finally {
        setInboundMapLoading(false)
      }
    },
    [buildInboundMapPayload, loadInboundMap, rebuildPanelId, tp])

  const loadBackups = useCallback(async () => {
    setListLoading(true)
    setListError(null)
    try {
      const json = await getAdminJson("/dashboard/admin/backups", {})
      if (!json.ok) {
        setListError(String(json.message || t("backupAdmin.loadError")))
        return
      }
      const list = Array.isArray(json.rows) ? (json.rows as BackupRow[]) : []
      setBackupRows(list)
      const panels = Array.isArray(json.panels) ? (json.panels as PanelOption[]) : []
      setPanelOptions(
        panels
          .map((p) => ({ id: num(p.id), label: String(p.label ?? `#${num(p.id)}`) }))
          .filter((p) => p.id > 0))
      setStoreOnSiteLive(bool(json.store_on_site))
      setLastBackupAt(num(json.last_backup_at))
      setLastBuiltAt(num(json.last_built_at))
      setNextBackupAt(num(json.next_backup_at))
      setCronRegistered(json.cron_registered !== false)
      setCronSchedule(String(json.cron_schedule ?? ""))
      setCronWantedSchedule(String(json.cron_wanted_schedule ?? ""))
      setBackupDisplayTz(String(json.backup_display_timezone ?? ""))
      setSiteTimezone(String(json.site_timezone ?? ""))
      const lr = json.last_run
      setLastRun(lr && typeof lr === "object" ? (lr as LastBackupRun) : null)
      setLastCronPingAt(num(json.last_cron_ping_at))
      setCronPingIntervalSeconds(Math.max(60, num(json.cron_ping_interval_seconds) || 120))
      setServerCrontabLine(String(json.server_crontab_line ?? ""))
    } finally {
      setListLoading(false)
    }
  }, [])

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
      setCronCopyHint(tp("cronServerCopied"))
    } catch {
      setCronCopyHint(null)
    }
    window.setTimeout(() => setCronCopyHint(null), 2200)
  }, [serverCrontabLine, tp])

  const onDownloadBackup = useCallback(
    async (filename: string) => {
      setDownloadBusy(filename)
      setListError(null)
      try {
        const res = await downloadAdminBackupFile(filename)
        if (!res.ok) {
          setListError(res.message || tp("downloadError"))
        }
      } finally {
        setDownloadBusy(null)
      }
    },
    [tp]
  )

  useEffect(() => {
    void loadBackups()
  }, [loadBackups])

  useEffect(() => {
    let cancelled = false
    const resume = async () => {
      const st = await getAdminJson("/dashboard/admin/backup/status", {})
      if (cancelled) return
      const status = String(st.status ?? "")
      if (status === "running") {
        setBackupRunning(true)
        setBackupMsg(tp("backupRunningAsync"))
        const final = await pollManualBackupUntilDone(tp, (elapsed) => {
          if (elapsed >= BACKUP_POLL_LONG_HINT_MS) {
            setBackupMsg(tp("backupRunningLong"))
          }
        })
        if (cancelled) return
        setBackupMsg(backupMsgFromManualStatus(final, tp))
        if (final.status === "done" || (final.data && typeof final.data === "object")) {
          await loadBackups()
        }
        setBackupRunning(false)
        return
      }
      if (status === "done" || status === "error") {
        setBackupMsg(backupMsgFromManualStatus(st, tp))
        await loadBackups()
      }
    }
    void resume()
    return () => {
      cancelled = true
    }
  }, [loadBackups, tp])

  useEffect(() => {
    setBackupPage(1)
  }, [backupRows.length])

  const backupListMeta = useMemo((): PaginationMeta | null => {
    if (backupRows.length === 0) return null
    return { page: backupPage, perPage: backupPerPage, total: backupRows.length }
  }, [backupRows.length, backupPage, backupPerPage])

  const pagedBackupRows = useMemo(() => {
    const start = (backupPage - 1) * backupPerPage
    return backupRows.slice(start, start + backupPerPage)
  }, [backupRows, backupPage, backupPerPage])

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
        setError(res.message || tp("saveError"))
        return
      }
      setStoreOnSiteLive(form.backup_store_on_site)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, onMutateSuccess, tp])

  const backupStuckLikely = useMemo(() => {
    if (!backupRunning || backupRunStartedAt < 1) return false
    return Date.now() - backupRunStartedAt >= BACKUP_POLL_LONG_HINT_MS
  }, [backupRunning, backupRunStartedAt])

  const onResetBackupStuck = useCallback(async () => {
    setResetStuckBusy(true)
    setListError(null)
    try {
      const json = await postAdminJson("/dashboard/admin/backup/reset-stuck", {})
      if (!json.ok) {
        setListError(String(json.message || tp("backupNowError")))
        return
      }
      setBackupRunning(false)
      setBackupRunStartedAt(0)
      setBackupMsg(tp("backupResetStuckOk"))
    } finally {
      setResetStuckBusy(false)
    }
  }, [tp])

  const onBackupNow = useCallback(async () => {
    setBackupRunning(true)
    setBackupRunStartedAt(Date.now())
    setBackupMsg(tp("backupNowRunning"))
    try {
      const json = await postAdminJson("/dashboard/admin/backup/run", {})
      const gateway504 =
        !json.ok &&
        json.message === "invalid_html_response" &&
        Number(json.http_status) === 504
      if (!json.ok && !gateway504) {
        setBackupMsg(backupMsgFromManualStatus(json, tp))
        setBackupRunning(false)
        setBackupRunStartedAt(0)
        return
      }
      if (gateway504) {
        setBackupMsg(tp("backupGatewayTimeout"))
      } else if (json.async === true || json.status === "running") {
        const warn = String(json.delivery_warning ?? "").trim()
        setBackupMsg(warn ? `${tp("backupRunningAsync")}\n${tp("backupDeliveryWarning")}\n${warn}` : tp("backupRunningAsync"))
      } else if (json.data && typeof json.data === "object") {
        const report = formatBackupRunReport(json.data as BackupRunData, tp)
        setBackupMsg(report || tp("backupNowSuccess"))
        await loadBackups()
        return
      }
      const final = await pollManualBackupUntilDone(tp, (elapsed) => {
        if (elapsed >= BACKUP_POLL_LONG_HINT_MS) {
          setBackupMsg(tp("backupRunningLong"))
        }
      })
      setBackupMsg(backupMsgFromManualStatus(final, tp))
      if (final.status === "done" || (final.data && typeof final.data === "object")) {
        await loadBackups()
      }
    } finally {
      setBackupRunning(false)
      setBackupRunStartedAt(0)
    }
  }, [loadBackups, tp])

  const onRestoreFile = useCallback(async () => {
    if (!restoreTarget) return
    setRestoreBusy(true)
    setListError(null)
    try {
      const json = await postAdminJson("/dashboard/admin/backup/restore", {
        filename: restoreTarget.filename,
        confirm: true,
        restore_panel_db: restorePanelDb ? 1 : 0,
      })
      if (!json.ok) {
        setListError(String(json.message || tp("restoreError")))
        return
      }
      const report = formatRestoreReport(json.data, tp)
      setBackupMsg([String(json.message || tp("restoreSuccess")), report].filter(Boolean).join("\n"))
      setRestoreTarget(null)
      setRestorePanelDb(false)
      onMutateSuccess?.()
    } finally {
      setRestoreBusy(false)
    }
  }, [onMutateSuccess, restorePanelDb, restoreTarget, tp])

  const onUploadRestore = useCallback(async () => {
    if (!uploadFile || !uploadConfirm) return
    setUploadBusy(true)
    setUploadMsg(null)
    try {
      const fd = new FormData()
      fd.append("confirm", "1")
      if (uploadRestorePanelDb) {
        fd.append("restore_panel_db", "1")
      }
      fd.append("file", uploadFile)
      const json = await postAdminFormData("/dashboard/admin/backup/restore-upload", fd)
      if (!json.ok) {
        setUploadMsg(String(json.message || tp("restoreError")))
        return
      }
      const report = formatRestoreReport(json.data, tp)
      setUploadMsg([String(json.message || tp("restoreSuccess")), report].filter(Boolean).join("\n"))
      setUploadFile(null)
      setUploadConfirm(false)
      setUploadRestorePanelDb(false)
      onMutateSuccess?.()
    } finally {
      setUploadBusy(false)
    }
  }, [onMutateSuccess, uploadConfirm, uploadFile, uploadRestorePanelDb, tp])

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
        if (mapPayload && Object.keys(mapPayload).length > 0) {
          body.inbound_map = mapPayload
        }
        const json = await postAdminJson("/dashboard/admin/panel/rebuild-from-db", body)
        if (!json.ok) {
          setRebuildMsg(String(json.message || tp("rebuildError")))
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
        if (bool(json.done)) {
          break
        }
      }
      setRebuildMsg(
        [
          tp("rebuildReport", {
            created: num(totals.created),
            patched: num(totals.patched),
            skipped: num(totals.skipped),
            failed: num(totals.failed),
          }),
          errSamples.length > 0 ? errSamples.join("\n") : "",
          rebuildDryRun ? "" : tp("rebuildDone"),
        ]
          .filter(Boolean)
          .join("\n"))
      setRebuildOpen(false)
      if (!rebuildDryRun) onMutateSuccess?.()
    } finally {
      setRebuildBusy(false)
    }
  }, [buildInboundMapPayload, onMutateSuccess, rebuildDryRun, rebuildPanelId, tp])

  const onFix51200 = useCallback(async () => {
    const pid = num(rebuildPanelId)
    if (pid < 1) return
    setFix51200Busy(true)
    setFix51200Msg(null)
    const totals = { fixed: 0, skipped: 0, failed: 0, noSource: 0 }
    let offset = 0
    try {
      for (;;) {
        const json = await postAdminJson("/dashboard/admin/panel/fix-51200-traffic", {
          confirm: true,
          panel_id: pid,
          offset,
          inbound_map: buildInboundMapPayload(),
        })
        if (!json.ok) {
          setFix51200Msg(String(json.message || tp("saveError")))
          return
        }
        const batch = json.totals as {
          fixed?: number
          skipped?: number
          failed?: number
          no_source?: number
        } | undefined
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
          totals.fixed < 1 && totals.failed < 1 ? tp("fix51200None") : "",
          tp("fix51200Report", {
            fixed: totals.fixed,
            skipped: totals.skipped,
            noSource: totals.noSource,
            failed: totals.failed,
          }),
          tp("fix51200Done"),
        ]
          .filter(Boolean)
          .join("\n"))
      setFix51200Open(false)
      await refreshFix51200Count()
      onMutateSuccess?.()
    } finally {
      setFix51200Busy(false)
    }
  }, [buildInboundMapPayload, onMutateSuccess, refreshFix51200Count, rebuildPanelId, tp])

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
    <DashPage className={"w-full space-y-6"} data-testid="dash-backup-tab">
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />

      <div className="grid gap-6 xl:grid-cols-2 xl:items-start">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{tp("cardTitle")}</CardTitle>
          <CardDescription>{tp("cardDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="b_int">{tp("intervalMinutes")}</Label>
            <Input
              id="b_int"
              type="number"
              min={5}
              value={form.backup_interval_minutes}
              onChange={(e) => setForm((f) => ({ ...f, backup_interval_minutes: e.target.value }))}
            />
            <p className="text-xs text-muted-foreground">{tp("intervalHint", { min: formatNumber(5, isFa) })}</p>
          </div>
          {showTg ? (
          <div className="space-y-2">
            <Label htmlFor="b_tg">{tp("telegramChatId")}</Label>
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
            <Label htmlFor="b_bl">{tp("baleChatId")}</Label>
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
            <p className="text-sm font-medium">{tp("siteStorageTitle")}</p>
            {chk("backup_store_on_site", "storeOnSite")}
            <div className="space-y-2">
              <Label htmlFor="b_ret">{tp("retentionCount")}</Label>
              <Input
                id="b_ret"
                type="number"
                min={1}
                max={500}
                value={form.backup_site_retention_count}
                onChange={(e) => setForm((f) => ({ ...f, backup_site_retention_count: e.target.value }))}
              />
              <p className="text-xs text-muted-foreground">{tp("retentionHint")}</p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="b_maxmb">{tp("maxZipMb")}</Label>
              <Input
                id="b_maxmb"
                type="number"
                min={0}
                value={form.backup_max_zip_mb}
                onChange={(e) => setForm((f) => ({ ...f, backup_max_zip_mb: e.target.value }))}
              />
              <p className="text-xs text-muted-foreground">{tp("maxZipMbHint")}</p>
            </div>
          </div>
          {error ? (
            <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {error}
            </div>
          ) : null}
          <Button type="button" disabled={saving} onClick={() => void onSave()}>
            {tp("save")}
          </Button>
        </CardContent>
      </Card>

      <Card className="min-w-0">
        <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
          <div>
            <CardTitle className="text-base">{tp("storedTitle")}</CardTitle>
            <CardDescription>{tp("storedDesc")}</CardDescription>
          </div>
          <Button type="button" variant="secondary" disabled={backupRunning} onClick={() => void onBackupNow()}>
            {backupRunning ? tp("backupNowRunning") : tp("backupNow")}
          </Button>
        </CardHeader>
        <CardContent className="space-y-3">
          {lastBuiltAt > 0 ? (
            <p className="text-xs text-muted-foreground">{tp("lastBuiltAt", { at: tsLabel(lastBuiltAt, isFa) })}</p>
          ) : null}
          {lastBackupAt > 0 ? (
            <p className="text-xs text-muted-foreground">{tp("lastBackupAt", { at: tsLabel(lastBackupAt, isFa) })}</p>
          ) : null}
          {nextBackupAt > 0 ? (
            <p className="text-xs text-muted-foreground">{tp("nextBackupAt", { at: tsLabel(nextBackupAt, isFa) })}</p>
          ) : null}
          {!cronRegistered ? (
            <p className="text-xs text-amber-700 dark:text-amber-400">{tp("cronNotRegistered")}</p>
          ) : null}
          {cronRegistered &&
          cronSchedule &&
          cronWantedSchedule &&
          cronSchedule !== cronWantedSchedule ? (
            <p className="text-xs text-amber-700 dark:text-amber-400">
              {tp("cronScheduleMismatch", { current: cronSchedule, wanted: cronWantedSchedule })}
            </p>
          ) : null}
          {backupDisplayTz ? (
            <p className="text-xs text-muted-foreground">{tp("backupTimezoneCaption", { tz: backupDisplayTz })}</p>
          ) : null}
          {siteTimezone && siteTimezone !== backupDisplayTz ? (
            <p className="text-xs text-muted-foreground">{tp("siteTimezoneCaption", { tz: siteTimezone })}</p>
          ) : null}
          {lastRun && num(lastRun.at) > 0 ? (
            <p className="text-xs text-muted-foreground">
              {String(lastRun.skipped_reason ?? "").trim()
                ? tp("lastRunSkipped", {
                    at: tsLabel(num(lastRun.at), isFa),
                    reason: formatSkippedReason(String(lastRun.skipped_reason), tp),
                  })
                : tp("lastRunSummary", {
                    at: tsLabel(num(lastRun.at), isFa),
                    built: lastRun.built ? "✓" : "✗",
                    sent: String(num(lastRun.sent)),
                  })}
            </p>
          ) : null}
          {backupStuckLikely ? (
            <div
              role="alert"
              className="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm text-amber-800 dark:text-amber-200">
              <p>{tp("backupStuckBanner")}</p>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-2"
                disabled={resetStuckBusy}
                onClick={() => void onResetBackupStuck()}>
                {tp("backupResetStuck")}
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
              onClick={() => void onResetBackupStuck()}>
              {tp("backupResetStuck")}
            </Button>
          ) : null}
          <div className="rounded-md border border-border/80 bg-muted/30 px-3 py-2 space-y-2">
            <p className="text-sm font-medium">{tp("cronKeeperTitle")}</p>
            <p className="text-xs text-muted-foreground">
              {tp("cronKeeperDesc", { seconds: String(cronPingIntervalSeconds) })}
            </p>
            {lastCronPingAt > 0 ? (
              <p className="text-xs text-muted-foreground">
                {tp("cronKeeperLastPing", { at: tsLabel(lastCronPingAt, isFa) })}
              </p>
            ) : (
              <p className="text-xs text-muted-foreground">{tp("cronKeeperNeverPing")}</p>
            )}
            <p className="text-sm font-medium pt-1">{tp("cronServerTitle")}</p>
            {serverCrontabLine ? (
              <pre className="overflow-x-auto rounded bg-background/80 px-2 py-1 text-xs font-mono whitespace-pre-wrap break-all">
                {serverCrontabLine}
              </pre>
            ) : null}
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={!serverCrontabLine.trim()}
                onClick={() => void onCopyCrontabLine()}>
                {tp("cronServerCopy")}
              </Button>
              {cronCopyHint ? <span className="text-xs text-emerald-600">{cronCopyHint}</span> : null}
            </div>
            <p className="text-xs text-muted-foreground">{tp("cronServerHint")}</p>
          </div>
          {backupMsg ? <p className="whitespace-pre-wrap text-sm text-muted-foreground">{backupMsg}</p> : null}
          {!storeOnSiteLive ? (
            <p className="text-sm text-amber-700 dark:text-amber-400">{tp("storeOffHint")}</p>
          ) : null}
          {listError ? (
            <div role="alert" className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {listError}
            </div>
          ) : null}
          <DashTableShell minWidth="40rem" colWidths={["28%", "12%", "28%", "32%"]}>
            <thead>
              <tr className="bg-muted/40">
                <DashTh>{tp("colDate")}</DashTh>
                <DashTh>{tp("colSize")}</DashTh>
                <DashTh>{tp("colPanel")}</DashTh>
                <DashTh />
              </tr>
            </thead>
            <tbody>
              {listLoading && backupRows.length === 0 ? (
                <tr>
                  <DashTd colSpan={4} className="text-center text-muted-foreground">
                    {tp("loading")}
                  </DashTd>
                </tr>
              ) : null}
              {!listLoading && backupRows.length === 0 ? (
                <tr>
                  <DashTd colSpan={4} className="text-center text-muted-foreground">
                    {tp("emptyList")}
                  </DashTd>
                </tr>
              ) : null}
              {pagedBackupRows.map((row) => (
                <tr key={row.filename}>
                  <DashTd className="whitespace-nowrap text-xs">
                    {backupRowDateLabel(row.created_at, isFa)}
                  </DashTd>
                  <DashTd className="text-xs tabular-nums">{formatBytes(row.size_bytes, isFa)}</DashTd>
                  <DashTd className="text-xs">{panelDbListLabel(row, tp)}</DashTd>
                  <DashTd>
                    <div className={cn("flex flex-wrap gap-2", isFa ? "justify-end" : "justify-start")}>
                      <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        disabled={downloadBusy === row.filename}
                        onClick={() => void onDownloadBackup(row.filename)}
                      >
                        {downloadBusy === row.filename ? tp("loading") : tp("downloadBtn")}
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
                        {tp("restoreBtn")}
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
          <CardTitle className="text-base">{tp("rebuildPanelTitle")}</CardTitle>
          <CardDescription>{tp("rebuildPanelDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="rebuild-panel">{tp("rebuildPanelScope")}</Label>
            <DashSelect
              id="rebuild-panel"
              value={rebuildPanelId}
              onValueChange={setRebuildPanelId}
              disabled={rebuildBusy}
              options={[
                { value: "0", label: tp("rebuildPanelAll") },
                ...panelOptions.map((p) => ({ value: String(p.id), label: p.label })),
              ]}
            />
          </div>

          <div className="space-y-3 rounded-md border border-amber-500/40 bg-amber-500/5 p-3">
            <p className="text-sm font-medium">{tp("inboundMapTitle")}</p>
            <p className="text-xs text-muted-foreground">{tp("inboundMapDesc")}</p>
            {num(rebuildPanelId) < 1 ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">{tp("inboundMapPickPanel")}</p>
            ) : (
              <>
                {inboundMapError ? (
                  <p className="text-xs text-destructive">{inboundMapError}</p>
                ) : null}
                {inboundMapMsg ? <p className="text-xs text-muted-foreground">{inboundMapMsg}</p> : null}
                {inboundMapMissing > 0 ? (
                  <p className="text-xs text-amber-700 dark:text-amber-400">
                    {tp("inboundMapMissing", { n: inboundMapMissing })}
                  </p>
                ) : null}
                <div className={cn("flex flex-wrap gap-2")}>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void loadInboundMap()}
                  >
                    {tp("inboundMapLoad")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={inboundMapLoading || rebuildBusy || dbInbounds.length === 0}
                    onClick={applyInboundSuggest}
                  >
                    {tp("inboundMapSuggest")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void saveInboundMap(false)}
                  >
                    {tp("inboundMapSave")}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={inboundMapLoading || rebuildBusy}
                    onClick={() => void saveInboundMap(true)}
                  >
                    {tp("inboundMapSaveDb")}
                  </Button>
                </div>
                {inboundMapLoading && dbInbounds.length === 0 ? (
                  <p className="text-xs text-muted-foreground">{tp("loading")}</p>
                ) : null}
                {dbInbounds.length > 0 ? (
                  <DashTableShell minWidth="40rem" colWidths={["45%", "55%"]}>
                    <thead>
                      <tr className="bg-muted/40">
                        <DashTh className="text-xs">{tp("inboundMapDbCol")}</DashTh>
                        <DashTh className="text-xs">{tp("inboundMapPanelCol")}</DashTh>
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
                                  {tp("inboundMapSameId")}
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
                                onValueChange={(v) =>
                                  setInboundMapDraft((d) => ({ ...d, [oldKey]: v }))
                                }
                                allowEmpty
                                placeholder={tp("inboundMapNone")}
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
            <p className="text-sm font-medium">{tp("resellerBackfillTitle")}</p>
            <p className="text-xs text-muted-foreground">{tp("resellerBackfillHint")}</p>
            {resellerBackfillResult ? (
              <p className="whitespace-pre-wrap text-xs text-muted-foreground">{resellerBackfillResult}</p>
            ) : null}
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={resellerBackfillBusy || rebuildBusy}
              onClick={() => {
                void (async () => {
                  setResellerBackfillBusy(true)
                  setResellerBackfillResult(null)
                  setError(null)
                  try {
                    const res = await postAdminMutate("reseller_backfill_run", {})
                    if (!res.ok) {
                      setError(String(res.message || tp("resellerBackfillError")))
                      return
                    }
                    const billing = (res.billing ?? {}) as Record<string, unknown>
                    const invited = (res.invited ?? {}) as Record<string, unknown>
                    setResellerBackfillResult(
                      tp("resellerBackfillResult", {
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
                })()
              }}
            >
              {resellerBackfillBusy ? t("loading") : tp("resellerBackfillRun")}
            </Button>
          </div>

          <div className="space-y-3 rounded-md border border-destructive/30 bg-destructive/5 p-3">
            <p className="text-sm font-medium">{tp("fix51200Title")}</p>
            <p className="text-xs text-muted-foreground">{tp("fix51200Desc")}</p>
            {num(rebuildPanelId) < 1 ? (
              <p className="text-xs text-amber-700 dark:text-amber-400">{tp("inboundMapPickPanel")}</p>
            ) : fix51200Count != null ? (
              <p className="text-xs font-medium text-amber-800 dark:text-amber-300">
                {tp("fix51200Preview", { n: formatNumber(fix51200Count, isFa) })}
              </p>
            ) : null}
            {fix51200Msg ? <p className="whitespace-pre-wrap text-xs text-muted-foreground">{fix51200Msg}</p> : null}
            <Button
              type="button"
              variant="secondary"
              disabled={fix51200Busy || rebuildBusy || num(rebuildPanelId) < 1 || fix51200Count === 0}
              onClick={() => setFix51200Open(true)}
            >
              {fix51200Busy ? tp("fix51200Running") : tp("fix51200Run")}
            </Button>
          </div>

          <label className={iconGapClass("text-sm")}>
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={rebuildDryRun}
              onChange={(e) => setRebuildDryRun(e.target.checked)}
              disabled={rebuildBusy}
            />
            {tp("rebuildDryRun")}
          </label>
          {rebuildBusy && rebuildProgress.total > 0 ? (
            <p className="text-sm text-muted-foreground">
              {tp("rebuildProgress", {
                done: formatNumber(rebuildProgress.done, isFa),
                total: formatNumber(rebuildProgress.total, isFa),
              })}
            </p>
          ) : null}
          {rebuildMsg ? <p className="whitespace-pre-wrap text-sm text-muted-foreground">{rebuildMsg}</p> : null}
          <Button
            type="button"
            variant="destructive"
            disabled={rebuildBusy}
            onClick={() => setRebuildOpen(true)}
          >
            {rebuildBusy ? tp("rebuildRunning") : tp("rebuildRun")}
          </Button>
        </CardContent>
      </Card>

      <Card className="max-w-2xl">
        <CardHeader>
          <CardTitle className="text-base">{tp("uploadTitle")}</CardTitle>
          <CardDescription>{tp("uploadDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="restore_zip">{tp("uploadPickFile")}</Label>
            <Input
              id="restore_zip"
              type="file"
              accept=".zip,application/zip"
              onChange={(e) => setUploadFile(e.target.files?.[0] ?? null)}
            />
          </div>
          <label className={iconGapClass("text-sm")}>
            <input
              type="checkbox"
              className="size-4 rounded border-input"
              checked={uploadConfirm}
              onChange={(e) => setUploadConfirm(e.target.checked)}
            />
            {tp("uploadConfirmLabel")}
          </label>
          <label className={iconGapClass("items-start text-sm")}>
            <input
              type="checkbox"
              className="mt-0.5 size-4 rounded border-input"
              checked={uploadRestorePanelDb}
              onChange={(e) => setUploadRestorePanelDb(e.target.checked)}
              disabled={uploadBusy}
            />
            <span>
              {tp("restorePanelDbLabel")}
              <span className="mt-1 block text-xs text-muted-foreground">{tp("restorePanelDbHint")}</span>
            </span>
          </label>
          {uploadMsg ? <p className="text-sm text-muted-foreground">{uploadMsg}</p> : null}
          <Button
            type="button"
            variant="destructive"
            disabled={uploadBusy || !uploadFile || !uploadConfirm}
            onClick={() => void onUploadRestore()}
          >
            {tp("uploadRestore")}
          </Button>
        </CardContent>
      </Card>

      <AlertDialog open={fix51200Open} onOpenChange={(open) => !open && !fix51200Busy && setFix51200Open(open)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{tp("fix51200ConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{tp("fix51200ConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter className={cn("")}>
            <AlertDialogCancel disabled={fix51200Busy}>{tp("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={fix51200Busy} onClick={() => void onFix51200()}>
              {tp("fix51200Confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={rebuildOpen} onOpenChange={(open) => !open && !rebuildBusy && setRebuildOpen(open)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{tp("rebuildConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{tp("rebuildConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter className={cn("")}>
            <AlertDialogCancel disabled={rebuildBusy}>{tp("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={rebuildBusy} onClick={() => void onRebuildPanels()}>
              {tp("rebuildConfirm")}
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
            <AlertDialogTitle>{tp("restoreDialogTitle")}</AlertDialogTitle>
            <AlertDialogDescription className="space-y-3">
              <span className="block">{tp("restoreWarning")}</span>
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
                    {tp("restorePanelDbLabel")}
                    <span className="mt-1 block text-xs opacity-90">{tp("restorePanelDbHint")}</span>
                  </span>
                </label>
              ) : null}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter className={cn("")}>
            <AlertDialogCancel disabled={restoreBusy}>{tp("cancel")}</AlertDialogCancel>
            <AlertDialogAction disabled={restoreBusy} onClick={() => void onRestoreFile()}>
              {tp("restoreConfirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </DashPage>
  )
}