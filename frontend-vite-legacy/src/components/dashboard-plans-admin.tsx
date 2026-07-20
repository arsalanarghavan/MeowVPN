"use client"

import {
  BadgeDollarSign,
  Clock,
  EllipsisVerticalIcon,
  HardDrive,
  Radio,
  Server,
  Settings2,
  Tag,
  Users,
} from "lucide-react"
import { useCallback, useEffect, useMemo, useState, type ComponentType, type ReactNode } from "react"
import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { DashboardWholesaleLinesAdmin } from "@/components/dashboard-wholesale-lines-admin"
import { DashPage } from "@/components/dash-page"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Sheet,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { DashSheetContent } from "@/components/dash-sheet-content"
import { DataPagination } from "@/components/data-pagination"
import { postAdminMutate } from "@/lib/dash-admin-mutate"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { DashSelect } from "@/components/dash-select"
import { formatNumber } from "@/lib/format-locale"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  type PlansView,
  isPlansView,
  readPlansViewFromUrl,
  writePlansViewToUrl,
} from "@/lib/plans-subview"

type DashRecord = Record<string, unknown>

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function panelCanSellPlan(p: DashRecord, resellerMode: boolean): boolean {
  if (!resellerMode) return true
  if (p.can_sell_plan === false) return false
  return true
}

function panelLabel(panels: DashRecord[], panelId: number): string {
  const row = panels.find((p) => num(p.id) === panelId)
  return String(row?.label ?? row?.name ?? `#${panelId}`)
}

function formatClientCap(v: unknown, unlimited: string): string {
  const { isFa } = useDashLocale()

  const n = num(v)
  return n < 1 ? unlimited : formatNumber(n, isFa)
}

function PlanInfoRow({
  icon: Icon,
  label,
  value,
}: {
  icon: ComponentType<{ className?: string }>
  label: string
  value: ReactNode
}) {
  return (
    <div className="flex min-w-0 gap-2 rounded-md border border-border/60 bg-muted/20 p-2 text-xs">
      <Icon className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" aria-hidden />
      <div className="min-w-0 flex-1">
        <p className="text-muted-foreground">{label}</p>
        <div className="truncate font-medium text-foreground">{value}</div>
      </div>
    </div>
  )
}

type PlanFormState = {
  plan_id: number
  name: string
  category: string
  plan_panel_id: number
  owner_svp_user_id: number
  traffic_gb: number
  price: number
  plan_pricing_type: "fixed" | "per_gb"
  price_per_gb: number
  traffic_gb_min: number
  traffic_gb_max: number
  duration_days: number
  clients_count: number
  inbound_id: number
  service_type: "xray" | "l2tp"
  l2tp_server_id: number
  sort_order: number
  plan_active: boolean
  wholesale_line_id: number
}

function emptyForm(defaultPanelId: number, defaultCategory: string, ownerId = 0): PlanFormState {
  return {
    plan_id: 0,
    name: "",
    category: defaultCategory,
    plan_panel_id: defaultPanelId,
    owner_svp_user_id: ownerId,
    traffic_gb: 0,
    price: 0,
    plan_pricing_type: "fixed",
    price_per_gb: 0,
    traffic_gb_min: 1,
    traffic_gb_max: 100,
    duration_days: 30,
    clients_count: 1,
    inbound_id: 0,
    service_type: "xray",
    l2tp_server_id: 0,
    sort_order: 0,
    plan_active: true,
    wholesale_line_id: 0,
  }
}

function formFromPlan(p: DashRecord): PlanFormState {
  return {
    plan_id: num(p.id),
    name: String(p.name ?? ""),
    category: String(p.category ?? ""),
    plan_panel_id: num(p.panel_id) || 1,
    owner_svp_user_id: num(p.owner_svp_user_id),
    traffic_gb: num(p.traffic_gb),
    price: num(p.price),
    plan_pricing_type: p.pricing_type === "per_gb" ? "per_gb" : "fixed",
    price_per_gb: num(p.price_per_gb),
    traffic_gb_min: num(p.traffic_gb_min),
    traffic_gb_max: num(p.traffic_gb_max),
    duration_days: num(p.duration_days),
    clients_count: Math.max(0, num(p.clients_count)),
    inbound_id: num(p.inbound_id),
    service_type: p.service_type === "l2tp" ? "l2tp" : "xray",
    l2tp_server_id: num(p.l2tp_server_id),
    sort_order: num(p.sort_order),
    plan_active: p.active === true || p.active === 1 || p.active === "1",
    wholesale_line_id: num(p.wholesale_line_id),
  }
}

function formToPayload(f: PlanFormState): Record<string, unknown> {
  return {
    name: f.name,
    category: f.category,
    plan_panel_id: f.plan_panel_id,
    owner_svp_user_id: f.owner_svp_user_id,
    traffic_gb: f.traffic_gb,
    price: f.price,
    plan_pricing_type: f.plan_pricing_type,
    price_per_gb: f.price_per_gb,
    traffic_gb_min: f.traffic_gb_min,
    traffic_gb_max: f.traffic_gb_max,
    duration_days: f.duration_days,
    clients_count: f.clients_count,
    inbound_id: f.inbound_id,
    service_type: f.service_type,
    l2tp_server_id: f.l2tp_server_id,
    sort_order: f.sort_order,
    plan_active: f.plan_active ? 1 : 0,
    ...(f.wholesale_line_id > 0 ? { wholesale_line_id: f.wholesale_line_id } : {}),
  }
}

