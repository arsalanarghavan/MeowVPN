"use client"

import { useTranslations } from "next-intl"
import { useAdminTabState } from "@/hooks/use-admin-tab-state"
import { useDashboardShellOptional } from "@/components/dashboard-shell-provider"

import { useEffect, useMemo, useRef, useState } from "react"
import { KeyRound, LayoutDashboard, Link2, LogIn, Package, Search, Settings2, ShieldCheck } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import { DashPage } from "@/components/dash-page"
import { dashActionsClass } from "@/lib/dash-locale"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { DataPagination } from "@/components/data-pagination"

const RESELLERS_TABLE_COLS = ["8%", "14%", "12%", "14%", "8%", "8%"]
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { DashSelect } from "@/components/dash-select"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>

function n(v: unknown): number {
  const x = Number(v)
  return Number.isFinite(x) ? x : 0
}

function bool(v: unknown): boolean {
  return v === true || v === 1 || v === "1"
}

const PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
const ARABIC_DIGITS = "٠١٢٣٤٥٦٧٨٩"

/** Normalize Persian/Arabic digits and separators for parseFloat. */
function normalizeNumericInput(raw: string): string {
  let s = String(raw)
    .trim()
    .replace(/[\u066C\u060C,]/g, "")
    .replace(/[\u066B\u06DF]/g, ".")
  for (let i = 0; i < 10; i++) {
    const d = String(i)
    s = s.split(PERSIAN_DIGITS[i]!).join(d)
    s = s.split(ARABIC_DIGITS[i]!).join(d)
  }
  return s.replace(/,/g, ".")
}

function parsePricePerGbToman(raw: string): number {
  const s = normalizeNumericInput(raw)
  if (!s) return 0
  const x = parseFloat(s)
  return Number.isFinite(x) ? x : 0
}

/** Show whole toman in inputs when the stored value is an integer (avoid 190000.0000). */
function formatTomanInputFromStored(raw: unknown): string {
  const s = String(raw ?? "").trim()
  if (!s) return ""
  const x = parsePricePerGbToman(s)
  if (!Number.isFinite(x) || x < 0) return s
  if (Math.abs(x - Math.round(x)) < 1e-6) return String(Math.round(x))
  const rounded = Math.round(x * 100) / 100
  return String(rounded)
}

function displayName(u: DashRecord): string {
  const name = `${String(u.first_name ?? "").trim()} ${String(u.last_name ?? "").trim()}`.trim()
  return name || String(u.username ?? "").trim() || "—"
}

const USER_STATUS_KEYS = new Set(["pending", "approved", "rejected", "blocked"])

function statusBadgeVariant(st: string): "default" | "secondary" | "destructive" | "outline" {
  const s = st.toLowerCase()
  if (s === "approved") return "default"
  if (s === "pending") return "secondary"
  if (s === "rejected") return "destructive"
  if (s === "blocked") return "outline"
  return "outline"
}

function resellerStatusLabel(tUsers: (k: string) => string, raw: unknown): string {
  const st = String(raw ?? "").trim().toLowerCase()
  if (USER_STATUS_KEYS.has(st)) {
    return tUsers(`status_${st}`)
  }
  return String(raw ?? "").trim() || "—"
}

type PanelPriceRow = {
  panel_id: number
  price_per_gb: string
  panel_access: boolean
}

/** Matches backend: access if explicit allow or positive wholesale (legacy rows). */
function panelAllowedFromStoredRow(ex: Record<string, unknown> | undefined): boolean {
  if (!ex) return false
  const raw = ex.panel_access
  const acc = raw === true || raw === 1 || raw === "1"
  const price = parsePricePerGbToman(String(ex.price_per_gb ?? ""))
  return acc || price > 0
}

function isDigitOnlyQuery(raw: string): boolean {
  const t = raw.replace(/\s/g, "")
  if (!t) return false
  return /^[\d۰-۹٠-٩]+$/.test(t)
}

