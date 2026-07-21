"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocale, useTranslations } from "next-intl"
import { Settings2 } from "lucide-react"
import { getAdminState, getAdminJson, postAdminMutate } from "@/lib/dash-admin-mutate"
import { formatNumber } from "@/lib/format-locale"
import { parsePaginationMeta, type PaginationMeta } from "@/lib/dash-pagination"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { DataPagination } from "@/components/data-pagination"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { DashboardWholesaleLinesAdmin } from "@/components/dashboard-wholesale-lines-admin"
import { readPlansViewFromUrl, writePlansViewToUrl, type PlansView } from "@/lib/plans-subview"

type DashRecord = Record<string, unknown>
type PlansView = "plans" | "wholesale"
type FormTarget = "site" | "reseller"

type PlanForm = {
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
  inbound_ids: number[]
  service_type: "xray" | "l2tp"
  l2tp_server_id: number
  sort_order: number
  plan_active: boolean
  wholesale_line_id: number
  panel_template_id: number
  quota_display_mode: "show" | "hide_as_unlimited"
}

type InboundRow = { id: number; remark: string; port: number; protocol: string }

type ResellerPanelAccessDiagnostics = {
  stored_rows?: number
  joinable_rows?: number
  orphan_panel_ids?: number[]
  inactive_row_count?: number
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

function parseInboundIds(p: DashRecord): number[] {
  const raw = p.inbound_ids
  if (Array.isArray(raw)) {
    const ids = raw.map((x) => num(x)).filter((x) => x > 0)
    if (ids.length) return ids
  }
  if (typeof raw === "string" && raw.trim()) {
    try {
      const parsed = JSON.parse(raw) as unknown
      if (Array.isArray(parsed)) {
        const ids = parsed.map((x) => num(x)).filter((x) => x > 0)
        if (ids.length) return ids
      }
    } catch {
      /* ignore */
    }
  }
  const iid = num(p.inbound_id)
  return iid > 0 ? [iid] : []
}

function inboundOptionLabel(row: InboundRow): string {
  const remark = String(row.remark ?? "").trim() || `#${row.id}`
  return `${remark} (${row.protocol}:${row.port}) #${row.id}`
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function rows(v: unknown): DashRecord[] {
  return Array.isArray(v) ? (v.filter((x) => x && typeof x === "object") as DashRecord[]) : []
}

function isActive(row: DashRecord): boolean {
  return row.active === true || row.active === 1 || row.active === "1"
}

function panelCanSellPlan(p: DashRecord, resellerMode: boolean): boolean {
  if (!resellerMode) return true
  if (p.can_sell_plan === false) return false
  return true
}

function planUserCount(p: DashRecord): number {
  return num(p.userCount ?? p.user_count ?? p.users_count)
}

function emptyForm(panelId: number, category: string, ownerId = 0): PlanForm {
  return {
    plan_id: 0,
    name: "",
    category,
    plan_panel_id: panelId || 1,
    owner_svp_user_id: ownerId,
    traffic_gb: 30,
    price: 0,
    plan_pricing_type: "fixed",
    price_per_gb: 0,
    traffic_gb_min: 1,
    traffic_gb_max: 100,
    duration_days: 30,
    clients_count: 1,
    inbound_id: 0,
    inbound_ids: [],
    service_type: "xray",
    l2tp_server_id: 0,
    sort_order: 0,
    plan_active: true,
    wholesale_line_id: 0,
    panel_template_id: 0,
    quota_display_mode: "show",
  }
}

function formFromPlan(plan: DashRecord): PlanForm {
  const inbound_ids = parseInboundIds(plan)
  return {
    plan_id: num(plan.id),
    name: String(plan.name ?? ""),
    category: String(plan.category ?? ""),
    plan_panel_id: num(plan.panel_id) || 1,
    owner_svp_user_id: num(plan.owner_svp_user_id),
    traffic_gb: num(plan.traffic_gb),
    price: num(plan.price),
    plan_pricing_type: plan.pricing_type === "per_gb" ? "per_gb" : "fixed",
    price_per_gb: num(plan.price_per_gb),
    traffic_gb_min: num(plan.traffic_gb_min) || 1,
    traffic_gb_max: num(plan.traffic_gb_max) || 100,
    duration_days: num(plan.duration_days),
    clients_count: num(plan.clients_count),
    inbound_id: inbound_ids[0] ?? num(plan.inbound_id),
    inbound_ids,
    service_type: plan.service_type === "l2tp" ? "l2tp" : "xray",
    l2tp_server_id: num(plan.l2tp_server_id),
    sort_order: num(plan.sort_order),
    plan_active: isActive(plan),
    wholesale_line_id: num(plan.wholesale_line_id),
    panel_template_id: num(plan.panel_template_id),
    quota_display_mode: plan.quota_display_mode === "hide_as_unlimited" ? "hide_as_unlimited" : "show",
  }
}

function formPayload(form: PlanForm): Record<string, unknown> {
  return {
    name: form.name.trim(),
    category: form.category.trim(),
    plan_panel_id: form.plan_panel_id,
    panel_id: form.plan_panel_id,
    owner_svp_user_id: form.owner_svp_user_id,
    traffic_gb: form.traffic_gb,
    price: form.price,
    plan_pricing_type: form.plan_pricing_type,
    pricing_type: form.plan_pricing_type,
    price_per_gb: form.price_per_gb,
    traffic_gb_min: form.traffic_gb_min,
    traffic_gb_max: form.traffic_gb_max,
    duration_days: form.duration_days,
    clients_count: form.clients_count,
    inbound_ids: form.inbound_ids,
    inbound_id: form.inbound_ids[0] ?? form.inbound_id,
    service_type: form.service_type,
    l2tp_server_id: form.l2tp_server_id,
    sort_order: form.sort_order,
    plan_active: form.plan_active ? 1 : 0,
    active: form.plan_active,
    quota_display_mode: form.quota_display_mode,
    ...(form.wholesale_line_id > 0 ? { wholesale_line_id: form.wholesale_line_id } : { wholesale_line_id: 0 }),
    ...(form.panel_template_id > 0
      ? { panel_template_id: form.panel_template_id }
      : { panel_template_id: 0 }),
  }
}

function formatPlanMutateError(
  code: string | undefined,
  message: string | undefined,
  tp: (k: string) => string
): string {
  const c = String(code ?? "").trim()
  if (c && SERVER_ERROR_LOCALE[c]) return tp(SERVER_ERROR_LOCALE[c])
  const msg = String(message ?? "").trim()
  if (msg && SERVER_ERROR_LOCALE[msg]) return tp(SERVER_ERROR_LOCALE[msg])
  const fallback = msg || c
  return fallback ? `${tp("mutateError")}: ${fallback}` : tp("mutateError")
}

function parsePanelAccessDiagnostics(raw: unknown): ResellerPanelAccessDiagnostics | null {
  if (!raw || typeof raw !== "object") return null
  const o = raw as Record<string, unknown>
  return {
    stored_rows: num(o.stored_rows),
    joinable_rows: num(o.joinable_rows),
    orphan_panel_ids: Array.isArray(o.orphan_panel_ids)
      ? o.orphan_panel_ids.map((x) => num(x)).filter((x) => x > 0)
      : [],
    inactive_row_count: num(o.inactive_row_count),
  }
}

function resellerChoiceLabel(r: DashRecord): string {
  const name = `${String(r.first_name ?? "")} ${String(r.last_name ?? "")}`.trim()
  const id = num(r.id)
  return name ? `${name} (#${id})` : `#${id}`
}

export function PlansAdminClient() {
  const t = useTranslations("plansAdmin")
  const locale = useLocale()
  const isFa = locale === "fa"
  const [plans, setPlans] = useState<DashRecord[]>([])
  const [panels, setPanels] = useState<DashRecord[]>([])
  const [categories, setCategories] = useState<DashRecord[]>([])
  const [resellerChoices, setResellerChoices] = useState<DashRecord[]>([])
  const [resellerPlanFloors, setResellerPlanFloors] = useState<DashRecord[]>([])
  const [wholesaleLinesCatalog, setWholesaleLinesCatalog] = useState<DashRecord[]>([])
  const [wholesaleLines, setWholesaleLines] = useState<DashRecord[]>([])
  const [l2tpServers, setL2tpServers] = useState<DashRecord[]>([])
  const [resellerMode, setResellerMode] = useState(false)
  const [actorSvpUserId, setActorSvpUserId] = useState(0)
  const [panelAccessDiagnostics, setPanelAccessDiagnostics] = useState<ResellerPanelAccessDiagnostics | null>(null)
  const [plansView, setPlansView] = useState<PlansView>(() =>
    typeof window === "undefined" ? "plans" : readPlansViewFromUrl()
  )
  const [sitePanelFilter, setSitePanelFilter] = useState("all")
  const [resellerPanelFilter, setResellerPanelFilter] = useState("all")
  const [panelFilter, setPanelFilter] = useState("all")
  const [formTarget, setFormTarget] = useState<FormTarget>("site")
  const [form, setForm] = useState<PlanForm>(() => emptyForm(1, ""))
  const [formOpen, setFormOpen] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [feedback, setFeedback] = useState<string | null>(null)
  const [panelInbounds, setPanelInbounds] = useState<InboundRow[]>([])
  const [panelInboundsBusy, setPanelInboundsBusy] = useState(false)
  const [panelInboundsError, setPanelInboundsError] = useState<string | null>(null)
  const [panelTemplates, setPanelTemplates] = useState<Array<{ id: number; name?: string }>>([])
  const [panelTemplatesBusy, setPanelTemplatesBusy] = useState(false)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(40)
  const [pagination, setPagination] = useState<PaginationMeta | null>(null)
  const [settings, setSettings] = useState<DashRecord>({})
  const [catalogDialogOpen, setCatalogDialogOpen] = useState(false)
  const [catalogSaving, setCatalogSaving] = useState(false)
  const [catalogError, setCatalogError] = useState<string | null>(null)
  const [catalogForm, setCatalogForm] = useState({
    default_concurrent_users: "2",
    price_per_extra_user: "0",
  })

  useEffect(() => {
    writePlansViewToUrl(plansView)
  }, [plansView])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await getAdminState("plans", {
        plans_page: page,
        plans_per_page: perPage,
        planCategories_page: 1,
        planCategories_per_page: 100,
        panels_page: 1,
        panels_per_page: 100,
        l2tp_page: 1,
        l2tp_per_page: 100,
      })
      const isResellerActor = data.isReseller === true || data.actorRole === "reseller"
      setResellerMode(isResellerActor)
      setActorSvpUserId(num(data.actorSvpUserId ?? data.actor_svp_user_id))
      setPlans(rows(data.plans))
      setPanels(rows(data.panels))
      setCategories(rows(data.planCategories ?? data.plan_categories))
      setResellerChoices(rows(data.resellers ?? data.resellerChoices))
      setResellerPlanFloors(rows(data.resellerPlanFloors))
      setWholesaleLinesCatalog(rows(data.wholesaleLinesCatalog))
      setWholesaleLines(rows(data.wholesaleLines))
      setL2tpServers(rows(data.l2tpServers))
      setPanelAccessDiagnostics(
        isResellerActor ? parsePanelAccessDiagnostics(data.resellerPanelAccessDiagnostics) : null
      )
      const pagRaw = data.pagination
      const plansMeta =
        pagRaw && typeof pagRaw === "object"
          ? parsePaginationMeta((pagRaw as DashRecord).plans)
          : parsePaginationMeta(data.plansPagination)
      setPagination(plansMeta)
      const s =
        data.settings && typeof data.settings === "object" ? (data.settings as DashRecord) : {}
      setSettings(s)
      setCatalogForm({
        default_concurrent_users: String(Math.max(0, Math.trunc(num(s.default_concurrent_users)) || 2)),
        price_per_extra_user: String(s.price_per_extra_user ?? "0"),
      })
    } catch {
      setError(t("loadError"))
    } finally {
      setLoading(false)
    }
  }, [t, page, perPage])

  useEffect(() => {
    void load()
  }, [load])

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
        setCatalogError(res.message || t("catalogSaveError"))
        return
      }
      setCatalogDialogOpen(false)
      await load()
    } catch {
      setCatalogError(t("catalogSaveError"))
    } finally {
      setCatalogSaving(false)
    }
  }, [catalogForm, load, t])

  const loadPanelInbounds = useCallback(async (panelId: number) => {
    if (panelId < 1) {
      setPanelInbounds([])
      return
    }
    setPanelInboundsBusy(true)
    setPanelInboundsError(null)
    try {
      const json = await getAdminJson("/dashboard/admin/panel-inbounds", { panel_id: panelId })
      const inboundRows = Array.isArray(json.inbounds) ? (json.inbounds as InboundRow[]) : []
      setPanelInbounds(inboundRows)
    } catch {
      setPanelInbounds([])
      setPanelInboundsError(t("inboundPickerError"))
    } finally {
      setPanelInboundsBusy(false)
    }
  }, [t])

  const loadPanelTemplates = useCallback(async (panelId: number) => {
    if (panelId < 1) {
      setPanelTemplates([])
      return
    }
    setPanelTemplatesBusy(true)
    try {
      const json = await getAdminJson("/dashboard/admin/panel-templates", { panel_id: panelId })
      const data = json.data as Record<string, unknown> | undefined
      const rows =
        data && Array.isArray(data.templates)
          ? (data.templates as Array<{ id: number; name?: string }>)
          : []
      setPanelTemplates(rows)
    } catch {
      setPanelTemplates([])
    } finally {
      setPanelTemplatesBusy(false)
    }
  }, [])

  const formPanelRow = useMemo(
    () => panels.find((p) => num(p.id) === form.plan_panel_id),
    [panels, form.plan_panel_id]
  )
  const isPgPlanPanel =
    String(formPanelRow?.panel_provider ?? "") === "pasarguard" ||
    String(formPanelRow?.panel_api_flavor ?? "") === "pasarguard_v5"

  useEffect(() => {
    if (!formOpen || form.service_type === "l2tp") return
    void loadPanelInbounds(form.plan_panel_id)
  }, [formOpen, form.plan_panel_id, form.service_type, loadPanelInbounds])

  useEffect(() => {
    if (!formOpen || resellerMode || !isPgPlanPanel) {
      if (!isPgPlanPanel) setPanelTemplates([])
      return
    }
    void loadPanelTemplates(form.plan_panel_id)
  }, [formOpen, resellerMode, isPgPlanPanel, form.plan_panel_id, loadPanelTemplates])

  useEffect(() => {
    if (!formOpen || panelInbounds.length === 0) return
    const valid = new Set(panelInbounds.map((r) => r.id))
    setForm((f) => {
      const next = f.inbound_ids.filter((id) => valid.has(id))
      if (next.length === f.inbound_ids.length && (next[0] ?? 0) === f.inbound_id) return f
      return { ...f, inbound_ids: next, inbound_id: next[0] ?? 0 }
    })
  }, [formOpen, panelInbounds])

  const toggleFormInbound = (inboundId: number) => {
    setForm((f) => {
      const has = f.inbound_ids.includes(inboundId)
      const next = has
        ? f.inbound_ids.filter((id) => id !== inboundId)
        : [...f.inbound_ids, inboundId].sort((a, b) => a - b)
      return { ...f, inbound_ids: next, inbound_id: next[0] ?? 0 }
    })
  }

  const sellablePanels = useMemo(() => {
    if (!resellerMode) return panels
    return panels.filter((p) => panelCanSellPlan(p, true))
  }, [panels, resellerMode])

  const firstCategoryForPanel = useCallback(
    (pid: number) => {
      const c = categories.find((x) => num(x.panel_id) === pid)
      return String(c?.slug ?? "")
    },
    [categories]
  )

  const defaultPanelId = useMemo(() => {
    const pf = resellerMode ? panelFilter : sitePanelFilter
    if (pf !== "all") return num(pf)
    const list = resellerMode ? sellablePanels : panels
    return num(list[0]?.id) || num(panels[0]?.id) || 1
  }, [panelFilter, sitePanelFilter, panels, resellerMode, sellablePanels])

  const filterByPanel = useCallback((list: DashRecord[], pf: string) => {
    if (pf === "all") return list
    const pid = num(pf)
    return list.filter((p) => num(p.panel_id) === pid)
  }, [])

  const resellerPlansAll = useMemo(
    () => (resellerMode ? plans : plans.filter((p) => num(p.owner_svp_user_id) > 0)),
    [plans, resellerMode]
  )
  const sitePlansAll = useMemo(
    () => (resellerMode ? [] : plans.filter((p) => num(p.owner_svp_user_id) === 0)),
    [plans, resellerMode]
  )

  const filteredResellerPlans = useMemo(
    () => filterByPanel(resellerPlansAll, resellerMode ? panelFilter : resellerPanelFilter),
    [resellerPlansAll, resellerPanelFilter, panelFilter, resellerMode, filterByPanel]
  )
  const filteredSitePlans = useMemo(
    () => filterByPanel(sitePlansAll, sitePanelFilter),
    [sitePlansAll, sitePanelFilter, filterByPanel]
  )
  const filteredResellerActorPlans = useMemo(
    () => filterByPanel(plans, panelFilter),
    [plans, panelFilter, filterByPanel]
  )

  const ranked = useMemo(() => {
    const source = resellerMode ? filteredResellerActorPlans : filteredSitePlans
    return [...source].sort((a, b) => {
      const uc = planUserCount(b) - planUserCount(a)
      if (uc !== 0) return uc
      return String(a.name ?? "").localeCompare(String(b.name ?? ""))
    })
  }, [filteredResellerActorPlans, filteredSitePlans, resellerMode])

  const hasUserCountData = useMemo(
    () => plans.some((p) => planUserCount(p) > 0 || "userCount" in p || "user_count" in p),
    [plans]
  )

  const stats = useMemo(() => {
    const active = plans.filter(isActive).length
    const l2tp = plans.filter((plan) => String(plan.service_type ?? "xray") === "l2tp").length
    const total = pagination?.total ?? plans.length
    return { total, active, inactive: Math.max(0, plans.length - active), xray: plans.length - l2tp, l2tp }
  }, [plans, pagination])

  const categoriesForFormPanel = useMemo(
    () => categories.filter((c) => num(c.panel_id) === form.plan_panel_id),
    [categories, form.plan_panel_id]
  )

  const wholesaleLinesForPanel = useMemo(
    () => wholesaleLines.filter((ln) => num(ln.panel_id) === form.plan_panel_id),
    [wholesaleLines, form.plan_panel_id]
  )

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

  const minPriceFloorPerGb = useMemo(
    () => num(floorForPanel?.min_price_per_gb_effective ?? floorForPanel?.min_price_per_gb),
    [floorForPanel]
  )

  const panelLabel = (panelId: number) => {
    const row = panels.find((panel) => num(panel.id) === panelId)
    return String(row?.label ?? row?.name ?? `#${panelId}`)
  }

  const openAddSite = () => {
    setFeedback(null)
    setFormTarget("site")
    const pid = num(sitePanelFilter !== "all" ? sitePanelFilter : defaultPanelId)
    setForm(emptyForm(pid, firstCategoryForPanel(pid), 0))
    setFormOpen(true)
  }

  const openAddReseller = () => {
    setFeedback(null)
    setFormTarget("reseller")
    const pid = num(resellerPanelFilter !== "all" ? resellerPanelFilter : defaultPanelId)
    setForm(emptyForm(pid, firstCategoryForPanel(pid), num(resellerChoices[0]?.id)))
    setFormOpen(true)
  }

  const openAdd = () => {
    setFeedback(null)
    setFormTarget("site")
    const pid = num(panelFilter !== "all" ? panelFilter : defaultPanelId)
    const lines = wholesaleLines.filter((ln) => num(ln.panel_id) === pid)
    const f = emptyForm(pid, firstCategoryForPanel(pid), resellerMode ? actorSvpUserId : 0)
    if (resellerMode && lines.length === 1) f.wholesale_line_id = num(lines[0]?.id)
    setForm(f)
    setFormOpen(true)
  }

  const openEdit = (plan: DashRecord) => {
    setFeedback(null)
    setFormTarget(num(plan.owner_svp_user_id) > 0 ? "reseller" : "site")
    setForm(formFromPlan(plan))
    setFormOpen(true)
  }

  const run = async (params: Record<string, unknown>) => {
    setSaving(true)
    setFeedback(null)
    try {
      const res = await postAdminMutate("plan", params)
      if (!res.ok) {
        setFeedback(formatPlanMutateError(typeof res.code === "string" ? res.code : undefined, res.message ?? res.reason, t))
        return false
      }
      setFeedback(t("mutateSuccess"))
      await load()
      return true
    } catch {
      setFeedback(t("mutateError"))
      return false
    } finally {
      setSaving(false)
    }
  }

  const saveForm = async () => {
    if (!form.name.trim()) {
      setFeedback(t("validationName"))
      return
    }
    if (!form.category.trim()) {
      setFeedback(t("validationCategory"))
      return
    }
    if (!resellerMode && formTarget === "reseller" && form.owner_svp_user_id < 1) {
      setFeedback(t("pickReseller"))
      return
    }
    if (!resellerMode && formTarget === "site" && form.service_type === "xray" && form.inbound_ids.length < 1) {
      setFeedback(t("validationInbound"))
      return
    }
    if (resellerMode && form.service_type === "xray" && form.inbound_ids.length < 1 && form.inbound_id < 1) {
      setFeedback(t("validationInbound"))
      return
    }
    if (resellerMode && wholesaleLinesForPanel.length > 1 && form.wholesale_line_id < 1) {
      setFeedback(t("validationWholesaleLine"))
      return
    }
    if (form.plan_pricing_type === "fixed" && form.price <= 0) {
      setFeedback(t("validationPrice"))
      return
    }
    if (form.plan_pricing_type === "per_gb") {
      if (form.price_per_gb <= 0) {
        setFeedback(t("validationPricePerGb"))
        return
      }
      if (form.traffic_gb_min < 1 || form.traffic_gb_max < 1 || form.traffic_gb_min > form.traffic_gb_max) {
        setFeedback(t("validationTrafficRange"))
        return
      }
    }
    const ok = await run({
      plan_action: form.plan_id > 0 ? "update" : "add",
      plan_id: form.plan_id,
      ...formPayload(form),
    })
    if (ok) setFormOpen(false)
  }

  const togglePlan = (plan: DashRecord) => run({ plan_action: "toggle", plan_id: num(plan.id) })
  const deletePlan = async () => {
    if (!deleteTarget) return
    const ok = await run({ plan_action: "delete", plan_id: num(deleteTarget.id) })
    if (ok) setDeleteTarget(null)
  }

  const onFormPanelChange = (pid: number) => {
    setForm((f) => {
      const linesForPid = wholesaleLines.filter((ln) => num(ln.panel_id) === pid)
      const keepLine = linesForPid.some((ln) => num(ln.id) === f.wholesale_line_id)
      return {
        ...f,
        plan_panel_id: pid,
        category: firstCategoryForPanel(pid) || f.category,
        wholesale_line_id: keepLine ? f.wholesale_line_id : linesForPid.length === 1 ? num(linesForPid[0]?.id) : 0,
        inbound_ids: [],
        inbound_id: 0,
        panel_template_id: 0,
      }
    })
  }

  const renderPlanCard = (plan: DashRecord, keyPrefix = "") => {
    const id = num(plan.id)
    const pricingType = String(plan.pricing_type ?? "fixed")
    const ownerId = num(plan.owner_svp_user_id)
    const uc = planUserCount(plan)
    return (
      <Card key={`${keyPrefix}${id}`}>
        <CardHeader className="pb-2">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <CardTitle className="truncate text-base">{String(plan.name ?? "—")}</CardTitle>
              <CardDescription>
                {panelLabel(num(plan.panel_id))}
                {hasUserCountData ? ` · ${t("cardUsers")}: ${formatNumber(uc, isFa)}` : null}
              </CardDescription>
            </div>
            <Badge variant={isActive(plan) ? "default" : "secondary"}>{isActive(plan) ? t("active") : t("statusInactive")}</Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-3 text-sm">
          <div className="grid grid-cols-2 gap-2 text-xs">
            {ownerId > 0 ? <span>{t("pickReseller")}: #{formatNumber(ownerId, isFa)}</span> : null}
            <span>{t("category")}: {String(plan.category ?? "—")}</span>
            <span>{t("serviceType")}: {String(plan.service_type ?? "xray") === "l2tp" ? t("protocolL2tp") : t("protocolXray")}</span>
            <span>{t("cardTraffic")}: {formatNumber(num(plan.traffic_gb), isFa)} {t("gbSuffix")}</span>
            <span>{t("duration")}: {formatNumber(num(plan.duration_days), isFa)}</span>
            <span>
              {pricingType === "per_gb" ? t("pricePerGb") : t("price")}:{" "}
              {formatNumber(pricingType === "per_gb" ? num(plan.price_per_gb) : num(plan.price), isFa)}
            </span>
            <span>
              {t("inbound")}: {parseInboundIds(plan).map((iid) => `#${formatNumber(iid, isFa)}`).join(" · ") || "—"}
            </span>
            {String(plan.quota_display_mode ?? "show") === "hide_as_unlimited" ? (
              <span className="col-span-2">
                <Badge variant="outline">{t("quotaDisplayBadge")}</Badge>
              </span>
            ) : null}
          </div>
          <div className="flex flex-wrap justify-end gap-2">
            <Button type="button" size="sm" variant="outline" onClick={() => openEdit(plan)}>
              {t("edit")}
            </Button>
            <Button type="button" size="sm" variant="outline" disabled={saving} onClick={() => void togglePlan(plan)}>
              {t("toggle")}
            </Button>
            <Button type="button" size="sm" variant="destructive" onClick={() => setDeleteTarget(plan)}>
              {t("delete")}
            </Button>
          </div>
        </CardContent>
      </Card>
    )
  }

  const panelFilterSelect = (
    value: string,
    onChange: (v: string) => void,
    id?: string
  ) => (
    <div className="flex flex-wrap items-center gap-2">
      <Label htmlFor={id} className="text-xs text-muted-foreground">{t("filterPanel")}</Label>
      <select
        id={id}
        className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
        value={value}
        onChange={(e) => onChange(e.target.value)}
      >
        <option value="all">{t("filterAll")}</option>
        {panels.map((panel) => (
          <option key={num(panel.id)} value={num(panel.id)}>
            {panelLabel(num(panel.id))}
          </option>
        ))}
      </select>
    </div>
  )

  const showPlansBody = resellerMode || plansView === "plans"

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-xl font-semibold">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" disabled={loading} onClick={() => void load()}>
            {t("refresh")}
          </Button>
          {showPlansBody && !resellerMode ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="gap-1.5"
              onClick={() => setCatalogDialogOpen(true)}
            >
              <Settings2 className="size-4 shrink-0" aria-hidden />
              <span>{t("catalogDefaultsButton")}</span>
            </Button>
          ) : null}
          {showPlansBody && resellerMode ? (
            <Button type="button" size="sm" onClick={openAdd} disabled={panels.length < 1}>
              {t("addPlan")}
            </Button>
          ) : null}
          {showPlansBody && !resellerMode ? (
            <>
              <Button type="button" size="sm" variant="outline" onClick={openAddReseller}>
                {t("addResellerPlan")}
              </Button>
              <Button type="button" size="sm" onClick={openAddSite}>
                {t("addPlan")}
              </Button>
            </>
          ) : null}
        </div>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {feedback ? <p className="text-sm text-muted-foreground">{feedback}</p> : null}
      {loading ? <p className="text-sm text-muted-foreground">{t("loading")}</p> : null}

      {!resellerMode ? (
        <Tabs
          value={plansView}
          onValueChange={(v) => setPlansView(v === "wholesale" ? "wholesale" : "plans")}
        >
          <TabsList>
            <TabsTrigger value="plans">{t("tabPlans")}</TabsTrigger>
            <TabsTrigger value="wholesale">{t("tabWholesaleLines")}</TabsTrigger>
          </TabsList>

          <TabsContent value="wholesale" className="mt-4">
            <DashboardWholesaleLinesAdmin
              catalog={wholesaleLinesCatalog}
              panels={panels}
              l2tpServers={l2tpServers}
              onMutateSuccess={() => void load()}
            />
          </TabsContent>

          <TabsContent value="plans" className="mt-4 space-y-6">
            {renderPlansCatalog()}
          </TabsContent>
        </Tabs>
      ) : (
        <div className="space-y-6">{renderPlansCatalog()}</div>
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              {form.plan_id > 0
                ? t("editPlan")
                : formTarget === "reseller" && !resellerMode
                  ? t("addResellerPlan")
                  : t("addPlan")}
            </DialogTitle>
            <DialogDescription>{t("subtitle")}</DialogDescription>
          </DialogHeader>
          <div className="grid max-h-[65vh] gap-3 overflow-y-auto pe-1 sm:grid-cols-2">
            <div className="grid gap-1.5 sm:col-span-2">
              <Label>{t("planName")}</Label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            {!resellerMode && formTarget === "reseller" ? (
              <div className="grid gap-1.5 sm:col-span-2">
                <Label>{t("pickReseller")}</Label>
                {resellerChoices.length > 0 ? (
                  <select
                    className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                    value={form.owner_svp_user_id}
                    onChange={(e) => setForm((f) => ({ ...f, owner_svp_user_id: num(e.target.value) }))}
                  >
                    <option value={0}>—</option>
                    {resellerChoices.map((r) => (
                      <option key={num(r.id)} value={num(r.id)}>
                        {resellerChoiceLabel(r)}
                      </option>
                    ))}
                  </select>
                ) : (
                  <Input
                    type="number"
                    value={form.owner_svp_user_id || ""}
                    onChange={(e) => setForm((f) => ({ ...f, owner_svp_user_id: num(e.target.value) }))}
                    placeholder="#"
                  />
                )}
              </div>
            ) : null}
            <div className="grid gap-1.5">
              <Label>{t("panelLine")}</Label>
              <select
                className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                value={form.plan_panel_id}
                onChange={(e) => onFormPanelChange(num(e.target.value))}
              >
                {(resellerMode ? sellablePanels : panels).map((panel) => {
                  const canSell = panelCanSellPlan(panel, resellerMode)
                  const base = panelLabel(num(panel.id))
                  return (
                    <option key={num(panel.id)} value={num(panel.id)} disabled={resellerMode && !canSell}>
                      {`${base}${resellerMode && !canSell ? t("panelNoAccessSuffix") : ""}`}
                    </option>
                  )
                })}
              </select>
            </div>
            {resellerMode && wholesaleLinesForPanel.length > 0 ? (
              <div className="grid gap-1.5">
                <Label>{t("wholesaleLine")}</Label>
                <select
                  className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                  value={form.wholesale_line_id}
                  onChange={(e) => setForm((f) => ({ ...f, wholesale_line_id: num(e.target.value) }))}
                >
                  <option value={0}>—</option>
                  {wholesaleLinesForPanel.map((ln) => (
                    <option key={num(ln.id)} value={num(ln.id)}>
                      {String(ln.label ?? ln.id ?? "")}
                    </option>
                  ))}
                </select>
                <p className="text-xs text-muted-foreground">{t("wholesaleLineHint")}</p>
              </div>
            ) : null}
            <div className="grid gap-1.5">
              <Label>{t("category")}</Label>
              {categoriesForFormPanel.length > 0 ? (
                <select
                  className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                  value={form.category}
                  onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}
                >
                  <option value="">—</option>
                  {categoriesForFormPanel.map((c) => (
                    <option key={`${num(c.id)}-${String(c.slug)}`} value={String(c.slug ?? "")}>
                      {`${String(c.label ?? c.slug)} (${String(c.slug)})`}
                    </option>
                  ))}
                </select>
              ) : (
                <Input value={form.category} onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))} />
              )}
              {categoriesForFormPanel.length === 0 ? (
                <p className="text-xs text-amber-600 dark:text-amber-400">{t("noCategories")}</p>
              ) : null}
            </div>
            {resellerMode && floorForPanel ? (
              <div className="space-y-1 rounded-md border bg-muted/40 p-3 text-sm sm:col-span-2">
                <p className="font-medium text-foreground">{t("connectionPresetTitle")}</p>
                <p className="text-muted-foreground">{t("connectionPresetHint")}</p>
                <p className="tabular-nums">
                  {t("protocolXray")} · {t("inbound")}: #{formatNumber(num(floorForPanel.default_inbound_id), isFa)}
                </p>
              </div>
            ) : null}
            <div className="grid gap-1.5">
              <Label>{t("serviceType")}</Label>
              <select
                className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                value={form.service_type}
                onChange={(e) => setForm((f) => ({ ...f, service_type: e.target.value === "l2tp" ? "l2tp" : "xray" }))}
              >
                <option value="xray">{t("protocolXray")}</option>
                <option value="l2tp">{t("protocolL2tp")}</option>
              </select>
            </div>
            <div className="grid gap-1.5">
              <Label>{t("pricingType")}</Label>
              <select
                className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                value={form.plan_pricing_type}
                onChange={(e) =>
                  setForm((f) => ({ ...f, plan_pricing_type: e.target.value === "per_gb" ? "per_gb" : "fixed" }))
                }
              >
                <option value="fixed">{t("pricingFixed")}</option>
                <option value="per_gb">{t("pricingPerGb")}</option>
              </select>
            </div>
            {[
              ["traffic_gb", t("cardTraffic")],
              ["price", t("price")],
              ["price_per_gb", t("pricePerGb")],
              ["traffic_gb_min", t("trafficGbMin")],
              ["traffic_gb_max", t("trafficGbMax")],
              ["duration_days", t("duration")],
              ["clients_count", t("clients")],
              ["l2tp_server_id", t("l2tpServer")],
              ["sort_order", t("sortOrder")],
            ].map(([key, label]) => (
              <div key={key} className="grid gap-1.5">
                <Label>{label}</Label>
                <Input
                  type="number"
                  value={String(form[key as keyof PlanForm])}
                  onChange={(e) => setForm((f) => ({ ...f, [key]: num(e.target.value) }))}
                />
              </div>
            ))}
            {resellerMode && minPriceFloorPerGb > 0 ? (
              <p className="text-xs text-muted-foreground sm:col-span-2">
                {form.plan_pricing_type === "per_gb"
                  ? t("minPriceHintPerGb", { min: formatNumber(minPriceFloorPerGb, isFa) })
                  : t("minPriceHintFixed", {
                      min: formatNumber(minPriceFloorPerGb * Math.max(1, form.traffic_gb || 1), isFa),
                    })}
              </p>
            ) : null}
            {form.service_type === "xray" ? (
              <div className="grid gap-1.5 sm:col-span-2">
                <Label>{t("inboundPickerLabel")}</Label>
                <p className="text-xs text-muted-foreground">{t("inboundPickerHint")}</p>
                {panelInboundsBusy ? (
                  <p className="text-sm text-muted-foreground">{t("inboundPickerLoading")}</p>
                ) : panelInboundsError ? (
                  <p className="text-sm text-destructive">{panelInboundsError}</p>
                ) : panelInbounds.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t("inboundPickerEmpty")}</p>
                ) : (
                  <div className="max-h-48 space-y-1 overflow-y-auto rounded-md border border-border/60 bg-muted/20 p-2">
                    {panelInbounds.map((row) => {
                      const checked = form.inbound_ids.includes(row.id)
                      return (
                        <label
                          key={row.id}
                          className={`flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted/60${checked ? " bg-muted/80" : ""}`}
                        >
                          <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={checked}
                            onChange={() => toggleFormInbound(row.id)}
                          />
                          <span className="min-w-0 flex-1">{inboundOptionLabel(row)}</span>
                        </label>
                      )
                    })}
                  </div>
                )}
                {!resellerMode && isPgPlanPanel ? (
                  <div className="mt-3 space-y-2">
                    <Label>{t("templatePickerLabel")}</Label>
                    {panelTemplatesBusy ? (
                      <p className="text-sm text-muted-foreground">{t("templatePickerLoading")}</p>
                    ) : (
                      <select
                        className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm"
                        value={form.panel_template_id || ""}
                        onChange={(e) =>
                          setForm((f) => ({ ...f, panel_template_id: num(e.target.value) }))
                        }
                      >
                        <option value="">{t("templatePickerNone")}</option>
                        {panelTemplates.map((tpl) => (
                          <option key={tpl.id} value={tpl.id}>
                            {String(tpl.name ?? `#${tpl.id}`)}
                          </option>
                        ))}
                      </select>
                    )}
                    <p className="text-xs text-muted-foreground">{t("templatePickerHint")}</p>
                  </div>
                ) : null}
              </div>
            ) : null}
            <div className="grid gap-1.5 sm:col-span-2">
              <Label>{t("quotaDisplayMode")}</Label>
              <select
                className="h-8 rounded-lg border border-input bg-background px-2 text-sm"
                value={form.quota_display_mode}
                onChange={(e) =>
                  setForm((f) => ({
                    ...f,
                    quota_display_mode:
                      e.target.value === "hide_as_unlimited" ? "hide_as_unlimited" : "show",
                  }))
                }
              >
                <option value="show">{t("quotaDisplayShow")}</option>
                <option value="hide_as_unlimited">{t("quotaDisplayHide")}</option>
              </select>
              <p className="text-xs text-muted-foreground">{t("quotaDisplayHint")}</p>
            </div>
            <Label className="flex items-center gap-2 sm:col-span-2">
              <Switch checked={form.plan_active} onCheckedChange={(plan_active) => setForm((f) => ({ ...f, plan_active }))} />
              {t("active")}
            </Label>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
              {t("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void saveForm()}>
              {t("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={deleteTarget != null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>{t("deleteDescription")}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
              {t("deleteCancel")}
            </Button>
            <Button type="button" variant="destructive" disabled={saving} onClick={() => void deletePlan()}>
              {t("deleteConfirm")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {!resellerMode ? (
        <Dialog open={catalogDialogOpen} onOpenChange={setCatalogDialogOpen}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>{t("catalogDefaultsDialogTitle")}</DialogTitle>
              <DialogDescription>{t("catalogCardDesc")}</DialogDescription>
            </DialogHeader>
            {catalogError ? (
              <p className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {catalogError}
              </p>
            ) : null}
            <div className="grid gap-4">
              <div className="space-y-2">
                <Label htmlFor="catalog_concurrent_dialog">{t("catalogConcurrent")}</Label>
                <Input
                  id="catalog_concurrent_dialog"
                  type="number"
                  min={0}
                  value={catalogForm.default_concurrent_users}
                  onChange={(e) =>
                    setCatalogForm((f) => ({ ...f, default_concurrent_users: e.target.value }))
                  }
                />
                <p className="text-xs text-muted-foreground">{t("clientsCountHint")}</p>
              </div>
              <div className="space-y-2">
                <Label htmlFor="catalog_extra_price_dialog">{t("catalogExtraPrice")}</Label>
                <Input
                  id="catalog_extra_price_dialog"
                  type="text"
                  inputMode="decimal"
                  value={catalogForm.price_per_extra_user}
                  onChange={(e) =>
                    setCatalogForm((f) => ({ ...f, price_per_extra_user: e.target.value }))
                  }
                />
                {num(settings.default_concurrent_users) > 0 || String(settings.price_per_extra_user ?? "") !== "" ? (
                  <p className="text-xs text-muted-foreground">
                    {t("catalogDefaultsSummary", {
                      concurrent: formatNumber(num(settings.default_concurrent_users) || 2, isFa),
                      extra: formatNumber(num(settings.price_per_extra_user), isFa),
                    })}
                  </p>
                ) : null}
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setCatalogDialogOpen(false)}>
                {t("cancel")}
              </Button>
              <Button type="button" disabled={catalogSaving} onClick={() => void onSaveCatalogDefaults()}>
                {catalogSaving ? "…" : t("catalogSave")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      ) : null}
    </div>
  )

  function renderPlansCatalog() {
    return (
      <>
        {resellerMode && panels.length === 0 && wholesaleLines.length === 0 ? (
          <div className="space-y-3">
            <p className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-100">
              {t("resellerNoPanels")}
            </p>
            {actorSvpUserId > 0 ? (
              <p className="text-sm text-muted-foreground">{t("resellerNoPanelsHint", { svpUserId: actorSvpUserId })}</p>
            ) : null}
            {panelAccessDiagnostics ? (
              <div className="space-y-1 rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                <p className="font-medium text-foreground">{t("resellerPanelDiagTitle")}</p>
                <p>{t("resellerPanelDiagStored", { n: panelAccessDiagnostics.stored_rows ?? 0 })}</p>
                <p>{t("resellerPanelDiagJoinable", { n: panelAccessDiagnostics.joinable_rows ?? 0 })}</p>
                {(panelAccessDiagnostics.orphan_panel_ids?.length ?? 0) > 0 ? (
                  <p>{t("resellerPanelDiagOrphans", { ids: (panelAccessDiagnostics.orphan_panel_ids ?? []).join(", ") })}</p>
                ) : null}
                <p>{t("resellerPanelDiagInactive", { n: panelAccessDiagnostics.inactive_row_count ?? 0 })}</p>
              </div>
            ) : null}
          </div>
        ) : null}

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {[
            [t("statsTotal"), stats.total],
            [t("statsActive"), stats.active],
            [t("statsInactive"), stats.inactive],
            [t("statsXray"), stats.xray],
            [t("statsL2tp"), stats.l2tp],
          ].map(([label, value]) => (
            <Card key={String(label)}>
              <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{formatNumber(Number(value), isFa)}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>

        {resellerMode && wholesaleLines.length > 0 ? (
          <div className="space-y-3">
            <div>
              <h3 className="text-sm font-medium">{t("wholesaleLadderTitle")}</h3>
              <p className="text-xs text-muted-foreground">{t("wholesaleLadderRenewNote")}</p>
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
                      <CardTitle className="text-base">{t("wholesaleLadderLine", { label })}</CardTitle>
                      <CardDescription>
                        {t("wholesaleLadderTotals", {
                          gb: formatNumber(totalGb, isFa),
                          toman: formatNumber(totalToman, isFa),
                        })}
                      </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                      {curRate != null && Number(curRate) > 0 ? (
                        <p>{t("wholesaleLadderCurrent", { rate: formatNumber(Number(curRate), isFa) })}</p>
                      ) : null}
                      {nextRate != null && Number(nextRate) > 0 ? (
                        <p className="text-muted-foreground">
                          {t("wholesaleLadderNext", {
                            rate: formatNumber(Number(nextRate), isFa),
                            gb: formatNumber(Number(gbNeed ?? 0), isFa),
                            toman: formatNumber(Number(tomanNeed ?? 0), isFa),
                          })}
                        </p>
                      ) : (
                        <p className="text-muted-foreground">{t("wholesaleLadderMax")}</p>
                      )}
                      {tiers.length > 0 ? (
                        <ul className="space-y-1 text-xs text-muted-foreground">
                          {tiers.map((tier) => (
                            <li key={String(tier.id ?? tier.sort_order)}>
                              {formatNumber(Number(tier.price_per_gb ?? 0), isFa)}
                              {t("wholesaleLadderPerGb")}
                              {Number(tier.min_total_gb ?? 0) > 0
                                ? ` · ${t("wholesaleLadderTierMinGb", { gb: formatNumber(Number(tier.min_total_gb), isFa) })}`
                                : ""}
                              {Number(tier.min_total_toman ?? 0) > 0
                                ? ` · ${t("wholesaleLadderTierMinToman", { toman: formatNumber(Number(tier.min_total_toman), isFa) })}`
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
            {resellerMode ? (
              <>
                {panelFilterSelect(panelFilter, setPanelFilter, "reseller-panel-filter")}
                {filteredResellerActorPlans.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t("rankEmpty")}</p>
                ) : (
                  <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {filteredResellerActorPlans.map((plan) => renderPlanCard(plan))}
                  </div>
                )}
              </>
            ) : (
              <>
                <div className="space-y-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-base font-semibold">{t("resellerPlansSection")}</h3>
                    {panelFilterSelect(resellerPanelFilter, setResellerPanelFilter, "reseller-catalog-filter")}
                  </div>
                  {filteredResellerPlans.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t("rankEmpty")}</p>
                  ) : (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                      {filteredResellerPlans.map((plan) => renderPlanCard(plan, "r-"))}
                    </div>
                  )}
                </div>

                <div className="space-y-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-base font-semibold">{t("sitePlansSection")}</h3>
                    {panelFilterSelect(sitePanelFilter, setSitePanelFilter, "site-catalog-filter")}
                  </div>
                  {filteredSitePlans.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t("rankEmpty")}</p>
                  ) : (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                      {filteredSitePlans.map((plan) => renderPlanCard(plan, "s-"))}
                    </div>
                  )}
                </div>
              </>
            )}
            <DataPagination
              meta={pagination}
              isFa={isFa}
              onPageChange={(p) => setPage(p)}
              onPerPageChange={(n) => {
                setPerPage(n)
                setPage(1)
              }}
            />
          </div>

          {hasUserCountData ? (
            <aside className="min-w-0 xl:sticky xl:top-20 xl:self-start">
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t("rankTitle")}</CardTitle>
                  <CardDescription>{t("rankSubtitle")}</CardDescription>
                </CardHeader>
                <CardContent className="max-h-[70vh] space-y-2 overflow-y-auto pe-1">
                  {ranked.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t("rankEmpty")}</p>
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
                          {formatNumber(planUserCount(p), isFa)}
                        </span>
                      </div>
                    ))
                  )}
                </CardContent>
              </Card>
            </aside>
          ) : null}
        </div>
      </>
    )
  }
}