function validateForm(
  f: PlanFormState,
  resellerMode: boolean,
  formTarget: "site" | "reseller",
  wholesaleLinesOnPanel = 0
): string | null {
  if (!f.name.trim()) return "name"
  if (!f.category.trim()) return "category"
  if (!resellerMode && formTarget === "reseller" && f.owner_svp_user_id < 1) return "reseller"
  if (!resellerMode && formTarget === "site" && f.inbound_id <= 0) return "inbound"
  if (resellerMode && f.inbound_id <= 0 && f.service_type === "xray") return "inbound"
  if (resellerMode && wholesaleLinesOnPanel > 1 && f.wholesale_line_id < 1) return "wholesale_line"
  if (f.plan_pricing_type === "fixed" && f.price <= 0) return "price"
  if (f.plan_pricing_type === "per_gb") {
    if (f.price_per_gb <= 0) return "price_per_gb"
    if (f.traffic_gb_min < 1 || f.traffic_gb_max < 1 || f.traffic_gb_min > f.traffic_gb_max)
      return "traffic_range"
  }
  return null
}

const CLIENT_VALIDATION_LOCALE: Record<string, string> = {
  name: "validationName",
  category: "validationCategory",
  reseller: "pickReseller",
  inbound: "validationInbound",
  price: "validationPrice",
  price_per_gb: "validationPricePerGb",
  traffic_range: "validationTrafficRange",
  wholesale_line: "validationWholesaleLine",
}

const SERVER_ERROR_LOCALE: Record<string, string> = {
  invalid: "errorCode_invalid",
  invalid_update: "errorCode_invalid",
  panel_not_allowed: "errorCode_panel_not_allowed",
  wholesale_line_required: "errorCode_wholesale_line_required",
  wholesale_line_not_assigned: "errorCode_wholesale_line_not_assigned",
  wholesale_line_invalid: "errorCode_wholesale_line_invalid",
  wholesale_line_no_tiers: "errorCode_wholesale_line_no_tiers",
  wholesale_line_bad: "errorCode_wholesale_line_bad",
  below_reseller_floor: "errorCode_below_reseller_floor",
  forbidden: "errorCode_forbidden",
  bad_actor: "errorCode_bad_actor",
  l2tp_forbidden_for_reseller: "errorCode_l2tp_forbidden_for_reseller",
  module_missing: "errorCode_module_missing",
}

function formatPlanMutateError(
  code: string | undefined,
  message: string | undefined,
  tp: (k: string) => string
): string {
  const c = String(code ?? "").trim()
  if (c && SERVER_ERROR_LOCALE[c]) return tp(SERVER_ERROR_LOCALE[c])
  if (c === "invalid" || c === "invalid_update") return tp("errorCode_invalid")
  const msg = String(message ?? c).trim()
  return msg ? `${tp("mutateError")}: ${msg}` : tp("mutateError")
}

/** Mirrors REST `resellerPanelAccessDiagnostics` when the reseller has zero visible panels. */
export type ResellerPanelAccessDiagnostics = {
  stored_rows?: number
  joinable_rows?: number
  orphan_panel_ids?: number[]
  inactive_row_count?: number
}

