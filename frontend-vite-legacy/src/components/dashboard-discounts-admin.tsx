"use client"

import {
  Banknote,
  CalendarRange,
  Check,
  EllipsisVerticalIcon,
  Hash,
  Layers,
  Percent,
  Tag,
  User,
  X,
} from "lucide-react"
import { useCallback, useMemo, useState } from "react"
import { useTranslation } from "react-i18next"

import { DashboardDateTimePicker } from "@/components/dashboard-datetime-picker"
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
import { Switch } from "@/components/ui/switch"
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
import { formatDateTime, formatNumber } from "@/lib/format-locale"
import { DashboardPageHeader } from "@/components/dashboard-page-header"
import { cn } from "@/lib/utils"
import { useDashLocale } from "@/lib/dash-locale-context"
import { DashDialogContent, DashDialogFooter, DashDialogHeader } from "@/components/dash-dialog-content"
import { Dialog, DialogDescription, DialogTitle } from "@/components/ui/dialog"

type DashRecord = Record<string, unknown>

type DiscountType = "percent" | "fixed_toman" | "percent_per_gb" | "fixed_per_gb"

type UsageSummary = {
  total_redemptions?: number
  total_discount_toman?: number
  active_codes?: number
}

type RedemptionRow = {
  id?: number
  svp_user_id?: number
  user_name?: string
  user_username?: string
  subtotal_toman?: number
  discount_toman?: number
  volume_gb?: number | null
  created_at?: string
}