export function ResellersAdminView({
  rows,
  panels,
  resellerPermissionsMap,
  resellerPanelPricesMap,
  wholesaleCatalogByPanel = {},
  wholesaleLinesCatalog = [],
  resellerWholesaleLineIdsMap = {},
  resellerBotMap = {},
  resellersSearchQuery = "",
  resellersStatusFilter = "all",
  onResellersFiltersChange,
  canManageResellerControls = true,
  canCreateSubReseller = false,
  canViewResellerControls = canManageResellerControls,
  canManagePanelPrices = canManageResellerControls,
  actorIsReseller = false,
  actorUserId = 0,
  pagination,
  onPageChange,
  onPerPageChange,
  onOpenUserDetail,
  onOpenWorkspace,
  onMutateSuccess,
  onImpersonateReseller,
}: {
  rows: DashRecord[]
  panels: DashRecord[]
  resellersSearchQuery?: string
  resellersStatusFilter?: string
  onResellersFiltersChange?: (patch: { q?: string; status?: string }) => void
  resellerPermissionsMap?: Record<string, Record<string, boolean> | undefined>
  resellerPanelPricesMap?: Record<string, Array<Record<string, unknown>> | undefined>
  wholesaleCatalogByPanel?: Record<string, { price_per_gb?: number; wholesale_line_label?: string }>
  wholesaleLinesCatalog?: DashRecord[]
  resellerWholesaleLineIdsMap?: Record<string, number[]>
  resellerBotMap?: Record<string, { enabled?: boolean; brand?: string } | undefined>
  canManageResellerControls?: boolean
  canCreateSubReseller?: boolean
  canViewResellerControls?: boolean
  canManagePanelPrices?: boolean
  actorIsReseller?: boolean
  actorUserId?: number
pagination: PaginationMeta | null
  onPageChange: (p: number) => void
  onPerPageChange: (n: number) => void
  onOpenUserDetail: (id: number) => void
  onOpenWorkspace?: (id: number) => void
  onMutateSuccess?: () => void
  onImpersonateReseller?: (id: number) => void
}) {
  const { isFa } = useDashLocale()

  const t = useTranslations("resellersAdmin")
  const tUsers = useTranslations("usersAdmin")
  const tp = (k: string, opts?: Record<string, string | number>) => t(`${k}`, opts)
  const isResellerActor = actorIsReseller
  const actorUid = actorUserId > 0 ? actorUserId : 0

  function canManagePanelPriceForReseller(_rid: number, row: DashRecord): boolean {
    if (!canManagePanelPrices) return false
    if (isResellerActor) {
      if (actorUid < 1 || n(row.invited_by) !== actorUid) return false
      if (panels.length > 0) return true
      return Object.values(resellerPanelPricesMap ?? {}).some(
        (prows) => Array.isArray(prows) && prows.length > 0
      )
    }
    return panels.length >= 1
  }
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState("")
  const [panelPriceErr, setPanelPriceErr] = useState("")
  const [panelPriceNotice, setPanelPriceNotice] = useState("")
  const [createOpen, setCreateOpen] = useState(false)
  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    username: "",
    dashboard_password: "",
    phone: "",
    tg_user_id: "",
    bale_user_id: "",
  })
  const [priceResellerId, setPriceResellerId] = useState<number | null>(null)
  const [priceRows, setPriceRows] = useState<PanelPriceRow[]>([])
  const [permResellerId, setPermResellerId] = useState<number | null>(null)
  const [permissions, setPermissions] = useState<Record<string, boolean>>({})
  const [searchDraft, setSearchDraft] = useState(resellersSearchQuery)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const [wholesaleAssignId, setWholesaleAssignId] = useState<number | null>(null)
  const [wholesaleSelectedIds, setWholesaleSelectedIds] = useState<number[]>([])
  const [wholesaleErr, setWholesaleErr] = useState("")
  const [wpProvisionId, setWpProvisionId] = useState<number | null>(null)
  const [wpProvisionForm, setWpProvisionForm] = useState({ username: "", password: "", email: "" })
  const [wpProvisionErr, setWpProvisionErr] = useState("")

  function openWholesaleAssign(rid: number) {
    setWholesaleErr("")
    setWholesaleAssignId(rid)
    setWholesaleSelectedIds([...(resellerWholesaleLineIdsMap[String(rid)] ?? [])])
  }

  function toggleWholesaleLine(lid: number) {
    setWholesaleSelectedIds((prev) =>
      prev.includes(lid) ? prev.filter((x) => x !== lid) : [...prev, lid]
    )
  }

  async function saveWholesaleAssign() {
    if (wholesaleAssignId == null) return
    setBusy(true)
    setWholesaleErr("")
    try {
      const res = await postAdminMutate("reseller_wholesale_lines_assign", {
        reseller_svp_user_id: wholesaleAssignId,
        line_ids: wholesaleSelectedIds,
      })
      if (!res.ok) {
        setWholesaleErr(res.message || t("createError"))
        return
      }
      setWholesaleAssignId(null)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function saveWpProvision() {
    if (wpProvisionId == null) return
    const username = wpProvisionForm.username.trim()
    const password = wpProvisionForm.password
    if (username.length < 1 || password.length < 6) {
      setWpProvisionErr(t("createError"))
      return
    }
    setBusy(true)
    setWpProvisionErr("")
    try {
      const res = await postAdminMutate("reseller_dashboard_provision", {
        svp_user_id: wpProvisionId,
        username,
        password,
        email: wpProvisionForm.email.trim(),
      })
      if (!res.ok) {
        setWpProvisionErr(res.message || t("createError"))
        return
      }
      setWpProvisionId(null)
      setWpProvisionForm({ username: "", password: "", email: "" })
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const showWholesaleAssign =
    wholesaleLinesCatalog.length > 0 && !isResellerActor && canManageResellerControls

  useEffect(() => {
    setSearchDraft(resellersSearchQuery)
  }, [resellersSearchQuery])

  useEffect(() => {
    if (!onResellersFiltersChange) return
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      const next = searchDraft.trim()
      const effective =
        next !== "" && !isDigitOnlyQuery(next) && next.length < 2 ? "" : next
      if (effective !== resellersSearchQuery.trim()) {
        onResellersFiltersChange({ q: effective })
      }
    }, 300)
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current)
    }
  }, [searchDraft, resellersSearchQuery, onResellersFiltersChange])

  const directUsersCount = useMemo(() => {
    const m = new Map<number, number>()
    for (const r of rows) {
      m.set(n(r.id), n(r.direct_users_count))
    }
    return m
  }, [rows])

  const canSubmitCreate = useMemo(() => {
    const u = form.username.trim()
    const pw = form.dashboard_password
    const hasDash = u.length > 0 && pw.length >= 6
    const hasBot = n(form.tg_user_id) > 0 || n(form.bale_user_id) > 0
    return hasDash || hasBot
  }, [form.username, form.dashboard_password, form.tg_user_id, form.bale_user_id])

  const canOpenCreate = canManageResellerControls || canCreateSubReseller
  const canSubmitCreateForm = canOpenCreate && canSubmitCreate

  function canDashboardProvisionForReseller(row: DashRecord): boolean {
    if (canManageResellerControls) return true
    if (!canCreateSubReseller || actorUid < 1) return false
    return n(row.invited_by) === actorUid
  }

  function openPriceDialog(rid: number) {
    setPanelPriceErr("")
    setPanelPriceNotice("")
    setPriceResellerId(rid)
    const existingRows = resellerPanelPricesMap?.[String(rid)] ?? []
    const existingByPanel = new Map<number, string>()
    for (const row of existingRows) {
      const pid = n(row?.panel_id)
      if (pid > 0) existingByPanel.set(pid, String(row?.price_per_gb ?? ""))
    }
    setPriceRows(
      panels.map((p) => {
        const pid = n(p.id)
        const ex = existingRows.find((x) => n(x.panel_id) === pid) as Record<string, unknown> | undefined
        if (isResellerActor) {
          return {
            panel_id: pid,
            price_per_gb: formatTomanInputFromStored(existingByPanel.get(pid) ?? ""),
            panel_access: panelAllowedFromStoredRow(ex),
          }
        }
        return {
          panel_id: pid,
          price_per_gb: formatTomanInputFromStored(existingByPanel.get(pid) ?? ""),
          panel_access: panelAllowedFromStoredRow(ex),
        }
      })
    )
  }

  async function savePrices() {
    if (priceResellerId == null) return
    setBusy(true)
    setPanelPriceErr("")
    try {
      const rows = isResellerActor
        ? priceRows
            .filter((r) => r.panel_access)
            .map((r) => ({
              panel_id: r.panel_id,
              price_per_gb: parsePricePerGbToman(String(r.price_per_gb)),
            }))
        : priceRows
            .filter((r) => r.panel_access)
            .map((r) => ({
              panel_id: r.panel_id,
              panel_access: true,
            }))
      const res = await postAdminMutate("reseller_panel_prices_save", {
        reseller_svp_user_id: priceResellerId,
        rows,
      })
      if (!res.ok) {
        setPanelPriceErr(
          res.message === "no_valid_panels" ? t("panelPricesSaveNoValidPanels") : res.message || t("createError")
        )
        return
      }
      const noticeParts: string[] = []
      const skipped = Array.isArray(res.skipped_panel_ids) ? (res.skipped_panel_ids as number[]) : []
      if (skipped.length) {
        noticeParts.push(t("panelPricesSkippedUnknownPanels", { ids: skipped.join(", ") }))
      }
      if (isResellerActor) {
        noticeParts.push(t("panelPricesParentFloorSavedHint"))
      }
      setPriceResellerId(null)
      if (noticeParts.length) setPanelPriceNotice(noticeParts.join(" "))
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  async function createReseller() {
    setBusy(true)
    setErr("")
    try {
      const payload: Record<string, unknown> = {
        role: "reseller",
        status: "approved",
        first_name: form.first_name,
        last_name: form.last_name,
        username: form.username,
        phone: form.phone,
        tg_user_id: form.tg_user_id,
        bale_user_id: form.bale_user_id,
      }
      if (form.dashboard_password.length >= 6) {
        payload.dashboard_password = form.dashboard_password
      }
      const res = await postAdminMutate("user_manual_create", payload)
      if (!res.ok) {
        setErr(res.message || t("createError"))
        return
      }
      setForm({
        first_name: "",
        last_name: "",
        username: "",
        dashboard_password: "",
        phone: "",
        tg_user_id: "",
        bale_user_id: "",
      })
      setCreateOpen(false)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const permDefs = useMemo(
    () => [
      { key: "users.manage", label: t("perm_users_manage") },
      { key: "users.bulk", label: t("perm_users_bulk") },
      { key: "broadcast.send", label: t("perm_broadcast_send") },
      { key: "receipts.review", label: t("perm_receipts_review") },
      { key: "plans.manage", label: t("perm_plans_manage") },
      { key: "services.manage", label: t("perm_services_manage") },
      { key: "marketing.lifecycle", label: t("perm_marketing_lifecycle") },
    ],
    [tp])

  async function savePermissions() {
    if (permResellerId == null) return
    setBusy(true)
    setErr("")
    try {
      const res = await postAdminMutate("reseller_permissions_save", {
        reseller_svp_user_id: permResellerId,
        permissions,
      })
      if (!res.ok) {
        setErr(res.message || t("createError"))
        return
      }
      setPermResellerId(null)
      onMutateSuccess?.()
    } finally {
      setBusy(false)
    }
  }

  const inputAlignClass = "text-start"
  const statusFilter = resellersStatusFilter || "all"

  return (
    <DashPage className={"space-y-4"} data-testid="dash-resellers-tab">
      <DashboardPageHeader
        title={t("title")}
        description={t("subtitle")}
        actions={
          canOpenCreate ? (
            <Button
              type="button"
              onClick={() => {
                setErr("")
                setCreateOpen(true)
              }}
            >
              {t("createTitle")}
            </Button>
          ) : null
        }
      />

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DashDialogContent className={cn("sm:max-w-2xl")}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{t("createTitle")}</DialogTitle>
            <DialogDescription>
              {isResellerActor ? t("subResellerCreateHint") : t("createHint")}
            </DialogDescription>
          </DashDialogHeader>
          <div className="grid gap-2 md:grid-cols-2">
            <Input
              placeholder={t("firstName")}
              className={inputAlignClass}
              value={form.first_name}
              onChange={(e) => setForm((p) => ({ ...p, first_name: e.target.value }))}
            />
            <Input
              placeholder={t("lastName")}
              className={inputAlignClass}
              value={form.last_name}
              onChange={(e) => setForm((p) => ({ ...p, last_name: e.target.value }))}
            />
            <Input
              placeholder={t("dashboardUsername")}
              dir="ltr"
              className={cn("font-mono", inputAlignClass)}
              value={form.username}
              onChange={(e) => setForm((p) => ({ ...p, username: e.target.value }))}
            />
            <Input
              placeholder={t("dashboardPassword")}
              dir="ltr"
              className={cn("font-mono", inputAlignClass)}
              type="password"
              autoComplete="new-password"
              value={form.dashboard_password}
              onChange={(e) => setForm((p) => ({ ...p, dashboard_password: e.target.value }))}
            />
            <Input
              placeholder={t("phone")}
              dir="ltr"
              className={inputAlignClass}
              value={form.phone}
              onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))}
            />
            <Input
              placeholder={t("tgUserId")}
              dir="ltr"
              className={cn("font-mono", inputAlignClass)}
              value={form.tg_user_id}
              onChange={(e) => setForm((p) => ({ ...p, tg_user_id: e.target.value }))}
            />
            <Input
              placeholder={t("baleUserId")}
              dir="ltr"
              className={cn("font-mono", inputAlignClass)}
              value={form.bale_user_id}
              onChange={(e) => setForm((p) => ({ ...p, bale_user_id: e.target.value }))}
            />
          </div>
          {err ? <p className="text-sm text-destructive">{err}</p> : null}
          <DashDialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
              {t("a11y.close")}
            </Button>
            <Button
              type="button"
              disabled={busy || !canSubmitCreateForm}
              onClick={() => void createReseller()}
            >
              {t("create")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <div className={cn("flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end")}>
        <div className="relative min-w-0 flex-1 sm:max-w-md">
          <Search className="pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground start-3" />
          <Input
            className="h-9 ps-9 text-start"
            placeholder={t("searchPlaceholder")}
            value={searchDraft}
            onChange={(e) => setSearchDraft(e.target.value)}
          />
        </div>
        <div className="flex min-w-[10rem] flex-col gap-1">
          <Label className="text-xs text-muted-foreground">{t("filterStatus")}</Label>
          <DashSelect
            value={statusFilter}
            onValueChange={(v) => onResellersFiltersChange?.({ status: v })}
            options={[
              { value: "all", label: t("filterStatusAll") },
              { value: "pending", label: tUsers("status_pending") },
              { value: "approved", label: tUsers("status_approved") },
              { value: "rejected", label: tUsers("status_rejected") },
              { value: "blocked", label: tUsers("status_blocked") },
            ]}
          />
        </div>
      </div>

      <Card>
        <CardHeader className={cn("flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between")}>
          <div className={cn("space-y-1")}>
            <CardTitle className="text-base">{t("listTitle")}</CardTitle>
            {pagination && pagination.total > 0 ? (
              <p className="text-xs text-muted-foreground">{t("listCount", { n: pagination.total })}</p>
            ) : null}
          </div>
          {panelPriceNotice ? (
            <p className="text-sm text-amber-900 dark:text-amber-100">{panelPriceNotice}</p>
          ) : null}
        </CardHeader>
        <CardContent>
          {rows.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("empty")}</p>
          ) : (
            <>
              <div className="space-y-3 md:hidden">
                {rows.map((r) => {
                  const id = n(r.id)
                  return (
                    <Card key={id}>
                      <CardContent className="space-y-3 p-4 text-sm">
                        <div className={cn("flex flex-wrap items-start justify-between gap-2")}>
                          <div className={cn("min-w-0 space-y-1")}>
                            <p className="font-medium">{displayName(r)}</p>
                            <p className="font-mono text-xs text-muted-foreground" dir="ltr">
                              #{id}
                            </p>
                          </div>
                          <Badge variant={statusBadgeVariant(String(r.status ?? ""))} className="shrink-0">
                            {resellerStatusLabel(tUsers, r.status)}
                          </Badge>
                        </div>
                        <div className={cn("grid grid-cols-2 gap-2 text-xs text-muted-foreground")}>
                          <span>{t("colUsers")}: {directUsersCount.get(id) ?? 0}</span>
                          <span>{tUsers("colPhone")}: {String(r.phone ?? "—")}</span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                          <Button type="button" variant="outline" size="icon" onClick={() => onOpenUserDetail(id)} aria-label={t("manage")}>
                            <KeyRound className="h-4 w-4" />
                          </Button>
                          <Button type="button" variant="outline" size="icon" onClick={() => onOpenWorkspace?.(id)} aria-label={isResellerActor ? t("openUserDetail") : t("sidebar.groups.resellerWorkspace")}>
                            <LayoutDashboard className="h-4 w-4" />
                          </Button>
                          {onImpersonateReseller && canManageResellerControls ? (
                            <Button
                              type="button"
                              variant="outline"
                              size="icon"
                              onClick={() => onImpersonateReseller(id)}
                              aria-label={t("impersonateReseller")}
                            >
                              <LogIn className="h-4 w-4" />
                            </Button>
                          ) : null}
                          {canDashboardProvisionForReseller(r) && !bool(r.has_dashboard_login) ? (
                            <Button
                              type="button"
                              variant="outline"
                              size="icon"
                              onClick={() => {
                                setWpProvisionErr("")
                                setWpProvisionId(id)
                                setWpProvisionForm({
                                  username: String(r.username ?? "").replace(/^@/, ""),
                                  password: "",
                                  email: "",
                                })
                              }}
                              aria-label={t("wpProvision")}
                            >
                              <Link2 className="h-4 w-4" />
                            </Button>
                          ) : null}
                          <Button type="button" variant="outline" size="icon" onClick={() => openPriceDialog(id)} disabled={!canManagePanelPriceForReseller(id, r)} aria-label={t("panelPrices")}>
                            <Settings2 className="h-4 w-4" />
                          </Button>
                          {showWholesaleAssign ? (
                            <Button
                              type="button"
                              variant="outline"
                              size="icon"
                              onClick={() => openWholesaleAssign(id)}
                              aria-label={t("wholesaleLinesAssign")}
                            >
                              <Package className="h-4 w-4" />
                            </Button>
                          ) : null}
                          <Button type="button" variant="outline" size="icon" onClick={() => { setPermResellerId(id); setPermissions({ ...(resellerPermissionsMap?.[String(id)] ?? {}) }) }} disabled={!canViewResellerControls} aria-label={t("permissionsColumn")}>
                            <ShieldCheck className="h-4 w-4" />
                          </Button>
                        </div>
                      </CardContent>
                    </Card>
                  )
                })}
              </div>
              <div className="hidden md:block">
                <DashTableShell
        minWidth="44rem" colWidths={RESELLERS_TABLE_COLS}>
                  <thead>
                    <tr className="bg-muted/40">
                      <DashTh>{t("colId")}</DashTh>
                      <DashTh>{t("colName")}</DashTh>
                      <DashTh>{t("colStatus")}</DashTh>
                      <DashTh>{t("colBot")}</DashTh>
                      <DashTh>{t("colUsers")}</DashTh>
                      <DashTh>{t("colActions")}</DashTh>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((r) => {
                      const id = n(r.id)
                      const botInfo = resellerBotMap?.[String(id)]
                      const botLabel = botInfo?.enabled
                        ? botInfo.brand?.trim() || t("botEnabledShort")
                        : t("botDisabledShort")
                      return (
                        <tr key={id}>
                          <DashTd dir="ltr" className="font-mono">
                            {id}
                          </DashTd>
                          <DashTd>
                            <div className="space-y-0.5">
                              <div className="truncate">{displayName(r)}</div>
                              <div className="truncate text-xs text-muted-foreground">{String(r.phone ?? "—")}</div>
                            </div>
                          </DashTd>
                          <DashTd>
                            <Badge variant={statusBadgeVariant(String(r.status ?? ""))} className="font-normal">
                              {resellerStatusLabel(tUsers, r.status)}
                            </Badge>
                          </DashTd>
                          <DashTd className="text-xs text-muted-foreground">
                            <span className="line-clamp-2">{botLabel}</span>
                          </DashTd>
                          <DashTd className="tabular-nums">{directUsersCount.get(id) ?? 0}</DashTd>
                          <DashTd>
                            <TooltipProvider>
                              <div className={dashActionsClass("gap-1")}>
                                <Tooltip><TooltipTrigger><Button type="button" variant="ghost" size="icon" onClick={() => onOpenUserDetail(id)}><KeyRound className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{t("manage")}</TooltipContent></Tooltip>
                                <Tooltip><TooltipTrigger><Button type="button" variant="ghost" size="icon" onClick={() => onOpenWorkspace?.(id)}><LayoutDashboard className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{isResellerActor ? t("openUserDetail") : t("sidebar.groups.resellerWorkspace")}</TooltipContent></Tooltip>
                                {onImpersonateReseller && canManageResellerControls ? (
                                  <Tooltip>
                                    <TooltipTrigger
              render={
                <Button type="button" variant="ghost" size="icon" onClick={() => onImpersonateReseller(id)}>
                                        <LogIn className="h-4 w-4" />
                                      </Button>
              }
            />
                                    <TooltipContent>{t("impersonateReseller")}</TooltipContent>
                                  </Tooltip>
                                ) : null}
                                {canDashboardProvisionForReseller(r) && !bool(r.has_dashboard_login) ? (
                                  <Tooltip>
                                    <TooltipTrigger
              render={
                <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => {
                                          setWpProvisionErr("")
                                          setWpProvisionId(id)
                                          setWpProvisionForm({
                                            username: String(r.username ?? "").replace(/^@/, ""),
                                            password: "",
                                            email: "",
                                          })
                                        }}
                                      >
                                        <Link2 className="h-4 w-4" />
                                      </Button>
              }
            />
                                    <TooltipContent>{t("wpProvision")}</TooltipContent>
                                  </Tooltip>
                                ) : null}
                                <Tooltip><TooltipTrigger><Button type="button" variant="ghost" size="icon" onClick={() => openPriceDialog(id)} disabled={!canManagePanelPriceForReseller(id, r)}><Settings2 className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{t("panelPrices")}</TooltipContent></Tooltip>
                                {showWholesaleAssign ? (
                                  <Tooltip>
                                    <TooltipTrigger
              render={
                <Button type="button" variant="ghost" size="icon" onClick={() => openWholesaleAssign(id)}>
                                        <Package className="h-4 w-4" />
                                      </Button>
              }
            />
                                    <TooltipContent>{t("wholesaleLinesAssign")}</TooltipContent>
                                  </Tooltip>
                                ) : null}
                                <Tooltip><TooltipTrigger><Button type="button" variant="ghost" size="icon" onClick={() => { setPermResellerId(id); setPermissions({ ...(resellerPermissionsMap?.[String(id)] ?? {}) }) }} disabled={!canViewResellerControls}><ShieldCheck className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{t("permissionsColumn")}</TooltipContent></Tooltip>
                              </div>
                            </TooltipProvider>
                          </DashTd>
                        </tr>
                      )
                    })}
                  </tbody>
                </DashTableShell>
              </div>
            </>
          )}
          <DataPagination
            meta={pagination}
        onPageChange={onPageChange}
            onPerPageChange={onPerPageChange}
            perPageOptions={[25, 50, 100, 150, 200]}
          />
        </CardContent>
      </Card>

      <Dialog
        open={priceResellerId != null}
        onOpenChange={(o) => {
          if (!o) {
            setPriceResellerId(null)
            setPanelPriceErr("")
          }
        }}
      >
        <DashDialogContent className={cn("sm:max-w-2xl")}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle className={cn("flex items-center gap-2")}>
              <span>{t("panelPricesTitle")}</span>
              {isResellerActor ? (
                <Badge variant="secondary" className="font-normal">
                  {t("panelPricesParentFloorBadge")}
                </Badge>
              ) : null}
            </DialogTitle>
            <DialogDescription className="space-y-2">
              <span>
                {t(
                  isResellerActor
                    ? "resellersAdmin.panelPricesDialogDescriptionParentFloor"
                    : "resellersAdmin.panelPricesDialogDescription",
                  { id: priceResellerId ?? 0 }
                )}
              </span>
              <span className="block text-muted-foreground">
                {t(
                  isResellerActor
                    ? "resellersAdmin.panelPricesDialogHintParentFloor"
                    : "resellersAdmin.panelPricesDialogHintAdmin"
                )}
              </span>
              {isResellerActor ? (
                <span className="block rounded-md border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-muted-foreground">
                  {t("panelPricesParentCatalogNote")}
                </span>
              ) : null}
            </DialogDescription>
          </DashDialogHeader>
          {panelPriceErr ? (
            <p className="text-sm text-destructive">{panelPriceErr}</p>
          ) : null}
          <div className="grid gap-2 py-2">
            {priceRows.map((row, idx) => {
              const pl = panels.find((p) => n(p.id) === row.panel_id)
              const label = String(pl?.label ?? pl?.name ?? `Panel ${row.panel_id}`)
              return (
                <div
                  key={row.panel_id}
                  className={cn(
                    "flex flex-col gap-2 rounded-md border border-border/60 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                  )}
                >
                  <span className="min-w-0 flex-1 text-sm font-medium leading-snug">{label}</span>
                  <div className={cn("flex flex-wrap items-center gap-3")}>
                    <Switch
                      id={`panel-access-${row.panel_id}`}
                      checked={priceRows[idx]?.panel_access ?? false}
                      onCheckedChange={(checked) => {
                        setPriceRows((prev) => prev.map((r, i) => (i === idx ? { ...r, panel_access: checked } : r)))
                      }}
                      aria-label={t("panelAccessToggleAria", { label })}
                    />
                    {(priceRows[idx]?.panel_access ?? false) ? (
                      isResellerActor ? (
                        <div className={cn("flex min-w-[9rem] flex-col gap-1", isFa && "items-end")}>
                          <Label htmlFor={`panel-price-${row.panel_id}`} className="text-xs text-muted-foreground">
                            {t("plansAdmin.pricePerGb")}
                          </Label>
                          <Input
                            id={`panel-price-${row.panel_id}`}
                            type="text"
                            inputMode="decimal"
                            className={cn("h-8 w-full max-w-[10rem]")}
                            value={priceRows[idx]?.price_per_gb ?? ""}
                            onChange={(e) => {
                              const v = e.target.value
                              setPriceRows((prev) =>
                                prev.map((r, i) => (i === idx ? { ...r, price_per_gb: v } : r))
                              )
                            }}
                            placeholder={t("panelPricesIncludePanelFloor")}
                            disabled={busy}
                          />
                        </div>
                      ) : (
                        <div className="min-w-0 max-w-[14rem] space-y-0.5 text-xs text-muted-foreground">
                          {(() => {
                            const cat = wholesaleCatalogByPanel[String(row.panel_id)] ?? wholesaleCatalogByPanel[row.panel_id]
                            const catPrice = parsePricePerGbToman(String(cat?.price_per_gb ?? ""))
                            const storedPrice = parsePricePerGbToman(String(priceRows[idx]?.price_per_gb ?? ""))
                            const showPrice = catPrice > 0 ? catPrice : storedPrice
                            if (showPrice > 0) {
                              return (
                                <>
                                  <p>{t("panelPricesCatalogWholesale", { price: formatTomanInputFromStored(showPrice) })}</p>
                                  {cat?.wholesale_line_label ? (
                                    <p>{t("panelPricesCatalogLine", { label: String(cat.wholesale_line_label) })}</p>
                                  ) : null}
                                </>
                              )
                            }
                            return <p className="text-amber-700 dark:text-amber-300">{t("panelPricesCatalogWholesaleMissing")}</p>
                          })()}
                        </div>
                      )
                    ) : null}
                  </div>
                </div>
              )
            })}
          </div>
          <DashDialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setPriceResellerId(null)}>
              {t("a11y.close")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void savePrices()}>
              {t("panelPricesSave")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
      <Dialog
        open={wholesaleAssignId != null}
        onOpenChange={(o) => {
          if (!o) {
            setWholesaleAssignId(null)
            setWholesaleErr("")
          }
        }}
      >
        <DashDialogContent className={cn("sm:max-w-lg")}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{t("wholesaleLinesDialogTitle", { id: wholesaleAssignId ?? 0 })}</DialogTitle>
            <DialogDescription>{t("wholesaleLinesAssignHint")}</DialogDescription>
          </DashDialogHeader>
          {wholesaleErr ? <p className="text-sm text-destructive">{wholesaleErr}</p> : null}
          <div className="grid gap-2 py-2">
            {wholesaleLinesCatalog.map((ln) => {
              const lid = n(ln.id)
              if (lid < 1) return null
              const checked = wholesaleSelectedIds.includes(lid)
              return (
                <label
                  key={lid}
                  className={cn("flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm")}
                >
                  <input type="checkbox" checked={checked} onChange={() => toggleWholesaleLine(lid)} />
                  <span
                    className="h-2 w-6 shrink-0 rounded-full"
                    style={{ backgroundColor: String(ln.badge_color ?? "#6366f1") }}
                  />
                  <span className="min-w-0 flex-1">{String(ln.label ?? `#${lid}`)}</span>
                </label>
              )
            })}
          </div>
          <DashDialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setWholesaleAssignId(null)}>
              {t("a11y.close")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void saveWholesaleAssign()}>
              {t("wholesaleLinesSave")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
      <Dialog open={permResellerId != null} onOpenChange={(o) => !o && setPermResellerId(null)}>
        <DashDialogContent className={cn("sm:max-w-lg")}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{t("permissionsDialogTitle")}</DialogTitle>
          </DashDialogHeader>
          {!canManageResellerControls ? (
            <p className="text-sm text-muted-foreground">{t("permissionsReadOnlyHint")}</p>
          ) : null}
          <div className="grid gap-2 py-2">
            {permDefs.map((p) => (
              <label key={p.key} className={cn("flex items-center gap-2 text-sm")}>
                <input
                  type="checkbox"
                  checked={permissions[p.key] !== false}
                  disabled={!canManageResellerControls}
                  onChange={(e) => setPermissions((prev) => ({ ...prev, [p.key]: e.target.checked }))}
                />
                {p.label}
              </label>
            ))}
          </div>
          <DashDialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setPermResellerId(null)}>
              {t("a11y.close")}
            </Button>
            {canManageResellerControls ? (
              <Button type="button" disabled={busy} onClick={() => void savePermissions()}>
                {t("permissionsSave")}
              </Button>
            ) : null}
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
      <Dialog
        open={wpProvisionId != null}
        onOpenChange={(o) => {
          if (!o) {
            setWpProvisionId(null)
            setWpProvisionErr("")
          }
        }}
      >
        <DashDialogContent className={cn("sm:max-w-md")}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{t("wpProvisionTitle", { id: wpProvisionId ?? 0 })}</DialogTitle>
          </DashDialogHeader>
          {wpProvisionErr ? <p className="text-sm text-destructive">{wpProvisionErr}</p> : null}
          <div className="grid gap-3 py-2">
            <div className="space-y-2">
              <Label htmlFor="wp-provision-username">{t("wpProvisionUsername")}</Label>
              <Input
                id="wp-provision-username"
                dir="ltr"
                value={wpProvisionForm.username}
                onChange={(e) => setWpProvisionForm((f) => ({ ...f, username: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="wp-provision-password">{t("wpProvisionPassword")}</Label>
              <Input
                id="wp-provision-password"
                type="password"
                dir="ltr"
                value={wpProvisionForm.password}
                onChange={(e) => setWpProvisionForm((f) => ({ ...f, password: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="wp-provision-email">{t("wpProvisionEmail")}</Label>
              <Input
                id="wp-provision-email"
                type="email"
                dir="ltr"
                value={wpProvisionForm.email}
                onChange={(e) => setWpProvisionForm((f) => ({ ...f, email: e.target.value }))}
              />
            </div>
          </div>
          <DashDialogFooter className={cn("gap-2")}>
            <Button type="button" variant="outline" onClick={() => setWpProvisionId(null)}>
              {t("a11y.close")}
            </Button>
            <Button type="button" disabled={busy} onClick={() => void saveWpProvision()}>
              {t("wpProvisionSave")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}

export function ResellersAdminClient() {
  const { data, loading, error, reload, setPage, setPer, pickPagination, rows, patchQuery, listQuery, isReseller, actorPermissions } =
    useAdminTabState("resellers", { resellers_status: "all" })
  const t = useTranslations("resellersAdmin")
  const shell = useDashboardShellOptional()
  if (loading && rows(data.resellers).length === 0) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (error) return <p className="text-sm text-destructive">{t("loadError")}</p>
  const canCreateSub =
    isReseller && (actorPermissions?.["users.manage"] === true || actorPermissions?.["resellers.manage"] === true)
  return (
    <ResellersAdminView
      rows={rows(data.resellers)}
      panels={rows(data.panels)}
      resellerPermissionsMap={(data.resellerPermissionsMap as Record<string, Record<string, boolean>>) ?? {}}
      resellerPanelPricesMap={(data.resellerPanelPricesMap as Record<string, Array<Record<string, unknown>>>) ?? {}}
      wholesaleCatalogByPanel={(data.wholesaleCatalogByPanel as Record<string, { price_per_gb?: number; wholesale_line_label?: string }>) ?? {}}
      wholesaleLinesCatalog={rows(data.wholesaleLinesCatalog)}
      resellerWholesaleLineIdsMap={(data.resellerWholesaleLineIdsMap as Record<string, number[]>) ?? {}}
      resellerBotMap={(data.resellerBotMap as Record<string, { enabled?: boolean; brand?: string }>) ?? {}}
      resellersSearchQuery={listQuery.resellers_q ?? ""}
      resellersStatusFilter={listQuery.resellers_status ?? "all"}
      onResellersFiltersChange={(patch) => {
        const next: Record<string, string> = {}
        if (patch.q !== undefined) next.resellers_q = patch.q
        if (patch.status !== undefined) next.resellers_status = patch.status
        patchQuery(next)
      }}
      pagination={pickPagination("resellers")}
      canManageResellerControls={!isReseller}
      canCreateSubReseller={canCreateSub}
      actorIsReseller={isReseller}
      actorUserId={Number(data.actorSvpUserId ?? 0)}
      onPageChange={(p) => setPage("resellers", p)}
      onPerPageChange={(n) => setPer("resellers", n)}
      onOpenUserDetail={shell?.openUserDetail ?? (() => {})}
      onOpenWorkspace={shell?.openResellerWorkspace}
      onImpersonateReseller={shell?.onImpersonateReseller}
      onMutateSuccess={reload}
    />
  )
}
