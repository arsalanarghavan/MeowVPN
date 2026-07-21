"use client"

import { EllipsisVertical } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Dialog,
  DialogDescription,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { PanelMergeDialog } from "@/components/admin/panel-merge-dialog"
import {
  DashboardPanelEconomicsSheet,
  type PanelEconomicsEntry,
} from "@/components/dashboard-panel-economics-sheet"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import {
  adminMutateErrorText,
  getAdminState,
  postAdminJson,
  postAdminMutate,
  type AdminMutateResult,
} from "@/lib/dash-admin-mutate"
import { DataPagination } from "@/components/data-pagination"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"

type DashRecord = Record<string, unknown>

type PanelForm = {
  id: number
  label: string
  panel_url: string
  panel_username: string
  panel_password: string
  panel_api_base: string
  panel_login_secret: string
  panel_api_token: string
  panel_provider: "xui" | "pasarguard"
  panel_api_flavor: string
  panel_template_required: boolean
  subscription_public_base: string
  sort_order: number
  active: boolean
}

type OrphanRow = {
  panel_id: number
  inbound_id: number
  email: string
  remark: string
  sub_id: string
  used_bytes: number
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

function providerValue(v: unknown): "xui" | "pasarguard" {
  return String(v) === "pasarguard" ? "pasarguard" : "xui"
}

function emptyForm(): PanelForm {
  return {
    id: 0,
    label: "",
    panel_url: "",
    panel_username: "",
    panel_password: "",
    panel_api_base: "panel/api",
    panel_login_secret: "",
    panel_api_token: "",
    panel_provider: "xui",
    panel_api_flavor: "",
    panel_template_required: false,
    subscription_public_base: "",
    sort_order: 0,
    active: true,
  }
}

function formFromRow(row: DashRecord): PanelForm {
  const provider = providerValue(row.panel_provider)
  return {
    id: num(row.id),
    label: String(row.label ?? ""),
    panel_url: String(row.panel_url ?? ""),
    panel_username: String(row.panel_username ?? ""),
    panel_password: "",
    panel_api_base: String(row.panel_api_base ?? (provider === "pasarguard" ? "api" : "panel/api")),
    panel_login_secret: "",
    panel_api_token: "",
    panel_provider: provider,
    panel_api_flavor: String(row.panel_api_flavor ?? ""),
    panel_template_required: bool(row.panel_template_required),
    subscription_public_base: String(row.subscription_public_base ?? ""),
    sort_order: num(row.sort_order),
    active: bool(row.active),
  }
}

function panelBadgeLabel(row: DashRecord, t: (key: string) => string): string {
  return providerValue(row.panel_provider) === "pasarguard" ? t("providerPasarguard") : t("providerXui")
}

function panelBadgeVariant(row: DashRecord): "default" | "secondary" | "outline" {
  return providerValue(row.panel_provider) === "pasarguard" ? "default" : "outline"
}

function probeLabelKey(name: string): string {
  if (name === "server_status") return "probe_server_status"
  if (name === "inbounds_list") return "probe_inbounds_list"
  if (name === "inbounds_onlines") return "probe_inbounds_onlines"
  if (name === "clients_onlines") return "probe_clients_onlines"
  if (name === "clients_list") return "probe_clients_list"
  return name
}

function mainProbeKeys(apiFlavor: string): string[] {
  if (apiFlavor === "v3_clients") {
    return ["server_status", "inbounds_list", "clients_onlines", "clients_list"]
  }
  return ["server_status", "inbounds_list", "inbounds_onlines"]
}

function PanelTestResults({
  testRes,
  tp,
  isFa,
}: {
  testRes: AdminMutateResult
  tp: (key: string, values?: Record<string, string | number>) => string
  isFa: boolean
}) {
  const data = testRes.data as Record<string, unknown> | undefined
  const diag = (data?.diag ?? {}) as Record<string, unknown>
  const probes = (data?.probes ?? {}) as Record<string, Record<string, unknown>>
  const suggested = data?.suggested_base != null ? String(data.suggested_base) : ""

  const authMode = String(diag.auth_mode ?? "")
  const authLabel =
    authMode === "bearer"
      ? tp("authBearer")
      : authMode === "cookie"
        ? tp("authCookie")
        : authMode === "incomplete"
          ? tp("authIncomplete")
          : authMode

  const probeHintLabel = (hint: string) => {
    if (!hint) return "—"
    const key = `probe_${hint}`
    try {
      return tp(key)
    } catch {
      return hint
    }
  }

  const mainProbes = mainProbeKeys(String(diag.api_flavor ?? ""))

  return (
    <div className="space-y-3 text-sm">
      <p className={testRes.ok ? "text-emerald-600 dark:text-emerald-400" : "text-destructive"}>
        {testRes.ok ? tp("testOk") : testRes.message || tp("testFail")}
      </p>
      {authMode ? (
        <p className="text-muted-foreground">
          {tp("testAuthMode")}: <span className="font-medium text-foreground">{authLabel}</span>
        </p>
      ) : null}
      {diag.api_flavor ? (
        <p className="text-muted-foreground">
          {tp("testApiFlavor")}:{" "}
          <span className="font-medium text-foreground" dir="ltr">
            {String(diag.api_flavor)}
          </span>
        </p>
      ) : null}
      {suggested ? (
        <div className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs">
          <p className="font-medium">{tp("testSuggestedBase")}</p>
          <p className="mt-1 font-mono" dir="ltr">
            {suggested}
          </p>
        </div>
      ) : null}
      {Object.keys(probes).length > 0 ? (
        <div className="overflow-x-auto rounded-md border border-border">
          <table
            className={cn(
              "w-full min-w-[20rem] border-collapse text-xs [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border",
              "text-start"
            )}
          >
            <thead>
              <tr className="bg-muted/40">
                <th className="p-2 font-medium">{tp("testProbeName")}</th>
                <th className="p-2 font-medium">{tp("testProbeHttp")}</th>
                <th className="p-2 font-medium">{tp("testProbeHint")}</th>
                <th className="p-2 font-medium">{tp("testProbeMsg")}</th>
              </tr>
            </thead>
            <tbody>
              {mainProbes.map((key) => {
                const row = probes[key]
                if (!row || row.skipped) return null
                return (
                  <tr key={key}>
                    <td className="p-2">{tp(probeLabelKey(key))}</td>
                    <td className="p-2 font-mono tabular-nums" dir="ltr">
                      {num(row.http) > 0 ? formatNumber(num(row.http), isFa) : "—"}
                    </td>
                    <td className="p-2">
                      <Badge variant={row.ok ? "default" : "destructive"} className="font-normal">
                        {probeHintLabel(String(row.hint ?? ""))}
                      </Badge>
                    </td>
                    <td className="max-w-[10rem] truncate p-2 text-muted-foreground" title={String(row.msg ?? "")}>
                      {String(row.msg ?? "")}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      ) : null}
      {data != null ? (
        <details className="text-xs">
          <summary className="cursor-pointer text-muted-foreground hover:text-foreground">{tp("testRawJson")}</summary>
          <pre className="mt-2 max-h-40 overflow-auto rounded-md border border-border bg-muted/40 p-2" dir="ltr">
            {JSON.stringify(data, null, 2)}
          </pre>
        </details>
      ) : null}
    </div>
  )
}

export function PanelsAdminClient() {
  const t = useTranslations("panelsAdmin")
  const tEco = useTranslations("panelEconomics")
  const locale = useLocale()
  const isFa = locale === "fa"

  const [panels, setPanels] = useState<DashRecord[]>([])
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(20)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [actionMsg, setActionMsg] = useState<string | null>(null)
  const [testResult, setTestResult] = useState<AdminMutateResult | null>(null)
  const [testOpen, setTestOpen] = useState(false)
  const [testPanelId, setTestPanelId] = useState(0)
  const [testLoading, setTestLoading] = useState(false)
  const [form, setForm] = useState<PanelForm>(emptyForm)
  const [mode, setMode] = useState<"add" | "edit">("add")
  const [saving, setSaving] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [mergePanel, setMergePanel] = useState<DashRecord | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)
  const [economicsOpen, setEconomicsOpen] = useState(false)
  const [economicsPanelId, setEconomicsPanelId] = useState(0)
  const [economicsPanelLabel, setEconomicsPanelLabel] = useState("")
  const [localEconomicsMap, setLocalEconomicsMap] = useState<Record<string, PanelEconomicsEntry>>({})
  const [globalEconomicsConfig, setGlobalEconomicsConfig] = useState<{
    total_sold_volume_gb?: number
    selling_price_per_gb?: number
    volume_mode?: string
    volume_window_days?: number
  }>({})
  const [orphanPanelId, setOrphanPanelId] = useState("")
  const [orphanUserId, setOrphanUserId] = useState("")
  const [orphanServiceId, setOrphanServiceId] = useState("")
  const [orphanScanning, setOrphanScanning] = useState(false)
  const [orphanDeleting, setOrphanDeleting] = useState(false)
  const [orphanRows, setOrphanRows] = useState<OrphanRow[]>([])
  const [orphanLinked, setOrphanLinked] = useState<string[]>([])
  const [orphanSelected, setOrphanSelected] = useState<Record<string, boolean>>({})
  const [orphanMsg, setOrphanMsg] = useState<string | null>(null)
  const [repairBusyId, setRepairBusyId] = useState<number | null>(null)
  const [repairMsg, setRepairMsg] = useState<string | null>(null)

  const load = useCallback(
    async (nextPage = page) => {
      setLoading(true)
      setError(null)
      try {
        const data = await getAdminState("xui_panels", {
          panels_page: nextPage,
          panels_per_page: perPage,
        })
        const rows = Array.isArray(data.panels)
          ? (data.panels as DashRecord[])
          : Array.isArray(data.panelRows)
            ? (data.panelRows as DashRecord[])
            : Array.isArray(data.rows)
              ? (data.rows as DashRecord[])
              : []
        setPanels(rows)
        setPagination(
          parsePaginationMeta((data.pagination as Record<string, unknown> | undefined)?.panels) ??
            parsePaginationMeta(data.panelsPagination)
        )
        if (data.panelEconomicsMap && typeof data.panelEconomicsMap === "object") {
          setLocalEconomicsMap(data.panelEconomicsMap as Record<string, PanelEconomicsEntry>)
        }
        const ue = data.unitEconomics
        if (ue && typeof ue === "object") {
          const inputs = (ue as DashRecord).inputs
          if (inputs && typeof inputs === "object") {
            const inp = inputs as DashRecord
            setGlobalEconomicsConfig({
              total_sold_volume_gb: num(inp.total_sold_volume_gb),
              selling_price_per_gb: num(inp.selling_price_per_gb),
              volume_mode: String(inp.volume_mode ?? "auto_sales"),
              volume_window_days: num(inp.volume_window_days) || 30,
            })
          }
        }
      } catch {
        setError(t("loadError"))
      } finally {
        setLoading(false)
      }
    },
    [page, perPage, t]
  )

  useEffect(() => {
    void load(page)
  }, [load, page])

  useEffect(() => {
    if (typeof window === "undefined") return
    const m = window.location.search.match(/[?&]panel_costs=(\d+)/)
    const pid = m ? Number(m[1]) : 0
    if (pid < 1) return
    const row = panels.find((p) => num(p.id) === pid)
    if (row) {
      setEconomicsPanelId(pid)
      setEconomicsPanelLabel(String(row.label ?? ""))
      setEconomicsOpen(true)
    }
  }, [panels])

  const sharedLines = useMemo(() => localEconomicsMap["0"]?.lines ?? [], [localEconomicsMap])
  const siteVolumeGb = useMemo(
    () => Math.max(0, Number(globalEconomicsConfig.total_sold_volume_gb) || 0),
    [globalEconomicsConfig]
  )
  const economicsEntry = useMemo(() => {
    if (economicsPanelId < 1) return localEconomicsMap["0"]
    return localEconomicsMap[String(economicsPanelId)]
  }, [localEconomicsMap, economicsPanelId])

  const openEconomics = (row: DashRecord) => {
    setEconomicsPanelId(num(row.id))
    setEconomicsPanelLabel(String(row.label ?? ""))
    setEconomicsOpen(true)
  }

  const openAdd = () => {
    setMode("add")
    setTestResult(null)
    setActionMsg(null)
    setForm(emptyForm())
  }

  const openEdit = (row: DashRecord) => {
    setMode("edit")
    setTestResult(null)
    setActionMsg(null)
    setForm(formFromRow(row))
  }

  const setProvider = (value: "xui" | "pasarguard") => {
    setForm((current) => {
      if (value === "pasarguard") {
        return {
          ...current,
          panel_provider: "pasarguard",
          panel_api_flavor: "pasarguard_v5",
          panel_api_base: "api",
        }
      }
      return {
        ...current,
        panel_provider: "xui",
        panel_api_flavor: current.panel_provider === "pasarguard" ? "" : current.panel_api_flavor,
        panel_api_base: current.panel_provider === "pasarguard" && current.panel_api_base === "api"
          ? "panel/api"
          : current.panel_api_base || "panel/api",
      }
    })
  }

  const savePanel = useCallback(async () => {
    setSaving(true)
    setActionMsg(null)
    try {
      const payload: Record<string, unknown> = {
        id: mode === "edit" ? form.id : 0,
        label: form.label.trim(),
        panel_url: form.panel_url.trim(),
        panel_username: form.panel_username.trim(),
        panel_api_base: form.panel_api_base.trim() || (form.panel_provider === "pasarguard" ? "api" : "panel/api"),
        panel_login_secret: form.panel_login_secret.trim(),
        panel_provider: form.panel_provider,
        panel_api_flavor: form.panel_api_flavor || (form.panel_provider === "pasarguard" ? "pasarguard_v5" : ""),
        panel_template_required: form.panel_template_required ? 1 : 0,
        subscription_public_base: form.subscription_public_base.trim(),
        sort_order: form.sort_order,
        active: form.active ? 1 : 0,
      }
      if (form.panel_password.trim()) {
        payload.panel_password = form.panel_password
      }
      if (form.panel_api_token.trim()) {
        payload.panel_api_token = form.panel_api_token
      }
      const res = await postAdminMutate("panel_xp", payload)
      if (!res.ok) {
        setActionMsg(res.message || res.reason || t("mutateError"))
        return
      }
      setActionMsg(null)
      setForm(emptyForm())
      setMode("add")
      await load(page)
    } catch {
      setActionMsg(t("mutateError"))
    } finally {
      setSaving(false)
    }
  }, [form, load, mode, page, t])

  const testConnection = useCallback(async (panelId: number) => {
    setBusyId(panelId)
    setTestPanelId(panelId)
    setTestLoading(true)
    setTestOpen(true)
    setTestResult(null)
    setActionMsg(null)
    try {
      const res = await postAdminMutate("panel_test", { panel_id: panelId })
      setTestResult(res)
    } catch {
      setActionMsg(t("mutateError"))
      setTestOpen(false)
    } finally {
      setTestLoading(false)
      setBusyId(null)
    }
  }, [t])

  const hardDeletePanel = useCallback(async () => {
    if (!deleteTarget) return
    setSaving(true)
    setActionMsg(null)
    try {
      const res = await postAdminMutate("panel_xp", {
        xp_action: "delete",
        xp_id: num(deleteTarget.id),
      })
      if (!res.ok) {
        setActionMsg(res.message || res.reason || t("mutateError"))
        return
      }
      setDeleteTarget(null)
      await load(page)
    } catch {
      setActionMsg(t("mutateError"))
    } finally {
      setSaving(false)
    }
  }, [deleteTarget, load, page, t])

  const repairIdentities = useCallback(async (panelId: number) => {
    setRepairBusyId(panelId)
    setRepairMsg(null)
    try {
      const res = await postAdminMutate("panels_repair_identities", { panel_id: panelId, limit: 500 })
      if (!res.ok) {
        setRepairMsg(adminMutateErrorText(res, t("repairFail")))
        return
      }
      const scanned = num((res as DashRecord).scanned ?? (res.data as DashRecord | undefined)?.scanned)
      const repaired = num((res as DashRecord).repaired ?? (res.data as DashRecord | undefined)?.repaired)
      setRepairMsg(t("repairDone", { scanned, repaired }))
    } catch {
      setRepairMsg(t("repairFail"))
    } finally {
      setRepairBusyId(null)
    }
  }, [t])

  const toggleActive = useCallback(
    async (row: DashRecord) => {
      const panelId = num(row.id)
      setBusyId(panelId)
      setActionMsg(null)
      try {
        const res = await postAdminMutate("panel_xp", {
          id: panelId,
          active: bool(row.active) ? 0 : 1,
        })
        if (!res.ok) {
          setActionMsg(res.message || res.reason || t("mutateError"))
          return
        }
        await load(page)
      } catch {
        setActionMsg(t("mutateError"))
      } finally {
        setBusyId(null)
      }
    },
    [load, page, t]
  )

  const runOrphanScan = useCallback(async () => {
    const panelId = num(orphanPanelId)
    const userId = num(orphanUserId)
    if (panelId < 1 || userId < 1) {
      setOrphanMsg(t("orphanScanNeedPanelUser"))
      return
    }
    setOrphanScanning(true)
    setOrphanMsg(null)
    setOrphanRows([])
    setOrphanLinked([])
    setOrphanSelected({})
    try {
      const payload: Record<string, unknown> = {
        panel_id: panelId,
        user_id: userId,
      }
      const serviceId = num(orphanServiceId)
      if (serviceId > 0) {
        payload.service_id = serviceId
      }
      const res = await postAdminJson("/admin/panel/orphan-clients/scan", payload)
      if (!res.ok) {
        setOrphanMsg(String(res.message ?? t("orphanScanError")))
        return
      }
      const orphans = Array.isArray(res.orphans) ? (res.orphans as OrphanRow[]) : []
      const linked = Array.isArray(res.linked) ? (res.linked as string[]) : []
      setOrphanRows(orphans)
      setOrphanLinked(linked)
      setOrphanMsg(
        orphans.length > 0
          ? t("orphanScanFound", { orphans: orphans.length, linked: linked.length })
          : t("orphanScanEmpty", { linked: linked.length })
      )
    } catch {
      setOrphanMsg(t("orphanScanError"))
    } finally {
      setOrphanScanning(false)
    }
  }, [orphanPanelId, orphanServiceId, orphanUserId, t])

  const deleteSelectedOrphans = useCallback(async () => {
    const panelId = num(orphanPanelId)
    const emails = Object.entries(orphanSelected)
      .filter(([, checked]) => checked)
      .map(([email]) => email)
    if (panelId < 1 || emails.length < 1) {
      setOrphanMsg(t("orphanDeleteNeedSelection"))
      return
    }
    if (!window.confirm(t("orphanDeleteConfirm", { n: emails.length }))) {
      return
    }
    setOrphanDeleting(true)
    setOrphanMsg(null)
    try {
      const res = await postAdminJson("/admin/panel/orphan-clients/delete", {
        panel_id: panelId,
        emails,
        confirm: true,
      })
      if (!res.ok) {
        setOrphanMsg(String(res.message ?? t("orphanDeleteError")))
        return
      }
      const deleted = num(res.deleted)
      setOrphanMsg(t("orphanDeleteDone", { deleted }))
      await runOrphanScan()
    } catch {
      setOrphanMsg(t("orphanDeleteError"))
    } finally {
      setOrphanDeleting(false)
    }
  }, [orphanPanelId, orphanSelected, runOrphanScan, t])

  const deletePanelOrphansV3 = useCallback(async () => {
    const panelId = num(orphanPanelId)
    if (panelId < 1) {
      setOrphanMsg(t("orphanScanNeedPanelUser"))
      return
    }
    if (!window.confirm(t("orphanPanelDelConfirm"))) {
      return
    }
    setOrphanDeleting(true)
    setOrphanMsg(null)
    try {
      const res = await postAdminMutate("configs_panel_del_orphans", { panel_id: panelId })
      setOrphanMsg(
        res.ok
          ? t("orphanPanelDelOk", { n: num((res.data as DashRecord | undefined)?.deleted) })
          : adminMutateErrorText(res, t("orphanPanelDelFail"))
      )
    } catch {
      setOrphanMsg(t("orphanPanelDelFail"))
    } finally {
      setOrphanDeleting(false)
    }
  }, [orphanPanelId, t])

  const orphanBusy = orphanScanning || orphanDeleting

  const editOrAddTitle = mode === "add" ? t("sheetAdd") : t("sheetEdit")

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" size="sm" onClick={openAdd}>
            {t("add")}
          </Button>
          <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load(page)}>
            {t("refresh")}
          </Button>
        </div>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {actionMsg ? <p className="text-sm text-muted-foreground">{actionMsg}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}
      <div className="space-y-3 rounded-lg border border-dashed bg-muted/20 p-3 text-sm">
        <div>
          <p className="font-medium text-foreground">{t("orphanScanTitle")}</p>
          <p className="text-muted-foreground">{t("orphanScanHint")}</p>
        </div>
        <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,10rem)_minmax(0,10rem)_auto]">
          <div className="space-y-1">
            <Label htmlFor="orphan_panel">{t("orphanScanPanel")}</Label>
            <select
              id="orphan_panel"
              className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
              value={orphanPanelId}
              onChange={(e) => setOrphanPanelId(e.target.value)}
              disabled={orphanBusy}
            >
              <option value="">{t("orphanScanPickPanel")}</option>
              {panels.map((panel) => (
                <option key={num(panel.id)} value={num(panel.id)}>
                  #{num(panel.id)} · {String(panel.label ?? "")}
                </option>
              ))}
            </select>
          </div>
          <div className="space-y-1">
            <Label htmlFor="orphan_user">{t("orphanScanUserId")}</Label>
            <Input
              id="orphan_user"
              inputMode="numeric"
              value={orphanUserId}
              onChange={(e) => setOrphanUserId(e.target.value)}
              disabled={orphanBusy}
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor="orphan_service">
              {t("orphanScanServiceId")}{" "}
              <span className="text-muted-foreground">({t("orphanScanServiceOptional")})</span>
            </Label>
            <Input
              id="orphan_service"
              inputMode="numeric"
              value={orphanServiceId}
              onChange={(e) => setOrphanServiceId(e.target.value)}
              disabled={orphanBusy}
            />
          </div>
          <div className="flex flex-wrap items-end gap-2">
            <Button
              type="button"
              size="sm"
              disabled={orphanBusy || num(orphanPanelId) < 1 || num(orphanUserId) < 1}
              onClick={() => void runOrphanScan()}
            >
              {orphanScanning ? t("orphanScanRunning") : t("orphanScanRun")}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="destructive"
              disabled={orphanBusy || Object.values(orphanSelected).every((v) => !v)}
              onClick={() => void deleteSelectedOrphans()}
            >
              {orphanDeleting ? t("orphanDeleteRunning") : t("orphanDeleteSelected")}
            </Button>
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={orphanBusy || num(orphanPanelId) < 1}
              onClick={() => void deletePanelOrphansV3()}
            >
              {t("orphanPanelDelV3")}
            </Button>
          </div>
        </div>
        {orphanMsg ? <p className="text-muted-foreground">{orphanMsg}</p> : null}
        {orphanLinked.length > 0 ? (
          <p className="text-muted-foreground">{t("orphanScanLinkedCount", { n: orphanLinked.length })}</p>
        ) : null}
        {orphanRows.length > 0 ? (
          <div className="overflow-x-auto rounded-md border bg-background">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10" />
                  <TableHead>{t("orphanColEmail")}</TableHead>
                  <TableHead>{t("orphanColInbound")}</TableHead>
                  <TableHead>{t("orphanColRemark")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {orphanRows.map((row) => {
                  const email = String(row.email ?? "").trim()
                  return (
                    <TableRow key={email || JSON.stringify(row)}>
                      <TableCell>
                        <input
                          type="checkbox"
                          checked={Boolean(orphanSelected[email])}
                          onChange={(e) =>
                            setOrphanSelected((prev) => ({ ...prev, [email]: e.target.checked }))
                          }
                        />
                      </TableCell>
                      <TableCell dir="ltr">{email || "-"}</TableCell>
                      <TableCell>{num(row.inbound_id) || "-"}</TableCell>
                      <TableCell className="max-w-[16rem] truncate">{String(row.remark ?? "") || "-"}</TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </div>
        ) : null}
      </div>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        <Card className="self-start">
          <CardHeader>
            <CardTitle className="text-base">{editOrAddTitle}</CardTitle>
            <CardDescription>{t("fieldPanelType")}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="panel_provider">{t("fieldPanelType")}</Label>
              <select
                id="panel_provider"
                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                value={form.panel_provider}
                onChange={(e) => setProvider(providerValue(e.target.value))}
              >
                <option value="xui">{t("providerXui")}</option>
                <option value="pasarguard">{t("providerPasarguard")}</option>
              </select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="panel_label">{t("fieldLabel")}</Label>
              <Input
                id="panel_label"
                value={form.label}
                onChange={(e) => setForm((current) => ({ ...current, label: e.target.value }))}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="panel_url">{t("fieldUrl")}</Label>
              <Input
                id="panel_url"
                value={form.panel_url}
                onChange={(e) => setForm((current) => ({ ...current, panel_url: e.target.value }))}
                dir="ltr"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="panel_username">{t("fieldUser")}</Label>
              <Input
                id="panel_username"
                value={form.panel_username}
                onChange={(e) => setForm((current) => ({ ...current, panel_username: e.target.value }))}
                dir="ltr"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="panel_password">{t("fieldPassword")}</Label>
              <Input
                id="panel_password"
                type="password"
                value={form.panel_password}
                onChange={(e) => setForm((current) => ({ ...current, panel_password: e.target.value }))}
                placeholder={mode === "edit" ? t("passwordKeep") : ""}
                dir="ltr"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="panel_login_secret">{t("fieldLoginSecret")}</Label>
              <Input
                id="panel_login_secret"
                type="password"
                value={form.panel_login_secret}
                onChange={(e) => setForm((current) => ({ ...current, panel_login_secret: e.target.value }))}
                dir="ltr"
              />
            </div>

            {form.panel_provider === "xui" ? (
              <div className="space-y-2">
                <Label htmlFor="panel_api_token">{t("fieldApiToken")}</Label>
                <Input
                  id="panel_api_token"
                  type="password"
                  value={form.panel_api_token}
                  onChange={(e) => setForm((current) => ({ ...current, panel_api_token: e.target.value }))}
                  placeholder={mode === "edit" ? t("apiTokenKeep") : ""}
                  dir="ltr"
                />
              </div>
            ) : null}

            <div className="space-y-2">
              <Label htmlFor="panel_api_base">{t("fieldApiBase")}</Label>
              <Input
                id="panel_api_base"
                value={form.panel_api_base}
                onChange={(e) => setForm((current) => ({ ...current, panel_api_base: e.target.value }))}
                dir="ltr"
                disabled={form.panel_provider === "pasarguard"}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="panel_subscription_public_base">{t("fieldSubBase")}</Label>
              <Input
                id="panel_subscription_public_base"
                value={form.subscription_public_base}
                onChange={(e) =>
                  setForm((current) => ({ ...current, subscription_public_base: e.target.value }))
                }
                dir="ltr"
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="panel_sort_order">{t("fieldSort")}</Label>
                <Input
                  id="panel_sort_order"
                  type="number"
                  value={form.sort_order}
                  onChange={(e) => setForm((current) => ({ ...current, sort_order: num(e.target.value) }))}
                />
              </div>
              <div className="flex items-end justify-between gap-3 rounded-md border p-3">
                <div className="space-y-1">
                  <Label htmlFor="panel_active">{t("fieldActive")}</Label>
                  <p className="text-xs text-muted-foreground">{t("statusActive")} / {t("statusInactive")}</p>
                </div>
                <input
                  id="panel_active"
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.active}
                  onChange={(e) => setForm((current) => ({ ...current, active: e.target.checked }))}
                />
              </div>
            </div>

            {form.panel_provider === "pasarguard" ? (
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.panel_template_required}
                  onChange={(e) =>
                    setForm((current) => ({ ...current, panel_template_required: e.target.checked }))
                  }
                />
                {t("fieldTemplateRequired")}
              </label>
            ) : null}

            <div className="flex flex-wrap gap-2">
              <Button type="button" disabled={saving} onClick={() => void savePanel()}>
                {t("save")}
              </Button>
              <Button
                type="button"
                variant="outline"
                disabled={saving}
                onClick={openAdd}
              >
                {t("cancel")}
              </Button>
            </div>
          </CardContent>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("title")}</CardTitle>
              <CardDescription>{t("subtitle")}</CardDescription>
            </CardHeader>
            <CardContent>
              {panels.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t("empty")}</p>
              ) : (
                <div className="rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{t("colId")}</TableHead>
                        <TableHead>{t("colLabel")}</TableHead>
                        <TableHead>{t("colProvider")}</TableHead>
                        <TableHead>{t("colUrl")}</TableHead>
                        <TableHead>{t("colApiBase")}</TableHead>
                        <TableHead>{t("colActive")}</TableHead>
                        <TableHead>{t("colActions")}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {panels.map((row) => {
                        const panelId = num(row.id)
                        const active = bool(row.active)
                        return (
                          <TableRow key={panelId}>
                            <TableCell className="tabular-nums" dir="ltr">
                              #{panelId}
                            </TableCell>
                            <TableCell className="max-w-[16rem] truncate">{String(row.label ?? "")}</TableCell>
                            <TableCell>
                              <Badge variant={panelBadgeVariant(row)}>{panelBadgeLabel(row, t)}</Badge>
                            </TableCell>
                            <TableCell className="max-w-[16rem] break-all text-xs" dir="ltr">
                              {String(row.panel_url ?? "—")}
                            </TableCell>
                            <TableCell className="truncate font-mono text-xs" dir="ltr">
                              {String(row.panel_api_base ?? "panel/api")}
                            </TableCell>
                            <TableCell>
                              <Badge variant={active ? "default" : "secondary"}>
                                {active ? t("statusActive") : t("statusInactive")}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              <DropdownMenu>
                                <DropdownMenuTrigger disabled={busyId === panelId || repairBusyId === panelId}>
                                  <Button type="button" size="icon" variant="ghost" className="size-8">
                                    <EllipsisVertical className="size-4" />
                                  </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align={isFa ? "start" : "end"}>
                                  <DropdownMenuItem onClick={() => void testConnection(panelId)}>
                                    {t("testConnection")}
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => void repairIdentities(panelId)}>
                                    {repairBusyId === panelId ? t("repairRunning") : t("repairIdentities")}
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => openEdit(row)}>{t("edit")}</DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => openEconomics(row)}>
                                    {tEco("menuItem")}
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => setMergePanel(row)}>
                                    {t("deleteMergeInstead")}
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => void toggleActive(row)}>
                                    {active ? t("toggleDeactivate") : t("toggleActivate")}
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    className="text-destructive"
                                    onClick={() => setDeleteTarget(row)}
                                  >
                                    {t("delete")}
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </TableCell>
                          </TableRow>
                        )
                      })}
                    </TableBody>
                  </Table>
                </div>
              )}
            </CardContent>
          </Card>

          <DataPagination
            meta={pagination}
            onPageChange={setPage}
            onPerPageChange={(n) => {
              setPerPage(n)
              setPage(1)
            }}
            perPageOptions={[20, 25, 50, 100]}
          />

          {repairMsg ? <p className="text-sm text-muted-foreground">{repairMsg}</p> : null}
        </div>
      </div>
      <PanelMergeDialog
        open={mergePanel !== null}
        sourcePanel={mergePanel}
        panels={panels}
        onOpenChange={(open) => {
          if (!open) setMergePanel(null)
        }}
        onCompleted={() => void load(page)}
      />

      <Dialog open={testOpen} onOpenChange={setTestOpen}>
        <DashDialogContent className="max-w-lg">
          <DashDialogHeader>
            <DialogTitle>{t("testDialogTitle", { id: formatNumber(testPanelId, isFa) })}</DialogTitle>
            <DialogDescription>{t("testDialogDesc")}</DialogDescription>
          </DashDialogHeader>
          {testLoading ? (
            <p className="text-sm text-muted-foreground">{t("testRunning")}</p>
          ) : testResult ? (
            <PanelTestResults testRes={testResult} tp={t} isFa={isFa} />
          ) : null}
          <DashDialogFooter>
            <Button type="button" variant="outline" onClick={() => setTestOpen(false)}>
              {t("cancel")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={Boolean(deleteTarget)} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DashDialogContent>
          <DashDialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>{t("deleteDesc")}</DialogDescription>
          </DashDialogHeader>
          <DashDialogFooter className="gap-2">
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {t("cancel")}
            </Button>
            <Button type="button" variant="destructive" disabled={saving} onClick={() => void hardDeletePanel()}>
              {t("delete")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <DashboardPanelEconomicsSheet
        open={economicsOpen}
        onOpenChange={setEconomicsOpen}
        panelId={economicsPanelId}
        panelLabel={economicsPanelLabel}
        entry={economicsEntry}
        globalConfig={globalEconomicsConfig}
        sharedLines={sharedLines}
        siteVolumeGb={siteVolumeGb}
        onSaved={({ panelId, entry }) => {
          setLocalEconomicsMap((m) => ({ ...m, [String(panelId)]: entry }))
          void load(page)
        }}
      />
    </div>
  )
}