function num(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function isDiscountActive(d: DashRecord): boolean {
  return d.active === true || d.active === 1 || d.active === "1"
}

function parsePlanIds(v: unknown): number[] {
  if (Array.isArray(v)) {
    return v.map((x) => num(x)).filter((x) => x > 0)
  }
  if (typeof v === "string" && v.trim()) {
    try {
      const j = JSON.parse(v) as unknown
      if (Array.isArray(j)) return j.map((x) => num(x)).filter((x) => x > 0)
    } catch {
      return []
    }
  }
  return []
}

type DiscountFormState = {
  svpc_id: number
  svpc_code: string
  svpc_type: DiscountType
  svpc_value: number
  svpc_max_uses: string
  svpc_valid_from: string
  svpc_valid_until: string
  svpc_min_order: string
  svpc_max_order: string
  svpc_max_discount: string
  svpc_restricted_user_id: string
  svpc_allowed_plan_ids: number[]
  svpc_active: boolean
  svpc_allow_new: boolean
  svpc_allow_renew: boolean
  svpc_allow_vol: boolean
  svpc_allow_users: boolean
}

function emptyForm(): DiscountFormState {
  return {
    svpc_id: 0,
    svpc_code: "",
    svpc_type: "percent",
    svpc_value: 0,
    svpc_max_uses: "",
    svpc_valid_from: "",
    svpc_valid_until: "",
    svpc_min_order: "",
    svpc_max_order: "",
    svpc_max_discount: "",
    svpc_restricted_user_id: "",
    svpc_allowed_plan_ids: [],
    svpc_active: true,
    svpc_allow_new: true,
    svpc_allow_renew: true,
    svpc_allow_vol: true,
    svpc_allow_users: true,
  }
}

function dtToApi(v: unknown): string {
  if (v == null || v === "") return ""
  const s = String(v).trim()
  if (!s) return ""
  if (s.length >= 19) return s.slice(0, 19).replace("T", " ")
  return s.replace("T", " ").slice(0, 16) + ":00"
}

function parseDiscountType(v: unknown): DiscountType {
  const s = String(v ?? "percent")
  if (s === "fixed_toman" || s === "percent_per_gb" || s === "fixed_per_gb") return s
  return "percent"
}

function formFromRow(d: DashRecord): DiscountFormState {
  const maxu = d.max_uses
  const restricted = num(d.restricted_user_id ?? d.restricted_svp_user_id)
  return {
    svpc_id: num(d.id),
    svpc_code: String(d.code ?? ""),
    svpc_type: parseDiscountType(d.discount_type),
    svpc_value: num(d.discount_value),
    svpc_max_uses: maxu == null || maxu === "" ? "" : String(maxu),
    svpc_valid_from: dtToApi(d.valid_from),
    svpc_valid_until: dtToApi(d.valid_until),
    svpc_min_order: d.min_order_toman == null || d.min_order_toman === "" ? "" : String(d.min_order_toman),
    svpc_max_order: d.max_order_toman == null || d.max_order_toman === "" ? "" : String(d.max_order_toman),
    svpc_max_discount:
      d.max_discount_toman == null || d.max_discount_toman === "" ? "" : String(d.max_discount_toman),
    svpc_restricted_user_id: restricted > 0 ? String(restricted) : "",
    svpc_allowed_plan_ids: parsePlanIds(d.allowed_plan_ids),
    svpc_active: isDiscountActive(d),
    svpc_allow_new: !!(d.allow_new_purchase === true || d.allow_new_purchase === 1 || d.allow_new_purchase === "1"),
    svpc_allow_renew: !!(d.allow_renew_same === true || d.allow_renew_same === 1 || d.allow_renew_same === "1"),
    svpc_allow_vol: !!(d.allow_add_volume === true || d.allow_add_volume === 1 || d.allow_add_volume === "1"),
    svpc_allow_users: !!(d.allow_add_user_slots === true || d.allow_add_user_slots === 1 || d.allow_add_user_slots === "1"),
  }
}

function formToPayload(f: DiscountFormState): Record<string, unknown> {
  const restricted = f.svpc_restricted_user_id.trim()
  return {
    id: f.svpc_id,
    code: f.svpc_code,
    discount_type: f.svpc_type,
    discount_value: f.svpc_value,
    max_uses: f.svpc_max_uses.trim(),
    valid_from: f.svpc_valid_from.trim(),
    valid_until: f.svpc_valid_until.trim(),
    min_order_toman: f.svpc_min_order.trim(),
    max_order_toman: f.svpc_max_order.trim(),
    max_discount_toman: f.svpc_max_discount.trim(),
    restricted_user_id: restricted === "" ? 0 : num(restricted),
    allowed_plan_ids: f.svpc_allowed_plan_ids,
    active: f.svpc_active ? 1 : 0,
    allow_new_purchase: f.svpc_allow_new ? 1 : 0,
    allow_renew_same: f.svpc_allow_renew ? 1 : 0,
    allow_add_volume: f.svpc_allow_vol ? 1 : 0,
    allow_add_user_slots: f.svpc_allow_users ? 1 : 0,
  }
}

function typeLabelKey(dtype: string): string {
  if (dtype === "fixed_toman") return "typeFixed"
  if (dtype === "percent_per_gb") return "typePercentPerGb"
  if (dtype === "fixed_per_gb") return "typeFixedPerGb"
  return "typePercent"
}

function flagIcon(ok: boolean) {
  return ok ? <Check className="size-3.5 text-primary" aria-hidden /> : <X className="size-3.5 text-muted-foreground" aria-hidden />
}

function DiscountCodeTile({
  d,
  saving,
  tp,
  typeLabel,
  planNameById,
  onToggleActive,
  onUsage,
  onEdit,
  onDelete,
  readOnlySettings = false,
}: {
  d: DashRecord
saving: boolean
  tp: (k: string) => string
  typeLabel: (dtype: string) => string
  planNameById: Map<number, string>
  onToggleActive: (row: DashRecord, checked: boolean) => void
  onUsage: (row: DashRecord) => void
  onEdit: (row: DashRecord) => void
  onDelete: (row: DashRecord) => void
  readOnlySettings?: boolean
}) {
  const { isFa } = useDashLocale()

  const act = isDiscountActive(d)
  const dtype = String(d.discount_type ?? "percent")
  const planIds = parsePlanIds(d.allowed_plan_ids)
  const restricted = num(d.restricted_user_id ?? d.restricted_svp_user_id)
  const TypeIcon = dtype === "fixed_toman" || dtype === "fixed_per_gb" ? Banknote : Percent

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
        <div className="flex min-w-0 items-start gap-2">
          <Tag className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden />
          <div className="min-w-0">
            <CardTitle className="font-mono text-base">{String(d.code ?? "")}</CardTitle>
            <CardDescription className="flex items-center gap-1">
              <TypeIcon className="size-3.5 shrink-0" aria-hidden />
              {typeLabel(dtype)}
            </CardDescription>
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <Switch
            checked={act}
            disabled={saving || readOnlySettings}
            onCheckedChange={(checked) => onToggleActive(d, checked)}
            aria-label={act ? tp("badgeActive") : tp("badgeInactive")}
          />
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button type="button" variant="ghost" size="icon" className="size-8">
                <EllipsisVerticalIcon className="size-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align={isFa ? "start" : "end"}>
              <DropdownMenuItem onClick={() => onUsage(d)}>{tp("details")}</DropdownMenuItem>
              {!readOnlySettings ? (
                <>
                  <DropdownMenuItem onClick={() => onEdit(d)}>{tp("edit")}</DropdownMenuItem>
                  <DropdownMenuItem className="text-destructive" onClick={() => onDelete(d)}>
                    {tp("delete")}
                  </DropdownMenuItem>
                </>
              ) : null}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardHeader>
      <CardContent className="space-y-2 text-xs text-muted-foreground">
        <div className="flex items-center gap-2">
          <Percent className="size-3.5 shrink-0" aria-hidden />
          <span>
            {tp("value")}: {formatNumber(num(d.discount_value), isFa)}
          </span>
        </div>
        <div className="flex items-center gap-2">
          <Hash className="size-3.5 shrink-0" aria-hidden />
          <span>
            {tp("uses")}: {formatNumber(num(d.uses_count), isFa)}
            {d.max_uses != null && d.max_uses !== ""
              ? ` / ${formatNumber(num(d.max_uses), isFa)}`
              : ` / ${tp("unlimited")}`}
          </span>
        </div>
        <div className="flex items-start gap-2">
          <CalendarRange className="mt-0.5 size-3.5 shrink-0" aria-hidden />
          <span>
            {formatDateTime(d.valid_from as string | undefined, isFa)} →{" "}
            {formatDateTime(d.valid_until as string | undefined, isFa)}
          </span>
        </div>
        <div className="flex items-center gap-2">
          <User className="size-3.5 shrink-0" aria-hidden />
          <span>
            {restricted > 0 ? `${tp("restrictedUser")}: #${formatNumber(restricted, isFa)}` : tp("allUsers")}
          </span>
        </div>
        <div className="flex items-start gap-2">
          <Layers className="mt-0.5 size-3.5 shrink-0" aria-hidden />
          <span>
            {planIds.length > 0
              ? planIds.map((pid) => planNameById.get(pid) ?? `#${pid}`).join(", ")
              : tp("allPlans")}
          </span>
        </div>
        <div className="flex flex-wrap gap-x-3 gap-y-1 border-t border-border/50 pt-2">
          <span className="inline-flex items-center gap-1">
            {flagIcon(!!(d.allow_new_purchase === true || d.allow_new_purchase === 1 || d.allow_new_purchase === "1"))}
            {tp("flagNew")}
          </span>
          <span className="inline-flex items-center gap-1">
            {flagIcon(!!(d.allow_renew_same === true || d.allow_renew_same === 1 || d.allow_renew_same === "1"))}
            {tp("flagRenew")}
          </span>
          <span className="inline-flex items-center gap-1">
            {flagIcon(!!(d.allow_add_volume === true || d.allow_add_volume === 1 || d.allow_add_volume === "1"))}
            {tp("flagVol")}
          </span>
          <span className="inline-flex items-center gap-1">
            {flagIcon(
              !!(d.allow_add_user_slots === true || d.allow_add_user_slots === 1 || d.allow_add_user_slots === "1")
            )}
            {tp("flagUsers")}
          </span>
        </div>
        <p className="text-[11px]">
          {tp("cardTotalDiscount")}: {formatNumber(num(d.total_discount_toman), isFa)}
        </p>
      </CardContent>
    </Card>
  )
}

