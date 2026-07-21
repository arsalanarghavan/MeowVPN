"use client"

import { useLocale, useTranslations } from "next-intl"
import { useCallback, useEffect, useMemo, useState } from "react"
import {
  Ban,
  CheckCircle2,
  ExternalLink,
  Hash,
  MessageSquare,
  Minus,
  PackagePlus,
  Plus,
  Radio,
  RotateCcw,
  Send,
  ShieldCheck,
  ShieldOff,
  Store,
  UsersRound,
  Wallet,
  XCircle,
} from "lucide-react"

import {
  DashboardUserServiceCard,
  ServiceActionDialog,
  type ServiceActionDlg,
  type ServiceActionKind,
} from "@/components/dashboard-user-service-card"
import { apiBase, apiHeaders, normalizeAdminApiPath } from "@/lib/api"
import { DashPage } from "@/components/dash-page"
import { DashboardPageHeader } from "@/components/dashboard-page-header"

import {
  type PaymentsListFilters,
} from "@/components/dashboard-receipts-list"
import { DashboardPaymentsTabs } from "@/components/dashboard-payments-tabs"
import { DataPagination } from "@/components/data-pagination"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
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
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { adminMutateErrorText, getAdminState, postAdminMutate } from "@/lib/dash-admin-mutate"
import { buildDashboardTabUrl } from "@/lib/dash-tab"
import { DashTableShell, DashTd, DashTh } from "@/components/dash-data-table"
import { dashActionsClass } from "@/lib/dash-locale"
import {
  formatDateTime,
  formatDigits,
  formatNumber,
  formatPlainLatinInt,
} from "@/lib/format-locale"
import { DashSelect } from "@/components/dash-select"
import type { PaginationMeta } from "@/lib/dash-pagination"
import { parsePaginationMeta } from "@/lib/dash-pagination"
import { cn } from "@/lib/utils"
import type { BotPlatformId } from "@/config/bot-platforms"
import { BOT_PLATFORMS } from "@/config/bot-platforms"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"
import type { PaymentsView } from "@/lib/payments-view-subtab"

type DashRecord = Record<string, unknown>

const actionBar =
  "flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-muted/20 p-2"

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function displayName(u: DashRecord): string {
  const fn = String(u.first_name ?? "").trim()
  const ln = String(u.last_name ?? "").trim()
  const combined = `${fn} ${ln}`.trim()
  if (combined) return combined
  const un = String(u.username ?? "").trim()
  return un || "—"
}

function statusVariant(st: string): "default" | "secondary" | "destructive" | "outline" {
  if (st === "approved") return "default"
  if (st === "pending") return "secondary"
  if (st === "rejected") return "destructive"
  if (st === "blocked") return "outline"
  return "outline"
}

function isPerGbPlan(p: DashRecord | undefined): boolean {
  return String(p?.pricing_type ?? "") === "per_gb"
}

function previewCreatePriceToman(plan: DashRecord | undefined, volGbStr: string): number | null {
  if (!plan) return null
  const v = parseInt(volGbStr.trim(), 10)
  if (isPerGbPlan(plan)) {
    if (!Number.isFinite(v) || v < 1) return null
    const min = num(plan.traffic_gb_min)
    const max = num(plan.traffic_gb_max)
    if (min > 0 && max > 0 && (v < min || v > max)) return null
    return Math.round(num(plan.price_per_gb) * v * 100) / 100
  }
  return Math.round(num(plan.price) * 100) / 100
}

function formatActivityChannel(
  channel: unknown,
  tp: (k: string) => string
): string {
  const c = String(channel ?? "").toLowerCase()
  if (c === "telegram") return tp("channelTelegram")
  if (c === "bale") return tp("channelBale")
  if (c === "rest") return tp("channelRest")
  return String(channel ?? "")
}

function formatUserActivityLine(
  row: DashRecord,
  t: (k: string, opts?: Record<string, string | number>) => string
): string {
  const display = String(row.summary_display ?? "").trim()
  if (display) return display

  const ev = String(row.event_type ?? "")
  const pl =
    row.payload && typeof row.payload === "object"
      ? (row.payload as Record<string, unknown>)
      : {}
  const g = (k: string) => String(pl[k] ?? "")
  const gn = (k: string) => num(pl[k])

  switch (ev) {
    case "balance_delta":
      return t("activity_balance_delta", {
        delta: formatPlainLatinInt(Math.round(gn("delta") * 100) / 100),
        after: formatPlainLatinInt(Math.round(gn("balance_after") * 100) / 100),
      })
    case "service_create":
      return t("activity_service_create", {
        plan: g("plan_id"),
        mode: g("mode"),
        service: g("service_id"),
      })
    case "service_renew":
      return t("activity_service_renew", { service: g("service_id"), mode: g("mode") })
    case "service_add_volume":
      return t("activity_service_add_volume", {
        service: g("service_id"),
        gb: g("extra_gb"),
        mode: g("mode"),
      })
    case "service_reduce_volume":
      return t("activity_service_reduce_volume", {
        service: g("service_id"),
        gb: g("reduce_gb"),
      })
    case "service_reduce_days":
      return t("activity_service_reduce_days", {
        service: g("service_id"),
        days: g("days"),
      })
    case "service_add_days":
      return t("activity_service_add_days", {
        service: g("service_id"),
        days: g("days"),
      })
    case "service_transfer_out":
      return t("activity_service_transfer_out", {
        service: g("service_id"),
        target: g("target_id") || g("target_raw"),
      })
    case "service_transfer_in":
      return t("activity_service_transfer_in", {
        service: g("service_id"),
        from: g("previous_user"),
      })
    case "service_soft_delete":
      return t("activity_service_soft_delete", { service: g("service_id") })
    case "user_merge":
    case "link_wp_user":
      return t("activity_user_merge", {
        defaultValue: "Merged users {{drop}} → {{keep}}",
        keep: String(g("keep_id") || g("user_id") || ""),
        drop: String(g("drop_id") || g("wp_user_id") || ""),
      })
    case "user_ban":
      return t("activity_user_ban")
    case "user_unban":
      return t("activity_user_unban")
    case "admin_message":
      return t("activity_admin_message", { channel: g("channel"), len: gn("length") })
    case "service_panel_sync":
      return t("activity_service_panel_sync", { service: g("service_id") })
    case "service_regen_key":
      return t("activity_service_regen_key", { service: g("service_id") })
    case "service_regen_sub_id":
      return t("activity_service_regen_sub_id", { service: g("service_id") })
    case "service_panel_refresh":
      return t("activity_service_panel_refresh", { service: g("service_id") })
    case "service_panel_delete_client":
      return t("activity_service_panel_delete_client", { service: g("service_id") })
    case "service_add_user_slots":
      return t("activity_service_add_user_slots", {
        service: g("service_id"),
        n: g("extra_users"),
      })
    case "service_reduce_user_slots":
      return t("activity_service_reduce_user_slots", {
        service: g("service_id"),
        n: g("reduce_users"),
      })
    case "service_set_limit_ip":
      return t("activity_service_set_limit_ip", {
        service: g("service_id"),
        n: g("limit_ip"),
      })
    case "service_alerts_patch":
      return t("activity_service_alerts_patch", { service: g("service_id") })
    case "service_set_note":
      return t("activity_service_set_note", { service: g("service_id") })
    case "user_role_change":
      return t("activity_user_role_change", { role: g("role") })
    case "user_set_referrer":
      return t("activity_user_set_referrer", { referrer: g("referrer_id") })
    case "service_toggle_enable":
      return t("activity_service_toggle_enable", {
        service: g("service_id"),
        state: num(g("enable")) === 1 ? t("enableOn") : t("enableOff"),
      })
    case "callback_query":
      return t("activity_callback_query", { data: g("callback_data").slice(0, 80) })
    case "command":
      return t("activity_command", { cmd: g("command") })
    case "message":
      return t("activity_message", { preview: g("text_preview").slice(0, 120) })
    default:
      return t("activity_generic", { event: ev || "—" })
  }
}

