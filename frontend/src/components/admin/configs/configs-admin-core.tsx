"use client"

import { useTranslations } from "next-intl"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { QRCodeSVG } from "qrcode.react"
import {
  ArrowRightLeft,
  ChevronDown,
  Copy,
  Info,
  MoreHorizontal,
  Network,
  Pencil,
  QrCode,
  RotateCcw,
  Trash2,
  UserCheck,
  UserPlus,
  UserRound,
} from "lucide-react"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  ConfigsAttachInboundsDialog,
  ConfigsExpiredOlderDeleteDialog,
  ConfigsPanelOpConfirmDialog,
} from "@/components/admin/configs/configs-panel-ops-dialogs"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { DataPagination } from "@/components/data-pagination"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import {
  DashboardDateTimePicker,
  apiDatetimeToMs,
  msToApiDatetime,
} from "@/components/dashboard-datetime-picker"
import { getAdminJson, postAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { DashPage } from "@/components/dash-page"
import { dashPageRootClass } from "@/lib/dash-locale"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { DashSelect } from "@/components/dash-select"
import { formatBytes, formatDateTime, formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogTitle } from "@/components/ui/dialog"
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from "recharts"

const CONFIGS_BATCH_MAX = 40
const ALL_PANELS = "all" as const
type PanelScope = typeof ALL_PANELS | number

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

/** Stable row id for bulk + busy (includes panel for all-panels mode). */
function rowKey(panelId: number, inboundId: number, email: string): string {
  return `${panelId}::${inboundId}::${email}`
}

function userRowLabel(u: DashRecord): string {
  const fn = String(u.first_name ?? "").trim()
  const ln = String(u.last_name ?? "").trim()
  const nm = `${fn} ${ln}`.trim()
  const un = String(u.username ?? "").trim()
  const bits: string[] = []
  if (nm) bits.push(nm)
  if (un) bits.push(`@${un}`)
  bits.push(`#${num(u.id)}`)
  return bits.join(" · ")
}

type ClientRow = DashRecord & {
  panel_id?: number
  email?: string
  enable?: number
  is_online?: number
  is_linked?: number
  used_bytes?: number
  limit_bytes?: number
  total_gb?: number
  expiry_ms?: number
  limit_ip?: number
  first_usage?: number
  linked_service_id?: number
  subscription_url?: string
  primary_config_uri?: string
  config_uris?: string[]
  portal_url?: string
  primary_link?: string
  volume_exhausted?: number
  date_expired?: number
  service_plan_id?: number
  comment?: string
  remark?: string
  panel_remark?: string
  subscription_name?: string
  subscription_id?: string
  service_canonical?: string
  service_remark?: string
  service_note?: string
  service_expires_at?: string
  client_ips?: unknown
}

function parseClientIps(row: ClientRow): string[] {
  const raw = row.client_ips
  if (!raw) return []
  if (Array.isArray(raw)) {
    return raw.map((x) => String(x).trim()).filter(Boolean)
  }
  return []
}

function parseConfigUris(row: ClientRow): string[] {
  const raw = row.config_uris
  if (!Array.isArray(raw)) return []
  return raw.map((x) => String(x).trim()).filter(Boolean)
}

/** Same subscription/config lines as bot (`Handler_Service::get_portal_service_data`). */
type QrCtx = { panel_id: number; inbound_id: number; row: ClientRow }

function parsePayloadConfigUris(payload: Record<string, unknown> | null): string[] {
  if (!payload || !Array.isArray(payload.config_uris)) return []
  return payload.config_uris.map((x) => String(x).trim()).filter(Boolean)
}

function parsePayloadConfigLabels(payload: Record<string, unknown> | null): string[] {
  if (!payload || !Array.isArray(payload.config_labels)) return []
  return payload.config_labels.map((x) => String(x).trim())
}

function configLineLabel(
  idx: number,
  labels: string[],
  tl: (k: string, opts?: Record<string, string | number>) => string
): string {
  const fromPayload = labels[idx]?.trim()
  if (fromPayload) return fromPayload
  return tl("configLineN", { n: idx + 1 })
}

function effectiveQrSubscriptionUrl(ctx: QrCtx | null, payload: Record<string, unknown> | null): string {
  const p = payload && typeof payload.subscription_url === "string" ? payload.subscription_url.trim() : ""
  if (p) return p
  return ctx ? String(ctx.row.subscription_url ?? "").trim() : ""
}

function effectiveQrPortalUrl(ctx: QrCtx | null, payload: Record<string, unknown> | null): string {
  const p = payload && typeof payload.portal_url === "string" ? payload.portal_url.trim() : ""
  if (p) return p
  return ctx ? String(ctx.row.portal_url ?? "").trim() : ""
}

function effectiveQrConfigUris(ctx: QrCtx | null, payload: Record<string, unknown> | null): string[] {
  const fromPayload = parsePayloadConfigUris(payload)
  if (fromPayload.length > 0) return fromPayload
  if (!ctx) return []
  return parseConfigUris(ctx.row)
}

function effectiveQrPrimaryConfigUri(ctx: QrCtx | null, payload: Record<string, unknown> | null): string {
  const uris = effectiveQrConfigUris(ctx, payload)
  if (uris.length > 0) return uris[0]
  const p = payload && typeof payload.primary_config_uri === "string" ? payload.primary_config_uri.trim() : ""
  if (p) return p
  return ctx ? String(ctx.row.primary_config_uri ?? "").trim() : ""
}

function isVolumeExhausted(row: ClientRow): boolean {
  if (num(row.volume_exhausted) !== 0) return true
  const lim = num(row.limit_bytes)
  if (lim < 1) return false
  return num(row.used_bytes) >= lim
}

/** Linked to bot user with a plan on the service row. */
function isLinkedWithPlan(row: ClientRow): boolean {
  return num(row.is_linked) !== 0 && num(row.linked_service_id) > 0 && num(row.service_plan_id) > 0
}

/** Unlinked user and/or connected service without plan_id — shown in orphan section. */
function isOrphanConfig(row: ClientRow): boolean {
  return !isLinkedWithPlan(row)
}

function planGroupKey(panelId: number, planId: number, inboundId: number): string {
  return `${panelId}-${planId}-${inboundId}`
}

function paginateSlice<T>(items: T[], page: number, perPage: number): T[] {
  const start = (page - 1) * perPage
  return items.slice(start, start + perPage)
}

function serviceExpiresMs(row: ClientRow): number {
  const s = row.service_expires_at
  if (!s || !String(s).trim()) return 0
  const t = Date.parse(String(s).replace(" ", "T"))
  return Number.isFinite(t) ? t : 0
}

/** Prefer panel `expiry_ms` (synced from panel); fall back to DB service expiry. */
function unifiedExpiryMs(row: ClientRow): number {
  const panel = num(row.expiry_ms)
  if (panel > 0) return panel
  return serviceExpiresMs(row)
}

function expirySourcesDiffer(row: ClientRow): boolean {
  const panel = num(row.expiry_ms)
  const svc = serviceExpiresMs(row)
  if (panel < 1 || svc < 1) return false
  return Math.abs(panel - svc) > 3600000
}

function isInternalPanelEmail(email: string): boolean {
  const em = email.trim().toLowerCase()
  return em.length > 0 && /^u\d+[-_][^@]+@svp\.local$/.test(em)
}

function configDisplayName(row: ClientRow): string {
  const subName = String(row.subscription_name ?? row.service_remark ?? "").trim()
  if (subName && !isInternalPanelEmail(subName)) return subName
  const em = String(row.email ?? "").trim()
  if (em && !isInternalPanelEmail(em)) return em
  if (subName) return subName
  return em
}

function needsCanonicalPanelRepair(row: ClientRow): boolean {
  const sid = num(row.linked_service_id)
  if (sid < 1) return false
  const em = String(row.email ?? "").trim()
  if (isInternalPanelEmail(em)) return true
  const canonical = String(row.service_canonical ?? row.service_remark ?? row.subscription_name ?? "").trim()
  return canonical.length > 0 && em.length > 0 && canonical !== em
}

type PlanGroup = {
  plan: DashRecord
  inbound_id: number
  inbound_remark?: string
  protocol?: string
  port?: number
  clients: ClientRow[]
}

type FlatClientItem = {
  panel_id: number
  panel_label: string
  planId: number
  planName: string
  inbound_id: number
  protocol: string
  port: number
  row: ClientRow
}

type OrphanClientItem = FlatClientItem

function collectOrphansForBlock(block: SnapshotPanelBlock): OrphanClientItem[] {
  const seen = new Set<string>()
  const out: OrphanClientItem[] = []
  for (const pg of block.plans) {
    const plan = pg.plan
    const planId = num(plan.id)
    const planName = String(plan.name ?? `#${planId}`)
    const iid = num(pg.inbound_id)
    for (const row of pg.clients) {
      if (!isOrphanConfig(row)) continue
      const rk = rowKey(block.panel_id, iid, String(row.email ?? ""))
      if (seen.has(rk)) continue
      seen.add(rk)
      out.push({
        panel_id: block.panel_id,
        panel_label: block.panel_label,
        planId,
        planName,
        inbound_id: iid,
        protocol: String(pg.protocol ?? "—"),
        port: num(pg.port),
        row,
      })
    }
  }
  return out
}

type UserPick = { id: number; label: string }

type SnapshotPanelBlock = {
  panel_id: number
  panel_label: string
  plans: PlanGroup[]
  truncated: number
  expired_linked_batch_count: number
  cache_synced_at: string | null
  cache_stale: boolean
  needs_sync: boolean
}

type MergedSnapshot = {
  panels: SnapshotPanelBlock[]
  default_svp_user_id: number
  syncWarnings: string[]
}

async function copyToClipboard(text: string): Promise<boolean> {
  const t = text.trim()
  if (!t) return false
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(t)
      return true
    }
  } catch {
    /* fallthrough */
  }
  try {
    const ta = document.createElement("textarea")
    ta.value = t
    ta.style.position = "fixed"
    ta.style.left = "-9999px"
    document.body.appendChild(ta)
    ta.focus()
    ta.select()
    const ok = document.execCommand("copy")
    document.body.removeChild(ta)
    return ok
  } catch {
    return false
  }
}

function DetailRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div
      className={cn(
        "grid gap-0.5 border-b border-border/40 py-2 last:border-0 sm:grid-cols-[minmax(7rem,32%)_1fr] sm:gap-3"
      )}
    >
      <div className="text-xs font-medium text-muted-foreground">{label}</div>
      <div className="min-w-0 text-sm break-all">{children}</div>
    </div>
  )
}