export function DashboardDiscountsAdmin({
  discountCodes,
  discountUsageSummary,
  plans,
  usersList,
  pagination,
  onMutateSuccess,
  onPageChange,
  onPerPageChange,
  readOnlySettings = false,
  portalAdminUrl = "",
}: {
  discountCodes: DashRecord[]
  discountUsageSummary?: UsageSummary | null
  plans: DashRecord[]
  usersList: DashRecord[]
  pagination: PaginationMeta | null
onMutateSuccess?: () => void
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: number) => void
  readOnlySettings?: boolean
  portalAdminUrl?: string
}) {
  const { isFa } = useDashLocale()

  const { t } = useTranslation()
  const tp = (k: string) => t(`discountsAdmin.${k}`)

  const [filter, setFilter] = useState<"all" | "active" | "inactive">("all")
  const [sheetOpen, setSheetOpen] = useState(false)
  const [formMode, setFormMode] = useState<"add" | "edit">("add")
  const [form, setForm] = useState<DiscountFormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DashRecord | null>(null)
  const [usageTarget, setUsageTarget] = useState<DashRecord | null>(null)
  const [usageRows, setUsageRows] = useState<RedemptionRow[]>([])
  const [usageLoading, setUsageLoading] = useState(false)
  const [userFilter, setUserFilter] = useState("")

  const planNameById = useMemo(() => {
    const m = new Map<number, string>()
    for (const p of plans) {
      const id = num(p.id)
      if (id > 0) m.set(id, String(p.name ?? p.title ?? `#${id}`))
    }
    return m
  }, [plans])

  const stats = useMemo(() => {
    let active = 0
    for (const d of discountCodes) {
      if (isDiscountActive(d)) active += 1
    }
    return {
      total: pagination?.total ?? discountCodes.length,
      active,
      totalRedemptions: num(discountUsageSummary?.total_redemptions),
      totalDiscountToman: num(discountUsageSummary?.total_discount_toman),
    }
  }, [discountCodes, pagination, discountUsageSummary])

  const filtered = useMemo(() => {
    if (filter === "active") return discountCodes.filter(isDiscountActive)
    if (filter === "inactive") return discountCodes.filter((d) => !isDiscountActive(d))
    return discountCodes
  }, [discountCodes, filter])

  const filteredUsers = useMemo(() => {
    const q = userFilter.trim().toLowerCase()
    if (!q) return usersList.slice(0, 80)
    return usersList
      .filter((u) => {
        const id = String(u.id ?? "")
        const name = `${u.first_name ?? ""} ${u.last_name ?? ""}`.toLowerCase()
        const un = String(u.username ?? "").toLowerCase()
        return id.includes(q) || name.includes(q) || un.includes(q)
      })
      .slice(0, 80)
  }, [usersList, userFilter])

  const openAdd = useCallback(() => {
    setError(null)
    setUserFilter("")
    setFormMode("add")
    setForm(emptyForm())
    setSheetOpen(true)
  }, [])

  const openEdit = useCallback((d: DashRecord) => {
    setError(null)
    setUserFilter("")
    setFormMode("edit")
    setForm(formFromRow(d))
    setSheetOpen(true)
  }, [])

  const openUsage = useCallback(async (d: DashRecord) => {
    const id = num(d.id)
    if (id < 1) return
    setUsageTarget(d)
    setUsageRows([])
    setUsageLoading(true)
    try {
      const res = await postAdminMutate("discount_redemptions", { code_id: id, limit: 20 })
      if (res.ok && Array.isArray(res.rows)) {
        setUsageRows(res.rows as RedemptionRow[])
      }
    } finally {
      setUsageLoading(false)
    }
  }, [])

  const mutateErrorMessage = useCallback(
    (msg: string | undefined) => {
      if (msg === "plan_overlap") return tp("errorPlanOverlap")
      return msg || tp("mutateError")
    },
    [tp]
  )

  const onSaveSheet = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      const payload = formToPayload(form)
      if (formMode === "add") {
        payload.id = 0
      }
      const res = await postAdminMutate("discount_save", payload)
      if (!res.ok) {
        setError(mutateErrorMessage(res.message))
        return
      }
      setSheetOpen(false)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [form, formMode, mutateErrorMessage, onMutateSuccess])

  const onConfirmDelete = useCallback(async () => {
    if (!deleteTarget) return
    const id = num(deleteTarget.id)
    setSaving(true)
    setError(null)
    try {
      const res = await postAdminMutate("discount_delete", { id })
      if (!res.ok) {
        setError(mutateErrorMessage(res.message))
        return
      }
      setDeleteTarget(null)
      onMutateSuccess?.()
    } finally {
      setSaving(false)
    }
  }, [deleteTarget, mutateErrorMessage, onMutateSuccess])

  const togglePlanId = (planId: number) => {
    setForm((f) => {
      const has = f.svpc_allowed_plan_ids.includes(planId)
      return {
        ...f,
        svpc_allowed_plan_ids: has
          ? f.svpc_allowed_plan_ids.filter((x) => x !== planId)
          : [...f.svpc_allowed_plan_ids, planId],
      }
    })
  }

  const onToggleActive = useCallback(
    async (d: DashRecord, checked: boolean) => {
      const f = formFromRow(d)
      f.svpc_active = checked
      setSaving(true)
      setError(null)
      try {
        const res = await postAdminMutate("discount_save", formToPayload(f))
        if (!res.ok) {
          setError(res.message === "plan_overlap" ? tp("errorPlanOverlap") : res.message || tp("mutateError"))
          return
        }
        onMutateSuccess?.()
      } finally {
        setSaving(false)
      }
    },
    [onMutateSuccess, tp]
  )

  const filterLabel =
    filter === "active" ? tp("filterActive") : filter === "inactive" ? tp("filterInactive") : tp("filterAll")

  return (
    <DashPage className={"w-full space-y-6"} data-testid="dash-discounts-tab">
      <DashboardPageHeader title={tp("title")} description={tp("subtitle")} />

      {error ? (
        <div
          role="alert"
          className="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsTotal")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.total, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsActive")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.active, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsTotalRedemptions")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.totalRedemptions, isFa)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{tp("statsTotalDiscount")}</CardDescription>
            <CardTitle className="text-2xl tabular-nums">{formatNumber(stats.totalDiscountToman, isFa)}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      {pagination ? (
        <p className="text-xs text-muted-foreground">{tp("statsPageBreakdown")}</p>
      ) : null}

      <div className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex flex-wrap items-center gap-2">
            <Label className="text-muted-foreground">{tp("filterLabel")}</Label>
            <DashSelect
              triggerClassName="w-auto min-w-[8rem]"
              value={filter}
              onValueChange={(v) => setFilter(v as typeof filter)}
              options={[
                { value: "all", label: tp("filterAll") },
                { value: "active", label: tp("filterActive") },
                { value: "inactive", label: tp("filterInactive") },
              ]}
            />
            <span className="text-xs text-muted-foreground">{filterLabel}</span>
          </div>
          {!readOnlySettings ? (
            <Button type="button" size="sm" onClick={openAdd}>
              {tp("addCode")}
            </Button>
          ) : null}
        </div>

        {readOnlySettings ? (
          <p className="mb-4 text-sm text-muted-foreground">
            {tp("readOnlyResellerHint")}
            {portalAdminUrl ? (
              <>
                {" "}
                <a
                  href={portalAdminUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="font-medium text-primary underline underline-offset-2"
                >
                  {tp("portalManageLink")}
                </a>
              </>
            ) : null}
          </p>
        ) : null}

        {filtered.length === 0 ? (
          <p className="text-sm text-muted-foreground">{tp("empty")}</p>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {filtered.map((d) => (
              <DiscountCodeTile
                key={num(d.id) || String(d.code)}
                d={d}
        saving={saving}
                tp={tp}
                typeLabel={(dtype) => tp(typeLabelKey(dtype))}
                planNameById={planNameById}
                onToggleActive={(row, checked) => void onToggleActive(row, checked)}
                onUsage={(row) => void openUsage(row)}
                onEdit={openEdit}
                onDelete={setDeleteTarget}
                readOnlySettings={readOnlySettings}
              />
            ))}
          </div>
        )}

        <DataPagination
          meta={pagination}
        onPageChange={onPageChange}
          onPerPageChange={onPerPageChange}
          perPageOptions={[40, 80, 120, 200]}
        />
      </div>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <DashSheetContent className={cn("flex w-full flex-col gap-0 overflow-y-auto sm:max-w-md")}>
          <SheetHeader className="border-b p-4 text-start">
            <SheetTitle>{formMode === "add" ? tp("addCode") : tp("editCode")}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 space-y-4 p-4">
            <div className="space-y-2">
              <Label>{tp("fieldCode")}</Label>
              <Input
                className="font-mono"
                disabled={formMode === "edit"}
                value={form.svpc_code}
                onChange={(e) => setForm((f) => ({ ...f, svpc_code: e.target.value }))}
              />
              {formMode === "edit" ? <p className="text-xs text-muted-foreground">{tp("codeLockedHint")}</p> : null}
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldType")}</Label>
              <DashSelect
                value={form.svpc_type}
                onValueChange={(v) => setForm((f) => ({ ...f, svpc_type: parseDiscountType(v) }))}
                options={[
                  { value: "percent", label: tp("typePercent") },
                  { value: "fixed_toman", label: tp("typeFixed") },
                  { value: "percent_per_gb", label: tp("typePercentPerGb") },
                  { value: "fixed_per_gb", label: tp("typeFixedPerGb") },
                ]}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldValue")}</Label>
              <Input
                type="number"
                min={0}
                step="0.01"
                value={form.svpc_value}
                onChange={(e) => setForm((f) => ({ ...f, svpc_value: num(e.target.value) }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldMaxUses")}</Label>
              <Input
                placeholder={tp("placeholderUnlimited")}
                value={form.svpc_max_uses}
                onChange={(e) => setForm((f) => ({ ...f, svpc_max_uses: e.target.value }))}
              />
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <DashboardDateTimePicker
                label={tp("fieldValidFrom")}
        value={form.svpc_valid_from}
                onChange={(v) => setForm((f) => ({ ...f, svpc_valid_from: v }))}
              />
              <DashboardDateTimePicker
                label={tp("fieldValidUntil")}
        value={form.svpc_valid_until}
                onChange={(v) => setForm((f) => ({ ...f, svpc_valid_until: v }))}
              />
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>{tp("fieldMinOrder")}</Label>
                <Input
                  value={form.svpc_min_order}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_min_order: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{tp("fieldMaxOrder")}</Label>
                <Input
                  value={form.svpc_max_order}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_max_order: e.target.value }))}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{tp("fieldMaxDiscount")}</Label>
              <Input
                value={form.svpc_max_discount}
                onChange={(e) => setForm((f) => ({ ...f, svpc_max_discount: e.target.value }))}
              />
            </div>
            <div className="space-y-2 border-t pt-2">
              <Label>{tp("fieldRestrictedUser")}</Label>
              <Input
                placeholder={tp("userSearchPlaceholder")}
                value={userFilter}
                onChange={(e) => setUserFilter(e.target.value)}
              />
              <DashSelect
                value={form.svpc_restricted_user_id}
                onValueChange={(v) => setForm((f) => ({ ...f, svpc_restricted_user_id: v }))}
                allowEmpty
                placeholder={tp("allUsers")}
                options={filteredUsers.map((u) => {
                  const uid = num(u.id)
                  const label = `${uid} — ${String(u.first_name ?? "")} ${String(u.last_name ?? "")}`.trim()
                  return { value: String(uid), label: label || `#${uid}` }
                })}
              />
            </div>
            <div className="space-y-2 border-t pt-2">
              <p className="text-xs font-medium text-muted-foreground">{tp("fieldAllowedPlans")}</p>
              <p className="text-xs text-muted-foreground">{tp("allowedPlansHint")}</p>
              <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
                {plans.length === 0 ? (
                  <p className="text-xs text-muted-foreground">{tp("noPlans")}</p>
                ) : (
                  plans.map((p) => {
                    const pid = num(p.id)
                    if (pid < 1) return null
                    const checked = form.svpc_allowed_plan_ids.includes(pid)
                    return (
                      <label key={pid} className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          className="size-4 rounded border-input"
                          checked={checked}
                          onChange={() => togglePlanId(pid)}
                        />
                        {String(p.name ?? p.title ?? `#${pid}`)}
                      </label>
                    )
                  })
                )}
              </div>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="size-4 rounded border-input"
                checked={form.svpc_active}
                onChange={(e) => setForm((f) => ({ ...f, svpc_active: e.target.checked }))}
              />
              {tp("active")}
            </label>
            <div className="space-y-2 border-t pt-2">
              <p className="text-xs font-medium text-muted-foreground">{tp("allowSection")}</p>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.svpc_allow_new}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_allow_new: e.target.checked }))}
                />
                {tp("flagNew")}
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.svpc_allow_renew}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_allow_renew: e.target.checked }))}
                />
                {tp("flagRenew")}
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.svpc_allow_vol}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_allow_vol: e.target.checked }))}
                />
                {tp("flagVol")}
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  className="size-4 rounded border-input"
                  checked={form.svpc_allow_users}
                  onChange={(e) => setForm((f) => ({ ...f, svpc_allow_users: e.target.checked }))}
                />
                {tp("flagUsers")}
              </label>
            </div>
          </div>
          <SheetFooter className="flex-row gap-2 border-t p-4">
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              {tp("cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void onSaveSheet()}>
              {tp("save")}
            </Button>
          </SheetFooter>
        </DashSheetContent>
      </Sheet>

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

      <Dialog open={Boolean(usageTarget)} onOpenChange={(o) => !o && setUsageTarget(null)}>
        <DashDialogContent className={cn("max-w-lg")}>
          <DashDialogHeader className={cn("text-start")}>
            <DialogTitle>{tp("usageDialogTitle")}</DialogTitle>
            <DialogDescription>
              {usageTarget ? String(usageTarget.code ?? "") : ""}
            </DialogDescription>
          </DashDialogHeader>
          {usageLoading ? (
            <p className="text-sm text-muted-foreground">{tp("usageLoading")}</p>
          ) : usageRows.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tp("usageEmpty")}</p>
          ) : (
            <div className="max-h-80 overflow-y-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-muted-foreground">
                    <th className="py-1 text-start">{tp("usageColDate")}</th>
                    <th className="py-1 text-start">{tp("usageColUser")}</th>
                    <th className="py-1 text-end">{tp("usageColDiscount")}</th>
                  </tr>
                </thead>
                <tbody>
                  {usageRows.map((row) => (
                    <tr key={row.id ?? `${row.created_at}-${row.svp_user_id}`} className="border-b border-border/50">
                      <td className="py-1.5">{formatDateTime(row.created_at, isFa)}</td>
                      <td className="py-1.5">
                        #{formatNumber(num(row.svp_user_id), isFa)}
                        {row.user_name ? ` · ${row.user_name}` : ""}
                      </td>
                      <td className="py-1.5 text-end tabular-nums">
                        {formatNumber(num(row.discount_toman), isFa)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          <DashDialogFooter>
            <Button type="button" variant="outline" onClick={() => setUsageTarget(null)}>
              {tp("cancel")}
            </Button>
          </DashDialogFooter>
        </DashDialogContent>
      </Dialog>
    </DashPage>
  )
}