export function UserDetailAdmin({
  userId,
  plans,
  planCategories = [],
  settings,
  isReseller = false,
  readOnlyAdminActions = false,
  actorPermissions,
  onBack,
  onMutateSuccess,
  onOpenUserDetail,
  enabledPlatforms = BOT_PLATFORMS.map((p) => p.id),
  canReviewReceipts = true,
}: {
  userId: number
  plans: DashRecord[]
  planCategories?: DashRecord[]
  settings?: DashRecord
/** When true, «free» payment mode is hidden (reseller pays from wallet / invoice only). */
  isReseller?: boolean
  /** When true, hide mutation controls (defense-in-depth; REST remains authoritative). */
  readOnlyAdminActions?: boolean
  actorPermissions?: Record<string, boolean>
  onBack: () => void
  onMutateSuccess?: () => void
  onOpenUserDetail?: (id: number) => void
  enabledPlatforms?: BotPlatformId[]
  canReviewReceipts?: boolean
}) {
  const { isFa } = useDashLocale()
  const locale = useLocale()
  const dashboardBaseUrl = `/${locale}/dashboard`

  const t = useTranslations("userDetailAdmin")
  const tUsers = useTranslations("usersAdmin")
  const tPay = useTranslations("paymentsAdmin")
  const tp = (k: string, opts?: Record<string, string | number>) => t(`${k}`, opts)

  const canManageUsers =
    !readOnlyAdminActions && (!isReseller || actorPermissions?.["users.manage"] === true)
  const canManageServices =
    !readOnlyAdminActions && (!isReseller || actorPermissions?.["services.manage"] === true)
  const canSiteAdminOnly = !readOnlyAdminActions && !isReseller

  const [loading, setLoading] = useState(true)
  const [err, setErr] = useState<string | null>(null)
  const [user, setUser] = useState<DashRecord | null>(null)
  const [services, setServices] = useState<DashRecord[]>([])
  const [referrals, setReferrals] = useState<DashRecord[]>([])
  const [marketingOffers, setMarketingOffers] = useState<DashRecord[]>([])
  const [activity, setActivity] = useState<DashRecord[]>([])
  const [actPage, setActPage] = useState(1)
  const [actMeta, setActMeta] = useState<PaginationMeta | null>(null)
  const [receipts, setReceipts] = useState<DashRecord[]>([])
  const [receiptAggregates, setReceiptAggregates] = useState<unknown>([])
  const [rcptPage, setRcptPage] = useState(1)
  const [rcptPerPage, setRcptPerPage] = useState(20)
  const [rcptMeta, setRcptMeta] = useState<PaginationMeta | null>(null)
  const [rcptFilters, setRcptFilters] = useState<PaymentsListFilters>({
    q: "",
    status: "all",
    type: "",
    method: "",
    sort: "created_desc",
    dateFrom: "",
    dateTo: "",
    amountMin: "",
    amountMax: "",
  })
  const [paymentsView, setPaymentsView] = useState<PaymentsView>("receipts")
  const [userPayments, setUserPayments] = useState<DashRecord[]>([])
  const [userOrders, setUserOrders] = useState<DashRecord[]>([])
  const [txPage, setTxPage] = useState(1)
  const [txPerPage, setTxPerPage] = useState(20)
  const [txMeta, setTxMeta] = useState<PaginationMeta | null>(null)
  const [ordersPage, setOrdersPage] = useState(1)
  const [ordersPerPage, setOrdersPerPage] = useState(20)
  const [ordersMeta, setOrdersMeta] = useState<PaginationMeta | null>(null)
  const [paymentsLoading, setPaymentsLoading] = useState(false)
  const [txFilters, setTxFilters] = useState<PaymentsListFilters>({
    q: "",
    status: "all",
    type: "",
    method: "",
    sort: "created_desc",
    dateFrom: "",
    dateTo: "",
    amountMin: "",
    amountMax: "",
  })
  const [busy, setBusy] = useState(false)
  const [alertText, setAlertText] = useState<string | null>(null)

  const [planId, setPlanId] = useState("")
  const [categorySlug, setCategorySlug] = useState("")
  const [volGb, setVolGb] = useState("")
  const [createMode, setCreateMode] = useState<"free" | "wallet" | "invoice">(() =>
    isReseller ? "wallet" : "free"
  )
  const [walletDialog, setWalletDialog] = useState<null | "add" | "sub">(null)
  const [walletAmount, setWalletAmount] = useState("")
  const [adminMsg, setAdminMsg] = useState("")
  const showTgPlatform = enabledPlatforms.includes("telegram")
  const showBalePlatform = enabledPlatforms.includes("bale")
  const defaultMsgChannel = showTgPlatform && showBalePlatform ? "both" : showTgPlatform ? "telegram" : "bale"
  const [adminMsgChannel, setAdminMsgChannel] = useState<"both" | "telegram" | "bale">(defaultMsgChannel as "both" | "telegram" | "bale")
  const [actionDlg, setActionDlg] = useState<ServiceActionDlg>(null)
  const [resellerChoices, setResellerChoices] = useState<Array<{ id: number; label: string }>>([])
  const [assignResellerOpen, setAssignResellerOpen] = useState(false)
  const [pickResellerId, setPickResellerId] = useState("")
  const [rolePick, setRolePick] = useState("")
  const [referrerQuery, setReferrerQuery] = useState("")
  const [referrerHits, setReferrerHits] = useState<DashRecord[]>([])
  const [pickReferrerId, setPickReferrerId] = useState("")
  const [loadedPlanCats, setLoadedPlanCats] = useState<DashRecord[]>([])
  const [roleDialogOpen, setRoleDialogOpen] = useState(false)
  const [referrerDialogOpen, setReferrerDialogOpen] = useState(false)
  const [messageDialogOpen, setMessageDialogOpen] = useState(false)

  const pricePerExtraUser = num(settings?.price_per_extra_user)
  const l2tpEnabled = useMemo(() => {
    const f = settings?.features
    return !!(f && typeof f === "object" && (f as Record<string, unknown>).l2tp === true)
  }, [settings?.features])

  const restBase = apiBase()
  const load = useCallback(async () => {
    if (!restBase || userId < 1) return
    setLoading(true)
    setErr(null)
    try {
      const sp = new URLSearchParams()
      sp.set("activity_page", String(actPage))
      sp.set("activity_per_page", "20")
      sp.set("receipts_page", String(rcptPage))
      sp.set("receipts_per_page", String(rcptPerPage))
      if (rcptFilters.q.trim()) sp.set("receipts_q", rcptFilters.q.trim())
      if (rcptFilters.status && rcptFilters.status !== "all") sp.set("receipts_status", rcptFilters.status)
      if (rcptFilters.sort) sp.set("receipts_sort", rcptFilters.sort)
      if (rcptFilters.dateFrom.trim()) sp.set("receipts_date_from", rcptFilters.dateFrom.trim())
      if (rcptFilters.dateTo.trim()) sp.set("receipts_date_to", rcptFilters.dateTo.trim())
      if (rcptFilters.amountMin.trim()) sp.set("receipts_amount_min", rcptFilters.amountMin.trim())
      if (rcptFilters.amountMax.trim()) sp.set("receipts_amount_max", rcptFilters.amountMax.trim())
      if (rcptFilters.type.trim()) sp.set("receipts_type", rcptFilters.type.trim())
      if (rcptFilters.method.trim()) sp.set("receipts_method", rcptFilters.method.trim())
      sp.set("lang", isFa ? "fa" : "en")
      const r = await fetch(
        `${restBase}${normalizeAdminApiPath(`/dashboard/admin/user/${userId}`)}?${sp.toString()}`,
        {
          headers: apiHeaders(),
          credentials: "include",
        }
      )
      const json = (await r.json()) as Record<string, unknown>
      if (!r.ok || !json.ok) {
        setErr(String(json.message || "not_found"))
        setUser(null)
        return
      }
      setUser((json.user as DashRecord) || null)
      const rc = json.resellerChoices
      setResellerChoices(
        Array.isArray(rc)
          ? (rc as Array<Record<string, unknown>>)
              .map((r) => ({ id: num(r.id), label: String(r.label ?? "") }))
              .filter((r) => r.id > 0)
          : []
      )
      setServices(Array.isArray(json.services) ? (json.services as DashRecord[]) : [])
      const apiCats = json.planCategories
      setLoadedPlanCats(Array.isArray(apiCats) ? (apiCats as DashRecord[]) : [])
      setReferrals(Array.isArray(json.referrals) ? (json.referrals as DashRecord[]) : [])
      setMarketingOffers(
        Array.isArray(json.marketingOffers) ? (json.marketingOffers as DashRecord[]) : []
      )
      setActivity(Array.isArray(json.activity) ? (json.activity as DashRecord[]) : [])
      const pag = json.activityPagination
      setActMeta(parsePaginationMeta(pag))
      setReceipts(Array.isArray(json.receipts) ? (json.receipts as DashRecord[]) : [])
      setReceiptAggregates(json.receiptAggregates ?? [])
      setRcptMeta(parsePaginationMeta(json.receiptsPagination))
    } catch {
      setErr(t("loadError"))
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [restBase, userId, actPage, rcptPage, rcptPerPage, rcptFilters, isFa])

  useEffect(() => {
    void load()
  }, [restBase, userId, actPage, rcptPage, rcptPerPage, rcptFilters, load])

  const onRcptFiltersChange = useCallback((patch: Partial<PaymentsListFilters>) => {
    setRcptFilters((f: PaymentsListFilters) => ({ ...f, ...patch }))
    setRcptPage(1)
  }, [])

  const onTxFiltersChange = useCallback((patch: Partial<PaymentsListFilters>) => {
    setTxFilters((f) => ({ ...f, ...patch }))
    setTxPage(1)
    setOrdersPage(1)
  }, [])

  const loadUserPayments = useCallback(async () => {
    if (userId < 1 || paymentsView === "receipts") return
    setPaymentsLoading(true)
    try {
      const query: Record<string, string | number> = {
        payments_view: paymentsView,
        payments_q: String(userId),
      }
      if (paymentsView === "transactions") {
        query.transactions_page = txPage
        query.transactions_per_page = txPerPage
      } else {
        query.orders_page = ordersPage
        query.orders_per_page = ordersPerPage
      }
      const data = await getAdminState("payments", query)
      if (paymentsView === "transactions") {
        setUserPayments(Array.isArray(data.payments) ? (data.payments as DashRecord[]) : [])
        const pag = data.pagination as DashRecord | undefined
        setTxMeta(
          parsePaginationMeta(pag?.transactions ?? pag?.payments ?? data.paymentsPagination)
        )
      } else {
        setUserOrders(Array.isArray(data.orders) ? (data.orders as DashRecord[]) : [])
        const pag = data.pagination as DashRecord | undefined
        setOrdersMeta(parsePaginationMeta(pag?.orders ?? data.ordersPagination))
      }
    } catch {
      if (paymentsView === "transactions") setUserPayments([])
      else setUserOrders([])
    } finally {
      setPaymentsLoading(false)
    }
  }, [ordersPage, ordersPerPage, paymentsView, txPage, txPerPage, userId])

  useEffect(() => {
    void loadUserPayments()
  }, [loadUserPayments])

  const onReceiptMutateSuccess = useCallback(async () => {
    await load()
    onMutateSuccess?.()
  }, [load, onMutateSuccess])

  useEffect(() => {
    if (!user) return
    setRolePick(String(user.effective_role ?? user.role ?? "user"))
  }, [user])

  const activePlans = useMemo(
    () =>
      plans.filter((p) => {
        if (num(p.active) !== 1 || num(p.id) < 1) return false
        if (!l2tpEnabled && String(p.service_type ?? "xray") === "l2tp") return false
        return true
      }),
    [plans, l2tpEnabled]
  )

  const effectivePlanCategories = useMemo(
    () => (planCategories.length > 0 ? planCategories : loadedPlanCats),
    [planCategories, loadedPlanCats]
  )

  const activeCategories = useMemo(
    () =>
      effectivePlanCategories.filter((c) => num(c.active) === 1 && String(c.slug ?? "").trim() !== ""),
    [effectivePlanCategories]
  )

  const categoryPlans = useMemo(() => {
    if (!categorySlug) return []
    return activePlans.filter((p) => String(p.category ?? "") === categorySlug)
  }, [activePlans, categorySlug])

  const selectedPlan = useMemo(
    () => categoryPlans.find((p) => String(num(p.id)) === planId) ?? activePlans.find((p) => String(num(p.id)) === planId),
    [categoryPlans, activePlans, planId]
  )

  const visibleServices = useMemo(
    () =>
      services.filter((s) => l2tpEnabled || String(s.service_type ?? "xray") !== "l2tp"),
    [services, l2tpEnabled]
  )

  const createPricePreview = useMemo(
    () => previewCreatePriceToman(selectedPlan, volGb),
    [selectedPlan, volGb]
  )

  useEffect(() => {
    if (!restBase || referrerQuery.trim().length < 2) {
      setReferrerHits([])
      return
    }
    const q = referrerQuery.trim()
    const t = window.setTimeout(() => {
      void fetch(
        `${restBase}${normalizeAdminApiPath("/dashboard/admin/user-search")}?q=${encodeURIComponent(q)}`,
        {
          headers: apiHeaders(),
          credentials: "include",
        }
      )
        .then((r) => r.json())
        .then((json) => {
          const rows = Array.isArray((json as Record<string, unknown>).users)
            ? ((json as Record<string, unknown>).users as DashRecord[])
            : []
          setReferrerHits(rows.filter((u) => num(u.id) !== userId))
        })
        .catch(() => setReferrerHits([]))
    }, 280)
    return () => window.clearTimeout(t)
  }, [restBase, referrerQuery, userId])

  const runMut = useCallback(
    async (op: string, params: Record<string, unknown>, okMsg?: string) => {
      setBusy(true)
      setAlertText(null)
      try {
        const res = await postAdminMutate(op, params)
        if (!res.ok) {
          setAlertText(adminMutateErrorText(res, t("mutateError")))
          return
        }
        if (okMsg) setAlertText(okMsg)
        setWalletAmount("")
        await load()
        onMutateSuccess?.()
      } finally {
        setBusy(false)
      }
    },
    [load, onMutateSuccess, t]
  )

  const applyWalletDelta = useCallback(
    (sign: 1 | -1) => {
      const v = parseFloat(walletAmount.replace(/,/g, ".").trim())
      if (!Number.isFinite(v) || v <= 0) return
      void runMut("user_balance_delta", { svp_user_id: num(user?.id), delta: sign * v })
      setWalletDialog(null)
    },
    [runMut, walletAmount, user]
  )

  if (loading && !user) {
    return <p className="text-sm text-muted-foreground">{t("loading")}</p>
  }
  if (err || !user) {
    return (
      <div className="space-y-3">
        <Button type="button" variant="outline" size="sm" onClick={onBack}>
          {t("back")}
        </Button>
        <p className="text-sm text-destructive">{err || t("notFound")}</p>
      </div>
    )
  }

  const uid = num(user.id)
  const st = String(user.status ?? "")
  const bal = num(user.balance)
  const portalUserUrl = String(user.portal_url ?? "")
  const userRole = String(user.role ?? "")
  const effectiveRole = String(user.effective_role ?? userRole)
  const invitedBy = num(user.invited_by)
  const invitedByLabel = String(user.invited_by_label ?? "").trim()
  const showAssignReseller = !isReseller && userRole !== "reseller" && resellerChoices.length > 0

  return (
    <TooltipProvider delay={200}>
      <Dialog open={roleDialogOpen} onOpenChange={setRoleDialogOpen}>
        <DashDialogContent showCloseButton>
          <DashDialogHeader>
            <DialogTitle>{t("roleLabel")}</DialogTitle>
            <DialogDescription>{t("roleApply")}</DialogDescription>
          </DashDialogHeader>
          <div className="space-y-3">
            <DashSelect
              value={rolePick || effectiveRole}
              onValueChange={setRolePick}
              disabled={busy}
              options={[
                { value: "user", label: t("roleUser") },
                { value: "reseller", label: t("roleReseller") },
                { value: "admin", label: t("roleAdmin") },
              ]}
            />
          </div>
          <DashDialogFooter className="gap-2">
            <Button type="button" variant="outline" onClick={() => setRoleDialogOpen(false)} disabled={busy}>
              {t("walletDialogCancel")}
            </Button>
            <Button
              type="button"
              disabled={busy || (rolePick || effectiveRole) === effectiveRole}
              onClick={async () => {
                await runMut("user_set_role", {
                  target_user_id: uid,
                  role: rolePick || effectiveRole,
                })
                setRoleDialogOpen(false)
              }}
            >
              {t("roleApply")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={referrerDialogOpen} onOpenChange={setReferrerDialogOpen}>
        <DashDialogContent showCloseButton>
          <DashDialogHeader>
            <DialogTitle>{t("referrerTitle")}</DialogTitle>
            <DialogDescription>
              {invitedBy > 0
                ? t("referrerFrom", {
                    name: invitedByLabel || "—",
                    id: formatPlainLatinInt(invitedBy),
                  })
                : t("referrerNone")}
            </DialogDescription>
          </DashDialogHeader>
          <div className="space-y-3 text-sm">
            <Input
              dir="ltr"
              value={referrerQuery}
              onChange={(e) => setReferrerQuery(e.target.value)}
              placeholder={t("referrerSearchPlaceholder")}
              disabled={busy}
            />
            {referrerHits.length > 0 ? (
              <DashSelect
                value={pickReferrerId}
                onValueChange={setPickReferrerId}
                disabled={busy}
                allowEmpty
                placeholder={`${t("referrerSearch")}…`}
                options={referrerHits.map((hit) => ({
                  value: String(num(hit.id)),
                  label: `${displayName(hit)} (#${formatPlainLatinInt(num(hit.id))})`,
                }))}
              />
            ) : null}
          </div>
          <DashDialogFooter className="gap-2">
            {invitedBy > 0 ? (
              <Button
                type="button"
                variant="outline"
                disabled={busy}
                onClick={async () => {
                  await runMut("user_set_referrer", { target_user_id: uid, referrer_id: 0 })
                  setReferrerDialogOpen(false)
                }}
              >
                {t("referrerRemove")}
              </Button>
            ) : null}
            <Button type="button" variant="outline" onClick={() => setReferrerDialogOpen(false)} disabled={busy}>
              {t("walletDialogCancel")}
            </Button>
            <Button
              type="button"
              disabled={busy || !pickReferrerId}
              onClick={async () => {
                await runMut("user_set_referrer", {
                  target_user_id: uid,
                  referrer_id: parseInt(pickReferrerId, 10),
                })
                setReferrerDialogOpen(false)
              }}
            >
              {t("referrerSet")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={messageDialogOpen} onOpenChange={setMessageDialogOpen}>
        <DashDialogContent showCloseButton>
          <DashDialogHeader>
            <DialogTitle>{t("adminMessageTitle")}</DialogTitle>
            <DialogDescription>{t("adminMessagePlaceholder")}</DialogDescription>
          </DashDialogHeader>
          <div className="space-y-3">
            <textarea
              className="min-h-[5rem] w-full rounded-md border border-input bg-background px-2 py-1.5 text-sm"
              value={adminMsg}
              onChange={(e) => setAdminMsg(e.target.value)}
              disabled={busy}
              placeholder={t("adminMessagePlaceholder")}
            />
            <DashSelect
              value={adminMsgChannel}
              onValueChange={(v) => setAdminMsgChannel(v as typeof adminMsgChannel)}
              disabled={busy}
              options={[
                ...(showTgPlatform && showBalePlatform ? [{ value: "both", label: t("msgChannelBoth") }] : []),
                ...(showTgPlatform ? [{ value: "telegram", label: t("msgChannelTelegram") }] : []),
                ...(showBalePlatform ? [{ value: "bale", label: t("msgChannelBale") }] : []),
              ]}
            />
          </div>
          <DashDialogFooter className="gap-2">
            <Button type="button" variant="outline" onClick={() => setMessageDialogOpen(false)} disabled={busy}>
              {t("walletDialogCancel")}
            </Button>
            <Button
              type="button"
              disabled={busy || !adminMsg.trim()}
              onClick={async () => {
                await runMut("user_admin_message", {
                  user_id: uid,
                  text: adminMsg.trim(),
                  channel: adminMsgChannel,
                })
                setAdminMsg("")
                setMessageDialogOpen(false)
              }}
            >
              {t("adminMessageSend")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={assignResellerOpen} onOpenChange={setAssignResellerOpen}>
        <DashDialogContent showCloseButton>
          <DashDialogHeader>
            <DialogTitle>{t("assignResellerTitle")}</DialogTitle>
            <DialogDescription>{t("assignResellerHint")}</DialogDescription>
          </DashDialogHeader>
          <div className="space-y-3 text-sm">
            <p>
              <span className="text-muted-foreground">{t("currentReseller")}: </span>
              {invitedBy > 0
                ? invitedByLabel || `#${formatPlainLatinInt(invitedBy)}`
                : t("resellerNone")}
            </p>
            <div className="space-y-2">
              <Label htmlFor="assign-reseller-pick">{t("assignResellerPick")}</Label>
              <DashSelect
                id="assign-reseller-pick"
                value={pickResellerId}
                onValueChange={setPickResellerId}
                disabled={busy}
                allowEmpty
                placeholder={`${t("assignResellerPick")}…`}
                options={resellerChoices.map((r) => ({
                  value: String(r.id),
                  label: `${r.label} (#${formatPlainLatinInt(r.id)})`,
                }))}
              />
            </div>
          </div>
          <DashDialogFooter className={cn("gap-2")}>
            {invitedBy > 0 ? (
              <Button
                type="button"
                variant="outline"
                disabled={busy}
                onClick={async () => {
                  await runMut(
                    "reseller_bind_users",
                    { reseller_svp_user_id: 0, user_ids: [uid], mode: "clear" },
                    t("assignResellerClear")
                  )
                  setAssignResellerOpen(false)
                }}
              >
                {t("assignResellerClear")}
              </Button>
            ) : null}
            <Button type="button" variant="outline" onClick={() => setAssignResellerOpen(false)} disabled={busy}>
              {t("walletDialogCancel")}
            </Button>
            <Button
              type="button"
              disabled={busy || !pickResellerId}
              onClick={async () => {
                const rid = parseInt(pickResellerId, 10)
                if (!Number.isFinite(rid) || rid < 1) return
                await runMut(
                  "reseller_bind_users",
                  { reseller_svp_user_id: rid, user_ids: [uid], mode: "set" },
                  t("assignResellerApply")
                )
                setAssignResellerOpen(false)
              }}
            >
              {t("assignResellerApply")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <Dialog open={walletDialog !== null} onOpenChange={(o) => !o && setWalletDialog(null)}>
        <DashDialogContent showCloseButton>
          <DashDialogHeader>
            <DialogTitle>
              {walletDialog === "add" ? t("walletDialogAddTitle") : t("walletDialogSubTitle")}
            </DialogTitle>
            <DialogDescription>{t("walletDialogHint")}</DialogDescription>
          </DashDialogHeader>
          <div className="space-y-2">
            <Label htmlFor="w-amt">{t("walletDialogAmount")}</Label>
            <Input
              id="w-amt"
              dir="ltr"
              className="font-mono"
              value={walletAmount}
              onChange={(e) => setWalletAmount(e.target.value)}
              disabled={busy}
            />
          </div>
          <DashDialogFooter>
            <Button type="button" variant="outline" onClick={() => setWalletDialog(null)} disabled={busy}>
              {t("walletDialogCancel")}
            </Button>
            <Button
              type="button"
              onClick={() => applyWalletDelta(walletDialog === "add" ? 1 : -1)}
              disabled={busy}
            >
              {t("walletDialogConfirm")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>

      <ServiceActionDialog
        dlg={actionDlg}
        setDlg={setActionDlg}
        isReseller={isReseller}
        busy={busy}
        plans={plans}
        pricePerExtraUser={pricePerExtraUser}
        tp={tp}
        onConfirm={(op: string, payload: Record<string, unknown>) => void runMut(op, payload)}
      />

      <DashPage className="mx-auto w-full min-w-0 max-w-7xl">
        <DashboardPageHeader
          title={t("title")}
          actions={
            <Button type="button" variant="outline" size="sm" onClick={onBack}>
              {t("back")}
            </Button>
          }
        />

        {alertText ? (
          <div
            role="status"
            className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-foreground"
          >
            {alertText}
          </div>
        ) : null}

        <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
          <Card>
            <CardHeader className="pb-2">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 flex-1 text-start">
                  <CardTitle className="text-base">{displayName(user)}</CardTitle>
                  <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-start text-xs text-muted-foreground">
                      <span className="inline-flex items-center gap-1">
                        <Hash className="size-3.5 shrink-0 opacity-70" aria-hidden />
                        <span>
                          {t("labelInternalId")}{" "}
                          <span className="font-mono tabular-nums" dir="ltr">
                            {formatDigits(`#${formatPlainLatinInt(uid)}`, isFa)}
                          </span>
                        </span>
                      </span>
                      <span className="text-muted-foreground" aria-hidden>
                        ·
                      </span>
                      {showTgPlatform ? (
                      <>
                      <span className="inline-flex items-center gap-1">
                        <Send className="size-3.5 shrink-0 opacity-70" aria-hidden />
                        <span>
                          {t("labelTelegram")}{" "}
                          <span className="font-mono tabular-nums" dir="ltr">
                            {formatDigits(formatPlainLatinInt(num(user.tg_user_id)), isFa)}
                          </span>
                        </span>
                      </span>
                      {showBalePlatform ? (
                      <span className="text-muted-foreground" aria-hidden>
                        ·
                      </span>
                      ) : null}
                      </>
                      ) : null}
                      {showBalePlatform ? (
                      <span className="inline-flex items-center gap-1">
                        <Radio className="size-3.5 shrink-0 opacity-70" aria-hidden />
                        <span>
                          {t("labelBale")}{" "}
                          <span className="font-mono tabular-nums" dir="ltr">
                            {formatDigits(formatPlainLatinInt(num(user.bale_user_id)), isFa)}
                          </span>
                        </span>
                      </span>
                      ) : null}
                    </div>
                </div>
                <Badge variant={statusVariant(st)}>{tUsers(`status_${st}`, { defaultValue: st })}</Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-4 text-sm text-start">
              <div className="flex flex-wrap items-center gap-2">
                <Wallet className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                <span className="text-muted-foreground">{t("balance")}:</span>
                <span className="font-medium tabular-nums" dir="ltr">
                  {formatNumber(bal, isFa)}
                </span>
              </div>

              <div className={dashActionsClass()}>
                {canManageUsers ? (
                  <>
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  className="gap-2"
                  disabled={busy}
                  onClick={() => {
                    setWalletAmount("")
                    setWalletDialog("add")
                  }}
                >
                  <Plus className="size-4" aria-hidden />
                  {t("walletIncrease")}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-2"
                  disabled={busy}
                  onClick={() => {
                    setWalletAmount("")
                    setWalletDialog("sub")
                  }}
                >
                  <Minus className="size-4" aria-hidden />
                  {t("walletDecrease")}
                </Button>
                  </>
                ) : null}
              </div>
              {canManageUsers ? (
              <p className="text-xs text-muted-foreground">{t("walletDeltaHint")}</p>
              ) : null}

              {canManageUsers || canSiteAdminOnly ? (
              <div>
                <p className="mb-2 text-start text-xs font-medium text-muted-foreground">
                  {t("adminActions")}
                </p>
                <div className={actionBar}>
                  <div className={dashActionsClass()}>
                  {canManageUsers && st === "pending" ? (
                    <>
                      <Tooltip>
                        <TooltipTrigger
              render={
                <Button
                            type="button"
                            size="icon"
                            variant="default"
                            disabled={busy}
                            aria-label={t("tooltipApprove")}
                            onClick={() =>
                              void runMut("membership", {
                                membership_user_id: uid,
                                svp_user_membership_action: "approve",
                              })
                            }
                          >
                            <CheckCircle2 className="size-4" />
                          </Button>
              }
            />
                        <TooltipContent>
                          <p>{t("tooltipApprove")}</p>
                        </TooltipContent>
                      </Tooltip>
                      <Tooltip>
                        <TooltipTrigger
              render={
                <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            disabled={busy}
                            aria-label={t("tooltipReject")}
                            onClick={() =>
                              void runMut("membership", {
                                membership_user_id: uid,
                                svp_user_membership_action: "reject",
                              })
                            }
                          >
                            <XCircle className="size-4" />
                          </Button>
              }
            />
                        <TooltipContent>
                          <p>{t("tooltipReject")}</p>
                        </TooltipContent>
                      </Tooltip>
                    </>
                  ) : null}
                  {canManageUsers && st === "rejected" ? (
                    <Tooltip>
                      <TooltipTrigger
              render={
                <Button
                          type="button"
                          size="icon"
                          variant="secondary"
                          disabled={busy}
                          aria-label={t("tooltipReopen")}
                          onClick={() =>
                            void runMut("membership", {
                              membership_user_id: uid,
                              svp_user_membership_action: "reopen",
                            })
                          }
                        >
                          <RotateCcw className="size-4" />
                        </Button>
              }
            />
                      <TooltipContent>
                        <p>{t("tooltipReopen")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : null}
                  {canManageUsers && st !== "blocked" ? (
                    <Tooltip>
                      <TooltipTrigger
              render={
                <Button
                          type="button"
                          size="icon"
                          variant="destructive"
                          disabled={busy}
                          aria-label={t("tooltipBan")}
                          onClick={() =>
                            void runMut("user_status", { svp_user_id: uid, user_status_action: "ban" })
                          }
                        >
                          <Ban className="size-4" />
                        </Button>
              }
            />
                      <TooltipContent>
                        <p>{t("tooltipBan")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : canManageUsers ? (
                    <Tooltip>
                      <TooltipTrigger
              render={
                <Button
                          type="button"
                          size="icon"
                          variant="outline"
                          disabled={busy}
                          aria-label={t("tooltipUnban")}
                          onClick={() =>
                            void runMut("user_status", { svp_user_id: uid, user_status_action: "unban" })
                          }
                        >
                          <ShieldOff className="size-4" />
                        </Button>
              }
            />
                      <TooltipContent>
                        <p>{t("tooltipUnban")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : null}
                  {portalUserUrl ? (
                    <Tooltip>
                      <TooltipTrigger>
                        <a
                          href={portalUserUrl}
                          target="_blank"
                          rel="noopener noreferrer"
                          aria-label={t("tooltipUserPortal")}
                          className={cn(
                            "inline-flex size-9 items-center justify-center rounded-md border border-input bg-background hover:bg-accent"
                          )}
                        >
                          <ExternalLink className="size-4" />
                        </a>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>{t("tooltipUserPortal")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : null}
                  {showAssignReseller ? (
                    <Tooltip>
                      <TooltipTrigger
              render={
                <Button
                          type="button"
                          size="icon"
                          variant="outline"
                          disabled={busy}
                          aria-label={t("tooltipAssignReseller")}
                          onClick={() => {
                            setPickResellerId(invitedBy > 0 ? String(invitedBy) : "")
                            setAssignResellerOpen(true)
                          }}
                        >
                          <Store className="size-4" />
                        </Button>
              }
            />
                      <TooltipContent>
                        <p>{t("tooltipAssignReseller")}</p>
                      </TooltipContent>
                    </Tooltip>
                  ) : null}
                  {!isReseller && canSiteAdminOnly ? (
                    <>
                      <Tooltip>
                        <TooltipTrigger
              render={
                <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            disabled={busy}
                            aria-label={t("roleLabel")}
                            onClick={() => setRoleDialogOpen(true)}
                          >
                            <ShieldCheck className="size-4" />
                          </Button>
              }
            />
                        <TooltipContent>
                          <p>{t("roleLabel")}</p>
                        </TooltipContent>
                      </Tooltip>
                      <Tooltip>
                        <TooltipTrigger
              render={
                <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            disabled={busy}
                            aria-label={t("referrerTitle")}
                            onClick={() => setReferrerDialogOpen(true)}
                          >
                            <UsersRound className="size-4" />
                          </Button>
              }
            />
                        <TooltipContent>
                          <p>{t("referrerTitle")}</p>
                        </TooltipContent>
                      </Tooltip>
                    </>
                  ) : null}
                  {canManageUsers ? (
                  <Tooltip>
                    <TooltipTrigger
              render={
                <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        disabled={busy}
                        aria-label={t("adminMessageTitle")}
                        onClick={() => setMessageDialogOpen(true)}
                      >
                        <MessageSquare className="size-4" />
                      </Button>
              }
            />
                    <TooltipContent>
                      <p>{t("adminMessageTitle")}</p>
                    </TooltipContent>
                  </Tooltip>
                  ) : null}
                  </div>
                </div>
              </div>
              ) : null}
            </CardContent>
          </Card>

          {canManageServices ? (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("createService")}</CardTitle>
              <ul className="list-inside list-disc space-y-0.5 text-start text-sm text-muted-foreground">
                {isReseller ? (
                  <>
                    <li>{t("createServiceHintReseller1")}</li>
                    <li>{t("createServiceHintReseller2")}</li>
                    <li>{t("createServiceHintReseller3")}</li>
                  </>
                ) : (
                  <>
                    <li>{t("createServiceHintShort1")}</li>
                    <li>{t("createServiceHintShort2")}</li>
                    <li>{t("createServiceHintShort3")}</li>
                  </>
                )}
              </ul>
            </CardHeader>
            <CardContent className="space-y-3">
              {activeCategories.length === 0 ? (
                <p className="text-xs text-muted-foreground">{t("selectCategory")}</p>
              ) : null}
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1">
                  <Label>{t("category")}</Label>
                  <DashSelect
                    value={categorySlug}
                    onValueChange={(v) => {
                      setCategorySlug(v)
                      setPlanId("")
                    }}
                    disabled={busy}
                    allowEmpty
                    placeholder={t("selectCategory")}
                    options={activeCategories.map((c) => ({
                      value: String(c.slug),
                      label: String(c.label ?? c.slug ?? ""),
                    }))}
                  />
                </div>
                <div className="space-y-1">
                  <Label>{t("plan")}</Label>
                  <DashSelect
                    value={planId}
                    onValueChange={setPlanId}
                    disabled={busy || !categorySlug}
                    allowEmpty
                    placeholder={t("selectPlan")}
                    options={categoryPlans.map((p) => ({
                      value: String(num(p.id)),
                      label: `#${num(p.id)} — ${String(p.label ?? p.name ?? "")}`,
                    }))}
                  />
                </div>
              </div>
              <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <div className="space-y-1">
                  <Label>{t("volumeGb")}</Label>
                  <Input
                    dir="ltr"
                    value={volGb}
                    onChange={(e) => setVolGb(e.target.value)}
                    placeholder={t("volumeGbExamplePlaceholder")}
                    disabled={busy}
                  />
                </div>
                <div className="space-y-1">
                  <Label>{t("mode")}</Label>
                  <DashSelect
                    value={createMode}
                    onValueChange={(v) => setCreateMode(v as typeof createMode)}
                    disabled={busy}
                    options={[
                      ...(!isReseller ? [{ value: "free", label: t("modeFree") }] : []),
                      { value: "wallet", label: t("modeWallet") },
                      { value: "invoice", label: t("modeInvoice") },
                    ]}
                  />
                </div>
                <Button
                  type="button"
                  size="sm"
                  className="gap-2 sm:self-end"
                  disabled={busy || !planId}
                  onClick={() => {
                    const pid = parseInt(planId, 10)
                    const v = volGb.trim() ? parseInt(volGb, 10) : NaN
                    void runMut("user_create_service", {
                      target_user_id: uid,
                      plan_id: pid,
                      volume_gb: Number.isFinite(v) ? v : null,
                      mode: createMode,
                    })
                  }}
                >
                  <PackagePlus className="size-4" />
                  {t("create")}
                </Button>
              </div>
              {createPricePreview != null ? (
                <p className="text-xs text-muted-foreground">
                  {t("estimatedCost")}:{" "}
                  <span className="font-medium text-foreground tabular-nums">
                    {formatNumber(createPricePreview, isFa)}
                  </span>
                </p>
              ) : selectedPlan && isPerGbPlan(selectedPlan) ? (
                <p className="text-xs text-muted-foreground">{t("estimatedCostNeedVolume")}</p>
              ) : null}
            </CardContent>
          </Card>
          ) : null}
        </div>

        <Card>
          <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-2">
            <div>
              <CardTitle className="text-base">{t("marketingOffersTitle")}</CardTitle>
              <CardDescription>
                {marketingOffers.length > 0
                  ? `${marketingOffers.length}`
                  : t("marketingOffersEmpty")}
              </CardDescription>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                variant="outline"
                render={
                  <a href={buildDashboardTabUrl(dashboardBaseUrl, "marketing_lifecycle")} />
                }
              >
                {t("marketingOpenLifecycle")}
              </Button>
              {canSiteAdminOnly ? (
              <Button
                type="button"
                size="sm"
                variant="secondary"
                disabled={busy}
                onClick={async () => {
                  setBusy(true)
                  try {
                    const res = await postAdminMutate("marketing_send_manual", {
                      user_id: userId,
                      rule_id: 0,
                    })
                    if (!res.ok) {
                      setAlertText(adminMutateErrorText(res, t("mutateError")))
                      return
                    }
                    onMutateSuccess?.()
                    void load()
                  } finally {
                    setBusy(false)
                  }
                }}
              >
                {t("marketingSendOffer")}
              </Button>
              ) : null}
            </div>
          </CardHeader>
          {marketingOffers.length > 0 ? (
            <CardContent>
              <DashTableShell minWidth="32rem" colWidths={["40%", "30%", "30%"]}>
                <thead>
                  <tr>
                    <DashTh>{t("marketingColCode")}</DashTh>
                    <DashTh>{t("marketingColStatus")}</DashTh>
                    <DashTh>{t("marketingColSent")}</DashTh>
                  </tr>
                </thead>
                <tbody>
                  {marketingOffers.map((o) => (
                    <tr key={num(o.id)}>
                      <DashTd className="font-mono text-xs">{String(o.discount_code ?? "—")}</DashTd>
                      <DashTd>{String(o.status ?? "—")}</DashTd>
                      <DashTd>{String(o.sent_at ?? "—")}</DashTd>
                    </tr>
                  ))}
                </tbody>
              </DashTableShell>
            </CardContent>
          ) : null}
        </Card>

        {referrals.length > 0 ? (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("referralsTitle")}</CardTitle>
              <CardDescription>{t("referralsHint")}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {referrals.map((ref) => {
                const rid = num(ref.id)
                return (
                  <div
                    key={rid}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 px-2 py-1.5"
                  >
                    <span>
                      #{formatPlainLatinInt(rid)} — {displayName(ref)}
                    </span>
                    {onOpenUserDetail ? (
                      <Button type="button" size="sm" variant="outline" onClick={() => onOpenUserDetail(rid)}>
                        {t("referralsManage")}
                      </Button>
                    ) : null}
                  </div>
                )
              })}
            </CardContent>
          </Card>
        ) : null}

        <div className="space-y-4">
          <h3 className="text-start text-base font-medium">{t("services")}</h3>
          {visibleServices.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("noServices")}</p>
          ) : (
            <div className="grid gap-4 md:grid-cols-2">
              {visibleServices.map((svc) => {
                const sid = num(svc.id)
                const patchAlert = (
                  key: "alerts_enabled" | "alerts_volume" | "alerts_expiry" | "alerts_users",
                  val: boolean
                ) => {
                  void runMut("service_alerts_patch", { service_id: sid, [key]: val ? 1 : 0 })
                }
                return (
                  <DashboardUserServiceCard
                    key={sid}
                    svc={svc}
                    plans={plans}
                    isReseller={isReseller}
                    busy={busy || !canManageServices}
                    tp={tp}
                    onOpenAction={
                      canManageServices
                        ? (kind: ServiceActionKind, s: DashRecord) =>
                            setActionDlg({ kind, sid: num(s.id), svc: s })
                        : () => {}
                    }
                    onPatchAlert={canManageServices ? patchAlert : () => {}}
                    onToggleEnable={
                      canManageServices
                        ? (enabled: boolean) =>
                            void runMut("user_service_toggle_enable", {
                              service_id: sid,
                              enable: enabled ? 1 : 0,
                            })
                        : () => {}
                    }
                    onSetServiceNote={
                      canManageServices
                        ? (note: string) => void runMut("service_set_note", { service_id: sid, service_note: note })
                        : () => {}
                    }
                  />
                )
              })}
            </div>
          )}
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("paymentsTitle")}</CardTitle>
          </CardHeader>
          <CardContent>
            <DashboardPaymentsTabs
              paymentsView={paymentsView}
              onPaymentsViewChange={setPaymentsView}
              receipts={receipts}
              receiptAggregates={receiptAggregates}
              payments={userPayments}
              paymentAggregates={null}
              orders={userOrders}
              orderAggregates={null}
              settings={settings}
              receiptsPagination={rcptMeta}
              paymentsPagination={txMeta}
              ordersPagination={ordersMeta}
              isReseller={isReseller}
              canReviewReceipts={canReviewReceipts}
              listFilters={paymentsView === "receipts" ? rcptFilters : txFilters}
              onListFiltersChange={paymentsView === "receipts" ? onRcptFiltersChange : onTxFiltersChange}
              dashboardBaseUrl={dashboardBaseUrl}
              onMutateSuccess={() => void onReceiptMutateSuccess()}
              onPageChange={(v, p) => {
                if (v === "receipts") setRcptPage(p)
                else if (v === "transactions") setTxPage(p)
                else setOrdersPage(p)
              }}
              onPerPageChange={(v, n) => {
                if (v === "receipts") {
                  setRcptPerPage(n)
                  setRcptPage(1)
                } else if (v === "transactions") {
                  setTxPerPage(n)
                  setTxPage(1)
                } else {
                  setOrdersPerPage(n)
                  setOrdersPage(1)
                }
              }}
              variant="userEmbed"
              embedEmptyHint={paymentsLoading ? tPay("empty") : t("paymentsEmpty")}
            />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("activity")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <DashTableShell
        minWidth="20rem"
              colWidths={isFa ? ["50%", "22%", "28%"] : ["28%", "22%", "50%"]}
            >
              <thead>
                <tr className="bg-muted/40">
                  {isFa ? (
                    <>
                      <DashTh>{t("colSummary")}</DashTh>
                      <DashTh>{t("colChannel")}</DashTh>
                      <DashTh className="whitespace-nowrap">{t("colTime")}</DashTh>
                    </>
                  ) : (
                    <>
                      <DashTh className="whitespace-nowrap">{t("colTime")}</DashTh>
                      <DashTh>{t("colChannel")}</DashTh>
                      <DashTh>{t("colSummary")}</DashTh>
                    </>
                  )}
                </tr>
              </thead>
              <tbody>
                {activity.map((row) => {
                  const id = num(row.id)
                  const summary = formatUserActivityLine(row, t)
                  const channel = formatActivityChannel(row.channel, tp)
                  const time = formatDateTime(String(row.created_at ?? ""), isFa)
                  return (
                    <tr key={id}>
                      {isFa ? (
                        <>
                          <DashTd className="text-foreground">{summary}</DashTd>
                          <DashTd>{channel}</DashTd>
                          <DashTd className="whitespace-nowrap">{time}</DashTd>
                        </>
                      ) : (
                        <>
                          <DashTd className="whitespace-nowrap">{time}</DashTd>
                          <DashTd>{channel}</DashTd>
                          <DashTd className="text-foreground">{summary}</DashTd>
                        </>
                      )}
                    </tr>
                  )
                })}
              </tbody>
            </DashTableShell>
            <DataPagination
              meta={actMeta}
        onPageChange={(p) => setActPage(p)}
              onPerPageChange={() => {
                /* fixed 20 via API */
              }}
            />
          </CardContent>
        </Card>
      </DashPage>
    </TooltipProvider>
  )
}