export function DashboardPlansAdmin({
  plans,
  panels,
  planCategories,
  l2tpServers = [],
  resellerChoices = [],
  resellerPlanFloors = [],
  wholesaleLinesCatalog = [],
  wholesaleLines = [],
  initialPlansView,
  resellerMode = false,
  actorSvpUserId = 0,
  panelAccessDiagnostics = null,
  pagination,
  settings,
  showCatalogDefaultsSave = true,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
}: {
  plans: DashRecord[]
  panels: DashRecord[]
  planCategories: DashRecord[]
  l2tpServers: DashRecord[]
  resellerChoices?: DashRecord[]
  resellerPlanFloors?: DashRecord[]
  wholesaleLinesCatalog?: DashRecord[]
  wholesaleLines?: DashRecord[]
  initialPlansView?: PlansView
  resellerMode?: boolean
  actorSvpUserId?: number
  panelAccessDiagnostics?: ResellerPanelAccessDiagnostics | null
  pagination: PaginationMeta | null
  settings?: DashRecord
  showCatalogDefaultsSave?: boolean
onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tp = (k: string, opts?: Record<string, string | number>) => t(`plansAdmin.${k}`, opts)

  const [plansView, setPlansView] = useState<PlansView>(() => initialPlansView ?? readPlansViewFromUrl())

  const [sitePanelFilter, setSitePanelFilter] = useState<string>("all")
  const [resellerPanelFilter, setResellerPanelFilter] = useState<string>("all")
  const [panelFilter, setPanelFilter] = useState<string>("all")
  const [sheetOpen, setSheetOpen] = useState(false)
  const [formMode, setFormMode] = useState<"add" | "edit">("add")
  const [formTarget, setFormTarget] = useState<"site" | "reseller">("site")
  const [form, setForm] = useState<PlanFormState>(() =>
    emptyForm(num(panels[0]?.id) || 1, "")
  )
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)

  const catalogInitial = useMemo(() => {
    const s = settings ?? {}
    return {
      default_concurrent_users: String(Math.max(0, Math.trunc(num(s.default_concurrent_users)) || 2)),
      price_per_extra_user: String(s.price_per_extra_user ?? "0"),
    }
  }, [settings])

  const [catalogForm, setCatalogForm] = useState(catalogInitial)
  useEffect(() => {
    setCatalogForm(catalogInitial)
  }, [catalogInitial])
  const [catalogSaving, setCatalogSaving] = useState(false)
  const [catalogError, setCatalogError] = useState<string | null>(null)
  const [catalogDialogOpen, setCatalogDialogOpen] = useState(false)

  const onSaveCatalogDefaults = useCallback(async () => {
    setCatalogSaving(true)
    setCatalogError(null)
    try {
      const res = await postAdminMutate("settings_tab", {
        tab: "plans_catalog",
        default_concurrent_users: Math.max(0, Math.trunc(num(catalogForm.default_concurrent_users))),
        price_per_extra_user: catalogForm.price_per_extra_user.trim().replace(",", "."),
      })
      if (!res.ok) {
        setCatalogError(res.message || tp("catalogSaveError"))
        return
      }
      onMutateSuccess?.()
      setCatalogDialogOpen(false)
    } finally {
      setCatalogSaving(false)
    }
  }, [catalogForm, onMutateSuccess, tp])

  const sellablePanels = useMemo(() => {
    if (!resellerMode) return panels
    return panels.filter((p) => panelCanSellPlan(p, true))
  }, [panels, resellerMode])

  const defaultPanelId = useMemo(() => {
    if (panelFilter !== "all") return num(panelFilter)
    const list = resellerMode ? sellablePanels : panels
    return num(list[0]?.id) || num(panels[0]?.id) || 1
  }, [panelFilter, panels, resellerMode, sellablePanels])

  const firstCategoryForPanel = useCallback(
    (pid: number) => {
      const c = planCategories.find((x) => num(x.panel_id) === pid)
      return String(c?.slug ?? "")
    },
    [planCategories]
  )

  const visiblePlans = useMemo(
    () => plans.filter((p) => String(p.service_type ?? "xray") !== "l2tp"),
    [plans]
  )

  const resellerPlansAll = useMemo(
    () => (resellerMode ? visiblePlans : visiblePlans.filter((p) => num(p.owner_svp_user_id) > 0)),
    [visiblePlans, resellerMode]
  )
  const sitePlansAll = useMemo(
    () => (resellerMode ? [] : visiblePlans.filter((p) => num(p.owner_svp_user_id) === 0)),
    [visiblePlans, resellerMode]
  )

  const filterByPanel = useCallback((list: DashRecord[], pf: string) => {
    if (pf === "all") return list
    const pid = num(pf)
    return list.filter((p) => num(p.panel_id) === pid)
  }, [])

  const filteredPlans = useMemo(() => {
    const pf = resellerMode ? panelFilter : sitePanelFilter
    return filterByPanel(resellerMode ? visiblePlans : sitePlansAll, pf)
  }, [visiblePlans, sitePlansAll, panelFilter, sitePanelFilter, resellerMode, filterByPanel])

  const filteredResellerPlans = useMemo(
    () => filterByPanel(resellerPlansAll, resellerPanelFilter),
    [resellerPlansAll, resellerPanelFilter, filterByPanel]
  )
  const filteredSitePlans = useMemo(
    () => filterByPanel(sitePlansAll, sitePanelFilter),
    [sitePlansAll, sitePanelFilter, filterByPanel]
  )

  const stats = useMemo(() => {
    let active = 0
    let inactive = 0
    for (const p of visiblePlans) {
      const on = p.active === true || p.active === 1 || p.active === "1"
      if (on) active++
      else inactive++
    }
    const total = pagination?.total ?? visiblePlans.length
    return { total, active, inactive }
  }, [visiblePlans, pagination])

  const ranked = useMemo(() => {
    return [...(resellerMode ? filteredPlans : filteredSitePlans)].sort((a, b) => {
      const uc = num(b.userCount) - num(a.userCount)
      if (uc !== 0) return uc
      return String(a.name ?? "").localeCompare(String(b.name ?? ""))
    })
  }, [filteredPlans, filteredSitePlans, resellerMode])

  const floorForPanel = useMemo(() => {
    const pid = form.plan_panel_id
    const lid = form.wholesale_line_id
    if (lid > 0) {
      const byLine = resellerPlanFloors.find((x) => num(x.wholesale_line_id) === lid)
      if (byLine) return byLine
    }
    return (
      resellerPlanFloors.find((x) => num(x.panel_id) === pid && !num(x.wholesale_line_id)) ??
      resellerPlanFloors.find((x) => num(x.panel_id) === pid)
    )
  }, [form.plan_panel_id, form.wholesale_line_id, resellerPlanFloors])

  const minPriceFloorPerGb = useMemo(() => num(floorForPanel?.min_price_per_gb_effective), [floorForPanel])

  const categoriesForFormPanel = useMemo(
    () => planCategories.filter((c) => num(c.panel_id) === form.plan_panel_id),
    [planCategories, form.plan_panel_id]
  )

  const wholesaleLinesForPanel = useMemo(
    () => wholesaleLines.filter((ln) => num(ln.panel_id) === form.plan_panel_id),
    [wholesaleLines, form.plan_panel_id]
  )

  useEffect(() => {
    if (!sheetOpen || !resellerMode) return
    const current = panels.find((x) => num(x.id) === form.plan_panel_id)
    if (current && !panelCanSellPlan(current, true)) {
      const first = sellablePanels[0]
      if (first) {
        const pid = num(first.id)
        setForm((f) => ({
          ...f,
          plan_panel_id: pid,
          category: firstCategoryForPanel(pid) || f.category,
        }))
      }
    }
  }, [sheetOpen, resellerMode, form.plan_panel_id, panels, sellablePanels, firstCategoryForPanel])

  const openAddSite = () => {
    setError(null)
    setFormMode("add")
    setFormTarget("site")
    const pid = num(sitePanelFilter !== "all" ? sitePanelFilter : defaultPanelId)
    setForm(emptyForm(pid, firstCategoryForPanel(pid), 0))
    setSheetOpen(true)
  }

  const openAddReseller = () => {
    setError(null)
    setFormMode("add")
    setFormTarget("reseller")
    const pid = num(resellerPanelFilter !== "all" ? resellerPanelFilter : defaultPanelId)
    setForm(emptyForm(pid, firstCategoryForPanel(pid), num(resellerChoices[0]?.id)))
    setSheetOpen(true)
  }

  const openAdd = () => {
    if (resellerMode) openAddSite()
    else openAddSite()
  }

  const openEdit = (p: DashRecord) => {
    setError(null)
    setFormMode("edit")
    setFormTarget(num(p.owner_svp_user_id) > 0 ? "reseller" : "site")
    setForm(formFromPlan(p))
    setSheetOpen(true)
  }

  const runMutate = async (params: Record<string, unknown>) => {
    setSaving(true)
    setError(null)
    const res = await postAdminMutate("plan", params)
    setSaving(false)
    if (!res.ok) {
      setError(formatPlanMutateError(res.code, res.message, tp))
      return false
    }
    onMutateSuccess?.()
    return true
  }

  const onSaveSheet = async () => {
    const inv = validateForm(form, resellerMode, formTarget, wholesaleLinesForPanel.length)
    if (inv) {
      const key = CLIENT_VALIDATION_LOCALE[inv]
      setError(key ? tp(key) : tp("mutateInvalid"))
      return
    }
    const payload = formToPayload(form)
    const action = formMode === "add" ? "add" : "update"
    const ok = await runMutate({
      plan_action: action,
      plan_id: formMode === "edit" ? form.plan_id : 0,
      ...payload,
    })
    if (ok) setSheetOpen(false)
  }

  const onToggle = async (p: DashRecord) => {
    await runMutate({ plan_action: "toggle", plan_id: num(p.id) })
  }

  const onConfirmDelete = async () => {
    if (!deleteTarget) return
    const ok = await runMutate({ plan_action: "delete", plan_id: num(deleteTarget.id) })
    if (ok) setDeleteTarget(null)
  }

  return (
    <DashPage data-testid="dash-plans-tab">
      <DashboardPageHeader
        title={<h2 className="text-xl font-semibold tracking-tight">{tp("title")}</h2>}
        description={tp("subtitle")}
      />

      {!resellerMode ? (
        <Tabs
          value={plansView}
          onValueChange={(v) => {
            const next = isPlansView(v) ? v : "plans"
            setPlansView(next)
            writePlansViewToUrl(next)
          }}
        >
          <TabsList>
            <TabsTrigger value="plans">{tp("tabPlans")}</TabsTrigger>
            <TabsTrigger value="wholesale">{tp("tabWholesaleLines")}</TabsTrigger>
          </TabsList>
        </Tabs>
      ) : null}

      {!resellerMode && plansView === "wholesale" ? (
        <DashboardWholesaleLinesAdmin
          catalog={wholesaleLinesCatalog}
          panels={panels}
          l2tpServers={l2tpServers}
          onMutateSuccess={onMutateSuccess}
        />
      ) : null}

      {resellerMode || plansView === "plans" ? (
        <>
      {resellerMode && panels.length === 0 && wholesaleLines.length === 0 ? (
        <div className="space-y-3">
          <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100">
            {tp("resellerNoPanels")}
          </p>
          {actorSvpUserId > 0 ? (
            <p className="text-sm text-muted-foreground">{tp("resellerNoPanelsHint", { svpUserId: actorSvpUserId })}</p>
          ) : null}
          {panelAccessDiagnostics ? (
            <div className="space-y-1 rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
              <p className="font-medium text-foreground">{tp("resellerPanelDiagTitle")}</p>
              <p>{tp("resellerPanelDiagStored", { n: panelAccessDiagnostics.stored_rows ?? 0 })}</p>
              <p>{tp("resellerPanelDiagJoinable", { n: panelAccessDiagnostics.joinable_rows ?? 0 })}</p>
              {(panelAccessDiagnostics.orphan_panel_ids?.length ?? 0) > 0 ? (
                <p>
                  {tp("resellerPanelDiagOrphans", {
                    ids: (panelAccessDiagnostics.orphan_panel_ids ?? []).join(", "),
                  })}
                </p>
              ) : null}
              <p>{tp("resellerPanelDiagInactive", { n: panelAccessDiagnostics.inactive_row_count ?? 0 })}</p>
            </div>
          ) : null}
        </div>
      ) : null}

      {error ? (
        <p className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </p>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsTotal")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.total, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsActive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums text-emerald-600 dark:text-emerald-400">
              {formatNumber(stats.active, isFa)}
            </CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsInactive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums text-muted-foreground">
              {formatNumber(stats.inactive, isFa)}
            </CardTitle>
          </CardHeader>
        </Card>
      </div>

      {pagination ? <p className="text-xs text-muted-foreground">{tp("statsPageBreakdown")}</p> : null}

      {resellerMode && wholesaleLines.length > 0 ? (
        <div className="space-y-3">
          <div>
            <h3 className="text-sm font-medium">{tp("wholesaleLadderTitle")}</h3>
            <p className="text-xs text-muted-foreground">{tp("wholesaleLadderRenewNote")}</p>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            {wholesaleLines.map((ln) => {
              const ladder = (ln.ladder ?? {}) as Record<string, unknown>
              const label = String(ln.label ?? ln.id ?? "")
              const totalGb = Number(ladder.total_gb ?? 0)
              const totalToman = Number(ladder.total_wholesale_toman ?? 0)
              const curRate = ladder.current_price_per_gb
              const nextRate = ladder.next_price_per_gb
              const gbNeed = ladder.gb_to_next_tier
              const tomanNeed = ladder.toman_to_next_tier
              const tiers = Array.isArray(ladder.tiers) ? (ladder.tiers as Record<string, unknown>[]) : []
              return (
                <Card key={String(ln.id ?? label)}>
                  <CardHeader className="pb-2">
                    <CardTitle className="text-base">{tp("wholesaleLadderLine", { label })}</CardTitle>
                    <CardDescription>
                      {tp("wholesaleLadderTotals", {
                        gb: formatNumber(totalGb, isFa),
                        toman: formatNumber(totalToman, isFa),
                      })}
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-2 text-sm">
                    {curRate != null && Number(curRate) > 0 ? (
                      <p>{tp("wholesaleLadderCurrent", { rate: formatNumber(Number(curRate), isFa) })}</p>
                    ) : null}
                    {nextRate != null && Number(nextRate) > 0 ? (
                      <p className="text-muted-foreground">
                        {tp("wholesaleLadderNext", {
                          rate: formatNumber(Number(nextRate), isFa),
                          gb: formatNumber(Number(gbNeed ?? 0), isFa),
                          toman: formatNumber(Number(tomanNeed ?? 0), isFa),
                        })}
                      </p>
                    ) : (
                      <p className="text-muted-foreground">{tp("wholesaleLadderMax")}</p>
                    )}
                    {tiers.length > 0 ? (
                      <ul className="space-y-1 text-xs text-muted-foreground">
                        {tiers.map((t) => (
                          <li key={String(t.id ?? t.sort_order)}>
                            {formatNumber(Number(t.price_per_gb ?? 0), isFa)}
                            {tp("wholesaleLadderPerGb")}
                            {Number(t.min_total_gb ?? 0) > 0
                              ? ` · ${tp("wholesaleLadderTierMinGb", { gb: formatNumber(Number(t.min_total_gb), isFa) })}`
                              : ""}
                            {Number(t.min_total_toman ?? 0) > 0
                              ? ` · ${tp("wholesaleLadderTierMinToman", { toman: formatNumber(Number(t.min_total_toman), isFa) })}`
                              : ""}
                          </li>
                        ))}
                      </ul>
                    ) : null}
                  </CardContent>
                </Card>
              )
            })}
          </div>
        </div>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-[1fr_260px]">
        <div className="min-w-0 space-y-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-2">
              <Label htmlFor="catalog-filter" className="sr-only">
                {tp("filterPanel")}
              </Label>
              <span className="text-sm text-muted-foreground">{tp("filterPanel")}</span>
              <DashSelect
                id="catalog-filter"
                triggerClassName="w-full min-w-[10rem] sm:w-56"
                value={resellerMode ? panelFilter : sitePanelFilter}
                onValueChange={(v) =>
                  resellerMode ? setPanelFilter(v) : setSitePanelFilter(v)
                }
                options={[
                  { value: "all", label: tp("filterAll") },
                  ...panels.map((p) => ({
                    value: String(p.id),
                    label: String(p.label ?? p.name ?? p.id),
                  })),
                ]}
              />
            </div>
            <div className={cn("flex flex-wrap items-center gap-2")}>
              {showCatalogDefaultsSave && !resellerMode ? (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="gap-1.5"
                  onClick={() => setCatalogDialogOpen(true)}
                >
                  <Settings2 className="size-4 shrink-0" aria-hidden />
                  <span>{tp("catalogDefaultsButton")}</span>
                </Button>
              ) : null}
              {!resellerMode ? (
                <>
                  <Button type="button" variant="outline" onClick={openAddReseller}>
                    {tp("addResellerPlan")}
                  </Button>
                  <Button type="button" onClick={openAddSite}>
                    {tp("addPlan")}
                  </Button>
                </>
              ) : (
                <Button type="button" onClick={openAdd} disabled={panels.length < 1}>
                  {tp("addPlan")}
                </Button>
              )}
            </div>
          </div>

          {!resellerMode ? (
            <div className="space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-base font-semibold">{tp("resellerPlansSection")}</h3>
                <DashSelect
                  triggerClassName="w-full min-w-[10rem] sm:w-48"
                  value={resellerPanelFilter}
                  onValueChange={setResellerPanelFilter}
                  options={[
                    { value: "all", label: tp("filterAll") },
                    ...panels.map((p) => ({
                      value: String(p.id),
                      label: String(p.label ?? p.name ?? p.id),
                    })),
                  ]}
                />
              </div>
              {filteredResellerPlans.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tp("rankEmpty")}</p>
              ) : (
                <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                  {filteredResellerPlans.map((p) => {
                    const id = num(p.id)
                    const pid = num(p.panel_id)
                    const ownerId = num(p.owner_svp_user_id)
                    const uc = num(p.userCount)
                    const price = num(p.price)
                    const ptype = String(p.pricing_type ?? "fixed")
                    const active = p.active === true || p.active === 1 || p.active === "1"
                    const priceLabel =
                      ptype === "per_gb"
                        ? `${formatNumber(num(p.price_per_gb), isFa)} / ${tp("gbSuffix")}`
                        : formatNumber(price, isFa)
                    return (
                      <Card key={`r-${id || String(p.name)}`} className="relative overflow-hidden pt-0">
                        <CardHeader className="space-y-2 pb-3 pt-4">
                          <div className={cn("flex items-start justify-between gap-2")}>
                            <div className="min-w-0 flex-1">
                              <CardTitle className="text-base leading-snug">{String(p.name ?? "—")}</CardTitle>
                              <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                                {tp("cardUsers")}: {formatNumber(uc, isFa)}
                              </p>
                            </div>
                            <div className={cn("flex shrink-0 items-center gap-1")}>
                              <Badge variant={active ? "default" : "outline"}>
                                {active ? tp("statsActive") : tp("statsInactive")}
                              </Badge>
                              <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                  <Button variant="ghost" size="icon-sm" className="size-8">
                                    <EllipsisVerticalIcon className="size-4" />
                                    <span className="sr-only">{tp("actions")}</span>
                                  </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align={isFa ? "start" : "end"}>
                                  <DropdownMenuItem onClick={() => openEdit(p)}>{tp("edit")}</DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => void onToggle(p)}>{tp("toggle")}</DropdownMenuItem>
                                  <DropdownMenuItem variant="destructive" onClick={() => setDeleteTarget(p)}>
                                    {tp("delete")}
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </div>
                          </div>
                        </CardHeader>
                        <CardContent className="grid gap-2 pb-4 sm:grid-cols-2">
                          {ownerId > 0 ? (
                            <PlanInfoRow
                              icon={Users}
                              label={tp("pickReseller")}
                              value={`#${formatNumber(ownerId, isFa)}`}
                            />
                          ) : null}
                          <PlanInfoRow icon={Server} label={tp("cardPanel")} value={panelLabel(panels, pid)} />
                          <PlanInfoRow icon={Tag} label={tp("cardCategory")} value={String(p.category ?? "—")} />
                          <PlanInfoRow icon={BadgeDollarSign} label={tp("cardPrice")} value={priceLabel} />
                        </CardContent>
                      </Card>
                    )
                  })}
                </div>
              )}
              <h3 className="text-base font-semibold pt-2">{tp("sitePlansSection")}</h3>
            </div>
          ) : null}

          {(resellerMode ? filteredPlans : filteredSitePlans).length === 0 ? (
            <Card>
              <CardContent className="py-10 text-center text-sm text-muted-foreground">
                {tp("rankEmpty")}
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
              {(resellerMode ? filteredPlans : filteredSitePlans).map((p) => {
                const id = num(p.id)
                const pid = num(p.panel_id)
                const ownerId = num(p.owner_svp_user_id)
                const uc = num(p.userCount)
                const price = num(p.price)
                const ptype = String(p.pricing_type ?? "fixed")
                const active = p.active === true || p.active === 1 || p.active === "1"
                const priceLabel =
                  ptype === "per_gb"
                    ? `${formatNumber(num(p.price_per_gb), isFa)} / ${tp("gbSuffix")}`
                    : formatNumber(price, isFa)
                return (
                  <Card key={id || String(p.name)} className="relative overflow-hidden pt-0">
                    <CardHeader className="space-y-2 pb-3 pt-4">
                      <div
                        className={cn(
                          "flex items-start justify-between gap-2"
                        )}
                      >
                        <div className="min-w-0 flex-1">
                          <CardTitle className="text-base leading-snug">{String(p.name ?? "—")}</CardTitle>
                          <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                            {tp("cardUsers")}: {formatNumber(uc, isFa)}
                          </p>
                        </div>
                        <div className={cn("flex shrink-0 items-center gap-1")}>
                          <Badge variant={active ? "default" : "outline"}>
                            {active ? tp("statsActive") : tp("statsInactive")}
                          </Badge>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="icon-sm" className="size-8">
                                <EllipsisVerticalIcon className="size-4" />
                                <span className="sr-only">{tp("actions")}</span>
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align={isFa ? "start" : "end"}>
                              <DropdownMenuItem onClick={() => openEdit(p)}>{tp("edit")}</DropdownMenuItem>
                              <DropdownMenuItem onClick={() => void onToggle(p)}>{tp("toggle")}</DropdownMenuItem>
                              <DropdownMenuItem variant="destructive" onClick={() => setDeleteTarget(p)}>
                                {tp("delete")}
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                      </div>
                    </CardHeader>
                    <CardContent className="grid gap-2 pb-4 sm:grid-cols-2">
                      {ownerId > 0 ? (
                        <PlanInfoRow
                          icon={Users}
                          label={tp("pickReseller")}
                          value={`#${formatNumber(ownerId, isFa)}`}
                        />
                      ) : null}
                      <PlanInfoRow icon={Server} label={tp("cardPanel")} value={panelLabel(panels, pid)} />
                      <PlanInfoRow icon={Tag} label={tp("cardCategory")} value={String(p.category ?? "—")} />
                      <PlanInfoRow
                        icon={Radio}
                        label={tp("cardInbound")}
                        value={formatNumber(num(p.inbound_id), isFa)}
                      />
                      <PlanInfoRow
                        icon={Clock}
                        label={tp("cardDuration")}
                        value={`${formatNumber(num(p.duration_days), isFa)} ${tp("periodDays")}`}
                      />
                      <PlanInfoRow
                        icon={Users}
                        label={tp("cardClients")}
                        value={formatClientCap(p.clients_count, tp("clientsUnlimited"))}
                      />
                      <PlanInfoRow icon={BadgeDollarSign} label={tp("cardPrice")} value={priceLabel} />
                      {ptype === "fixed" ? (
                        <PlanInfoRow
                          icon={HardDrive}
                          label={tp("cardTraffic")}
                          value={`${formatNumber(num(p.traffic_gb), isFa)} ${tp("gbSuffix")}`}
                        />
                      ) : (
                        <PlanInfoRow
                          icon={HardDrive}
                          label={tp("cardTrafficRange")}
                          value={`${formatNumber(num(p.traffic_gb_min), isFa)}–${formatNumber(num(p.traffic_gb_max), isFa)} ${tp("gbSuffix")}`}
                        />
                      )}
                    </CardContent>
                  </Card>
                )
              })}
            </div>
          )}
          <DataPagination
            meta={pagination}
        onPageChange={onPageChange}
            onPerPageChange={onPerPageChange}
          />
        </div>

        <aside className="min-w-0 xl:sticky xl:top-20 xl:self-start">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{tp("rankTitle")}</CardTitle>
              <CardDescription>{tp("rankSubtitle")}</CardDescription>
            </CardHeader>
            <CardContent className="max-h-[70vh] space-y-2 overflow-y-auto pe-1">
              {ranked.length === 0 ? (
                <p className="text-sm text-muted-foreground">{tp("rankEmpty")}</p>
              ) : (
                ranked.map((p, idx) => (
                  <div
                    key={String(p.id)}
                    className="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                  >
                    <span className="flex min-w-0 items-center gap-2">
                      <span className="shrink-0 tabular-nums text-muted-foreground">{idx + 1}.</span>
                      <span className="truncate font-medium">{String(p.name ?? "—")}</span>
                    </span>
                    <span className="shrink-0 tabular-nums font-semibold">
                      {formatNumber(num(p.userCount), isFa)}
                    </span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </aside>
      </div>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <DashSheetContent
          className="w-full overflow-y-auto sm:max-w-md"
          showCloseButton
        >
          <SheetHeader>
            <SheetTitle>
              {formMode === "add"
                ? formTarget === "reseller"
                  ? tp("addResellerPlan")
                  : tp("addPlan")
                : tp("editPlan")}
            </SheetTitle>
          </SheetHeader>
          <div className="flex flex-col gap-4 px-4 pb-4">
            <div className="space-y-2">
              <Label>{tp("planName")}</Label>
              <Input
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              />
            </div>
            {!resellerMode && formTarget === "reseller" ? (
              <div className="space-y-2">
                <Label>{tp("pickReseller")}</Label>
                <DashSelect
                  value={String(form.owner_svp_user_id || 0)}
                  onValueChange={(v) => setForm((f) => ({ ...f, owner_svp_user_id: num(v) }))}
                  options={[
                    { value: "0", label: "—" },
                    ...resellerChoices.map((r) => ({
                      value: String(num(r.id)),
                      label: `${String(r.first_name ?? "")} ${String(r.last_name ?? "")} (#${num(r.id)})`.trim(),
                    })),
                  ]}
                />
              </div>
            ) : null}
            <div className="space-y-2">
              <Label>{tp("filterPanel")}</Label>
              <DashSelect
                value={String(form.plan_panel_id)}
                onValueChange={(v) => {
                  const pid = num(v)
                  setForm((f) => {
                    const linesForPid = wholesaleLines.filter((ln) => num(ln.panel_id) === pid)
                    const keepLine = linesForPid.some((ln) => num(ln.id) === f.wholesale_line_id)
                    return {
                      ...f,
                      plan_panel_id: pid,
                      category: firstCategoryForPanel(pid) || f.category,
                      wholesale_line_id: keepLine
                        ? f.wholesale_line_id
                        : linesForPid.length === 1
                          ? num(linesForPid[0]?.id)
                          : 0,
                    }
                  })
                }}
                options={(resellerMode ? sellablePanels : panels).map((p) => {
                  const canSell = panelCanSellPlan(p, resellerMode)
                  const base = String(p.label ?? p.name ?? p.id)
                  return {
                    value: String(p.id),
                    label: `${base}${resellerMode && !canSell ? tp("panelNoAccessSuffix") : ""}`,
                    disabled: resellerMode && !canSell,
                  }
                })}
              />
            </div>
            {resellerMode && wholesaleLinesForPanel.length > 0 ? (
              <div className="space-y-2">
                <Label>{tp("wholesaleLine")}</Label>
                <DashSelect
                  value={String(form.wholesale_line_id || 0)}
                  onValueChange={(v) => setForm((f) => ({ ...f, wholesale_line_id: num(v) }))}
                  allowEmpty
                  placeholder="—"
                  options={[
                    { value: "0", label: "—" },
                    ...wholesaleLinesForPanel.map((ln) => ({
                      value: String(num(ln.id)),
                      label: String(ln.label ?? ln.id ?? ""),
                    })),
                  ]}
                />
                <p className="text-xs text-muted-foreground">{tp("wholesaleLineHint")}</p>
              </div>
            ) : null}
            <div className="space-y-2">
              <Label>{tp("category")}</Label>
              <DashSelect
                value={form.category}
                onValueChange={(v) => setForm((f) => ({ ...f, category: v }))}
                allowEmpty
                placeholder="—"
                options={categoriesForFormPanel.map((c) => ({
                  value: String(c.slug ?? ""),
                  label: `${String(c.label ?? c.slug)} (${String(c.slug)})`,
                }))}
              />
              {categoriesForFormPanel.length === 0 ? (
                <p className="text-xs text-amber-600 dark:text-amber-400">{tp("noCategories")}</p>
              ) : null}
            </div>
            {resellerMode && floorForPanel ? (
              <div className={cn("space-y-2 rounded-md border bg-muted/40 p-3 text-sm")}>
                <p className="font-medium text-foreground">{tp("connectionPresetTitle")}</p>
                <p className="text-muted-foreground">{tp("connectionPresetHint")}</p>
                <p className="tabular-nums">
                  {tp("protocolXray")} · {tp("inbound")}: #{formatNumber(num(floorForPanel.default_inbound_id), isFa)}
                </p>
              </div>
            ) : null}
            {!resellerMode ? (
              <div className="space-y-2">
                <Label>{tp("inbound")}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.inbound_id || ""}
                  onChange={(e) => setForm((f) => ({ ...f, inbound_id: num(e.target.value) }))}
                />
              </div>
            ) : null}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label>{tp("duration")}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.duration_days}
                  onChange={(e) => setForm((f) => ({ ...f, duration_days: num(e.target.value) }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{tp("clients")}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.clients_count}
                  onChange={(e) => setForm((f) => ({ ...f, clients_count: Math.max(0, num(e.target.value)) }))}
                />
                <p className="text-xs text-muted-foreground">{tp("clientsCountHint")}</p>
              </div>
            </div>
            <div className="space-y-2">
              <Label>{tp("pricingType")}</Label>
              <DashSelect
                value={form.plan_pricing_type}
                onValueChange={(v) =>
                  setForm((f) => ({
                    ...f,
                    plan_pricing_type: v === "per_gb" ? "per_gb" : "fixed",
                  }))
                }
                options={[
                  { value: "fixed", label: tp("pricingFixed") },
                  { value: "per_gb", label: tp("pricingPerGb") },
                ]}
              />
            </div>
            {form.plan_pricing_type === "fixed" ? (
              <>
                <div className="space-y-2">
                  <Label>{tp("price")}</Label>
                  <Input
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.price}
                    onChange={(e) => setForm((f) => ({ ...f, price: num(e.target.value) }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{tp("cardTraffic")} ({tp("gbSuffix")})</Label>
                  <Input
                    type="number"
                    min={0}
                    value={form.traffic_gb}
                    onChange={(e) => setForm((f) => ({ ...f, traffic_gb: num(e.target.value) }))}
                  />
                </div>
              </>
            ) : (
              <>
                <div className="space-y-2">
                  <Label>{tp("pricePerGb")}</Label>
                  <Input
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.price_per_gb}
                    onChange={(e) => setForm((f) => ({ ...f, price_per_gb: num(e.target.value) }))}
                  />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-2">
                    <Label>{tp("trafficGbMin")}</Label>
                    <Input
                      type="number"
                      min={1}
                      value={form.traffic_gb_min}
                      onChange={(e) => setForm((f) => ({ ...f, traffic_gb_min: num(e.target.value) }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>{tp("trafficGbMax")}</Label>
                    <Input
                      type="number"
                      min={1}
                      value={form.traffic_gb_max}
                      onChange={(e) => setForm((f) => ({ ...f, traffic_gb_max: num(e.target.value) }))}
                    />
                  </div>
                </div>
              </>
            )}
            {resellerMode && minPriceFloorPerGb > 0 ? (
              <div className="space-y-1 text-xs text-muted-foreground">
                <p>
                  {form.plan_pricing_type === "per_gb"
                    ? t("plansAdmin.minPriceHintPerGb", {
                        min: formatNumber(minPriceFloorPerGb, isFa),
                      })
                    : t("plansAdmin.minPriceHintFixed", {
                        min: formatNumber(
                          minPriceFloorPerGb * Math.max(1, form.traffic_gb || 1),
                          isFa
                        ),
                      })}
                </p>
              </div>
            ) : null}
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.plan_active}
                onChange={(e) => setForm((f) => ({ ...f, plan_active: e.target.checked }))}
              />
              {tp("active")}
            </label>
          </div>
          <SheetFooter className="flex-row gap-2 border-t p-4">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              {tp("cancel")}
            </Button>
            <Button
              type="button"
              disabled={saving || categoriesForFormPanel.length === 0}
              onClick={() => void onSaveSheet()}
            >
              {tp("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

      {showCatalogDefaultsSave ? (
        <Dialog open={catalogDialogOpen} onOpenChange={setCatalogDialogOpen}>
          <DashDialogContent className={cn("sm:max-w-md")}>
            <DashDialogHeader className={cn("text-start")}>
              <DialogTitle>{tp("catalogDefaultsDialogTitle")}</DialogTitle>
              <DialogDescription>{tp("catalogCardDesc")}</DialogDescription>
            </DashDialogHeader>
            {catalogError ? (
              <p className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {catalogError}
              </p>
            ) : null}
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2 sm:col-span-2">
                <Label htmlFor="catalog_concurrent_dialog">{tp("catalogConcurrent")}</Label>
                <Input
                  id="catalog_concurrent_dialog"
                  type="number"
                  min={0}
                  value={catalogForm.default_concurrent_users}
                  onChange={(e) =>
                    setCatalogForm((f) => ({ ...f, default_concurrent_users: e.target.value }))
                  }
                />
                <p className="text-xs text-muted-foreground">{tp("clientsCountHint")}</p>
              </div>
              <div className="space-y-2 sm:col-span-2">
                <Label htmlFor="catalog_extra_price_dialog">{tp("catalogExtraPrice")}</Label>
                <Input
                  id="catalog_extra_price_dialog"
                  type="text"
                  inputMode="decimal"
                  value={catalogForm.price_per_extra_user}
                  onChange={(e) =>
                    setCatalogForm((f) => ({ ...f, price_per_extra_user: e.target.value }))
                  }
                />
              </div>
            </div>
            <DashDialogFooter className={cn("gap-2")}>
              <Button type="button" variant="outline" onClick={() => setCatalogDialogOpen(false)}>
                {tp("cancel")}
              </Button>
              <Button type="button" disabled={catalogSaving} onClick={() => void onSaveCatalogDefaults()}>
                {catalogSaving ? "…" : tp("catalogSave")}
              </Button>
            </DashDialogFooter>
          </DashDialogContent>
        </Dialog>
      ) : null}

      <Dialog open={Boolean(deleteTarget)} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DashDialogContent className={cn()}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{tp("deleteTitle")}</DialogTitle>
            <DialogDescription>{tp("deleteDescription")}</DialogDescription>
          </DashDialogHeader>
          <DashDialogFooter className={cn("gap-2 sm:justify-between")}>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {tp("deleteCancel")}
            </Button>
            <Button type="button" variant="destructive" disabled={saving} onClick={() => void onConfirmDelete()}>
              {tp("deleteConfirm")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
        </>
      ) : null}
    </DashPage>
  )
}