export function ConfigsAdminCore({
  panels,
  plans,
  configsActive = true,
  onMutateSuccess,
}: {
  panels: DashRecord[]
  plans: DashRecord[]
configsActive?: boolean
  onMutateSuccess?: () => void
}) {
  const { isFa, dir: dialogDir } = useDashLocale()

  const t = useTranslations("configsAdmin")
  const tInbound = useTranslations("inboundLinkAdmin")
  const tl = useCallback(
    (k: string, opts?: Record<string, string | number>) => t(`${k}`, opts),
    [t]
  )

  const [panelScope, setPanelScope] = useState<PanelScope>(ALL_PANELS)
  const [merged, setMerged] = useState<MergedSnapshot | null>(null)
  const [refreshing, setRefreshing] = useState(false)
  const refreshGen = useRef(0)

  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const [infoOpen, setInfoOpen] = useState(false)
  const [infoRow, setInfoRow] = useState<ClientRow | null>(null)
  const [infoCtx, setInfoCtx] = useState<QrCtx | null>(null)
  const [infoPortalPayload, setInfoPortalPayload] = useState<Record<string, unknown> | null>(null)
  const [infoPortalLoading, setInfoPortalLoading] = useState(false)
  const [infoCopyHint, setInfoCopyHint] = useState<string | null>(null)

  const [qrOpen, setQrOpen] = useState(false)
  const [qrCtx, setQrCtx] = useState<QrCtx | null>(null)
  /** Bot-identical portal payload from REST (`get_portal_service_data`). */
  const [qrPortalPayload, setQrPortalPayload] = useState<Record<string, unknown> | null>(null)
  const [qrPortalLoading, setQrPortalLoading] = useState(false)
  const [qrCopyHint, setQrCopyHint] = useState<string | null>(null)
  const qrCopyTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const [editOpen, setEditOpen] = useState(false)
  const [editPanelId, setEditPanelId] = useState(0)
  const [editInboundId, setEditInboundId] = useState(0)
  const [editEmail, setEditEmail] = useState("")
  const [editRemark, setEditRemark] = useState("")
  const [editClientComment, setEditClientComment] = useState("")
  const [editLimitIp, setEditLimitIp] = useState("0")
  const [editStartAfterFirstUse, setEditStartAfterFirstUse] = useState(false)
  const [editTotalGb, setEditTotalGb] = useState("0")
  /** Panel expiry in local Date ms; 0 = clear / unlimited per save rules */
  const [editExpiryMs, setEditExpiryMs] = useState(0)

  const [delOpen, setDelOpen] = useState(false)
  const [delRow, setDelRow] = useState<ClientRow | null>(null)
  const [delInboundId, setDelInboundId] = useState(0)
  const [delPanelId, setDelPanelId] = useState(0)

  const [resetOpen, setResetOpen] = useState(false)
  const [resetRow, setResetRow] = useState<ClientRow | null>(null)
  const [resetInboundId, setResetInboundId] = useState(0)
  const [resetPanelId, setResetPanelId] = useState(0)

  const [quickOpen, setQuickOpen] = useState(false)
  const [quickPlanId, setQuickPlanId] = useState(0)
  const [quickTarget, setQuickTarget] = useState("")

  const [bulkSel, setBulkSel] = useState<Record<string, { panel_id: number; inbound_id: number; email: string }>>({})
  const [planPages, setPlanPages] = useState<Record<string, number>>({})
  const [orphanPageByPanel, setOrphanPageByPanel] = useState<Record<number, number>>({})
  const [clientsPerPage, setClientsPerPage] = useState(25)
  const [ipsTarget, setIpsTarget] = useState<{ panel_id: number; inbound_id: number; row: ClientRow } | null>(null)
  const [ipsLive, setIpsLive] = useState<string[] | null>(null)
  const [ipsLoading, setIpsLoading] = useState(false)
  const [ipsClearBusy, setIpsClearBusy] = useState(false)
  const [resetAllOpen, setResetAllOpen] = useState(false)
  const [resetAllPanelOpen, setResetAllPanelOpen] = useState(false)
  const [delDepletedOpen, setDelDepletedOpen] = useState(false)
  const [delOrphansOpen, setDelOrphansOpen] = useState(false)
  const [attachOpen, setAttachOpen] = useState(false)
  const [attachMode, setAttachMode] = useState<"single" | "bulk">("bulk")
  const [attachTarget, setAttachTarget] = useState<{ panel_id: number; email: string } | null>(null)
  const [deleteExpiredOlderOpen, setDeleteExpiredOlderOpen] = useState(false)
  const [deleteExpiredOlderMinDays, setDeleteExpiredOlderMinDays] = useState(7)
  const [inboundPatchTarget, setInboundPatchTarget] = useState<{
    panel_id: number
    inbound_id: number
    remark: string
  } | null>(null)
  const [inboundPatchBusy, setInboundPatchBusy] = useState(false)
  const [batchBusy, setBatchBusy] = useState(false)
  const [assignPlanOpen, setAssignPlanOpen] = useState(false)
  const [assignPlanMode, setAssignPlanMode] = useState<"single" | "bulk">("bulk")
  const [assignPlanTarget, setAssignPlanTarget] = useState<{ panel_id: number; inbound_id: number; row: ClientRow } | null>(null)
  const [assignPlanId, setAssignPlanId] = useState(0)
  const [transferOpen, setTransferOpen] = useState(false)
  const [transferMode, setTransferMode] = useState<"single" | "bulk">("single")
  const [transferTarget, setTransferTarget] = useState<{ panel_id: number; inbound_id: number; row: ClientRow } | null>(null)
  const [transferPanelId, setTransferPanelId] = useState(0)
  const [transferPlanId, setTransferPlanId] = useState(0)

  const [linkOpen, setLinkOpen] = useState(false)
  const [linkCtx, setLinkCtx] = useState<{ panel_id: number; inbound_id: number; row: ClientRow } | null>(null)
  const [linkQuery, setLinkQuery] = useState("")
  const [linkHits, setLinkHits] = useState<DashRecord[]>([])
  const [linkPick, setLinkPick] = useState<UserPick | null>(null)
  const linkSearchTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const [busyRow, setBusyRow] = useState<string | null>(null)

  const bulkCount = useMemo(() => Object.keys(bulkSel).length, [bulkSel])
  const singlePanelMode = typeof panelScope === "number"

  const allPlans = useMemo(() => plans, [plans])

  /** Xray plans for current panel from configs snapshot (full list for panel); paginated `plans` prop often omits them. */
  const activePlansForPanelFromMerged = useCallback(
    (panelId: number): DashRecord[] => {
      if (panelId < 1 || !merged) return []
      const block = merged.panels.find((b) => b.panel_id === panelId)
      if (!block || block.plans.length < 1) return []
      const seen = new Set<number>()
      const out: DashRecord[] = []
      for (const pg of block.plans) {
        const p = pg.plan
        const id = num(p.id)
        if (id < 1 || seen.has(id)) continue
        if (num(p.active) === 0) continue
        seen.add(id)
        out.push(p)
      }
      return out
    },
    [merged]
  )

  const scopedActivePlans = useMemo(() => {
    if (!singlePanelMode || typeof panelScope !== "number") return [] as DashRecord[]
    const fromSnap = activePlansForPanelFromMerged(panelScope)
    if (fromSnap.length > 0) return fromSnap
    return allPlans.filter((p) => num(p.panel_id) === panelScope && num(p.active) !== 0)
  }, [activePlansForPanelFromMerged, allPlans, panelScope, singlePanelMode])

  const transferPanelPlans = useMemo(() => {
    if (transferPanelId < 1) return [] as DashRecord[]
    const fromSnap = activePlansForPanelFromMerged(transferPanelId)
    if (fromSnap.length > 0) return fromSnap
    return allPlans.filter((p) => num(p.panel_id) === transferPanelId && num(p.active) !== 0)
  }, [activePlansForPanelFromMerged, allPlans, transferPanelId])

  const panelOptions = useMemo(() => {
    return panels.map((p) => ({
      id: num(p.id),
      label: String(p.label ?? "").trim() || `#${num(p.id)}`,
    }))
  }, [panels])

  const panelLabel = useCallback(
    (id: number) => panelOptions.find((p) => p.id === id)?.label ?? `#${id}`,
    [panelOptions]
  )

  const resolvePanelIds = useCallback((): number[] => {
    if (panelScope === ALL_PANELS) {
      return panelOptions.map((p) => p.id).filter((id) => id > 0)
    }
    return typeof panelScope === "number" && panelScope > 0 ? [panelScope] : []
  }, [panelScope, panelOptions])

  const runAutoRefresh = useCallback(async () => {
    const ids = resolvePanelIds()
    if (ids.length < 1) {
      setErr(t("pickPanel"))
      setMerged(null)
      return
    }
    const gen = ++refreshGen.current
    setRefreshing(true)
    setErr(null)
    setMsg(null)
    const warnings: string[] = []
    try {
      await Promise.all(
        ids.map(async (pid) => {
          const syncJson = await postAdminJson("/dashboard/admin/configs-sync", { panel_id: pid })
          if (!syncJson.ok) {
            warnings.push(`${panelLabel(pid)}: ${String(syncJson.message ?? t("syncFailed"))}`)
          }
        })
      )
      if (gen !== refreshGen.current) return

      const blocks: SnapshotPanelBlock[] = []
      let defaultUid = 0

      await Promise.all(
        ids.map(async (pid) => {
          const json = await getAdminJson("/dashboard/admin/configs-snapshot", { panel_id: pid })
          if (gen !== refreshGen.current) return
          if (!json.ok) {
            warnings.push(`${panelLabel(pid)}: ${String(json.message ?? t("loadFailed"))}`)
            return
          }
          const data = json.data as Record<string, unknown> | undefined
          const rawPlans = data && Array.isArray(data.plans) ? (data.plans as PlanGroup[]) : []
          const plans: PlanGroup[] = rawPlans.map((pg) => ({
            ...pg,
            clients: (pg.clients ?? []).map((c) => ({ ...(c as ClientRow), panel_id: pid })),
          }))
          blocks.push({
            panel_id: pid,
            panel_label: panelLabel(pid),
            plans,
            truncated: num(data?.truncated),
            expired_linked_batch_count: num(data?.expired_linked_batch_count),
            cache_synced_at:
              typeof data?.cache_synced_at === "string" && data.cache_synced_at ? data.cache_synced_at : null,
            cache_stale: Boolean(data?.cache_stale),
            needs_sync: Boolean(data?.needs_sync),
          })
          const du = num(data?.default_svp_user_id)
          if (du > 0 && defaultUid < 1) defaultUid = du
        })
      )

      if (gen !== refreshGen.current) return
      blocks.sort((a, b) => a.panel_id - b.panel_id)
      setMerged({ panels: blocks, default_svp_user_id: defaultUid, syncWarnings: warnings })
      setBulkSel({})
      if (warnings.length) {
        setMsg(t("partialSyncNotice"))
      } else {
        setMsg(t("autoRefreshed"))
      }
    } finally {
      if (gen === refreshGen.current) setRefreshing(false)
    }
  }, [resolvePanelIds, panelLabel, tl])

  const panelIdsKey = useMemo(() => panelOptions.map((p) => p.id).sort((a, b) => a - b).join(","), [panelOptions])

  useEffect(() => {
    if (!configsActive) return
    void runAutoRefresh()
  }, [configsActive, panelScope, panelIdsKey, runAutoRefresh])

  useEffect(() => {
    if (!configsActive) return
    const onVis = () => {
      if (document.visibilityState === "visible") void runAutoRefresh()
    }
    document.addEventListener("visibilitychange", onVis)
    return () => document.removeEventListener("visibilitychange", onVis)
  }, [configsActive, runAutoRefresh])

  useEffect(() => {
    return () => {
      if (linkSearchTimer.current) clearTimeout(linkSearchTimer.current)
      if (qrCopyTimer.current) clearTimeout(qrCopyTimer.current)
    }
  }, [])

  useEffect(() => {
    if (linkOpen && linkCtx) {
      setLinkQuery("")
      setLinkHits([])
      setLinkPick(null)
    }
  }, [linkOpen, linkCtx?.panel_id, linkCtx?.inbound_id, linkCtx?.row.email])

  useEffect(() => {
    if (!qrOpen || !qrCtx) return
    let cancelled = false
    setQrPortalPayload(null)
    setQrPortalLoading(true)
    const sid = num(qrCtx.row.linked_service_id)
    const q: Record<string, string | number> = {
      panel_id: qrCtx.panel_id,
      inbound_id: qrCtx.inbound_id,
      email: String(qrCtx.row.email ?? ""),
    }
    if (sid > 0) {
      q.service_id = sid
    }
    void getAdminJson("/dashboard/admin/configs-portal-payload", q).then((json) => {
      if (cancelled) return
      setQrPortalLoading(false)
      if (json.ok && json.data && typeof json.data === "object" && json.data !== null && !Array.isArray(json.data)) {
        setQrPortalPayload(json.data as Record<string, unknown>)
      }
    })
    return () => {
      cancelled = true
    }
  }, [qrOpen, qrCtx])

  useEffect(() => {
    if (!infoOpen || !infoCtx) return
    let cancelled = false
    setInfoPortalPayload(null)
    setInfoPortalLoading(true)
    const sid = num(infoCtx.row.linked_service_id)
    const q: Record<string, string | number> = {
      panel_id: infoCtx.panel_id,
      inbound_id: infoCtx.inbound_id,
      email: String(infoCtx.row.email ?? ""),
    }
    if (sid > 0) {
      q.service_id = sid
    }
    void getAdminJson("/dashboard/admin/configs-portal-payload", q).then((json) => {
      if (cancelled) return
      setInfoPortalLoading(false)
      if (json.ok && json.data && typeof json.data === "object" && json.data !== null && !Array.isArray(json.data)) {
        setInfoPortalPayload(json.data as Record<string, unknown>)
      }
    })
    return () => {
      cancelled = true
    }
  }, [infoOpen, infoCtx])

  const scheduleLinkSearch = useCallback((q: string) => {
    if (linkSearchTimer.current) clearTimeout(linkSearchTimer.current)
    const trimmed = q.trim()
    if (trimmed.length < 2) {
      setLinkHits([])
      return
    }
    linkSearchTimer.current = setTimeout(() => {
      void (async () => {
        try {
          const json = await getAdminJson("/dashboard/admin/user-search", { q: trimmed })
          if (!json.ok) return
          const users = Array.isArray(json.users) ? (json.users as DashRecord[]) : []
          setLinkHits(users)
        } catch {
          /* ignore */
        }
      })()
    }, 320)
  }, [])

  const afterMutate = useCallback(async () => {
    onMutateSuccess?.()
    await runAutoRefresh()
  }, [onMutateSuccess, runAutoRefresh])

  const onToggleEnable = useCallback(
    async (panel_id: number, inboundId: number, row: ClientRow, enabled: boolean) => {
      const email = String(row.email ?? "")
      const rk = rowKey(panel_id, inboundId, email)
      setErr(null)
      setBusyRow(rk)
      try {
        const res = await postAdminMutate("configs_client_toggle_enable", {
          panel_id,
          inbound_id: inboundId,
          email,
          enable: enabled ? 1 : 0,
        })
        if (!res.ok) {
          setErr(res.message ?? t("mutateError"))
          return
        }
        await afterMutate()
      } finally {
        setBusyRow(null)
      }
    },
    [afterMutate, tl]
  )

  const onResetTraffic = useCallback(async () => {
    if (!resetRow || resetInboundId < 1 || resetPanelId < 1) return
    const email = String(resetRow.email ?? "")
    const rk = rowKey(resetPanelId, resetInboundId, email)
    setErr(null)
    setBusyRow(rk)
    try {
      const res = await postAdminMutate("configs_client_reset_traffic", {
        panel_id: resetPanelId,
        inbound_id: resetInboundId,
        email,
      })
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setResetOpen(false)
      setResetRow(null)
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [afterMutate, resetInboundId, resetPanelId, resetRow, tl])

  const onApplyCanonicalIdentity = useCallback(
    async (serviceId: number, panel_id: number, inboundId: number, row: ClientRow) => {
      const email = String(row.email ?? "")
      const rk = rowKey(panel_id, inboundId, email)
      setErr(null)
      setBusyRow(rk)
      try {
        const res = await postAdminMutate("service_apply_canonical_panel_identity", {
          service_id: serviceId,
        })
        if (!res.ok) {
          setErr(res.message ?? t("mutateError"))
          return
        }
        await afterMutate()
      } finally {
        setBusyRow(null)
      }
    },
    [afterMutate, tl]
  )

  const onDelete = useCallback(async () => {
    if (!delRow || delInboundId < 1 || delPanelId < 1) return
    const email = String(delRow.email ?? "")
    const rk = rowKey(delPanelId, delInboundId, email)
    setErr(null)
    setBusyRow(rk)
    try {
      const res = await postAdminMutate("configs_client_delete", {
        panel_id: delPanelId,
        inbound_id: delInboundId,
        email,
        linked_service_id: num(delRow.linked_service_id),
      })
      if (!res.ok) {
        setErr(res.message ?? res.reason ?? t("mutateError"))
        return
      }
      setDelOpen(false)
      setDelRow(null)
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [afterMutate, delInboundId, delPanelId, delRow, tl])

  const onSaveEdit = useCallback(async () => {
    if (editInboundId < 1 || !editEmail || editPanelId < 1) return
    setErr(null)
    setBusyRow(rowKey(editPanelId, editInboundId, editEmail))
    try {
      const payload: Record<string, unknown> = {
        panel_id: editPanelId,
        inbound_id: editInboundId,
        email: editEmail,
        client_remark: editRemark,
        client_comment: editClientComment,
        limit_ip: parseInt(editLimitIp, 10) || 0,
        start_after_first_use: editStartAfterFirstUse ? 1 : 0,
        total_gb: parseInt(editTotalGb, 10) || 0,
      }
      if (editExpiryMs > 0) {
        payload.expiry_ms = editExpiryMs
      } else {
        payload.expiry_ms = 0
      }
      const res = await postAdminMutate("configs_panel_client_patch", payload)
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setEditOpen(false)
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [
    afterMutate,
    editClientComment,
    editEmail,
    editExpiryMs,
    editInboundId,
    editLimitIp,
    editPanelId,
    editRemark,
    editStartAfterFirstUse,
    editTotalGb,
    tl,
  ])

  const submitLink = useCallback(async () => {
    if (!linkCtx) return
    const { panel_id, inbound_id, row } = linkCtx
    const email = String(row.email ?? "")
    const rk = rowKey(panel_id, inbound_id, email)
    const body: Record<string, unknown> = {
      inbound_id,
      panel_id,
      email,
    }
    if (linkPick && linkPick.id > 0) {
      body.user_id = linkPick.id
    } else if (linkQuery.trim().length >= 2) {
      body.user_query = linkQuery.trim()
    } else {
      const uid = parseInt(linkQuery.trim(), 10)
      if (Number.isFinite(uid) && uid >= 1) {
        body.user_id = uid
      } else {
        setErr(tInbound("badLinkParams"))
        return
      }
    }
    setErr(null)
    setBusyRow(rk)
    try {
      const res = await postAdminMutate("inbound_link", body)
      if (!res.ok) {
        const rsn = res.reason
        if (rsn === "ambiguous") setErr(tInbound("resolveAmbiguous"))
        else if (rsn === "not_found" || rsn === "empty") setErr(tInbound("resolveNotFound"))
        else setErr(res.message ?? "error")
        return
      }
      setLinkOpen(false)
      setLinkCtx(null)
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [afterMutate, linkCtx, linkPick, linkQuery, t])

  const runQuickAdd = useCallback(async () => {
    const def = merged?.default_svp_user_id ?? 0
    const raw = quickTarget.trim()
    let target = def
    if (raw) {
      const n = parseInt(raw, 10)
      if (Number.isFinite(n) && n >= 1) target = n
      else {
        setErr(tInbound("badLinkParams"))
        return
      }
    }
    if (target < 1 || quickPlanId < 1) {
      setErr(tInbound("badLinkParams"))
      return
    }
    setErr(null)
    setBusyRow(`quick:${quickPlanId}`)
    try {
      const res = await postAdminMutate("user_create_service", {
        target_user_id: target,
        plan_id: quickPlanId,
        mode: "free",
      })
      if (!res.ok) {
        setErr(res.reason ?? res.message ?? t("mutateError"))
        return
      }
      setQuickOpen(false)
      setQuickPlanId(0)
      setQuickTarget("")
      await afterMutate()
    } finally {
      setBusyRow(null)
    }
  }, [afterMutate, merged?.default_svp_user_id, quickPlanId, quickTarget, t, tl])

  const purgeSettingsUrl = useMemo(() => {
    if (typeof window === "undefined") return "#"
    const boot = window.__SIMPLEVPBOT_DASH__ || {}
    const base = String(boot.dashboardUrl || `${window.location.origin}/dashboard/`).replace(/\/$/, "")
    return `${base}/site_settings/?site_subtab=purge_expired`
  }, [])

  const toggleBulkRow = useCallback(
    (rk: string, panel_id: number, inboundId: number, email: string, checked: boolean) => {
      if (!checked) {
        setBulkSel((prev) => {
          const next = { ...prev }
          delete next[rk]
          return next
        })
        setErr(null)
        return
      }
      setBulkSel((prev) => {
        if (prev[rk]) return prev
        if (Object.keys(prev).length >= CONFIGS_BATCH_MAX) {
          queueMicrotask(() => setErr(t("batchMax", { max: CONFIGS_BATCH_MAX })))
          return prev
        }
        queueMicrotask(() => setErr(null))
        return { ...prev, [rk]: { panel_id, inbound_id: inboundId, email } }
      })
    },
    [tl]
  )

  const runClientsBatch = useCallback(
    async (batch_op: "reset_traffic" | "set_enable", enable?: boolean) => {
      if (!singlePanelMode || typeof panelScope !== "number") return
      const items = Object.values(bulkSel)
      if (items.length < 1) return
      if (items.some((it) => it.panel_id !== panelScope)) {
        setErr(t("batchSinglePanelOnly"))
        return
      }
      if (items.length > CONFIGS_BATCH_MAX) {
        setErr(t("batchMax", { max: CONFIGS_BATCH_MAX }))
        return
      }
      setBatchBusy(true)
      setErr(null)
      try {
        if (batch_op === "reset_traffic") {
          const emails = [...new Set(items.map((it) => it.email).filter(Boolean))]
          const res = await postAdminMutate("configs_bulk_reset_traffic", {
            panel_id: panelScope,
            emails,
          })
          if (!res.ok) {
            setErr(res.message ?? t("mutateError"))
            return
          }
          const d = res.data as { succeeded?: number; failed?: number } | undefined
          const fail = num(d?.failed)
          if (fail > 0) {
            setMsg(t("batchPartial", { ok: num(d?.succeeded), fail }))
          } else {
            setMsg(t("autoRefreshed"))
          }
          setBulkSel({})
          await afterMutate()
          return
        }
        const payloadItems = items.map((it) =>
          batch_op === "set_enable" ? { ...it, enable: enable ? 1 : 0 } : { inbound_id: it.inbound_id, email: it.email }
        )
        const res = await postAdminMutate("configs_clients_batch", {
          panel_id: panelScope,
          batch_op,
          items: payloadItems,
        })
        if (!res.ok && res.message !== "partial") {
          setErr(res.message ?? t("mutateError"))
          return
        }
        if (res.message === "partial") {
          const d = res.data as { succeeded?: number; failed?: unknown[] } | undefined
          const okn = num(d?.succeeded)
          const fn = Array.isArray(d?.failed) ? d.failed.length : 0
          setMsg(t("batchPartial", { ok: okn, fail: fn }))
        } else {
          setMsg(t("autoRefreshed"))
        }
        setBulkSel({})
        await afterMutate()
      } finally {
        setBatchBusy(false)
      }
    },
    [afterMutate, bulkSel, panelScope, singlePanelMode, t]
  )

  const openEdit = (panel_id: number, inboundId: number, row: ClientRow) => {
    const email = String(row.email ?? "")
    setEditPanelId(panel_id)
    setEditInboundId(inboundId)
    setEditEmail(email)
    setEditRemark(String(row.remark ?? ""))
    setEditClientComment(String(row.comment ?? ""))
    setEditLimitIp(String(num(row.limit_ip)))
    setEditStartAfterFirstUse(num(row.first_usage) !== 0)
    setEditTotalGb(String(num(row.total_gb)))
    setEditExpiryMs(unifiedExpiryMs(row))
    setEditOpen(true)
  }

  const openLinkModal = (panel_id: number, inboundId: number, row: ClientRow) => {
    setLinkCtx({ panel_id, inbound_id: inboundId, row })
    setLinkOpen(true)
  }

  const progressVal = (row: ClientRow) => {
    const lim = num(row.limit_bytes)
    const used = num(row.used_bytes)
    if (lim <= 0) return 0
    return Math.min(100, Math.round((100 * used) / lim))
  }

  const unifiedExpirySummary = useCallback(
    (row: ClientRow) => {
      const ms = unifiedExpiryMs(row)
      if (ms < 1) return t("noPanelExpiry")
      const left = ms - Date.now()
      const abs = formatDateTime(ms, isFa)
      if (left <= 0) return `${t("expired")} — ${abs}`
      const d = Math.ceil(left / 86400000)
      return `${abs} · ${t("daysLeft", { n: d })}`
    },
    [isFa, tl]
  )

  const clientStats = useMemo(() => {
    if (!merged) return null
    let total = 0
    let enabled = 0
    let online = 0
    let linked = 0
    let expired = 0
    let exhausted = 0
    const now = Date.now()
    for (const block of merged.panels) {
      for (const pg of block.plans) {
        for (const row of pg.clients) {
          total++
          if (num(row.enable) !== 0) enabled++
          if (num(row.is_online) === 1) online++
          if (num(row.is_linked) !== 0) linked++
          const ms = unifiedExpiryMs(row)
          if (ms > 0 && ms <= now) expired++
          if (isVolumeExhausted(row)) exhausted++
        }
      }
    }
    return {
      total,
      enabled,
      disabled: total - enabled,
      online,
      linked,
      unlinked: total - linked,
      expired,
      exhausted,
    }
  }, [merged])

  const flatClients = useMemo((): FlatClientItem[] => {
    if (!merged) return []
    const out: FlatClientItem[] = []
    for (const block of merged.panels) {
      for (const pg of block.plans) {
        const plan = pg.plan
        const planId = num(plan.id)
        const planName = String(plan.name ?? `#${planId}`)
        const iid = num(pg.inbound_id)
        for (const row of pg.clients) {
          out.push({
            panel_id: block.panel_id,
            panel_label: block.panel_label,
            planId,
            planName,
            inbound_id: iid,
            protocol: String(pg.protocol ?? "—"),
            port: num(pg.port),
            row,
          })
        }
      }
    }
    return out
  }, [merged])

  const runAssignPlan = useCallback(async () => {
    if (assignPlanId < 1) {
      setErr(t("pickPlan"))
      return
    }
    const items =
      assignPlanMode === "single" && assignPlanTarget
        ? [
            {
              linked_service_id: num(assignPlanTarget.row.linked_service_id),
              inbound_id: assignPlanTarget.inbound_id,
              email: String(assignPlanTarget.row.email ?? ""),
            },
          ]
        : Object.values(bulkSel).map((it) => {
            const linked = flatClients.find(
              (f) => f.panel_id === it.panel_id && f.inbound_id === it.inbound_id && String(f.row.email ?? "") === it.email
            )
            return {
              linked_service_id: num(linked?.row.linked_service_id),
              inbound_id: it.inbound_id,
              email: it.email,
            }
          })
    const filtered = items.filter((it) => it.linked_service_id > 0 && it.inbound_id > 0 && it.email)
    if (filtered.length < 1) {
      setErr(t("noEligibleRows"))
      return
    }
    const pid = assignPlanMode === "single" ? assignPlanTarget?.panel_id ?? 0 : (typeof panelScope === "number" ? panelScope : 0)
    if (pid < 1) {
      setErr(t("pickPanel"))
      return
    }
    setErr(null)
    setBatchBusy(true)
    try {
      const res = await postAdminMutate("configs_assign_plan", { panel_id: pid, plan_id: assignPlanId, items: filtered })
      if (!res.ok && res.message !== "partial") {
        setErr(res.message ?? t("mutateError"))
        return
      }
      if (res.message === "partial") {
        const d = res.data as { succeeded?: number; failed?: unknown[] } | undefined
        setMsg(t("batchPartial", { ok: num(d?.succeeded), fail: Array.isArray(d?.failed) ? d.failed.length : 0 }))
      }
      setAssignPlanOpen(false)
      setAssignPlanTarget(null)
      setBulkSel({})
      await afterMutate()
    } finally {
      setBatchBusy(false)
    }
  }, [afterMutate, assignPlanId, assignPlanMode, assignPlanTarget, bulkSel, flatClients, panelScope, tl])

  const runTransferPanel = useCallback(async () => {
    if (transferPanelId < 1) {
      setErr(t("pickTargetPanel"))
      return
    }
    const serviceIds =
      transferMode === "single" && transferTarget
        ? [num(transferTarget.row.linked_service_id)].filter((id) => id > 0)
        : Object.values(bulkSel)
            .map((it) => {
              const row = flatClients.find(
                (f) => f.panel_id === it.panel_id && f.inbound_id === it.inbound_id && String(f.row.email ?? "") === it.email
              )
              return num(row?.row.linked_service_id)
            })
            .filter((id) => id > 0)
    if (serviceIds.length < 1) {
      setErr(t("noEligibleRows"))
      return
    }
    setErr(null)
    setBatchBusy(true)
    try {
      const res = await postAdminMutate("service_panel_transfer", {
        service_ids: serviceIds,
        target_panel_id: transferPanelId,
        target_plan_id: transferPlanId > 0 ? transferPlanId : undefined,
      })
      if (!res.ok && res.message !== "partial") {
        setErr(res.message ?? t("mutateError"))
        return
      }
      if (res.message === "partial") {
        const d = res.data as { succeeded?: number; failed?: unknown[] } | undefined
        setMsg(t("batchPartial", { ok: num(d?.succeeded), fail: Array.isArray(d?.failed) ? d.failed.length : 0 }))
      }
      setTransferOpen(false)
      setTransferTarget(null)
      setBulkSel({})
      await afterMutate()
    } finally {
      setBatchBusy(false)
    }
  }, [afterMutate, bulkSel, flatClients, tl, transferMode, transferPanelId, transferPlanId, transferTarget])

  const panelInbounds = useMemo(() => {
    if (!singlePanelMode || typeof panelScope !== "number" || !merged) return [] as { id: number; label: string }[]
    const block = merged.panels.find((p) => p.panel_id === panelScope)
    if (!block) return []
    const map = new Map<number, string>()
    for (const pg of block.plans) {
      const iid = num(pg.inbound_id)
      if (iid < 1 || map.has(iid)) continue
      const remark = String((pg as { inbound_remark?: string }).inbound_remark ?? pg.plan?.name ?? "")
      map.set(iid, remark ? `#${iid} — ${remark}` : `#${iid}`)
    }
    return [...map.entries()].map(([id, label]) => ({ id, label }))
  }, [merged, panelScope, singlePanelMode])

  const isScopedPasarguard = useMemo(() => {
    if (!singlePanelMode || typeof panelScope !== "number") return false
    const row = panels.find((p) => num(p.id) === panelScope)
    const provider = String(row?.panel_provider ?? row?.provider ?? "").toLowerCase()
    return provider.includes("pasar")
  }, [panelScope, panels, singlePanelMode])

  const filteredClientsForPanel = useMemo(() => {
    if (!singlePanelMode || typeof panelScope !== "number" || !merged) {
      return [] as { panel_id: number; inbound_id: number; email: string }[]
    }
    const block = merged.panels.find((p) => p.panel_id === panelScope)
    if (!block) return []
    const out: { panel_id: number; inbound_id: number; email: string }[] = []
    for (const pg of block.plans) {
      const iid = num(pg.inbound_id)
      for (const row of pg.clients) {
        const email = String(row.email ?? "").trim()
        if (!email) continue
        out.push({ panel_id: block.panel_id, inbound_id: iid, email })
      }
    }
    return out
  }, [merged, panelScope, singlePanelMode])

  const expiredOlderDeleteCount = useMemo(() => {
    if (!singlePanelMode || typeof panelScope !== "number" || !merged) return 0
    const block = merged.panels.find((p) => p.panel_id === panelScope)
    if (!block) return 0
    const nowMs = Date.now()
    const cutoff = nowMs - deleteExpiredOlderMinDays * 86400 * 1000
    let n = 0
    for (const pg of block.plans) {
      for (const row of pg.clients) {
        const exp = num(row.expiry_ms)
        if (exp > 0 && exp < nowMs && exp <= cutoff) n += 1
      }
    }
    return Math.min(50, n)
  }, [deleteExpiredOlderMinDays, merged, panelScope, singlePanelMode])

  const runResetAllFiltered = useCallback(async () => {
    if (!singlePanelMode || typeof panelScope !== "number") return
    const emails = [...new Set(filteredClientsForPanel.map((it) => it.email).filter(Boolean))]
    if (emails.length < 1) {
      setErr(tl("noEligibleRows"))
      return
    }
    setBatchBusy(true)
    try {
      const res = await postAdminMutate("configs_bulk_reset_traffic", { panel_id: panelScope, emails })
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setResetAllOpen(false)
      await afterMutate()
    } finally {
      setBatchBusy(false)
    }
  }, [afterMutate, filteredClientsForPanel, panelScope, singlePanelMode, t, tl])

  const runResetAllPanelTraffic = useCallback(async () => {
    if (!singlePanelMode || typeof panelScope !== "number") return
    setBatchBusy(true)
    try {
      const res = await postAdminMutate("configs_reset_all_panel_traffic", { panel_id: panelScope })
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setResetAllPanelOpen(false)
      await afterMutate()
    } finally {
      setBatchBusy(false)
    }
  }, [afterMutate, panelScope, singlePanelMode, t])

  const runDelDepleted = useCallback(async () => {
    if (!singlePanelMode || typeof panelScope !== "number") return
    setBatchBusy(true)
    try {
      const res = await postAdminMutate("configs_panel_del_depleted", { panel_id: panelScope })
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setDelDepletedOpen(false)
      await afterMutate()
    } finally {
      setBatchBusy(false)
    }
  }, [afterMutate, panelScope, singlePanelMode, t])

  const runDelOrphans = useCallback(async () => {
    if (!singlePanelMode || typeof panelScope !== "number") return
    setBatchBusy(true)
    try {
      const res = await postAdminMutate("configs_panel_del_orphans", { panel_id: panelScope })
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setDelOrphansOpen(false)
      await afterMutate()
    } finally {
      setBatchBusy(false)
    }
  }, [afterMutate, panelScope, singlePanelMode, t])

  const runClearIps = useCallback(async () => {
    if (!ipsTarget) return
    setIpsClearBusy(true)
    try {
      const res = await postAdminMutate("configs_client_clear_ips", {
        panel_id: ipsTarget.panel_id,
        inbound_id: ipsTarget.inbound_id,
        email: String(ipsTarget.row.email ?? ""),
      })
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setIpsLive([])
      setIpsTarget((prev) => (prev ? { ...prev, row: { ...prev.row, client_ips: [] } } : null))
    } finally {
      setIpsClearBusy(false)
    }
  }, [ipsTarget, t])

  const runAttachInbounds = useCallback(
    async (attachIds: number[], detachIds: number[]) => {
      if (!singlePanelMode || typeof panelScope !== "number") return
      setBatchBusy(true)
      try {
        if (attachMode === "single" && attachTarget) {
          const res = await postAdminMutate("configs_client_set_inbounds", {
            panel_id: attachTarget.panel_id,
            email: attachTarget.email,
            attach_inbound_ids: attachIds,
            detach_inbound_ids: detachIds,
          })
          if (!res.ok) {
            setErr(res.message ?? t("mutateError"))
            return
          }
        } else {
          const emails = Object.values(bulkSel)
            .map((it) => it.email)
            .filter(Boolean)
          if (emails.length < 1) {
            setErr(tl("noEligibleRows"))
            return
          }
          const res = await postAdminMutate("configs_clients_bulk_set_inbounds", {
            panel_id: panelScope,
            emails,
            attach_inbound_ids: attachIds,
            detach_inbound_ids: detachIds,
          })
          if (!res.ok) {
            setErr(res.message ?? t("mutateError"))
            return
          }
        }
        setAttachOpen(false)
        setAttachTarget(null)
        setBulkSel({})
        await afterMutate()
      } finally {
        setBatchBusy(false)
      }
    },
    [afterMutate, attachMode, attachTarget, bulkSel, panelScope, singlePanelMode, t, tl]
  )

  const runDeleteExpiredOlderThan = useCallback(async () => {
    if (!singlePanelMode || typeof panelScope !== "number") return
    setBatchBusy(true)
    try {
      const res = await postAdminMutate("configs_delete_expired_older_than", {
        panel_id: panelScope,
        min_days: deleteExpiredOlderMinDays,
        confirm_count: expiredOlderDeleteCount,
      })
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setDeleteExpiredOlderOpen(false)
      await afterMutate()
    } finally {
      setBatchBusy(false)
    }
  }, [afterMutate, deleteExpiredOlderMinDays, expiredOlderDeleteCount, panelScope, singlePanelMode, t])

  const runInboundPatch = useCallback(async () => {
    if (!inboundPatchTarget) return
    setInboundPatchBusy(true)
    try {
      const res = await postAdminMutate("configs_inbound_patch", {
        panel_id: inboundPatchTarget.panel_id,
        inbound_id: inboundPatchTarget.inbound_id,
        remark: inboundPatchTarget.remark,
      })
      if (!res.ok) {
        setErr(res.message ?? t("mutateError"))
        return
      }
      setInboundPatchTarget(null)
      await afterMutate()
    } finally {
      setInboundPatchBusy(false)
    }
  }, [afterMutate, inboundPatchTarget, t])

  useEffect(() => {
    if (!ipsTarget) {
      setIpsLive(null)
      setIpsLoading(false)
      return
    }
    let cancelled = false
    setIpsLive(null)
    setIpsLoading(true)
    void postAdminMutate("configs_client_fetch_ips", {
      panel_id: ipsTarget.panel_id,
      inbound_id: ipsTarget.inbound_id,
      email: String(ipsTarget.row.email ?? ""),
    }).then((res) => {
      if (cancelled) return
      setIpsLoading(false)
      if (!res.ok) {
        setIpsLive(parseClientIps(ipsTarget.row))
        return
      }
      const data = res.data as { client_ips?: unknown } | undefined
      const raw = data?.client_ips
      if (Array.isArray(raw)) {
        setIpsLive(raw.map((x) => String(x).trim()).filter(Boolean))
      } else {
        setIpsLive(parseClientIps(ipsTarget.row))
      }
    })
    return () => {
      cancelled = true
    }
  }, [ipsTarget])

  const setPlanPage = useCallback((key: string, page: number) => {
    setPlanPages((prev) => ({ ...prev, [key]: page }))
  }, [])

  const setOrphanPage = useCallback((panelId: number, page: number) => {
    setOrphanPageByPanel((prev) => ({ ...prev, [panelId]: page }))
  }, [])

  useEffect(() => {
    setPlanPages({})
    setOrphanPageByPanel({})
  }, [panelScope, panelIdsKey])

  const visibleRows = useMemo(() => {
    if (!singlePanelMode || typeof panelScope !== "number" || !merged) {
      return [] as { rk: string; panel_id: number; inbound_id: number; email: string; linked: number }[]
    }
    const block = merged.panels.find((p) => p.panel_id === panelScope)
    if (!block) return []
    const out: { rk: string; panel_id: number; inbound_id: number; email: string; linked: number }[] = []
    for (const pg of block.plans) {
      const planId = num(pg.plan.id)
      const iid = num(pg.inbound_id)
      const linked = pg.clients.filter(isLinkedWithPlan)
      const pk = planGroupKey(block.panel_id, planId, iid)
      const page = planPages[pk] ?? 1
      for (const row of paginateSlice(linked, page, clientsPerPage)) {
        const email = String(row.email ?? "")
        out.push({
          rk: rowKey(block.panel_id, iid, email),
          panel_id: block.panel_id,
          inbound_id: iid,
          email,
          linked: num(row.linked_service_id),
        })
      }
    }
    const orphans = collectOrphansForBlock(block)
    const orphanPage = orphanPageByPanel[block.panel_id] ?? 1
    for (const item of paginateSlice(orphans, orphanPage, clientsPerPage)) {
      const email = String(item.row.email ?? "")
      out.push({
        rk: rowKey(item.panel_id, item.inbound_id, email),
        panel_id: item.panel_id,
        inbound_id: item.inbound_id,
        email,
        linked: num(item.row.linked_service_id),
      })
    }
    return out
  }, [clientsPerPage, merged, orphanPageByPanel, panelScope, planPages, singlePanelMode])

  const panelAllSelected = useMemo(() => {
    if (visibleRows.length < 1) return false
    return visibleRows.every((r) => Boolean(bulkSel[r.rk]))
  }, [bulkSel, visibleRows])

  const toggleSelectAllVisible = useCallback(
    (checked: boolean) => {
      if (!checked) {
        setBulkSel((prev) => {
          const next = { ...prev }
          for (const r of visibleRows) delete next[r.rk]
          return next
        })
        return
      }
      setBulkSel((prev) => {
        const next = { ...prev }
        for (const r of visibleRows) {
          if (Object.keys(next).length >= CONFIGS_BATCH_MAX) break
          next[r.rk] = { panel_id: r.panel_id, inbound_id: r.inbound_id, email: r.email }
        }
        return next
      })
    },
    [visibleRows]
  )

  const enablePieData = useMemo(() => {
    if (!clientStats || clientStats.total < 1) return [] as { name: string; value: number }[]
    return [
      { name: t("chartEnabled"), value: clientStats.enabled },
      { name: t("chartDisabled"), value: clientStats.disabled },
    ]
  }, [clientStats, tl])

  const statusBarData = useMemo(() => {
    if (!clientStats || clientStats.total < 1) return [] as { name: string; value: number }[]
    return [
      { name: t("chartOnline"), value: clientStats.online },
      { name: t("chartLinked"), value: clientStats.linked },
      { name: t("chartUnlinked"), value: clientStats.unlinked },
      { name: t("chartExpired"), value: clientStats.expired },
      { name: t("chartExhausted"), value: clientStats.exhausted },
    ]
  }, [clientStats, tl])

  const showQrCopy = (kind: "ok" | "fail") => {
    if (qrCopyTimer.current) clearTimeout(qrCopyTimer.current)
    setQrCopyHint(kind === "ok" ? t("copyOk") : `__err__${t("copyFail")}`)
    qrCopyTimer.current = setTimeout(() => setQrCopyHint(null), 2200)
  }

  const showInfoCopy = (kind: "ok" | "fail") => {
    setInfoCopyHint(kind === "ok" ? t("copyOk") : t("copyFail"))
    setTimeout(() => setInfoCopyHint(null), 2200)
  }

  const totalTruncated = merged?.panels.reduce((s, p) => s + p.truncated, 0) ?? 0
  const expiredBlock =
    singlePanelMode && typeof panelScope === "number"
      ? merged?.panels.find((p) => p.panel_id === panelScope)
      : null
  const anyStale = merged?.panels.some((p) => p.cache_stale)
  const anyNeeds = merged?.panels.some((p) => p.needs_sync)

  const contentClass = dashPageRootClass("w-full")
  const dialogHeaderClass = cn("flex flex-col gap-2 text-start")
  const dialogContentCn = (extra: string) => cn(extra, contentClass)
  const selectTriggerClass = "px-2 shadow-sm"

  function renderConfigClientRow(block: SnapshotPanelBlock, iid: number, row: ClientRow) {
    const pid = num(row.panel_id) || block.panel_id
    const email = String(row.email ?? "")
    const rk = rowKey(pid, iid, email)
    const enabled = num(row.enable) !== 0
    const online = num(row.is_online) === 1
    const linked = num(row.is_linked) !== 0
    const exhausted = isVolumeExhausted(row)
    const capLabel =
      num(row.total_gb) < 1 && num(row.limit_bytes) < 1
        ? t("unlimited")
        : `${formatNumber(num(row.total_gb), isFa)} ${t("gbUnit")}`
    return (
      <div
        key={rk}
        className={cn(
          "border-b border-border/40 px-3 py-3 last:border-b-0 sm:grid sm:grid-cols-[1fr_auto] sm:items-center sm:gap-3",
          exhausted && "border-destructive/30 bg-destructive/5"
        )}
      >
        <div className="min-w-0 space-y-2">
          <div className="flex w-full flex-wrap items-center justify-between gap-2">
            <span
              className={cn("min-w-0 max-w-[min(100%,20rem)] truncate font-mono text-sm font-medium", exhausted && "text-destructive")}
              title={configDisplayName(row)}
            >
              {configDisplayName(row)}
            </span>
            <div className="flex shrink-0 flex-wrap items-center gap-2">
              {singlePanelMode ? (
                <input
                  type="checkbox"
                  className="size-4 shrink-0"
                  checked={Boolean(bulkSel[rk])}
                  disabled={batchBusy || busyRow === rk}
                  onChange={(e) => toggleBulkRow(rk, pid, iid, email, e.target.checked)}
                  aria-label={t("batchSelectRow")}
                />
              ) : null}
              <Switch
                checked={enabled}
                disabled={batchBusy || busyRow === rk}
                aria-label={t("enable")}
                onCheckedChange={(v) => void onToggleEnable(pid, iid, row, v)}
              />
              <Badge variant={online ? "default" : "secondary"}>
                {online ? t("online") : t("offline")}
              </Badge>
            </div>
          </div>
          {num(row.limit_bytes) > 0 ? (
            <div className="dir-ltr max-w-xl" dir="ltr">
              <div className="flex items-center gap-2">
                <span className="w-24 shrink-0 text-xs text-muted-foreground tabular-nums sm:w-28">
                  {formatBytes(num(row.used_bytes), isFa)}
                </span>
                <div className="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                  <div
                    className={cn("h-full transition-all", exhausted ? "bg-destructive" : "bg-primary")}
                    style={{ width: `${progressVal(row)}%` }}
                  />
                </div>
                <span className="w-24 shrink-0 text-end text-xs text-muted-foreground tabular-nums sm:w-28">
                  {formatBytes(num(row.limit_bytes), isFa)}
                </span>
              </div>
              {exhausted ? <p className="mt-1 text-center text-xs text-destructive">{t("volumeExhausted")}</p> : null}
              <p className="mt-1.5 text-center text-xs text-muted-foreground">
                {t("expiryUnified")}: {unifiedExpirySummary(row)}
              </p>
            </div>
          ) : (
            <div className="dir-ltr space-y-1 text-xs text-muted-foreground" dir="ltr">
              <div className="flex flex-wrap justify-between gap-x-4 gap-y-1">
                <span>
                  {t("used")}: {formatBytes(num(row.used_bytes), isFa)}
                </span>
                <span>
                  {t("cap")}: {capLabel}
                </span>
              </div>
              <p className="text-center">
                {t("expiryUnified")}: {unifiedExpirySummary(row)}
              </p>
            </div>
          )}
        </div>
        <div className={cn("mt-3 flex flex-wrap items-center gap-1 sm:mt-0")}>
          <Tooltip>
            <TooltipTrigger
              render={
                <Button
                type="button"
                size="icon"
                variant="ghost"
                className={cn("size-9", linked ? "text-primary" : "text-muted-foreground")}
                disabled={batchBusy || busyRow === rk}
                onClick={() => openLinkModal(pid, iid, row)}
                aria-label={linked ? t("linkStatusLinked") : t("linkStatusUnlinked")}
              >
                {linked ? <UserCheck className="size-4" /> : <UserRound className="size-4" />}
              </Button>
              }
            />
            <TooltipContent>{linked ? t("linkUserEdit") : t("linkUserAdd")}</TooltipContent>
          </Tooltip>
          <Tooltip>
            <TooltipTrigger
              render={
                <Button
                type="button"
                size="icon"
                variant="ghost"
                className="size-9"
                onClick={() => {
                  setInfoRow(row)
                  setInfoCtx({ panel_id: pid, inbound_id: iid, row })
                  setInfoCopyHint(null)
                  setInfoOpen(true)
                }}
              >
                <Info className="size-4" />
              </Button>
              }
            />
            <TooltipContent>{t("infoTitle")}</TooltipContent>
          </Tooltip>
          <Tooltip>
            <TooltipTrigger
              render={
                <Button
                type="button"
                size="icon"
                variant="ghost"
                className="size-9"
                onClick={() => {
                  setQrCtx({ panel_id: pid, inbound_id: iid, row })
                  setQrCopyHint(null)
                  setQrOpen(true)
                }}
              >
                <QrCode className="size-4" />
              </Button>
              }
            />
            <TooltipContent>{t("qrTitle")}</TooltipContent>
          </Tooltip>
          {needsCanonicalPanelRepair(row) ? (
            <Tooltip>
              <TooltipTrigger
              render={
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  className="size-9 text-amber-600 dark:text-amber-400"
                  disabled={batchBusy || busyRow === rk}
                  onClick={() => void onApplyCanonicalIdentity(num(row.linked_service_id), pid, iid, row)}
                  aria-label={t("applyCanonicalIdentity")}
                >
                  <RotateCcw className="size-4" />
                </Button>
              }
            />
              <TooltipContent className="max-w-xs">{t("applyCanonicalIdentityHint")}</TooltipContent>
            </Tooltip>
          ) : null}
          <Tooltip>
            <TooltipTrigger
              render={
                <Button type="button" size="icon" variant="ghost" className="size-9" onClick={() => openEdit(pid, iid, row)}>
                <Pencil className="size-4" />
              </Button>
              }
            />
            <TooltipContent>{t("editTitle")}</TooltipContent>
          </Tooltip>
          <Tooltip>
            <TooltipTrigger
              render={
                <Button type="button" size="icon" variant="ghost" className="size-9" onClick={() => setIpsTarget({ panel_id: pid, inbound_id: iid, row })}>
                <Network className="size-4" />
              </Button>
              }
            />
            <TooltipContent className="max-w-xs">{t("ipsPlaceholder")}</TooltipContent>
          </Tooltip>
          {num(row.linked_service_id) > 0 && num(row.service_plan_id) < 1 ? (
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={batchBusy || busyRow === rk}
              onClick={() => {
                setAssignPlanMode("single")
                setAssignPlanTarget({ panel_id: pid, inbound_id: iid, row })
                setAssignPlanId(0)
                setAssignPlanOpen(true)
              }}
            >
              {t("assignPlan")}
            </Button>
          ) : null}
          {num(row.linked_service_id) > 0 ? (
            <Tooltip>
              <TooltipTrigger
              render={
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  className="size-9"
                  disabled={batchBusy || busyRow === rk}
                  onClick={() => {
                    setTransferMode("single")
                    setTransferTarget({ panel_id: pid, inbound_id: iid, row })
                    setTransferPanelId(0)
                    setTransferPlanId(0)
                    setTransferOpen(true)
                  }}
                >
                  <ArrowRightLeft className="size-4" />
                </Button>
              }
            />
              <TooltipContent>{t("transferPanel")}</TooltipContent>
            </Tooltip>
          ) : null}
          <Button
            type="button"
            size="icon"
            variant="ghost"
            className="size-9"
            disabled={batchBusy || busyRow === rk}
            onClick={() => {
              setResetPanelId(pid)
              setResetInboundId(iid)
              setResetRow(row)
              setResetOpen(true)
            }}
          >
            <RotateCcw className="size-4" />
          </Button>
          <Button
            type="button"
            size="icon"
            variant="ghost"
            className="size-9 text-destructive"
            disabled={batchBusy || busyRow === rk}
            onClick={() => {
              setDelPanelId(pid)
              setDelInboundId(iid)
              setDelRow(row)
              setDelOpen(true)
            }}
          >
            <Trash2 className="size-4" />
          </Button>
        </div>
      </div>
    )
  }

  return (
    <DashPage className={contentClass} data-testid="dash-configs-tab">
      <DashboardPageHeader
        title={t("title")}
        description={
          <>
            <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
            <p className="mt-1 text-xs text-muted-foreground">{t("autoSyncHint")}</p>
          </>
        }
      />

      <div className="flex flex-col gap-4 rounded-lg border border-border/60 p-4 sm:flex-row sm:flex-wrap sm:items-end">
        <div className="grid gap-2">
          <Label>{t("fieldPanel")}</Label>
          <DashSelect
            triggerClassName="max-w-md shadow-sm"
            value={panelScope === ALL_PANELS ? "all" : String(panelScope)}
            onValueChange={(v) => {
              if (v === "all") setPanelScope(ALL_PANELS)
              else {
                const n = parseInt(v, 10)
                setPanelScope(Number.isFinite(n) && n > 0 ? n : ALL_PANELS)
              }
              setMerged(null)
              setBulkSel({})
              setPlanPages({})
              setOrphanPageByPanel({})
              setErr(null)
              setMsg(null)
            }}
            options={[
              { value: "all", label: t("allPanels") },
              ...(panelOptions.length === 0
                ? [{ value: "__no_panels", label: t("noPanels"), disabled: true }]
                : panelOptions.map((p) => ({
                    value: String(p.id),
                    label: `#${p.id} — ${p.label}`,
                  }))),
            ]}
          />
        </div>
        <div className="text-sm text-muted-foreground">
          {refreshing ? <span className="text-foreground">{t("syncBusy")}</span> : <span>{t("idleReady")}</span>}
        </div>
        {singlePanelMode ? (
          <label className={cn("flex items-center gap-2 text-sm")}>
            <input
              type="checkbox"
              className="size-4"
              checked={panelAllSelected}
              onChange={(e) => toggleSelectAllVisible(e.target.checked)}
            />
            <span>{t("selectAllInPanel")}</span>
          </label>
        ) : null}
        {singlePanelMode ? (
          <div className={cn("flex flex-wrap gap-2")}>
            <Button type="button" size="sm" variant="outline" disabled={batchBusy} onClick={() => void runResetAllPanelTraffic()}>
              {t("resetAllPanelTraffic")}
            </Button>
            <Button type="button" size="sm" variant="outline" disabled={batchBusy} onClick={() => void runDelDepleted()}>
              {t("delDepleted")}
            </Button>
          </div>
        ) : null}
      </div>

      {merged && clientStats ? (
        <div className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-sm">
          <p>
            {t("statsLine", {
              total: clientStats.total,
              enabled: clientStats.enabled,
              disabled: clientStats.disabled,
              online: clientStats.online,
              expired: clientStats.expired,
              linked: clientStats.linked,
              unlinked: clientStats.unlinked,
            })}
          </p>
          <p className="mt-1 text-xs text-muted-foreground">{t("statsHint")}</p>
          <p className="mt-1 text-xs text-muted-foreground">{t("clientsListPagedHint")}</p>
        </div>
      ) : null}

      {merged && clientStats && clientStats.total > 0 ? (
        <div className="grid gap-4 md:grid-cols-2">
          <div className="rounded-lg border border-border/60 bg-card/40 p-3">
            <p className="mb-2 text-xs font-medium text-muted-foreground">{t("chartEnabled")} / {t("chartDisabled")}</p>
            <div className="h-[220px] w-full min-h-[200px] min-w-0" dir="ltr">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={enablePieData}
                    dataKey="value"
                    nameKey="name"
                    cx="50%"
                    cy="50%"
                    innerRadius={52}
                    outerRadius={78}
                    paddingAngle={2}
                  >
                    {enablePieData.map((_, i) => (
                      <Cell key={i} fill={`hsl(var(--chart-${(i % 5) + 1}))`} />
                    ))}
                  </Pie>
                  <Legend wrapperStyle={{ fontSize: 12 }} />
                  <RechartsTooltip formatter={(v: number) => formatNumber(v, isFa)} />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </div>
          <div className="rounded-lg border border-border/60 bg-card/40 p-3">
            <p className="mb-2 text-xs font-medium text-muted-foreground">
              {t("chartOnline")} · {t("chartLinked")} · {t("chartUnlinked")} · {t("chartExpired")} · {t("chartExhausted")}
            </p>
            <div className="h-[220px] w-full min-h-[200px] min-w-0" dir="ltr">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={statusBarData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-muted/40" />
                  <XAxis dataKey="name" tick={{ fontSize: 10 }} interval={0} angle={isFa ? 0 : -12} textAnchor="end" height={56} />
                  <YAxis tick={{ fontSize: 11 }} width={36} tickFormatter={(v) => formatNumber(Number(v), isFa)} />
                  <RechartsTooltip formatter={(v: number) => formatNumber(v, isFa)} />
                  <Bar dataKey="value" radius={[4, 4, 0, 0]}>
                    {statusBarData.map((it, i) => (
                      <Cell key={i} fill={it.name === t("chartExhausted") ? "hsl(var(--destructive))" : `hsl(var(--chart-${(i % 5) + 1}))`} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>
        </div>
      ) : null}

      {merged && merged.syncWarnings.length > 0 ? (
        <div className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-900 dark:text-amber-100">
          <p className="font-medium">{t("partialSyncNotice")}</p>
          <ul className="mt-1 list-inside list-disc space-y-0.5">
            {merged.syncWarnings.slice(0, 8).map((w, i) => (
              <li key={i}>{w}</li>
            ))}
          </ul>
        </div>
      ) : null}

      {merged && (merged.panels.some((p) => p.cache_synced_at) || anyStale || anyNeeds) ? (
        <div className="space-y-1 text-xs text-muted-foreground">
          {merged.panels.map((p) =>
            p.cache_synced_at ? (
              <p key={`cs-${p.panel_id}`}>
                #{p.panel_id} — {t("cacheSyncedAt", { time: p.cache_synced_at })}
              </p>
            ) : null
          )}
          {anyStale ? (
            <p className="text-amber-700 dark:text-amber-400" data-testid="dash-configs-stale-badge">
              {t("cacheStaleBanner")}
            </p>
          ) : null}
          {anyNeeds ? <p>{t("needsSyncBanner")}</p> : null}
        </div>
      ) : null}

      {merged && totalTruncated > 0 ? (
        <p className="text-xs text-amber-600 dark:text-amber-400">{t("truncated")}</p>
      ) : null}

      {expiredBlock && expiredBlock.expired_linked_batch_count > 0 ? (
        <div className="space-y-2 rounded-lg border border-border/60 bg-muted/30 p-4">
          <p className="text-sm text-muted-foreground">
            {t("purgeMovedHint", { count: expiredBlock.expired_linked_batch_count })}
          </p>
          <Button type="button" variant="outline" size="sm">
            <a href={purgeSettingsUrl}>{t("purgeMovedLink")}</a>
          </Button>
        </div>
      ) : null}

      {msg ? <p className="text-sm text-green-600 dark:text-green-400">{msg}</p> : null}
      {err ? <p className="text-sm text-destructive">{err}</p> : null}

      {singlePanelMode && bulkCount > 0 ? (
        <div
          className={cn(
            "flex flex-wrap items-center gap-3 rounded-lg border border-border/60 bg-muted/30 p-3"
          )}
        >
          <span className="text-sm font-medium">{t("batchBar", { n: bulkCount, max: CONFIGS_BATCH_MAX })}</span>
          <div className={cn("flex flex-wrap gap-2")}>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={batchBusy}
              onClick={() => {
                setBulkSel({})
                setErr(null)
              }}
            >
              {t("batchClear")}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="secondary"
              disabled={batchBusy}
              onClick={() => void runClientsBatch("reset_traffic")}
            >
              {t("batchReset")}
            </Button>
            <Button type="button" size="sm" disabled={batchBusy} onClick={() => void runClientsBatch("set_enable", true)}>
              {t("batchEnable")}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={batchBusy}
              onClick={() => void runClientsBatch("set_enable", false)}
            >
              {t("batchDisable")}
            </Button>
            <Button type="button" size="sm" variant="outline" disabled={batchBusy} onClick={() => {
              setAssignPlanMode("bulk")
              setAssignPlanTarget(null)
              setAssignPlanId(0)
              setAssignPlanOpen(true)
            }}>
              {t("assignPlan")}
            </Button>
            <Button type="button" size="sm" variant="outline" disabled={batchBusy} onClick={() => {
              setTransferMode("bulk")
              setTransferTarget(null)
              setTransferPanelId(0)
              setTransferPlanId(0)
              setTransferOpen(true)
            }}>
              {t("transferPanel")}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={batchBusy}
              onClick={() => {
                setAttachMode("bulk")
                setAttachTarget(null)
                setAttachOpen(true)
              }}
            >
              {t("bulkAttachInbounds")}
            </Button>
          </div>
        </div>
      ) : null}

      {singlePanelMode ? (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-muted/10 p-3">
          <DropdownMenu>
            <DropdownMenuTrigger disabled={batchBusy}>
              <Button type="button" variant="outline" size="sm" disabled={batchBusy}>
                <MoreHorizontal className="size-4" />
                <span className="ms-2">{t("toolbarMore")}</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align={isFa ? "start" : "end"}>
              <DropdownMenuItem
                onClick={() => setResetAllOpen(true)}
                disabled={filteredClientsForPanel.length < 1}
              >
                {t("moreResetAllTraffic")}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => setResetAllPanelOpen(true)}>
                {t("moreResetAllPanelTraffic")}
              </DropdownMenuItem>
              {!isScopedPasarguard ? (
                <>
                  <DropdownMenuItem onClick={() => setDelDepletedOpen(true)}>
                    {t("moreDelDepleted")}
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => setDelOrphansOpen(true)}>
                    {t("moreDelOrphans")}
                  </DropdownMenuItem>
                </>
              ) : null}
            </DropdownMenuContent>
          </DropdownMenu>
          {isScopedPasarguard ? (
            <p className="text-xs text-muted-foreground">{t("pasarguardPanelOpsNote")}</p>
          ) : null}
          <div className="ms-auto flex flex-wrap items-end gap-2">
            <div className="grid gap-1">
              <Label className="text-xs">{t("expiredOlderMinDays")}</Label>
              <Input
                type="number"
                min={0}
                max={3650}
                className="h-9 w-24"
                value={deleteExpiredOlderMinDays}
                onChange={(e) => setDeleteExpiredOlderMinDays(Math.max(0, parseInt(e.target.value || "0", 10) || 0))}
              />
            </div>
            <Button
              type="button"
              size="sm"
              variant="destructive"
              disabled={batchBusy || expiredOlderDeleteCount < 1}
              onClick={() => setDeleteExpiredOlderOpen(true)}
            >
              {t("expiredOlderDeleteAll")} ({expiredOlderDeleteCount})
            </Button>
          </div>
        </div>
      ) : null}

      {!merged && refreshing ? (
        <p className="text-sm text-muted-foreground">{t("loading")}</p>
      ) : null}

      {merged && merged.panels.every((p) => p.plans.length === 0) ? (
        <p className="text-sm text-muted-foreground">{t("noPlans")}</p>
      ) : null}

      {merged?.panels.map((block) => (
        <div key={block.panel_id} className="space-y-3 rounded-xl border border-border/60 bg-card/30 p-3 sm:p-4">
          <div className={cn("flex flex-wrap items-baseline justify-between gap-2 border-b border-border/50 pb-2")}>
            <div>
              <h3 className="text-base font-semibold">
                {t("panelHeading", { id: block.panel_id, label: block.panel_label })}
              </h3>
              {block.truncated > 0 ? (
                <p className="text-xs text-amber-600 dark:text-amber-400">{t("panelTruncated", { n: block.truncated })}</p>
              ) : null}
            </div>
          </div>

          {block.plans.map((pg) => {
            const plan = pg.plan
            const planName = String(plan.name ?? `#${num(plan.id)}`)
            const planId = num(plan.id)
            const iid = num(pg.inbound_id)
            const linkedClients = pg.clients.filter(isLinkedWithPlan)
            const planPk = planGroupKey(block.panel_id, planId, iid)
            const planPage = planPages[planPk] ?? 1
            const planPageSlice = paginateSlice(linkedClients, planPage, clientsPerPage)
            const planPaginationMeta: PaginationMeta | null =
              linkedClients.length > 0
                ? { page: planPage, perPage: clientsPerPage, total: linkedClients.length }
                : null
            const sub = t("planInbound", {
              id: iid,
              protocol: String(pg.protocol ?? "—"),
              port: num(pg.port),
            })
            return (
              <Collapsible
                key={`${block.panel_id}-${planId}-${iid}`}
                defaultOpen
                className="group/collapsible overflow-hidden rounded-lg border border-border/50"
              >
                <div className="flex items-stretch gap-0 border-b border-border/50">
                  <CollapsibleTrigger>
                    <button
                      type="button"
                      className={cn(
                        "flex min-w-0 flex-1 items-center gap-2 p-3 text-start hover:bg-muted/40"
                      )}
                    >
                      <ChevronDown className="size-4 shrink-0 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                      <div className="min-w-0">
                        <div className="font-medium">{planName}</div>
                        <div className="text-xs text-muted-foreground">{sub}</div>
                      </div>
                    </button>
                  </CollapsibleTrigger>
                  <div className="flex shrink-0 items-center border-s border-border/50 px-2">
                    {singlePanelMode ? (
                      <input
                        type="checkbox"
                        className="me-2 size-4"
                        checked={
                          planPageSlice.length > 0 &&
                          planPageSlice.every((row) => {
                            const rrk = rowKey(block.panel_id, iid, String(row.email ?? ""))
                            return Boolean(bulkSel[rrk])
                          })
                        }
                        onChange={(e) => {
                          for (const row of planPageSlice) {
                            const em = String(row.email ?? "")
                            const rrk = rowKey(block.panel_id, iid, em)
                            toggleBulkRow(rrk, block.panel_id, iid, em, e.target.checked)
                          }
                        }}
                        aria-label={t("selectAllInPanel")}
                      />
                    ) : null}
                    <Button
                      type="button"
                      size="icon"
                      variant="ghost"
                      className="size-8"
                      disabled={isScopedPasarguard || iid < 1}
                      onClick={(e) => {
                        e.preventDefault()
                        e.stopPropagation()
                        setInboundPatchTarget({
                          panel_id: block.panel_id,
                          inbound_id: iid,
                          remark: String(pg.inbound_remark ?? ""),
                        })
                      }}
                      aria-label={t("fieldRemark")}
                    >
                      <Pencil className="size-4" />
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={busyRow === `quick:${planId}` || planId < 1}
                      onClick={(e) => {
                        e.preventDefault()
                        e.stopPropagation()
                        setQuickPlanId(planId)
                        setQuickTarget(
                          merged.default_svp_user_id > 0 ? String(merged.default_svp_user_id) : ""
                        )
                        setQuickOpen(true)
                      }}
                    >
                      <UserPlus className="size-4" />
                      <span className="sr-only md:not-sr-only md:inline">{t("quickAdd")}</span>
                    </Button>
                  </div>
                </div>
                <CollapsibleContent>
                  <div className="border-t border-border/50 bg-muted/5">
                    {linkedClients.length === 0 ? (
                      <p className="p-3 text-sm text-muted-foreground">{t("noClientsInPlan")}</p>
                    ) : (
                      <>
                        {planPageSlice.map((row) => renderConfigClientRow(block, iid, row))}
                        <div className="px-3 pb-3">
                          <DataPagination
                            meta={planPaginationMeta}
        onPageChange={(page) => setPlanPage(planPk, page)}
                            onPerPageChange={(pp) => {
                              setClientsPerPage(pp)
                              setPlanPages({})
                              setOrphanPageByPanel({})
                            }}
                            perPageOptions={[25, 50, 100, 150, 200]}
                          />
                        </div>
                      </>
                    )}
                  </div>
                </CollapsibleContent>
              </Collapsible>
            )
          })}

          {(() => {
            const orphans = collectOrphansForBlock(block)
            if (orphans.length < 1) return null
            const orphanPage = orphanPageByPanel[block.panel_id] ?? 1
            const orphanTotalPages = Math.max(1, Math.ceil(orphans.length / clientsPerPage))
            const safeOrphanPage = Math.min(orphanPage, orphanTotalPages)
            const orphanSlice = paginateSlice(orphans, safeOrphanPage, clientsPerPage)
            const orphanMeta: PaginationMeta = {
              page: safeOrphanPage,
              perPage: clientsPerPage,
              total: orphans.length,
            }
            return (
              <Collapsible
                key={`orphan-${block.panel_id}`}
                defaultOpen
                className="group/orphan overflow-hidden rounded-lg border border-amber-500/50"
              >
                <CollapsibleTrigger>
                  <button
                    type="button"
                    className={cn(
                      "flex w-full items-center gap-2 border-b border-amber-500/30 bg-amber-500/5 p-3 text-start hover:bg-amber-500/10"
                    )}
                  >
                    <ChevronDown className="size-4 shrink-0 transition-transform group-data-[state=open]/orphan:rotate-180" />
                    <div className="min-w-0 flex-1">
                      <div className="font-medium text-amber-900 dark:text-amber-100">
                        {t("orphanConfigsSection", { n: orphans.length })}
                      </div>
                      <p className="text-xs text-muted-foreground">{t("orphanConfigsHint")}</p>
                    </div>
                  </button>
                </CollapsibleTrigger>
                <CollapsibleContent>
                  <div className="space-y-0 border-t border-amber-500/20 bg-amber-500/5">
                    {orphanSlice.map((item) => {
                      const stubBlock: SnapshotPanelBlock = {
                        panel_id: item.panel_id,
                        panel_label: item.panel_label,
                        plans: [],
                        truncated: 0,
                        expired_linked_batch_count: 0,
                        cache_synced_at: null,
                        cache_stale: false,
                        needs_sync: false,
                      }
                      const pid = num(item.row.panel_id) || item.panel_id
                      const rk = rowKey(pid, item.inbound_id, String(item.row.email ?? ""))
                      return (
                        <div key={rk} className="overflow-hidden border-b border-amber-500/15 last:border-b-0">
                          <div
                            className={cn(
                              "border-b border-amber-500/15 bg-amber-500/10 px-3 py-2 text-xs text-muted-foreground",
                              "text-start"
                            )}
                          >
                            <span className="font-medium text-foreground">{item.planName}</span>
                            <span className="mx-1.5">·</span>
                            <span>
                              {t("planInbound", {
                                id: item.inbound_id,
                                protocol: item.protocol,
                                port: item.port,
                              })}
                            </span>
                          </div>
                          {renderConfigClientRow(stubBlock, item.inbound_id, item.row)}
                        </div>
                      )
                    })}
                    <div className="px-3 py-3">
                      <DataPagination
                        meta={orphanMeta}
        onPageChange={(page) => setOrphanPage(block.panel_id, page)}
                        onPerPageChange={(pp) => {
                          setClientsPerPage(pp)
                          setPlanPages({})
                          setOrphanPageByPanel({})
                        }}
                        perPageOptions={[25, 50, 100, 150, 200]}
                      />
                    </div>
                  </div>
                </CollapsibleContent>
              </Collapsible>
            )
          })()}
        </div>
      ))}

      <Dialog
        open={infoOpen}
        onOpenChange={(open) => {
          setInfoOpen(open)
          if (!open) {
            setInfoCtx(null)
            setInfoPortalPayload(null)
          }
        }}
      >
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-lg")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("infoTitle")}</DialogTitle>
          </DashDialogHeader>
          {infoRow ? (
            <div className="max-h-[70vh] space-y-4 overflow-y-auto pe-1">
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("detailsIdentity")}
                </p>
                <DetailRow label={t("fieldEmail")}>{String(infoRow.email ?? "—")}</DetailRow>
                <DetailRow label={t("fieldServiceName")}>
                  {String(
                    infoRow.service_canonical ??
                      infoRow.subscription_name ??
                      infoRow.service_remark ??
                      t("none")
                  )}
                </DetailRow>
                <DetailRow label={t("fieldRemark")}>
                  {String(infoRow.panel_remark ?? infoRow.remark ?? t("none"))}
                </DetailRow>
                <DetailRow label={t("fieldAdminComment")}>{String(infoRow.comment ?? t("none"))}</DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("detailsTraffic")}
                </p>
                <DetailRow label={t("used")}>{formatBytes(num(infoRow.used_bytes), isFa)}</DetailRow>
                <DetailRow label={t("cap")}>
                  {num(infoRow.total_gb) < 1 && num(infoRow.limit_bytes) < 1
                    ? t("unlimited")
                    : `${formatNumber(num(infoRow.total_gb), isFa)} ${t("gbUnit")} · ${formatBytes(num(infoRow.limit_bytes), isFa)}`}
                </DetailRow>
                <DetailRow label={t("fieldLimitIp")}>{formatNumber(num(infoRow.limit_ip), isFa)}</DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("detailsExpiry")}
                </p>
                <DetailRow label={t("expiryUnified")}>{unifiedExpirySummary(infoRow)}</DetailRow>
                {expirySourcesDiffer(infoRow) ? (
                  <p className="text-xs text-amber-700 dark:text-amber-400">{t("expiryMismatchHint")}</p>
                ) : null}
                <DetailRow label={t("fieldStartAfterFirstUse")}>
                  {num(infoRow.first_usage) !== 0 ? t("yes") : t("no")}
                </DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("detailsLink")}
                </p>
                <DetailRow label={t("linkStatus")}>
                  {num(infoRow.is_linked) !== 0 ? t("linkStatusLinked") : t("linkStatusUnlinked")}
                </DetailRow>
                <DetailRow label="linked_service_id">{String(num(infoRow.linked_service_id) || t("none"))}</DetailRow>
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("detailsEndpoints")}
                </p>
                <DetailRow label={t("qrSub")}>
                  {String(infoRow.subscription_url ?? "").trim() ? (
                    <span className="font-mono text-xs">{String(infoRow.subscription_url)}</span>
                  ) : (
                    t("noSubUrl")
                  )}
                </DetailRow>
                {infoPortalLoading ? (
                  <p className="text-xs text-muted-foreground">{t("configsLoading")}</p>
                ) : null}
                {infoCopyHint ? (
                  <p className={cn("text-xs", infoCopyHint === t("copyOk") ? "text-emerald-600" : "text-destructive")}>
                    {infoCopyHint}
                  </p>
                ) : null}
                {(() => {
                  const cfgUris = infoCtx ? effectiveQrConfigUris(infoCtx, infoPortalPayload) : []
                  const cfgLabels = parsePayloadConfigLabels(infoPortalPayload)
                  if (cfgUris.length > 0) {
                    return (
                      <div className="space-y-3">
                        <p className="text-xs font-medium text-muted-foreground">
                          {t("detailsConfigs")}
                          {cfgUris.length > 1 ? ` · ${t("configsCount", { n: cfgUris.length })}` : ""}
                        </p>
                        {cfgUris.map((uri, idx) => (
                          <div key={`${idx}-${uri}`} className="space-y-1 rounded-md border border-border/60 p-2">
                            <div className="flex items-center justify-between gap-2">
                              <span className="text-xs font-medium">
                                {configLineLabel(idx, cfgLabels, tl)}
                              </span>
                              <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="h-7 shrink-0"
                                onClick={() => void copyToClipboard(uri).then((ok) => showInfoCopy(ok ? "ok" : "fail"))}
                              >
                                <Copy className="size-3" />
                                {t("copyAction")}
                              </Button>
                            </div>
                            <code className="block break-all font-mono text-xs">{uri}</code>
                          </div>
                        ))}
                      </div>
                    )
                  }
                  return (
                    <DetailRow label={t("qrCfg")}>
                      {String(infoRow.primary_config_uri ?? "").trim() ? (
                        <span className="font-mono text-xs">{String(infoRow.primary_config_uri)}</span>
                      ) : (
                        t("noCfgUri")
                      )}
                    </DetailRow>
                  )
                })()}
              </div>
              <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("detailsIps")}
                </p>
                <DetailRow label={t("ipsTitle")}>
                  {parseClientIps(infoRow).length ? parseClientIps(infoRow).join(", ") : t("ipsEmpty")}
                </DetailRow>
              </div>
            </div>
          ) : null}
        </DashDialogContent>
      </Dialog>

      <Dialog
        open={Boolean(ipsTarget)}
        onOpenChange={(open) => {
          if (!open) {
            setIpsTarget(null)
            setIpsLive(null)
          }
        }}
      >
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("ipsTitle")}</DialogTitle>
          </DashDialogHeader>
          {ipsTarget ? (
            <div className="space-y-2">
              <p className="break-all font-mono text-xs text-muted-foreground">{String(ipsTarget.row.email ?? "")}</p>
              {ipsLoading ? <p className="text-sm text-muted-foreground">{t("syncBusy")}</p> : null}
              {(() => {
                const ips = ipsLive ?? parseClientIps(ipsTarget.row)
                return ips.length ? (
                  <ul className="max-h-64 list-inside list-disc space-y-1 overflow-y-auto text-sm">
                    {ips.map((ip) => (
                      <li key={ip} className="font-mono">
                        {ip}
                      </li>
                    ))}
                  </ul>
                ) : !ipsLoading ? (
                  <p className="text-sm text-muted-foreground">{t("ipsEmpty")}</p>
                ) : null
              })()}
              <DashDialogFooter>
                <Button type="button" variant="outline" disabled={ipsClearBusy || ipsLoading} onClick={() => void runClearIps()}>
                  {t("ipsClear")}
                </Button>
              </DashDialogFooter>
            </div>
          ) : null}
        </DashDialogContent>
      </Dialog>

      <Dialog
        open={qrOpen}
        onOpenChange={(open) => {
          setQrOpen(open)
          if (!open) {
            setQrCtx(null)
            setQrPortalPayload(null)
            setQrPortalLoading(false)
            setQrCopyHint(null)
          }
        }}
      >
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-lg")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("qrTitle")}</DialogTitle>
          </DashDialogHeader>
          <p className="text-xs text-muted-foreground">{t("qrClickCopyHint")}</p>
          {qrPortalLoading ? <p className="text-xs text-muted-foreground">{t("syncBusy")}</p> : null}
          {qrCopyHint ? (
            <p
              className={cn(
                "text-sm",
                qrCopyHint.startsWith("__err__") ? "text-destructive" : "text-green-600 dark:text-green-400"
              )}
            >
              {qrCopyHint.startsWith("__err__") ? qrCopyHint.slice(7) : qrCopyHint}
            </p>
          ) : null}
          {qrCtx ? (
            <div className="grid gap-8">
              <div className="grid justify-items-center gap-3">
                <div className="text-sm font-medium">{t("qrSub")}</div>
                {effectiveQrSubscriptionUrl(qrCtx, qrPortalPayload) ? (
                  <button
                    type="button"
                    className="rounded-lg border border-border/60 bg-background p-3 shadow-sm transition hover:bg-muted/50"
                    onClick={() =>
                      void copyToClipboard(effectiveQrSubscriptionUrl(qrCtx, qrPortalPayload)).then((ok) =>
                        showQrCopy(ok ? "ok" : "fail")
                      )
                    }
                  >
                    <QRCodeSVG value={effectiveQrSubscriptionUrl(qrCtx, qrPortalPayload)} size={168} level="M" />
                    <span className="mt-2 flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <Copy className="size-3" /> {t("copyAction")}
                    </span>
                  </button>
                ) : (
                  <p className="text-xs text-muted-foreground">{t("noSubUrl")}</p>
                )}
              </div>
              <div className="grid justify-items-center gap-3">
                <div className="text-sm font-medium">{t("qrPortal")}</div>
                {effectiveQrPortalUrl(qrCtx, qrPortalPayload) ? (
                  <button
                    type="button"
                    className="rounded-lg border border-border/60 bg-background p-3 shadow-sm transition hover:bg-muted/50"
                    onClick={() =>
                      void copyToClipboard(effectiveQrPortalUrl(qrCtx, qrPortalPayload)).then((ok) =>
                        showQrCopy(ok ? "ok" : "fail")
                      )
                    }
                  >
                    <QRCodeSVG value={effectiveQrPortalUrl(qrCtx, qrPortalPayload)} size={168} level="M" />
                    <span className="mt-2 flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <Copy className="size-3" /> {t("copyAction")}
                    </span>
                  </button>
                ) : (
                  <p className="text-xs text-muted-foreground">{t("none")}</p>
                )}
              </div>
              <div className="grid justify-items-center gap-3">
                <div className="text-sm font-medium">{t("qrCfg")}</div>
                {effectiveQrConfigUris(qrCtx, qrPortalPayload).length > 0 ? (
                  <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {effectiveQrConfigUris(qrCtx, qrPortalPayload).map((cfg, idx) => (
                      <button
                        key={`${idx}-${cfg}`}
                        type="button"
                        className="rounded-lg border border-border/60 bg-background p-3 shadow-sm transition hover:bg-muted/50"
                        onClick={() => void copyToClipboard(cfg).then((ok) => showQrCopy(ok ? "ok" : "fail"))}
                      >
                        <div className="mb-2 text-center text-xs font-medium">
                          {configLineLabel(idx, parsePayloadConfigLabels(qrPortalPayload), tl)}
                        </div>
                        <QRCodeSVG value={cfg} size={168} level="M" />
                        <span className="mt-2 flex items-center justify-center gap-1 text-xs text-muted-foreground">
                          <Copy className="size-3" /> {t("copyAction")}
                        </span>
                      </button>
                    ))}
                  </div>
                ) : effectiveQrPrimaryConfigUri(qrCtx, qrPortalPayload) ? (
                  <button
                    type="button"
                    className="rounded-lg border border-border/60 bg-background p-3 shadow-sm transition hover:bg-muted/50"
                    onClick={() =>
                      void copyToClipboard(effectiveQrPrimaryConfigUri(qrCtx, qrPortalPayload)).then((ok) =>
                        showQrCopy(ok ? "ok" : "fail")
                      )
                    }
                  >
                    <QRCodeSVG value={effectiveQrPrimaryConfigUri(qrCtx, qrPortalPayload)} size={168} level="M" />
                    <span className="mt-2 flex items-center justify-center gap-1 text-xs text-muted-foreground">
                      <Copy className="size-3" /> {t("copyAction")}
                    </span>
                  </button>
                ) : (
                  <p className="text-xs text-muted-foreground">{t("noCfgUri")}</p>
                )}
              </div>
            </div>
          ) : null}
        </DashDialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-lg")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("editTitle")}</DialogTitle>
          </DashDialogHeader>
          <div className="grid max-h-[70vh] gap-3 overflow-y-auto py-2 pe-1">
            <div className="grid gap-1">
              <Label>{t("fieldRemark")}</Label>
              <Input value={editRemark} onChange={(e) => setEditRemark(e.target.value)} />
            </div>
            <div className="grid gap-1">
              <Label>{t("fieldAdminComment")}</Label>
              <Textarea value={editClientComment} onChange={(e) => setEditClientComment(e.target.value)} rows={3} />
            </div>
            <div className="grid gap-1">
              <Label>{t("fieldLimitIp")}</Label>
              <Input inputMode="numeric" value={editLimitIp} onChange={(e) => setEditLimitIp(e.target.value)} />
            </div>
            <div className={cn("flex items-center justify-between gap-3 rounded-md border border-border/50 px-3 py-2")}>
              <Label htmlFor="cfg-safu" className="cursor-pointer text-sm">
                {t("fieldStartAfterFirstUse")}
              </Label>
              <Switch
                id="cfg-safu"
                checked={editStartAfterFirstUse}
                onCheckedChange={setEditStartAfterFirstUse}
                aria-label={t("fieldStartAfterFirstUse")}
              />
            </div>
            <div className="grid gap-1">
              <Label>{t("fieldTotalGb")}</Label>
              <Input inputMode="numeric" value={editTotalGb} onChange={(e) => setEditTotalGb(e.target.value)} />
            </div>
            <DashboardDateTimePicker
              label={isFa ? t("fieldExpiryShamsi") : t("fieldExpiry")}
        value={editExpiryMs > 0 ? msToApiDatetime(editExpiryMs) : ""}
              onChange={(v) => setEditExpiryMs(apiDatetimeToMs(v))}
            />
          </div>
          <DashDialogFooter className={cn("")}>
            <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
              {t("cancel")}
            </Button>
            <Button
              type="button"
              disabled={busyRow === rowKey(editPanelId, editInboundId, editEmail)}
              onClick={() => void onSaveEdit()}
            >
              {t("save")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={delOpen} onOpenChange={setDelOpen}>
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("deleteOneTitle")}</DialogTitle>
          </DashDialogHeader>
          <p className="text-sm text-muted-foreground">
            {delRow && num(delRow.linked_service_id) > 0 ? t("deleteOneLinked") : t("deleteOneOrphan")}
          </p>
          <DashDialogFooter className={cn("")}>
            <Button type="button" variant="outline" onClick={() => setDelOpen(false)}>
              {t("cancel")}
            </Button>
            <Button type="button" variant="destructive" onClick={() => void onDelete()}>
              {t("delete")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={resetOpen} onOpenChange={setResetOpen}>
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("resetTrafficTitle")}</DialogTitle>
          </DashDialogHeader>
          <DashDialogFooter className={cn("")}>
            <Button type="button" variant="outline" onClick={() => setResetOpen(false)}>
              {t("cancel")}
            </Button>
            <Button type="button" onClick={() => void onResetTraffic()}>
              {t("resetTraffic")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={quickOpen} onOpenChange={setQuickOpen}>
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("quickAdd")}</DialogTitle>
          </DashDialogHeader>
          <p className="text-sm text-muted-foreground">{t("quickAddHint")}</p>
          {merged && merged.default_svp_user_id < 1 ? (
            <p className="text-xs text-amber-600 dark:text-amber-400">{t("defaultUserHint")}</p>
          ) : null}
          <div className="grid gap-1">
            <Label>{t("targetUser")} (svp_users.id)</Label>
            <Input
              placeholder={merged && merged.default_svp_user_id > 0 ? String(merged.default_svp_user_id) : ""}
              value={quickTarget}
              onChange={(e) => setQuickTarget(e.target.value)}
            />
          </div>
          <DashDialogFooter className={cn("")}>
            <Button type="button" variant="outline" onClick={() => setQuickOpen(false)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={busyRow === `quick:${quickPlanId}`} onClick={() => void runQuickAdd()}>
              {t("createService")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={linkOpen} onOpenChange={setLinkOpen}>
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>
              {linkCtx && num(linkCtx.row.is_linked) !== 0 ? t("linkUserEdit") : t("linkUserAdd")}
            </DialogTitle>
          </DashDialogHeader>
          {linkCtx ? (
            <div className="space-y-3">
              <p className="break-all font-mono text-xs text-muted-foreground">{String(linkCtx.row.email ?? "")}</p>
              <div className="grid gap-1">
                <Label>{t("userSearchPlaceholder")}</Label>
                <Input
                  placeholder={t("userSearchPlaceholder")}
                  value={linkQuery}
                  onChange={(e) => {
                    const v = e.target.value
                    setLinkQuery(v)
                    scheduleLinkSearch(v)
                  }}
                />
              </div>
              {linkHits.length > 0 ? (
                <div className="max-h-32 overflow-y-auto rounded border border-border/60 bg-muted/20 p-1 text-xs">
                  {linkHits.map((u) => (
                    <button
                      key={num(u.id)}
                      type="button"
                      className={cn(
                        "block w-full truncate rounded px-2 py-1 hover:bg-muted",
                        isFa ? "text-end" : "text-start"
                      )}
                      onClick={() => setLinkPick({ id: num(u.id), label: userRowLabel(u) })}
                    >
                      {userRowLabel(u)}
                    </button>
                  ))}
                </div>
              ) : null}
              {linkPick ? (
                <p className="text-xs text-muted-foreground">
                  {linkPick.label}
                  <button
                    type="button"
                    className="ms-2 underline"
                    onClick={() => setLinkPick(null)}
                  >
                    {tInbound("clearPick")}
                  </button>
                </p>
              ) : null}
            </div>
          ) : null}
          <DashDialogFooter className={cn("")}>
            <Button type="button" variant="outline" onClick={() => setLinkOpen(false)}>
              {t("cancel")}
            </Button>
            <Button
              type="button"
              variant="secondary"
              disabled={!linkCtx || busyRow === (linkCtx ? rowKey(linkCtx.panel_id, linkCtx.inbound_id, String(linkCtx.row.email ?? "")) : "")}
              onClick={() => void submitLink()}
            >
              {t("link")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={assignPlanOpen} onOpenChange={setAssignPlanOpen}>
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("assignPlanTitle")}</DialogTitle>
          </DashDialogHeader>
          <p className="text-sm text-muted-foreground">{t("assignPlanHint")}</p>
          <div className="grid gap-1">
            <Label>{t("pickPlan")}</Label>
            <DashSelect
              triggerClassName={selectTriggerClass}
              value={String(assignPlanId)}
              onValueChange={(v) => setAssignPlanId(parseInt(v || "0", 10) || 0)}
              options={[
                { value: "0", label: t("pickPlan") },
                ...scopedActivePlans.map((p) => ({
                  value: String(num(p.id)),
                  label: String(p.name ?? `#${num(p.id)}`),
                })),
              ]}
            />
          </div>
          <DashDialogFooter className={cn("")}>
            <Button type="button" variant="outline" onClick={() => setAssignPlanOpen(false)}>
              {t("cancel")}
            </Button>
            <Button type="button" onClick={() => void runAssignPlan()}>
              {t("assignPlan")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={transferOpen} onOpenChange={setTransferOpen}>
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("transferPanelTitle")}</DialogTitle>
          </DashDialogHeader>
          <div className="grid gap-3">
            <div className="grid gap-1">
              <Label>{t("pickTargetPanel")}</Label>
              <DashSelect
                triggerClassName={selectTriggerClass}
                value={String(transferPanelId)}
                onValueChange={(v) => {
                  const n = parseInt(v || "0", 10) || 0
                  setTransferPanelId(n)
                  setTransferPlanId(0)
                }}
                options={[
                  { value: "0", label: t("pickTargetPanel") },
                  ...panelOptions.map((p) => ({
                    value: String(p.id),
                    label: `#${p.id} — ${p.label}`,
                  })),
                ]}
              />
            </div>
            <div className="grid gap-1">
              <Label>{t("pickTargetPlan")}</Label>
              <DashSelect
                triggerClassName={selectTriggerClass}
                value={String(transferPlanId)}
                onValueChange={(v) => setTransferPlanId(parseInt(v || "0", 10) || 0)}
                options={[
                  { value: "0", label: t("transferKeepRemaining") },
                  ...transferPanelPlans.map((p) => ({
                    value: String(num(p.id)),
                    label: String(p.name ?? `#${num(p.id)}`),
                  })),
                ]}
              />
            </div>
          </div>
          <DashDialogFooter className={cn("")}>
            <Button type="button" variant="outline" onClick={() => setTransferOpen(false)}>
              {t("cancel")}
            </Button>
            <Button type="button" onClick={() => void runTransferPanel()}>
              {t("transferConfirm")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <ConfigsPanelOpConfirmDialog
        open={resetAllOpen}
        onOpenChange={setResetAllOpen}
        titleKey="moreResetAllTraffic"
        hintKey="resetAllPanelHint"
        confirmKey="panelOpConfirm"
        ackKey="resetAllPanelAck"
        busy={batchBusy}
        onConfirm={() => void runResetAllFiltered()}
        tl={tl}
        contentClass={dialogContentCn("max-w-md")}
        dialogHeaderClass={dialogHeaderClass}
        destructive
      />
      <ConfigsPanelOpConfirmDialog
        open={resetAllPanelOpen}
        onOpenChange={setResetAllPanelOpen}
        titleKey="moreResetAllPanelTraffic"
        hintKey="resetAllPanelHint"
        confirmKey="resetAllPanelConfirm"
        ackKey="resetAllPanelAck"
        busy={batchBusy}
        onConfirm={() => void runResetAllPanelTraffic()}
        tl={tl}
        contentClass={dialogContentCn("max-w-md")}
        dialogHeaderClass={dialogHeaderClass}
        destructive={false}
      />
      <ConfigsPanelOpConfirmDialog
        open={delDepletedOpen}
        onOpenChange={setDelDepletedOpen}
        titleKey="moreDelDepleted"
        hintKey="delDepletedHint"
        confirmKey="panelOpConfirm"
        ackKey="delDepletedAck"
        busy={batchBusy}
        onConfirm={() => void runDelDepleted()}
        tl={tl}
        contentClass={dialogContentCn("max-w-md")}
        dialogHeaderClass={dialogHeaderClass}
      />
      <ConfigsPanelOpConfirmDialog
        open={delOrphansOpen}
        onOpenChange={setDelOrphansOpen}
        titleKey="moreDelOrphans"
        hintKey="delOrphansHint"
        confirmKey="panelOpConfirm"
        ackKey="delOrphansAck"
        busy={batchBusy}
        onConfirm={() => void runDelOrphans()}
        tl={tl}
        contentClass={dialogContentCn("max-w-md")}
        dialogHeaderClass={dialogHeaderClass}
      />
      <ConfigsAttachInboundsDialog
        open={attachOpen}
        onOpenChange={setAttachOpen}
        mode={attachMode}
        email={attachTarget?.email}
        emails={attachMode === "bulk" ? Object.values(bulkSel).map((it) => it.email) : undefined}
        inbounds={panelInbounds}
        busy={batchBusy}
        onConfirm={(attachIds, detachIds) => void runAttachInbounds(attachIds, detachIds)}
        tl={tl}
        contentClass={dialogContentCn("max-w-lg")}
        dialogHeaderClass={dialogHeaderClass}
      />
      <ConfigsExpiredOlderDeleteDialog
        open={deleteExpiredOlderOpen}
        onOpenChange={setDeleteExpiredOlderOpen}
        count={expiredOlderDeleteCount}
        minDays={deleteExpiredOlderMinDays}
        busy={batchBusy}
        onConfirm={() => void runDeleteExpiredOlderThan()}
        tl={tl}
        contentClass={dialogContentCn("max-w-md")}
        dialogHeaderClass={dialogHeaderClass}
      />
      <Dialog
        open={Boolean(inboundPatchTarget)}
        onOpenChange={(open) => {
          if (!open) setInboundPatchTarget(null)
        }}
      >
        <DashDialogContent dir={dialogDir} className={dialogContentCn("max-w-md")}>
          <DashDialogHeader className={dialogHeaderClass}>
            <DialogTitle>{t("fieldRemark")}</DialogTitle>
          </DashDialogHeader>
          {inboundPatchTarget ? (
            <div className="grid gap-3">
              <p className="text-xs text-muted-foreground">
                #{inboundPatchTarget.panel_id} / inbound #{inboundPatchTarget.inbound_id}
              </p>
              <Input
                value={inboundPatchTarget.remark}
                onChange={(e) =>
                  setInboundPatchTarget((prev) => (prev ? { ...prev, remark: e.target.value } : prev))
                }
              />
            </div>
          ) : null}
          <DashDialogFooter>
            <Button type="button" variant="outline" onClick={() => setInboundPatchTarget(null)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={inboundPatchBusy} onClick={() => void runInboundPatch()}>
              {t("save")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}
